<?php

namespace mod_coursework\models;

/**
 * Class null_user
 */
class null_user implements \mod_coursework\allocation\allocatable {

    /**
     * @return string
     */
    public function name() {
        return '';
    }

    /**
     * @return string
     */
    public function picture() {
        return '';
    }

    /**
     * @return int
     */
    public function id() {
        return 0;
    }

    /**
     * @return string
     */
    public function type() {
        return 'user';
    }

    /**
     * @param bool $with_picture
     * @return string
     */
    public function profile_link($with_picture = false) {
        return '';
    }

    /**
     * @param \stdClass $course
     * @return mixed
     */
    public function is_valid_for_course($course) {
        return true;
    }

    /**
     * @param coursework $coursework
     * @return bool
     */
    public function has_agreed_feedback($coursework) {
        return false;
    }

    /**
     * @param coursework $coursework
     * @return feedback[]
     */
    public function get_initial_feedbacks($coursework) {
        return array();
    }


    /**
     * @param coursework $coursework
     * @return bool
     */
    public function has_all_initial_feedbacks($coursework) {
        return false;
    }


    /**
     * @param coursework $coursework
     * @return bool
     */
    public function get_agreed_feedback($coursework){
        return false;
    }


    /**
     * @param coursework $coursework
     * @return submission
     */
    public function get_submission($coursework) {
    }

}