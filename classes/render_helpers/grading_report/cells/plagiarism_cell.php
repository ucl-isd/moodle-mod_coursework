<?php

namespace mod_coursework\render_helpers\grading_report\cells;
use coding_exception;
use html_table_cell;
use html_writer;
use mod_coursework\ability;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\user;
use mod_coursework_submission_files;
use moodle_url;

/**
 * Class feedback_cell
 */
class plagiarism_cell extends cell_base {

    /**
     * @param grading_table_row_base $rowobject
     * @throws coding_exception
     * @return string
     */
    public function get_table_cell($rowobject) {
        global $USER;

        $content = '';
        $ability = new ability(user::find($USER), $rowobject->get_coursework());

        if ($rowobject->has_submission() && $ability->can('show', $rowobject->get_submission())) {
            // The files and the form to resubmit them.
            $submission_files = $rowobject->get_submission_files();
            if ($submission_files) {
                $content .= $this->get_renderer()->render_plagiarism_links(new mod_coursework_submission_files($submission_files));
            }
        }

        return $this->get_new_cell_with_class($content);
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options = array()) {
        return get_string('plagiarism', 'mod_coursework');
    }

    /**
     * @return string
     */
    public function get_table_header_class(){
        return 'plagiarism';
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'submission';
    }
}