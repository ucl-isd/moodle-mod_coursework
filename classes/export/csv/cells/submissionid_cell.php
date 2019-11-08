<?php

namespace mod_coursework\export\csv\cells;

/**
 * Class submissionid_cell
 */
class submissionid_cell extends cell_base {

    /**
     * @param $submission
     * @param $student
     * @param $stage_identifier
     * @return mixed
     */
    public function get_cell($submission, $student, $stage_identifier){
        return $submission->id;
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage){
        return  get_string('submissionid', 'coursework');
    }

    public function validate_cell($value,$submissionid,$stage_dentifier='',$uploadedgradecells = array()) {
        global $DB;
        return ($DB->record_exists('coursework_submissions',array('id'=>$submissionid,'courseworkid'=>$this->coursework->id()))) ? true: get_string('submissionnotfoundincoursework','coursework');
    }

}