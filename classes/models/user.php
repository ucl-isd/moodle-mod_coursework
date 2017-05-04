<?php

/**
 * This class allows us to add functionality to the users, despite the fact that Moodle has no
 * core user class. Initially, it is using the active record approach, but this may need to change to
 * a decorator if Moodle implements such a class in future.
 */

namespace mod_coursework\models;

use mod_coursework\framework\table_base;
use \mod_coursework\allocation\allocatable;
use \mod_coursework\allocation\moderatable;
use mod_coursework\traits\allocatable_functions;

/**
 * Class user
 * @package mod_coursework\models
 */
class user extends table_base implements allocatable, moderatable {

    use allocatable_functions;

    /**
     * @var string
     */
    protected static $table_name = 'user';

    /**
     * @param bool $data
     */
    public function __construct($data = false) {
        $allnames = get_all_user_name_fields();
        foreach($allnames as $namefield) {
            $this->$namefield = '';
        }
        parent::__construct($data);
    }

    /**
     * @return string
     */
    public function name() {
        return fullname($this);
    }

    /**
     * @return string
     */
    public function type() {
        return 'user';
    }

    /**
     * @return string
     */
    public function picture() {
        global $OUTPUT;

        return $OUTPUT->user_picture($this->get_raw_record());
    }

    /**
     * @param bool $with_picture
     * @return string
     */
    public function profile_link($with_picture = false) {
        global $OUTPUT;

        $output = '';
        if ($with_picture) {
            $output .= $OUTPUT->user_picture($this->get_raw_record(), array('link' => false));
            $output .= ' ';
        }
        $output .= ' ' . $this->name();

        return \html_writer::link(new \moodle_url('/user/view.php', array('id' => $this->id())), $output, array('data-assessorid' => $this->id()));
    }

    /**
     * @param \stdClass $course
     * @return mixed
     */
    public function is_valid_for_course($course) {
        $course_context = \context_course::instance($course->id);
        return is_enrolled($course_context, $this->id(), 'mod/coursework:submit');
    }

    /**
     * @param coursework $coursework
     * @param int $reminder_number
     * @return bool
     */
    public function has_not_been_sent_reminder($coursework, $reminder_number, $extension=0) {
        $conditions = array(
            'coursework_id' => $coursework->id,
            'userid' => $this->id(),
            'remindernumber' => $reminder_number,
            'extension' => $extension
        );
        return !reminder::exists($conditions);

    }

    /**
     * This is here because running an array_unique against an array of user objects was failing for not obvious
     * reason. A comparison of the objects as strings seemed to show them as
     * @return int|string
     */
    public function __toString() {
        return $this->id;
    }


}