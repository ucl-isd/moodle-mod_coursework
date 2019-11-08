<?php

namespace mod_coursework\models;

use  mod_coursework\framework\table_base;

/**
 * Class plagiarism flag is responsible for representing one row of the plagiarism flags table.

 *
 * @property mixed personal_deadline
 * @property mixed courseworkid
 * @property mixed allocatabletype
 * @property mixed allocatableid
 * @package mod_coursework\models
 */
class plagiarism_flag extends table_base {

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @var string
     */
    protected static $table_name = 'coursework_plagiarism_flags';

    /**
     * Constants with Statuses for Plagiarism flagging
     */
    const INVESTIGATION =   0;
    const RELEASED      =   1;
    const CLEARED       =   2;
    const NOTCLEARED    =   3;

    /**
     * @return mixed|\mod_coursework_coursework
     */
    public function get_coursework() {
        if (!isset($this->coursework)) {
            $this->coursework = coursework::find($this->courseworkid);
        }

        return $this->coursework;
    }

    /**
     * Memoized getter
     *
     * @return bool|submission
     */
    public function get_submission() {
        if (!isset($this->submission) && !empty($this->submissionid)) {
            $this->submission = submission::find($this->submissionid);
        }

        return $this->submission;
    }

    /**
     * @param $submission
     * @return static
     */
    public static function get_plagiarism_flag($submission){
        return static::find(array('submissionid' => $submission->id));
    }


    /**
     * @return bool
     */
    public function can_release_grades(){

        switch ($this->status){

            case self::INVESTIGATION:
            case self::NOTCLEARED:
                return false;
                break;
            case self::RELEASED:
            case self::CLEARED:
            return true;
            break;

        }
    }


}