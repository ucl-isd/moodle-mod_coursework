<?php

namespace mod_coursework\export\csv\cells;

/**
 * Class submissionfileid_cell
 */
class submissionfileid_cell extends cell_base {

    /**
     * @param $submission
     * @param $student
     * @param $stage_identifier
     * @return string
     */
    public function get_cell($submission, $student, $stage_identifier){
        return  $this->coursework->get_username_hash($submission->allocatableid);
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage){
        return  get_string('submissionfileid', 'coursework');
    }

    public function validate_cell($value,$submissionid,$stage_dentifier='',$uploadedgradecells = array())  {
        global $DB;

        if (empty($value))  {
            return 'No submission hash value entered';
        }

        $subdbrecord =   $DB->get_record('coursework_submissions',array('id'=>$submissionid));

        $submission = \mod_coursework\models\submission::find($subdbrecord);

        $hash   =    $this->coursework->get_username_hash($submission->allocatableid);

        return ($value == $hash) ? true : get_string('submissionnotfound','coursework');
    }

}