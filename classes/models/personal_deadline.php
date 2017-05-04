<?php

namespace mod_coursework\models;

use  mod_coursework\framework\table_base;

/**
 * Class personal_deadline is responsible for representing one row of the personal_deadline table.

 *
 * @property mixed personal_deadline
 * @property mixed courseworkid
 * @property mixed allocatabletype
 * @property mixed allocatableid
 * @package mod_coursework\models
 */
class personal_deadline extends table_base {

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @var string
     */
    protected static $table_name = 'coursework_person_deadlines';

    /**
     * @return mixed|\mod_coursework_coursework
     */
    public function get_coursework() {
        if (!isset($this->coursework)) {
            $this->coursework = coursework::find($this->courseworkid);
        }

        return $this->coursework;
    }

    public function get_allocatable() {
        $class_name = "\\mod_coursework\\models\\{$this->allocatabletype}";
        return $class_name::find($this->allocatableid);
    }

    /**
     * Function to check if extension for this personal deadline (alloctable) exists
     * @return static
     */
    public function extension_exists(){
        $coursework = $this->get_coursework();

        $params = array('courseworkid' => $coursework->id,
                        'allocatableid' => $this->allocatableid,
                        'allocatabletype' => $this->allocatabletype);

        return   deadline_extension::find($params);
    }

    /**
     * @param user $student
     * @param coursework $coursework
     * @return personal_deadline|bool
     */
    public static function get_personal_deadline_for_student($student, $coursework) {
        if ($coursework->is_configured_to_have_group_submissions()) {
            $allocatable = $coursework->get_student_group($student);
        } else {
            $allocatable = $student;
        }
        if ($allocatable) {
            return static::find(array('courseworkid' => $coursework->id,
                                      'allocatableid' => $allocatable->id(),
                                      'allocatabletype' => $allocatable->type(),
            ));
        }
    }

}