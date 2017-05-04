<?php

namespace mod_coursework\export\csv\cells;
use mod_coursework\models\submission;

/**
 * Class assessor_cell
 */
class assessor_cell extends cell_base{


    /**
     * @param $submission
     * @param $student
     * @param $stage_identifier
     * @return string
     */

    public function get_cell($submission, $student, $stage_identifier){

        $assessor = '';
        $allocation = $this->coursework->get_assessor_allocation($submission, $stage_identifier );
        if ($allocation) {
            $assessor = $this->get_assessor_name($allocation->assessorid);
        } else if($this->coursework->sampling_enabled()){
            $assessor = 'Not in sample';
        }
        return $assessor;
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage){
        return  get_string('assessorcsv', 'coursework', $stage);
    }



}