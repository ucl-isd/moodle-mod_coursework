<?php

namespace mod_coursework\render_helpers\grading_report\cells;
use coding_exception;
use html_writer;
use mod_coursework\ability;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use mod_coursework\models\plagiarism_flag;
use mod_coursework_submission_files;
use moodle_url;
use pix_icon;

/**
 * Class plagiarism_flag_cell
 */
class plagiarism_flag_cell extends cell_base {

    /**
     * @param grading_table_row_base $rowobject
     * @throws coding_exception
     * @return string
     */
    public function get_table_cell($rowobject) {

        global $USER;

        $content = '';
        $ability = new ability(user::find($USER), $rowobject->get_coursework());

        if($rowobject->has_submission() && $rowobject->get_submission()->finalised){
            $plagiarism_flag_params = array(
                'submissionid' => $rowobject->get_submission()->id
            );
            $plagiarism_flag =  plagiarism_flag::find($plagiarism_flag_params);

            if(!$plagiarism_flag){  // if plagiarism flag for this submission doesn't exist, we can create one
                $plagiarism_flag_params = array('courseworkid' => $rowobject->get_coursework()->id,
                                                'submissionid' => $rowobject->get_submission()->id);
                $new_plagiarism_flag = plagiarism_flag::build($plagiarism_flag_params);

                if ($ability->can('new', $new_plagiarism_flag)) {
                    $content .= $this->new_flag_plagiarism_button($rowobject); // new button
                    $content .= html_writer::empty_tag('br');
                }
            } else {

                $content .= "<div class = plagiarism_".$plagiarism_flag->status.">".get_string('plagiarism_' . $plagiarism_flag->status, 'coursework')." ";

                if ($ability->can('edit', $plagiarism_flag)) { // Edit
                    $content .= $this->edit_flag_plagiarism_button($rowobject); // edit button
                }
                $content .= "</div>";
            }
        }

        return $this->get_new_cell_with_class($content);
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options = array()) {
        return (get_string('tableheadplagiarismalert', 'coursework'));
    }

    /**
     * @return string
     */
    public function get_table_header_class(){
        return 'tableheadplagiarismalert';
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'submission';
    }


    /**
     * @param grading_table_row_base $rowobject
     * @param $row_object
     * @return array
     * @throws \coding_exception
     */
    private function new_flag_plagiarism_button($row_object) {
        global $OUTPUT;

        $title = get_string('flagplagiarism', 'coursework');

        $feedback_params = array(
            'submission' => $row_object->get_submission()
        );
        $link = $this->get_router()->get_path('new plagiarism flag', $feedback_params);

        $html_attributes = array(
            'id' => 'new_plagiarism_flag_' . $row_object->get_coursework()->get_allocatable_identifier_hash($row_object->get_allocatable()),
            'class' => 'new_plagiarism_flag',
        );

        return $OUTPUT->action_link($link, $title, null, $html_attributes);
    }


    /**
     * @param grading_table_row_base $rowobject
     * @param $row_object
     * @return array
     * @throws \coding_exception
     */
    private function edit_flag_plagiarism_button($row_object) {
        global $OUTPUT;

        $title = get_string('editflagplagiarism', 'coursework');

        $feedback_params = array(
            'flag' => $row_object->get_plagiarism_flag()
        );
        $link = $this->get_router()->get_path('edit plagiarism flag', $feedback_params);

        $link_id = 'edit_plagiarism_flag_' . $row_object->get_coursework()->get_allocatable_identifier_hash($row_object->get_allocatable());

        $icon = new pix_icon('edit', $title, 'coursework');
        return $OUTPUT->action_icon($link, $icon, null, array('id' => $link_id));
    }
}