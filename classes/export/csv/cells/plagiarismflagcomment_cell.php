<?php

namespace mod_coursework\export\csv\cells;

/**
 * Class plagiarismflagcomment_cell
 */
class plagiarismflagcomment_cell extends cell_base {

    /**
     * @param $submission
     * @param $student
     * @param $stage_identifier
     * @return string
     */
    public function get_cell($submission, $student, $stage_identifier){

        if ($this->plagiarism_flagged($submission)) {
            $flag = $this->get_plagiarism_flag_comment_for_csv($submission);
        } else {
            $flag = '';
        }
        return $flag;
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage){
        return  get_string('plagiarismcomment', 'coursework');
    }

}