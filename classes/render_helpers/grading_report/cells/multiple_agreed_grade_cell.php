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
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\render_helpers\grading_report\cells;

use html_writer;
use mod_coursework\ability;
use mod_coursework\grade_judge;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\feedback;
use mod_coursework\models\user;
use mod_coursework\stages\base as stage_base;
use pix_icon;

/**
 * Class feedback_cell
 */
class multiple_agreed_grade_cell extends cell_base {

    /**
     * @var stage_base
     */
    private $stage;

    /**
     * @param array $items
     */
    protected function after_initialisation($items) {
        $this->stage = $items['stage'];
    }

    /**
     * @param grading_table_row_base $rowobject
     * @return string
     */
    public function get_table_cell($rowobject) {
        $content = $this->get_content($rowobject);
        return $this->get_new_cell_with_class($content);
    }

    /**
     * @param $rowobject
     * @return \html_table_cell|string
     */
    public function get_content($rowobject) {

        global $USER, $OUTPUT;

        // If coursework uses sampling check if any enabled for this submission, otherwise there is no agreed grade
        if ($rowobject->get_coursework()->sampling_enabled() && $rowobject->get_submission() && !$rowobject->get_submission()->sampled_feedback_exists()) {
            $content = get_string('singlemarker', 'coursework');
            return $content;
        }
        $ability = new ability($USER->id, $rowobject->get_coursework());

        $content = '';

        $finalfeedback = $this->stage->get_feedback_for_allocatable($rowobject->get_allocatable());

        if ($finalfeedback !== false) {
            $gradejudge = new grade_judge($this->coursework);
            $content .= $gradejudge->grade_to_display($finalfeedback->get_grade());
            // $content .= html_writer::empty_tag('br');
            // $content .= ' by: ' . $finalfeedback->get_assesor_username();
        }

        // Edit/new link
        $existingfeedback = $this->stage->get_feedback_for_allocatable($rowobject->get_allocatable());
        $title = get_string('editfinalgrade', 'coursework');
        $icon = new pix_icon('edit', $title, 'coursework');
        $iconlink = '';

        if ($existingfeedback && $ability->can('edit', $existingfeedback)) {

            $feedbackrouteparams = [
                'feedback' => $finalfeedback,
            ];
            $link = $this->get_router()->get_path('ajax edit feedback', $feedbackrouteparams);

            $iconlink = $OUTPUT->action_icon($link,
                                             $icon,
                                             null,
                                             [
                                                 'class' => 'edit_final_feedback',
                                                 'id' => 'edit_final_feedback_' . $rowobject->get_coursework()
                                                     ->get_allocatable_identifier_hash($rowobject->get_allocatable())]);

        } else if ($rowobject->has_submission()) { // New

            $feedbackparams = [
                'submissionid' => $rowobject->get_submission()->id,
                'assessorid' => $USER->id,
                'stage_identifier' => $this->stage->identifier(),
            ];
            $newfeedback = feedback::build($feedbackparams);

            // If the user is a site admin then they can add final feedback
            if ($ability->can('new', $newfeedback) || is_siteadmin()) {
                $title = get_string('addfinalfeedback', 'coursework');
                $feedbackrouteparams = [
                    'submission' => $rowobject->get_submission(),
                    'assessor' => $USER,
                    'stage' => $this->stage,
                ];
                $link = $this->get_router()->get_path('ajax new final feedback', $feedbackrouteparams);

                $iconlink = $OUTPUT->action_link($link,
                                                 $title,
                                                 null,
                                                 ['class' => 'new_final_feedback',
                                                       'id' => 'new_final_feedback_' . $rowobject->get_coursework()
                                                           ->get_allocatable_identifier_hash($rowobject->get_allocatable())]);

            } else if ($existingfeedback && $ability->can('show', $existingfeedback)) {

                $linktitle = get_string('viewfeedback', 'mod_coursework');
                $linkid = "show_feedback_" . $rowobject->get_coursework()
                    ->get_allocatable_identifier_hash($rowobject->get_allocatable());
                $link = $this->get_router()
                    ->get_path('show feedback', ['feedback' => $this->stage->get_feedback_for_allocatable($rowobject->get_allocatable())]);
                $iconlink = $OUTPUT->action_link($link,
                                                 $linktitle,
                                                 null,
                                                 ['class' => 'show_feedback', 'id' => $linkid]);
            }
        }

        if ($iconlink) {
            $content .= ' ' . $iconlink;
        }

        if ($finalfeedback !== false) {
            $content .= html_writer::empty_tag('br');
            if ((!$this->coursework->sampling_enabled() || $rowobject->get_submission()->sampled_feedback_exists()) && ($finalfeedback->get_feedbacks_assessorid() == 0
                 && $finalfeedback->timecreated == $finalfeedback->timemodified)
                 || $finalfeedback->lasteditedbyuser == 0) { // if the grade was automatically agreed
                $content .= "(".get_string('automaticagreement', 'coursework').")";
            } else {
                $content .= ' by: ' . $finalfeedback->get_assesor_username();
            }
        }
        return $content;
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options  = []) {

        // Adding this line so that the sortable heading function will make a sortable link unique to the table
        // If tablename is set
        $tablename = (isset($options['tablename'])) ? $options['tablename'] : '';

        $columnname = get_string('agreedgrade', 'coursework');
        return $this->helper_sortable_heading($columnname, 'finalgrade', $options['sorthow'], $options['sortby'], $tablename);
    }

    /**
     * @return string
     */
    public function get_table_header_class() {
        return 'agreedgrade';
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'grades';
    }
}
