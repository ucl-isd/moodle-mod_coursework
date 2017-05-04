<?php

namespace mod_coursework\export\csv\cells;
use mod_coursework\models\submission;

/**
 * Class submissiondate_cell
 */
class submissiondate_cell extends cell_base {


    /**
     * @param submission $submission
     * @param $student
     * @param $stage_identifier
     * @return string
     */
    public function get_cell($submission, $student, $stage_identifier){
        return userdate($submission->time_submitted(), $this->dateformat);
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage){
        return  get_string('submissiondate', 'coursework');
    }

}