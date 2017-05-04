<?php

namespace mod_coursework\plagiarism_helpers;
use mod_coursework\models\coursework;

/**
 * Class base
 * @package mod_coursework\plagiarism_helpers
 */
abstract class base {

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @param $coursework
     */
    public function __construct($coursework) {
        $this->coursework = $coursework;
    }

    /**
     * @return coursework
     */
    protected function get_coursework() {
        return $this->coursework;
    }

    /**
     * @return string
     */
    abstract public function file_submission_instructions();

    /**
     * @return bool
     */
    abstract public function enabled();

    /**
     * @return string
     */
    abstract public function human_readable_name();

}