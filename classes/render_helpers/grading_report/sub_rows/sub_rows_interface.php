<?php

namespace mod_coursework\render_helpers\grading_report\sub_rows;

use mod_coursework\grading_table_row_base;

/**
 * Interface sub_rows_interface
 */
interface sub_rows_interface {

    /**
     * @param grading_table_row_base $row_object
     * @param int $column_width
     * @return mixed
     */
    public function get_row_with_assessor_feedback_table($row_object, $column_width);

}