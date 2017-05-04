<?php

namespace mod_coursework\export\csv\cells;

/**
 * Class extensionreason_cell
 */
class extensionreason_cell extends cell_base {

    /**
     * @param $submission
     * @param $student
     * @param $stage_identifier
     * @return string
     */
    public function get_cell($submission, $student, $stage_identifier){

        if ($this->extension_exists($student)) {
            $reason = $this->get_extension_reason_for_csv($student);
        } else {
            $reason = '';
        }
        return $reason;
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage){
        return  get_string('extensionreason', 'coursework');
    }

}