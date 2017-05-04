<?php

namespace mod_coursework\export\csv\cells;

/**
 * Class group_cell
 */
class group_cell extends cell_base {

    /**
     * @param $submission
     * @param $group
     * @param $stage_identifier
     * @return mixed
     */
    public function get_cell($submission, $group, $stage_identifier){
        return  $group->name;
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage){
        return  get_string('group', 'coursework');
    }
}