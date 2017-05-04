<?php

namespace mod_coursework\export\csv\cells;

/**
 * Class extensionextrainfo_cell
 */
class extensionextrainfo_cell extends cell_base {


    /**
     * @param $submission
     * @param $student
     * @param $stage_identifier
     * @return string
     */
    public function get_cell($submission, $student, $stage_identifier){

        if ($this->extension_exists($student)) {
            $extra_info = $this->get_extension_extra_info_for_csv($student);
        } else {
            $extra_info = '';
        }
        return $extra_info;
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage){
        return  get_string('extensionextrainfo', 'coursework');
    }

}