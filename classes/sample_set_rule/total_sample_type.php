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

/**
 * File for a sampling rule that will include X students from between an upper and lower limit.
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2015 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\sample_set_rule;

use html_writer;
use mod_coursework\allocation\allocatable;
use mod_coursework\models\coursework;
use mod_coursework\sample_set_rule\sample_base;

defined('MOODLE_INTERNAL') || die();


/**
 * Defines a rule that will include all students above or below a particular percentage of
 * the total grade.
 */
class total_sample_type extends \mod_coursework\sample_set_rule\sample_base {

    public function adjust_set(array &$moderation_set, array &$potential_allocatables, $stage) {

    }


    public function get_numeric_boundaries()    {

    }

    public function get_default_rule_order()    {

    }

    public function add_form_elements($assessor_number=0) {

        global $DB;

        $sql    =   "SELECT     sr.*
                     FROM       {coursework_sample_set_rules}   sr,
                                {coursework_sample_set_plugin}  sp
                     WHERE      sr.sample_set_plugin_id  =  sp.id
                     AND        sr.courseworkid = {$this->coursework->id}
                     AND        sr.stage_identifier = 'assessor_{$assessor_number}'
                     AND        sp.rulename = 'total_sample_type'";

        $selected   =   ($record = $DB->get_record_sql($sql))  ?   array($record->upperlimit=>$record->upperlimit) : false;
        $checked    =   ($selected) ?   true : false;



        $percentage_options = array();

        for($i = 5;$i <= 100; $i = $i + 5)   {
            $percentage_options[$i] = "{$i}";
        }

        $html   =   html_writer::start_div('sampletotal');

        $html    .=  html_writer::checkbox("assessor_{$assessor_number}_sampletotal_checkbox",1,$checked,get_string('topupto','mod_coursework'),
            array('id'=>"assessor_{$assessor_number}_sampletotal_checkbox",'class'=>"assessor_{$assessor_number} total_checkbox sample_set_rule"));



        $html   .= html_writer::select($percentage_options,
            "assessor_{$assessor_number}_sampletotal",
            "",
            $selected,
            array('id'=>"assessor_{$assessor_number}_sampletotal", 'class' => " sample_set_rule"));
        $html    .= html_writer::label(get_string('ofallstudents', 'mod_coursework'),'assessortwo_sampletotal[]');

        $html   .=  html_writer::end_div();



        return $html;
    }


    public function add_form_elements_js($assessor_number=0) {

        $js_script   =  "

            $('.total_checkbox').each(function(e,element) {

                    var ele_id =   $(this).attr('id').split('_');
                    var sampletotal    =   '#'+ele_id[0]+'_'+ele_id[1]+'_sampletotal';
                    var disabled   =   !$(this).prop('checked');
                   $(sampletotal).attr('disabled',disabled);


                    $(element).on('change',function()   {
                        var ele_id =   $(this).attr('id').split('_');
                        var sampletotal    =   '#'+ele_id[0]+'_'+ele_id[1]+'_sampletotal';
                        var disabled   =   !$(this).prop('checked');
                       $(sampletotal).attr('disabled',disabled);
                    })

            });

        ";

        return  html_writer::script($js_script,null);

    }

    function save_form_data($assessor_number=0,&$order=0) {
        global $DB;

        $total_checkbox     =    optional_param("assessor_{$assessor_number}_sampletotal_checkbox",false,PARAM_INT);
        $sample_total       =    optional_param("assessor_{$assessor_number}_sampletotal",false,PARAM_INT);

        if ($total_checkbox) {


            $dbrecord = new \stdClass();
            $dbrecord->ruletype = "";
            $dbrecord->lowerlimit = 0;
            $dbrecord->upperlimit = $sample_total;
            $dbrecord->sample_set_plugin_id = 2; //TODO: THIS SHOULD NOT BE HARD CODED - AF
            $dbrecord->courseworkid = $this->coursework->id;
            $dbrecord->ruleorder = $order;
            $dbrecord->stage_identifier = "assessor_{$assessor_number}";

            $DB->insert_record('coursework_sample_set_rules',$dbrecord);
        }


    }


    static function compare_key($a, $b) {
        if ($a === $b) return 0;
        return ($a > $b)? 1:-1;
    }

    public function adjust_sample_set($stage_number,&$allocatables,&$manual_sample_set,&$auto_sample_set) {

        global $DB;

        $stage  =   "assessor_".$stage_number;

        $sql    =   "SELECT         r.*,p.rulename
                         FROM           {coursework_sample_set_plugin} p,
                                        {coursework_sample_set_rules} r
                         WHERE          p.id  = r.sample_set_plugin_id
                         AND            r.courseworkid = :courseworkid
                         AND            p.rulename = 'total_sample_type'
                         AND            stage_identifier = :stage
                         ORDER BY       ruleorder";

        $rule     =   $DB->get_record_sql($sql,array('courseworkid'=>$this->coursework->id,'stage'=>$stage));

        if ($rule)  {

            $finalised = $this->finalised_submissions();
            $published = $this->released_submissions();
            $number_of_alloctables      =  count($allocatables);

            $total_to_return            =   ceil(($rule->upperlimit/100) * $number_of_alloctables);

            //we include the manual sample set in the count
            // TODO: should we do this?
            $total_to_return -=  count($manual_sample_set);

            //if the resultant number isnt greater than 0 then no automatic sample allocatables will be used
            if ($total_to_return > 0) {

                //use array chunk to split auto sample set into chunks we will only use the first chunk
                if ($chunked_array = array_chunk($auto_sample_set, $total_to_return, true)) $auto_sample_set = $chunked_array[0];



                //if the number in the sample set is less than the total to return
                if (count($auto_sample_set) < $total_to_return) {

                    //we need to top up the sample set with other allocatables


                    //graded at the previous stage take precedence

                    $previous_stage_number = $stage_number - 1;

                    $previous_stage = 'assessor_' . $previous_stage_number;

                    $allocatables_feedback = $this->coursework->get_allocatables_with_feedback($previous_stage, true);

                    foreach ($allocatables_feedback as $af) {

                        if (!isset($published[$af->allocatableid]) && !isset($finalised[$af->allocatableid])
                            && !isset($auto_sample_set[$af->allocatableid]) && !isset($manual_sample_set[$af->allocatableid]))
                            $auto_sample_set[$af->allocatableid] = $allocatables[$af->allocatableid];

                        if (count($auto_sample_set) == $total_to_return) break;

                    }
                }


                //if this is not enough select anyone (which should == the ungraded as all graded should have been added)
                if (count($auto_sample_set) < $total_to_return) {

                        //remove allocatables with published submissions
                        $allocatable_sample_set = array_diff_ukey($allocatables,$published,array("mod_coursework\\sample_set_rule\\total_sample_type", "compare_key"));

                    //remove allocatables with finalised submissions
                    $allocatable_sample_set = array_diff_ukey($allocatable_sample_set,$finalised,array("mod_coursework\\sample_set_rule\\total_sample_type", "compare_key"));

                    //remove allocatables who have been manually selected
                    $allocatable_sample_set = array_diff_ukey($allocatable_sample_set,$manual_sample_set,array("mod_coursework\\sample_set_rule\\total_sample_type", "compare_key"));

                    //remove allocatables already in the sample set
                    $allocatable_sample_set = array_diff_ukey($allocatable_sample_set,$auto_sample_set,array("mod_coursework\\sample_set_rule\\total_sample_type", "compare_key"));

                        $array_keys = array_rand($allocatable_sample_set, $total_to_return - count($auto_sample_set));

                        if (!is_array($array_keys)) $array_keys =   array($array_keys);

                        //use the allocatables array to get other ungraded allocatables
                        foreach ($array_keys as $id) {

                            if (!isset($published[$id]) && !isset($finalised[$id])
                                && !isset($auto_sample_set[$id]) && !isset($manual_sample_set[$id])
                            )
                                $auto_sample_set[$id] = $allocatables[$id];

                            if (count($auto_sample_set) == $total_to_return) break;
                        }

                }

            } else {
                $auto_sample_set = array();
            }


        }

    }






}