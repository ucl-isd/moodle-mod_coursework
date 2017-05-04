<?php

namespace mod_coursework\export\csv\cells;

/**
 * Class submissiontime_cell
 */
class submissiontime_cell extends cell_base {


    /**
     * @param $submission
     * @param $student
     * @param $stage_identifier
     * @return string
     */
    public function get_cell($submission, $student, $stage_identifier){
       return $this->submission_time($submission);
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage){
        return  get_string('submissiontime', 'coursework');

    }


}