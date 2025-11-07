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
 * @package    mod_coursework
 * @copyright  2015 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\sample_set_rule;

use html_writer;
use stdClass;

/**
 * Defines a rule that will include all students above or below a particular percentage of
 * the total grade.
 */
class total_sample_type extends \mod_coursework\sample_set_rule\sample_base {

    public function adjust_set(array &$moderationset, array &$potentialallocatables, $stage) {

    }

    public function get_numeric_boundaries() {

    }

    public function get_default_rule_order() {

    }

    public function add_form_elements($assessornumber = 0) {

        global $DB;

        $sql = "SELECT     sr.*
                     FROM       {coursework_sample_set_rules}   sr,
                                {coursework_sample_set_plugin}  sp
                     WHERE      sr.samplesetpluginid = sp.id
                     AND        sr.courseworkid = {$this->coursework->id}
                     AND        sr.stageidentifier = 'assessor_{$assessornumber}'
                     AND        sp.rulename = 'total_sample_type'";

        $selected = ($record = $DB->get_record_sql($sql)) ? [$record->upperlimit => $record->upperlimit] : false;
        $checked = ($selected) ? true : false;

        $percentageoptions = [];

        for ($i = 5; $i <= 100; $i = $i + 5) {
            $percentageoptions[$i] = "{$i}";
        }

        $html = html_writer::start_div('sampletotal');

        $html .= html_writer::checkbox(
            "assessor_{$assessornumber}_sampletotal_checkbox", 1, $checked, get_string('topupto', 'mod_coursework'),
            ['id' => "assessor_{$assessornumber}_sampletotal_checkbox", 'class' => "assessor{$assessornumber} totalcheckbox samplesetrule"]);

        $html .= html_writer::select(
            $percentageoptions,
            "assessor_{$assessornumber}_sampletotal",
            "",
            $selected,
            ['id' => "assessor_{$assessornumber}_sampletotal", 'class' => " sample_set_rule"]);
        $html  .= html_writer::label(get_string('ofallstudents', 'mod_coursework'), 'assessortwo_sampletotal[]');

        $html .= html_writer::end_div();

        return $html;
    }

    public function add_form_elements_js($assessornumber = 0) {

        $jsscript = "

            $('.total_checkbox').each(function(e,element) {

                    var ele_id = $(this).attr('id').split('_');
                    var sampletotal = '#'+ele_id[0]+'_'+ele_id[1]+'_sampletotal';
                    var disabled = !$(this).prop('checked');
                   $(sampletotal).attr('disabled',disabled);

                    $(element).on('change',function() {
                        var ele_id = $(this).attr('id').split('_');
                        var sampletotal = '#'+ele_id[0]+'_'+ele_id[1]+'_sampletotal';
                        var disabled = !$(this).prop('checked');
                       $(sampletotal).attr('disabled',disabled);
                    })

            });

        ";

        return  html_writer::script($jsscript, null);

    }

    public function save_form_data($assessornumber = 0, &$order = 0) {
        global $DB;

        $totalcheckbox = optional_param("assessor_{$assessornumber}_sampletotal_checkbox", false, PARAM_INT);
        $sampletotal = optional_param("assessor_{$assessornumber}_sampletotal", false, PARAM_INT);

        if ($totalcheckbox) {

            $dbrecord = new stdClass();
            $dbrecord->ruletype = "";
            $dbrecord->lowerlimit = 0;
            $dbrecord->upperlimit = $sampletotal;
            $dbrecord->samplesetpluginid = 2; // TODO: THIS SHOULD NOT BE HARD CODED - AF
            $dbrecord->courseworkid = $this->coursework->id;
            $dbrecord->ruleorder = $order;
            $dbrecord->stageidentifier = "assessor_{$assessornumber}";

            $DB->insert_record('coursework_sample_set_rules', $dbrecord);
        }

    }

    private static function compare_key($a, $b) {
        if ($a === $b) {
            return 0;
        }
        return ($a > $b) ? 1 : -1;
    }

    public function adjust_sample_set($stagenumber, &$allocatables, &$manualsampleset, &$autosampleset) {

        global $DB;

        $stage = "assessor_" . $stagenumber;

        $sql = "SELECT         r.*,p.rulename
                         FROM           {coursework_sample_set_plugin} p,
                                        {coursework_sample_set_rules} r
                         WHERE          p.id = r.samplesetpluginid
                         AND            r.courseworkid = :courseworkid
                         AND            p.rulename = 'total_sample_type'
                         AND            stageidentifier = :stage
                         ORDER BY       ruleorder";

        $rule = $DB->get_record_sql($sql, ['courseworkid' => $this->coursework->id, 'stage' => $stage]);

        if ($rule) {

            $finalised = $this->finalised_submissions();
            $published = $this->released_submissions();
            $numberofalloctables = count($allocatables);

            $totaltoreturn = ceil(($rule->upperlimit / 100) * $numberofalloctables);

            // We include the manual sample set in the count
            // TODO: should we do this?
            $totaltoreturn -= count($manualsampleset);

            // If the resultant number isnt greater than 0 then no automatic sample allocatables will be used
            if ($totaltoreturn > 0) {

                // Use array chunk to split auto sample set into chunks we will only use the first chunk
                if ($chunkedarray = array_chunk($autosampleset, $totaltoreturn, true)) {
                    $autosampleset = $chunkedarray[0];
                }

                // If the number in the sample set is less than the total to return
                if (count($autosampleset) < $totaltoreturn) {

                    // We need to top up the sample set with other allocatables

                    // Graded at the previous stage take precedence

                    $previousstagenumber = $stagenumber - 1;

                    $previousstage = 'assessor_' . $previousstagenumber;

                    $allocatablesfeedback = $this->coursework->get_allocatables_with_feedback($previousstage, true);

                    foreach ($allocatablesfeedback as $af) {

                        if (!isset($published[$af->allocatableid]) && !isset($finalised[$af->allocatableid])
                            && !isset($autosampleset[$af->allocatableid]) && !isset($manualsampleset[$af->allocatableid])) {
                                $autosampleset[$af->allocatableid] = $allocatables[$af->allocatableid];
                        }

                        if (count($autosampleset) == $totaltoreturn) {
                            break;
                        }

                    }
                }

                // If this is not enough select anyone (which should == the ungraded as all graded should have been added)
                if (count($autosampleset) < $totaltoreturn) {

                        // Remove allocatables with published submissions
                        $allocatablesampleset = array_diff_ukey($allocatables, $published, ["mod_coursework\\sample_set_rule\\total_sample_type", "compare_key"]);

                    // Remove allocatables with finalised submissions
                    $allocatablesampleset = array_diff_ukey($allocatablesampleset, $finalised, ["mod_coursework\\sample_set_rule\\total_sample_type", "compare_key"]);

                    // Remove allocatables who have been manually selected
                    $allocatablesampleset = array_diff_ukey($allocatablesampleset, $manualsampleset, ["mod_coursework\\sample_set_rule\\total_sample_type", "compare_key"]);

                    // Remove allocatables already in the sample set
                    $allocatablesampleset = array_diff_ukey($allocatablesampleset, $autosampleset, ["mod_coursework\\sample_set_rule\\total_sample_type", "compare_key"]);

                        $arraykeys = array_rand($allocatablesampleset, $totaltoreturn - count($autosampleset));

                    if (!is_array($arraykeys)) {
                        $arraykeys = [$arraykeys];
                    }

                        // Use the allocatables array to get other ungraded allocatables
                    foreach ($arraykeys as $id) {

                        if (!isset($published[$id]) && !isset($finalised[$id])
                            && !isset($autosampleset[$id]) && !isset($manualsampleset[$id])
                        ) {
                            $autosampleset[$id] = $allocatables[$id];
                        }

                        if (count($autosampleset) == $totaltoreturn) {
                            break;
                        }
                    }

                }

            } else {
                $autosampleset = [];
            }

        }

    }

}
