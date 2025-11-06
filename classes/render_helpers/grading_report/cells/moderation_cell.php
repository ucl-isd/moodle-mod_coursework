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

use coding_exception;
use mod_coursework\ability;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\feedback;
use mod_coursework\stages\moderator;
use pix_icon;

/**
 * Class feedback_cell
 */
class moderation_cell extends cell_base {

    /**
     * @var moderator
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
     * @throws coding_exception
     * @return string
     */
    public function get_table_cell($rowobject) {
        global $USER;

        $content = '';

        if ($this->stage->has_feedback($rowobject->get_allocatable())) {
            $content .= $this->add_existing_moderator_feedback_details_to_cell($rowobject);
        }

        $ability = new ability($USER->id, $rowobject->get_coursework());
        $existingfeedback = $this->stage->get_feedback_for_allocatable($rowobject->get_allocatable());
        $newfeedback = feedback::build([
            'submissionid' => $rowobject->get_submission_id(),
            'stageidentifier' => $this->stage->identifier(),
            'assessorid' => $USER->id,
        ]);
        // New or edit for moderators
        if ($existingfeedback && $ability->can('edit', $existingfeedback)) { // Edit
            $content .= $this->add_edit_feedback_link_to_cell($rowobject, $existingfeedback);
        } else if ($ability->can('new', $newfeedback)) { // New
            $content .= $this->add_new_feedback_link_to_cell($rowobject);
        }

        return $this->get_new_cell_with_class($content);
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options  = []) {

        // Adding this line so that the sortable heading function will make a sortable link unique to the table
        // If tablename is set
        $tablename = (!empty($options['tablename'])) ? $options['tablename'] : '';

        return $this->helper_sortable_heading(get_string('moderator', 'coursework'),
                                              'modgrade',
                                              $options['sorthow'],
                                              $options['sortby'],
                                              $tablename);
    }

    /**
     * @return string
     */
    public function get_table_header_class() {
        return 'moderator';
    }

    /**
     * @param grading_table_row_base $rowobject
     * @return string
     */
    protected function add_existing_moderator_feedback_details_to_cell($rowobject) {
        $feedback = $this->stage->get_feedback_for_allocatable($rowobject->get_allocatable());
        $html = '';
        $html .= $feedback->assessor()->profile_link();
        $html .= '<br>';
        $html .= $feedback->get_grade();
        return $html;
    }

    /**
     * @param grading_table_row_base $rowobject
     * @param feedback $feedback
     * @return string
     * @throws coding_exception
     */
    protected function add_edit_feedback_link_to_cell($rowobject, $feedback) {
        global $OUTPUT;

        $title = get_string('moderatethis', 'coursework');
        $icon = new pix_icon('moderate', $title, 'coursework', ['width' => '20px']);

        $feedbackparams = [
            'feedback' => $feedback,
        ];
        $link = $this->get_router()->get_path('edit feedback', $feedbackparams);
        $htmlattributes = [
            'id' => 'edit_moderator_feedback_' . $rowobject->get_filename_hash(),
            'class' => 'edit_feedback',
        ];
        $iconlink = $OUTPUT->action_icon($link, $icon, null, $htmlattributes);

        return ' ' . $iconlink;
    }

    /**
     * @param grading_table_row_base $rowobject
     * @return string
     * @throws coding_exception
     */
    protected function add_new_feedback_link_to_cell($rowobject) {
        global $OUTPUT;

        $title = get_string('moderatethis', 'coursework');
        $icon = new pix_icon('moderate', $title, 'coursework', ['width' => '20px']);

        $feedbackparams = [
            'submission' => $rowobject->get_submission(),
            'stage' => $this->stage,
        ];
        $link = $this->get_router()->get_path('new moderator feedback', $feedbackparams);

        $htmlattributes = [
            'id' => 'new_moderator_feedback_' . $rowobject->get_coursework()->get_allocatable_identifier_hash($rowobject->get_allocatable()),
            'class' => 'new_feedback',
        ];
        $iconlink = $OUTPUT->action_icon($link, $icon, null, $htmlattributes);
        return ' ' . $iconlink;
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'grades';
    }
}
