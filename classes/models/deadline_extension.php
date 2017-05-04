<?php

namespace mod_coursework\models;

use mod_coursework\framework\table_base;
use mod_coursework\allocation\allocatable;

/**
 * Class deadline_extension is responsible for representing one row of the deadline_extensions table.
 * Each extension is awarded to a user or group so that they are allowed to submit after the deadline
 * due to extenuating circumstances.
 *
 * @property mixed extended_deadline
 * @property mixed courseworkid
 * @property mixed allocatabletype
 * @property mixed allocatableid
 * @package mod_coursework\models
 */
class deadline_extension extends table_base {

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @var string
     */
    protected static $table_name = 'coursework_extensions';

    /**
     * @param allocatable $allocatable
     * @param coursework $coursework
     * @return bool
     */
    public static function allocatable_extension_allows_submission($allocatable, $coursework) {
        $params = array('allocatabletype' => $allocatable->type(),
                        'allocatableid' => $allocatable->id(),
                        'courseworkid' => $coursework->id,
        );
        $extension = self::find($params);
        return !empty($extension) && $extension->extended_deadline > time();
    }

    /**
     * @param user $student
     * @param coursework $coursework
     * @return deadline_extension|bool
     */
    public static function get_extension_for_student($student, $coursework) {
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

    protected function pre_save_hook() {
        global $USER;

        if (!$this->persisted()) {
            $this->createdbyid = $USER->id;
        }
    }
}