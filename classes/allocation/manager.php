<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_coursework\allocation;

/**
 * Page that prints a table of all students and all markers so that first marker, second marker,
 * moderators etc can be allocated manually or automatically.
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2011 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use coding_exception;
use mod_coursework\models\coursework;
use mod_coursework\models\moderation_set_rule;
use mod_coursework\moderation_set_widget;
use mod_coursework\sampling_set_widget;
use \mod_coursework\stages\base as stage_base;
use moodle_exception;
use mod_coursework\grade_judge;

defined('MOODLE_INTERNAL') || die();


/**
 * This takes responsibility for managing the allocations of markers to students, both manually and
 * automatically. Specifically, it processes user input from the allocation screen and performs the allocation
 * and deallocations as well as making the moderation set.
 */
class manager {

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @var strategy\base
     */
    private  $assessorallocationstrategy;

    /**
     * @var strategy\base
     */
    private  $moderatorallocationstrategy;

    /**
     * New instance created with references to the coursework stored.
     *
     * @param coursework $coursework
     * @throws \coding_exception
     */
    public function __construct(coursework $coursework) {

        $this->coursework = $coursework;

        // Instantiate the allocation strategies so we can use them.
        $strategytypes = array();
        if (!empty($this->coursework->assessorallocationstrategy)) {
            $strategytypes[] = coursework::ASSESSOR;
        }
        if (!empty($this->coursework->moderatorallocationstrategy)) {
            $strategytypes[] = coursework::MODERATOR;
        }
        foreach ($strategytypes as $strategytype) {

            $propertyname = $strategytype.'allocationstrategy';

            $classname = !empty($coursework->$propertyname) ?
                '\\mod_coursework\allocation\strategy\\'.$coursework->$propertyname : '';


            if (class_exists($classname)) {
                $this->$propertyname = new $classname($coursework);
                if (!($this->$propertyname instanceof strategy\base)) {
                    $message = "Allocation classname {$classname} is not an instance of ".
                        "\\mod_coursework\\allocation_strategy\\base";
                    throw new coding_exception($message);
                }
            } else {
                // Default.
                $this->$propertyname = new strategy\equal($coursework);
            }
        }
    }

    /**
     * Scans the classes directory and returns the names of all classes that are allocation strategies, along with their
     * human readable names, ready to go in a mforms select element.
     *
     * @param string $type e.g. coursework::ASSESSOR
     * @return array $classname => human readable name
     */
    public static function get_allocation_classnames($type = coursework::ASSESSOR) {

        global $CFG;

        $classdir = $CFG->dirroot . '/mod/coursework/classes/allocation/strategy';
        $fullclassnames = glob($classdir . '/*.php');
        $options = array();
        foreach ($fullclassnames as $fullclassname) {
            if (strpos($fullclassname, 'base') !== false) {
                continue;
            }
            preg_match('/([^\/]+).php/', $fullclassname, $matches);
            $strategyname = $matches[1]; // E.g. equal.
            $options[$strategyname] =
                get_string('coursework_allocation_strategy_'.$strategyname, 'mod_coursework', strtolower(get_string($type, 'mod_coursework')));
        }

        // move 'none' to be the first option
        if (array_key_exists('none', $options)){
            $new_value = array('none'=> $options['none']);
            unset($options['none']);
            $options = $new_value + $options;
        }

        return $options;
    }

    /**
     * @param allocatable[] $allocatables
     * @param stage_base $stage
     * @param allocatable[] $moderationset
     * @return mixed
     */
    protected function add_already_moderated_allocatables_to_set($allocatables, $stage, $moderationset) {
        foreach ($allocatables as $allocatableid => $allocatable) {
            if ($stage->has_feedback($allocatable)) {
                $moderationset[$allocatableid] = $allocatable;
                unset($allocatables[$allocatableid]);
            }
        }
        return $moderationset;
    }

    /**
     * This will use the rules associated with each coursework to generate a set of students for
     * moderation. It is meant to use rules that are pluggable to include certain groups e.g.
     * all students with grades under 40%. The set is saved via empty allocations with teacherid of 0.
     *
     * @return void
     */
    public function auto_generate_moderation_set() {

        // We assume that any which have allocations attached need to have them kept. Still important
        // to get all students, rather than just unmarked ones as we may need totals.
        $allocatables = $this->get_coursework()->get_allocatables();
        $stage = $this->get_coursework()->get_moderator_marking_stage();

        $set_rules = $this->get_moderation_set_rules();

        // No set to make.
        if (empty($set_rules)) {
            return;
        }

        // These are the ones we will actually moderate (or which have already been moderated).
        $moderation_set = array();

        // Move all the already marked ones into the set. These have to stay in it and ought to
        // be taken into account so that the other rules just add to them.
        $moderation_set = $this->add_already_moderated_allocatables_to_set($allocatables, $stage, $moderation_set);

        // Now, we loop over the set repeatedly, once for each rule, and add those we want to the set.
        foreach ($set_rules as $rule) {
            // The rule will separate out those students that ought to be included, leaving
            // the arrays altered.
            $rule->adjust_set($moderation_set, $allocatables, $stage);
        }

    }


    /**
     * Returns array of sampling options. This function should be looked upon as a placeholder
     * in case more complex functionality is defined
     *
     * @return array
     */
    public function get_sampling_options()  {
        return array('0'=>get_string('manual','mod_coursework'),
                     '1'=>get_string('automatic','mod_coursework'));

    }

    /**
     * Gets the sampling rules as an array of classes.
     *
     * @return moderation_set_rule[] array
     */
    public function get_sampling_set_rules() {

        global $DB;

        // Get rules for include sets.
        $params = array('courseworkid' => $this->coursework->id);
        $rules = $DB->get_records('coursework_mod_set_rules', $params, 'ruleorder');

        foreach ($rules as $key => &$rule) {
            $classname = '\mod_coursework\sample_set_rule\\'.$rule->rulename;
            if (!class_exists($classname)) {
                unset($rules[$key]);
                continue;
            }
            $rules[$key] = new $classname($rule);
        }

        return $rules;
    }

    /**
     * Returns a new widget object that can be rendered to make the bit where the user defines the sampling set.
     *
     * @param string|bool $requestedrule
     * @return \mod_coursework_sampling_set_widget
     */
    public function get_sampling_set_widget($requestedrule = false) {



        $rules = $this->get_sampling_set_rules();



        $widget = new sampling_set_widget($rules, $this->coursework, $requestedrule);

        return new \mod_coursework_sampling_set_widget($widget);
    }



    /**
     * We know a rule came in, so we save it by delegating to the class, which will know what the form submitted.
     *
     * @param string $rule_name
     * @throws moodle_exception
     */
    public function save_sample_set_rule($assessor_number) {

        global $CFG, $DB;

        $sampleplugins  =   $DB->get_records('coursework_sample_set_plugin',null,'pluginorder');
        $order  =   0;
        foreach ($sampleplugins as $plugin) {

            $classname = '\mod_coursework\sample_set_rule\\' . $plugin->rulename;

            $rule = new $classname($this->coursework);

            $rule->save_form_data($assessor_number,$order);

        }

    }

    public function save_sample()   {

        global  $DB;
        $DB->delete_records('coursework_sample_set_rules',array('courseworkid'=>$this->coursework->id));
        for ($i = 2; $i <= $this->coursework->get_max_markers(); $i++)   {

            $sample_strategy    =   required_param("assessor_{$i}_samplingstrategy",PARAM_INT);

            if ($sample_strategy)   {
                $this->save_sample_set_rule($i);
            }


        }
        $this->auto_generate_sample_set();
    }

    /**
     * @return coursework
     */
    private function get_coursework() {
        return $this->coursework;
    }


    public function auto_generate_sample_set()  {
        global $DB;

        $sampleplugins  =   $DB->get_records('coursework_sample_set_plugin',null,'pluginorder');
        $order  =   0;


        $sample_set  =   array();

        $allocatables = $this->get_coursework()->get_allocatables();

        $final_agreed_allocatables  =   $this->get_allocatables_with_final_agreed();

        //remove any allocatables that have a status of final agreed as these can not be sampled
        foreach($final_agreed_allocatables as $faa)   {
            if (isset($allocatables[$faa->allocatableid]))  unset($allocatables[$faa->allocatableid]);
        }

        for($stage_number = 2; $stage_number <= $this->get_coursework()->get_max_markers(); $stage_number++) {


            $stage  =   "assessor_{$stage_number}";

            $this->remove_unmarked_automatic_allocatables($stage);

            $sql    =   "SELECT DISTINCT rulename
                         FROM (SELECT         rulename,ruleorder
                               FROM           {coursework_sample_set_plugin} p,
                                              {coursework_sample_set_rules} r
                               WHERE          p.id  = r.sample_set_plugin_id
                               AND            r.courseworkid = :courseworkid
                               AND            stage_identifier = :stage
                               ORDER BY       ruleorder)a";


            if ($sampleplugins  = $DB->get_records_sql($sql,array('courseworkid'=>$this->coursework->id,'stage'=>$stage))) {

                //$allocatables = $this->get_coursework()->get_allocatables_with_feedback();
                $allocatables = $this->get_coursework()->get_allocatables();
                $manual_sample_set = $this->get_include_in_sample_set($stage_number);

                $auto_with_feedback =   $this->get_automatic_with_feedback($stage);




                //ok this array merge is being carried out using an foreach rather than array_merge as we want to preserve keys
                //I am also not using add the two arrays as using the overloaded + can produce dubious results when a key exists
                //in both arrays

                foreach($auto_with_feedback as $k => $v)   {
                    if (!isset($manual_sample_set[$k])) $manual_sample_set[$k]  =   $v;
                }


                $auto_sample_set    =   array();

                foreach ($sampleplugins as $plugin) {
                    $classname = '\mod_coursework\sample_set_rule\\' . $plugin->rulename;

                    $rule = new $classname($this->coursework);

                       $rule->adjust_sample_set($stage_number,$allocatables,$manual_sample_set,$auto_sample_set);

                }

                //save sample set
                if (!empty($auto_sample_set))    {
                        foreach($auto_sample_set    as  $allocatable)   {
                            $sample     =   new \stdClass();
                            $sample->courseworkid       =   $this->coursework->id;
                            $sample->allocatableid      =   $allocatable->id;
                            $sample->allocatabletype    =   ($this->coursework->is_configured_to_have_group_submissions()) ? "group" : "user";
                            $sample->stage_identifier   =   "assessor_{$stage_number}";
                            $sample->selectiontype      =   "automatic";

                            //if this a manually selected allocatable check to see if the allocatable is already in the table
                            $DB->insert_record("coursework_sample_set_mbrs", $sample);

                        }
                }
            }
        }
    }

    public function get_include_in_sample_set($stage_number) {

        global  $DB;

        $stage  =   "assessor_{$stage_number}";

        $sql    =   "SELECT     allocatableid,
                                courseworkid,
                                allocatabletype,
                                stage_identifier,
                                selectiontype
                     FROM       {coursework_sample_set_mbrs}
                     WHERE      courseworkid  = :courseworkid
                     AND        stage_identifier  = :stage_identifier
                     AND        selectiontype = 'manual'";


        //get all users in manually selected for stage in coursework
        return $DB->get_records_sql($sql,
            array('courseworkid'=>$this->coursework->id,'stage_identifier'=>$stage));

    }

    public function get_automatic_with_feedback($stage)  {

        global $DB;

        $sql    =   "SELECT         s.allocatableid, f.*
                         FROM       {coursework_submissions}  s,
                                    {coursework_feedbacks}    f,
                                    {coursework_sample_set_mbrs} m
                         WHERE      s.id   = f.submissionid
                         AND        s.courseworkid = :courseworkid
                         AND        f.stage_identifier = :stage
                         AND        s.courseworkid = m.courseworkid
                         AND        s.allocatableid =  m.allocatableid
                         AND        s.allocatabletype =  m.allocatabletype
                         AND        f.stage_identifier = m.stage_identifier
                         ";

        return $DB->get_records_sql($sql,array('courseworkid'=>$this->coursework->id,'stage'=>$stage));
    }


    public function remove_unmarked_automatic_allocatables($stage)    {
        global $DB;

        $sql    =   "DELETE
                     FROM     {coursework_sample_set_mbrs}
                     WHERE    selectiontype = 'automatic'
                     AND      stage_identifier = '{$stage}'
                     AND      courseworkid  = {$this->coursework->id}
                     AND      allocatableid NOT IN (
                        SELECT    s.allocatableid
                        FROM      {coursework_submissions} s,
                                  {coursework_feedbacks}    f
                        WHERE     s.id   = f.submissionid
                         AND      s.courseworkid = {$this->coursework->id}
                         AND      f.stage_identifier = '{$stage}'

                     )";

        return $DB->execute($sql);



    }

    public function get_allocatables_with_final_agreed()    {

        global $DB;

        $sql = "SELECT s.allocatableid, f.*
                  FROM {coursework_submissions} s
                  JOIN {coursework_feedbacks} f
                    ON f.submissionid = s.id
                 WHERE s.courseworkid = {$this->coursework->id}
                   AND f.stage_identifier = 'final_agreed_1'";

        return $DB->get_records_sql($sql, array('courseworkid'=>$this->coursework->id));

    }




}
