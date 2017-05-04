<?php

namespace mod_coursework\export\csv\cells;
use mod_coursework\models\submission;
/**
 * Class personaldeadline_cell
 */
class personaldeadline_cell extends cell_base {

    /**
     * @param submission $submission
     * @param $student
     * @param $stage_identifier
     * @return string
     */
    public function get_cell($submission, $student, $stage_identifier){

        $personal_deadline = $submission->submission_personal_deadline();
        
        return userdate($personal_deadline, $this->dateformat);
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage){
        return  get_string('personal_deadline', 'coursework');
    }

}