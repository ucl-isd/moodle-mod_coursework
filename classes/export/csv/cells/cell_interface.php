<?php

namespace mod_coursework\export\csv\cells;



/**
 * Interface cell_interface makes sure that all of the grading report cells are the same.
 */
interface cell_interface {

    /**
     * @param $submission
     * @param $student
     * @param $stage_identifier
     * @return mixed
     */
    public function get_cell($submission,$student,$stage_identifier);

    /**
     * @param $stage
     * @return mixed
     */
    public function get_header($stage);

    /**
     * @param $value
     * @param $submissions
     * @param $stage_dentifier
     * @return mixed
     */
    public function validate_cell($value,$submissions,$stage_dentifier='');


}