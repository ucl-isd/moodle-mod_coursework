<?php

namespace mod_coursework\allocation;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\models\submission;

/**
 * This tells us that a class (e.g. user or group) can be allocated to a teacher for marking
 * or moderation.
 *
 * @property int id
 * @package mod_coursework\allocation
 */
interface allocatable {

    /**
     * @return string
     */
    public function name();

    /**
     * @return int
     */
    public function id();

    /**
     * @return string
     */
    public function type();

    /**
     * @return string
     */
    public function picture();

    /**
     * @param bool $with_picture
     * @return string
     */
    public function profile_link($with_picture = false);

    /**
     * @param \stdClass $course
     * @return mixed
     */
    public function is_valid_for_course($course);

    /**
     * @param coursework $coursework
     * @return bool
     */
    public function has_agreed_feedback($coursework);

    /**
     * @param coursework $coursework
     * @return bool
     */
    public function get_agreed_feedback($coursework);

    /**
     * @param coursework $coursework
     * @return feedback[]
     */
    public function get_initial_feedbacks($coursework);

    /**
     * @param coursework $coursework
     * @return bool
     */
    public function has_all_initial_feedbacks($coursework);

    /**
     * @param coursework $coursework
     * @return submission
     */
    public function get_submission($coursework);
}