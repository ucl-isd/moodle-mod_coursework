<?php

namespace mod_coursework\auto_grader;

/**
 * Class null is responsible for implementing the null object pattern for the auto grader mechanism.
 *
 * @package mod_coursework\auto_grader
 */
class null implements auto_grader {

    /**
     * @param $coursework
     * @param $allocatable
     * @param $percentage
     */
    public function __construct($coursework, $allocatable, $percentage) {
    }

    public function create_auto_grade_if_rules_match() {
    }
}