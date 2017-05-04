<?php

namespace mod_coursework\render_helpers\grading_report\cells;


use coding_exception;
use html_table_cell;
use mod_coursework\ability;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\feedback;
use mod_coursework\models\user;
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
     * @param grading_table_row_base $row_object
     * @throws coding_exception
     * @return string
     */
    public function get_table_cell($row_object) {
        global $USER;

        $content = '';

        if ($this->stage->has_feedback($row_object->get_allocatable())) {
            $content .= $this->add_existing_moderator_feedback_details_to_cell($row_object);
        }

        $ability = new ability(user::find($USER), $row_object->get_coursework());
        $existing_feedback = $this->stage->get_feedback_for_allocatable($row_object->get_allocatable());
        $new_feedback = feedback::build(array(
            'submissionid' => $row_object->get_submission_id(),
            'stage_identifier' => $this->stage->identifier(),
            'assessorid' => $USER->id,
        ));
        // New or edit for moderators
        if ($existing_feedback && $ability->can('edit', $existing_feedback)) { // Edit
            $content .= $this->add_edit_feedback_link_to_cell($row_object, $existing_feedback);
        } else if ($ability->can('new', $new_feedback)) { // New
            $content .= $this->add_new_feedback_link_to_cell($row_object);
        }

        return $this->get_new_cell_with_class($content);
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options = array()) {

        //adding this line so that the sortable heading function will make a sortable link unique to the table
        //if tablename is set
        $tablename  =   (!empty($options['tablename']))  ? $options['tablename']  : ''  ;

        return $this->helper_sortable_heading(get_string('moderator', 'coursework'),
                                              'modgrade',
                                              $options['sorthow'],
                                              $options['sortby'],
                                              $tablename);
    }

    /**
     * @return string
     */
    public function get_table_header_class(){
        return 'moderator';
    }

    /**
     * @param grading_table_row_base $row_object
     * @return string
     */
    protected function add_existing_moderator_feedback_details_to_cell($row_object) {
        $feedback = $this->stage->get_feedback_for_allocatable($row_object->get_allocatable());
        $html = '';
        $html .= $feedback->assessor()->profile_link();
        $html .= '<br>';
        $html .= $feedback->get_grade();
        return $html;
    }

    /**
     * @param grading_table_row_base $row_object
     * @param feedback $feedback
     * @return string
     * @throws \coding_exception
     */
    protected function add_edit_feedback_link_to_cell($row_object, $feedback) {
        global $OUTPUT;

        $title = get_string('moderatethis', 'coursework');
        $icon = new pix_icon('moderate', $title, 'coursework', array('width' => '20px'));

        $feedback_params = array(
            'feedback' => $feedback,
        );
        $link = $this->get_router()->get_path('edit feedback', $feedback_params);
        $html_attributes = array(
            'id' => 'edit_moderator_feedback_' . $row_object->get_filename_hash(),
            'class' => 'edit_feedback',
        );
        $iconlink = $OUTPUT->action_icon($link, $icon, null, $html_attributes);

        return ' ' . $iconlink;
    }

    /**
     * @param grading_table_row_base $row_object
     * @return string
     * @throws \coding_exception
     */
    protected function add_new_feedback_link_to_cell($row_object) {
        global $OUTPUT;

        $title = get_string('moderatethis', 'coursework');
        $icon = new pix_icon('moderate', $title, 'coursework', array('width' => '20px'));

        $feedback_params = array(
            'submission' => $row_object->get_submission(),
            'stage' => $this->stage,
        );
        $link = $this->get_router()->get_path('new moderator feedback', $feedback_params);

        $html_attributes = array(
            'id' => 'new_moderator_feedback_' . $row_object->get_coursework()->get_allocatable_identifier_hash($row_object->get_allocatable()),
            'class' => 'new_feedback',
        );
        $iconlink = $OUTPUT->action_icon($link, $icon, null, $html_attributes);
        return ' ' . $iconlink;
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'grades';
    }
}