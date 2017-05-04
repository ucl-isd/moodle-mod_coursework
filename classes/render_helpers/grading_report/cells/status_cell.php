<?php

namespace mod_coursework\render_helpers\grading_report\cells;
use html_table_cell;
use mod_coursework\grading_table_row_base;

/**
 * Class feedback_cell
 */
class status_cell extends cell_base {

    /**
     * @param grading_table_row_base $rowobject
     * @return string
     */
    public function get_table_cell($rowobject) {
        $content = '';

        $submission = $rowobject->get_submission();
        if ($submission) {
            $content = $submission->get_status_text();
        }
        return $this->get_new_cell_with_class($content);
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options = array()) {
        return get_string('tableheadstatus', 'coursework');
    }

    /**
     * @return string
     */
    public function get_table_header_class(){
        return 'tableheadstatus';
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'empty';
    }
}