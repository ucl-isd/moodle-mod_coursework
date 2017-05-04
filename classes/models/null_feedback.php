<?php

namespace mod_coursework\models;

/**
 * Class null_feedback is responsible for implementing the null objecti pattern for the feedback
 * class.
 *
 */
class null_feedback {

    /**
     * @var int
     */
    public $id = 0;

    /**
     * @return string
     */
    public function get_grade() {
        return '';
    }

}