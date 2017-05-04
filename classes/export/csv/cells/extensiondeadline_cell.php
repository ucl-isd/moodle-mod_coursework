<?php

namespace mod_coursework\export\csv\cells;

/**
 * Class extensiondeadline_cell
 */
class extensiondeadline_cell extends cell_base {

    /**
     * @param $submission
     * @param $student
     * @param $stage_identifier
     * @return string
     */
    public function get_cell($submission, $student, $stage_identifier){

        if ($this->extension_exists($student)) {
            $deadline = $this->get_extension_date_for_csv($student);
        } else {
            $deadline = '';
        }
        return $deadline;
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage){
        return  get_string('extensiondeadline', 'coursework');
    }

}