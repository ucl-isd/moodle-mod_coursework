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
 * @copyright  2017 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\auto_grader;

use mod_coursework\allocation\allocatable;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\traits\autoagreement_functions;
use stdClass;

/**
 * Class percentage_distance is responsible for calculating and applying the automatically agreed grade if the initial
 * assessor grades are within a certain percentage of one another.
 *
 * @package mod_coursework\auto_grader
 */
class percentage_distance implements auto_grader {
    use autoagreement_functions;

    /**
     * @var coursework
     */
    private $coursework;

    /**
     * @var int
     */
    private $percentage;

    /**
     * @var allocatable
     */
    private $allocatable;

    /**
     * @param coursework $coursework
     * @param allocatable $allocatable
     */
    public function __construct($coursework, $allocatable) {
        $this->coursework = $coursework;
        $this->percentage = (int)$this->coursework->automaticagreementrange;
        $this->allocatable = $allocatable;
    }

    /**
     * This will test whether there is a grade already present, test whether the rules for this class match the
     * state of the initial assessor grades and make an automatic grade if they do.
     *
     */
    public function create_auto_grade_if_rules_match() {

        // bounce out if conditions are not right/
        if (!$this->get_allocatable()->has_all_initial_feedbacks($this->get_coursework())) {
            return;
        }
        if ($this->get_coursework()->numberofmarkers == 1) {
            return;
        }

        if ($this->grades_are_close_enough()) {
            if (!$this->get_allocatable()->has_agreed_feedback($this->get_coursework())) {
                $this->create_final_feedback();
            } else {
                // update only if AgreedGrade has been automatic
                $agreedfeedback = $this->get_allocatable()->get_agreed_feedback($this->get_coursework());
                if ($agreedfeedback->timecreated == $agreedfeedback->timemodified || $agreedfeedback->lasteditedbyuser == 0) {
                    $this->update_final_feedback($agreedfeedback);
                }
            }
        }

        // trigger events?
    }

    /**
     * @return coursework
     */
    private function get_coursework() {
        return $this->coursework;
    }

    /**
     * @return int
     */
    private function automatic_grade() {
        $grades = $this->grades_as_percentages();
        return max($grades);
    }

    /**
     * @return bool
     */
    private function grades_are_close_enough() {
        // test if the rules apply
        $grades = $this->grades_as_percentages();
        $maxgrade = max($grades);
        $mingrade = min($grades);

        return ($maxgrade - $mingrade) <= $this->percentage;
    }

    /**
     * @return allocatable
     */
    private function get_allocatable() {
        return $this->allocatable;
    }

    /**
     *
     */
    private function create_final_feedback() {
        $feedback =
            [
                'stageidentifier' => 'final_agreed_1',
                'submissionid' => $this->get_allocatable()->get_submission($this->get_coursework())->id(),
                'grade' => $this->automatic_grade(),
            ];

        if ($this->coursework->autopopulatefeedbackcomment_enabled()) {
            $feedback['feedbackcomment'] = $this->feedback_comments();
        }

        feedback::create($feedback);
    }

    /**
     *
     */
    private function update_final_feedback($feedback) {
        global $DB;

        $updatedfeedback = new stdClass();
        $updatedfeedback->id = $feedback->id;
        $updatedfeedback->grade = $this->automatic_grade();
        $updatedfeedback->lasteditedbyuser = 0;

        if ($this->coursework->autopopulatefeedbackcomment_enabled()) {
            $updatedfeedback->feedbackcomment = $this->feedback_comments();
        }

        $DB->update_record('coursework_feedbacks', $updatedfeedback);
    }

    /**
     * @return array
     */
    private function grades_as_percentages() {
        $initialfeedbacks = $this->get_allocatable()->get_initial_feedbacks($this->get_coursework());
        $grades = array_map(function ($feedback) {
            return ($feedback->get_grade() / $this->get_coursework()->get_max_grade()) * 100;
        },
            $initialfeedbacks);
        return $grades;
    }

    /**
     * Set percentage.
     * @param int $percentage
     * @return void
     */
    public function set_percentage(int $percentage) {
        $this->percentage = $percentage;
    }
}
