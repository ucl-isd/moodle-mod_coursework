<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework;

use mod_coursework\allocation\allocatable;
use mod_coursework\models\assessment_set_membership;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\models\null_feedback;
use mod_coursework\models\submission;
use mod_coursework\stages\base as stage_base;

/**
 * Class grade_judge is responsible for deciding what the student's final grade should be, given
 * various capping settings.
 *
 * @package mod_coursework
 */
class grade_judge {

    /**
     * @var coursework
     */
    private $coursework;

    /**
     * @param $coursework
     */
    public function __construct($coursework) {

        $this->coursework = $coursework;
    }

    /**
     * @param submission $submission
     * @return mixed
     */
    public function get_grade_capped_by_submission_time($submission) {

        if (empty($submission)) {
            return null;
        }

        return $this->get_submission_grade_to_use($submission);
    }

    /**
     * @param int|float $grade
     * @return float
     */
    private function round_grade_decimals($grade) {
        if ($grade === '' || $grade === null) {
            // Avoid PHPUnit exception passing null or empty string to round().
            return null;
        }
        return round($grade, 2);
    }

    /**
     * @param int $grade
     * @return null
     */
    public function grade_to_display($grade) {
        if (is_null($grade)) {
            return '';
        } else if ($this->coursework->grade >= 1) {
            // Numeric grade
            return $this->round_grade_decimals($grade);
        } else if ($this->coursework->grade == 0) {
            // No grade
            return null;
        } else if ($this->coursework->grade <= -1) {
            // Scale
            $scale = \grade_scale::fetch(array('id' => abs($this->coursework->grade)));
            return $scale->get_nearest_item($grade);
        }
    }

    /**
     * The grade to send to the gradebook when the publish action happens
     *
     * @param submission $submission
     * @return float
     */
    public function get_grade_for_gradebook($submission) {
        return $this->round_grade_decimals($this->get_grade_capped_by_submission_time($submission));
    }

    /**
     * @param submission $submission
     * @return int
     */
    private function get_submission_grade_to_use($submission) {

        $gradebook_feedback = $this->get_feedback_that_is_promoted_to_gradebook($submission);

        if ($gradebook_feedback && ($submission->ready_to_publish()) || $submission->already_published()) {
            return $gradebook_feedback->get_grade();
        }
        return null;
    }

    /**
     * @param submission $submission
     * @return bool|feedback|null|static
     */
    public function get_feedback_that_is_promoted_to_gradebook($submission) {

        if (!isset($submission->id)) {
            return new null_feedback();
        }

        if ($this->allocatable_needs_more_than_one_feedback($submission->get_allocatable())) {
            $feedback = feedback::find(array('submissionid' => $submission->id, 'stage_identifier' => 'final_agreed_1'));
        } else {
            $feedback = feedback::find(array('submissionid' => $submission->id, 'stage_identifier' => 'assessor_1'));
        }

        return $feedback ? $feedback : new null_feedback();
    }

    /**
     * @param submission $submission
     * @return bool
     */
    public function has_feedback_that_is_promoted_to_gradebook($submission) {
        return $this->get_feedback_that_is_promoted_to_gradebook($submission)->id != 0;
    }

    /**
     * @param submission $submission
     * @return int|null
     */
    public function get_time_graded($submission) {
        return $this->get_feedback_that_is_promoted_to_gradebook($submission)->timemodified ?? null;
    }

    /**
     * @param feedback $feedback
     * @return bool
     */
    public function is_feedback_that_is_promoted_to_gradebook(feedback $feedback) {
        $gradebook_feedback = $this->get_feedback_that_is_promoted_to_gradebook($feedback->get_submission());
        return $gradebook_feedback && $gradebook_feedback->id == $feedback->id;
    }

    /**
     * @param allocatable $allocatable
     * @return bool
     */
    public function allocatable_needs_more_than_one_feedback ($allocatable) {

        if ($this->coursework->sampling_enabled()) {
            assessment_set_membership::fill_pool_coursework($this->coursework->id);
            $record = assessment_set_membership::get_object($this->coursework->id, 'allocatableid-allocatabletype', [$allocatable->id(), $allocatable->type()]);
            return !empty($record);
        } else {
            return $this->coursework->has_multiple_markers();
        }

    }

    public function grade_in_scale($value) {
        if (is_null($value)) {
            return true;
        } else if ($this->coursework->grade >= 1) {
            // Numeric grade
            return is_numeric($value) && $value < $this->coursework->grade + 1 && $value > 0;
        } else if ($this->coursework->grade == 0) {
            // No grade
            return true;
        } else if ($this->coursework->grade <= -1) {
            // Scale
            $scale = \grade_scale::fetch(array('id' => abs($this->coursework->grade)));
            $scale->load_items();
            return in_array($value, $scale->scale_items);
        }
    }

    /**
     * Returns the grade
     *
     * @param $value
     * @return mixed
     */
    public function get_grade($value) {

        if ($this->coursework->grade <= -1) {
            // Scale
            $scale = \grade_scale::fetch(array('id' => abs($this->coursework->grade)));
            $scale->load_items();
            return array_search($value, $scale->scale_items) + 1;
        } else {
            return $value;
        }

    }

}
