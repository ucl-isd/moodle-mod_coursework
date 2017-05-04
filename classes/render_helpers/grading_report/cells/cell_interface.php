<?php

namespace mod_coursework\render_helpers\grading_report\cells;


use html_table_cell;
use mod_coursework\grading_table_row_base;
use mod_coursework\user_row;

/**
 * Interface cell_interface makes sure that all of the grading report cells are the same.
 */
interface cell_interface {

    /**
     * @param user_row $rowobject
     * @return string
     */
    public function get_table_cell($rowobject);

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options = array());


    /**
     * @return string
     */
    public function get_table_header_class();

    /**
     * Provides a class that will be applied to the cell
     *
     * @return string
     */
    public function cell_name();

    /**
     * @return string
     */
    public function header_group();

    /**
     * @return mixed
     */
    public function get_table_header_help_icon();
}