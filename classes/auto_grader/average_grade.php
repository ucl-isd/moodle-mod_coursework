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
use mod_coursework\stages\final_agreed;
use mod_coursework\traits\autoagreement_functions;
use stdClass;

/**
 * Class average_grade is responsible for calculating and applying the automatically agreed grade based on the initial
 * assessor grades
 *
 * @package mod_coursework\auto_grader
 */
class average_grade implements auto_grader {
    use autoagreement_functions;

    /**
     * @var coursework
     */
    private $coursework;

    /**
     * @var
     */
    private $roundingrule;

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
        $this->roundingrule = $this->coursework->roundingrule;
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

        if (!$this->get_coursework()->uses_numeric_grade()) {
            return;
        }

        if ($this->get_coursework()->is_using_advanced_grading()) {
            // If the coursework uses advanced grading (rubric/guide) there will be a detailed marks breakdown.
            // For now, we don't want to automatically "agree" a final grade without a breakdown to match.
            // It is planned to implement something more complex to cover that requirement separately.
            return;
        }

        if (!$this->get_allocatable()->has_agreed_feedback($this->get_coursework())) {
            $this->create_final_feedback();
        } else {
            // update only if AgreedGrade has been automatic
            $agreedfeedback = $this->get_allocatable()->get_agreed_feedback($this->get_coursework());
            if ($agreedfeedback->timecreated == $agreedfeedback->timemodified || $agreedfeedback->lasteditedbyuser == 0) {
                $this->update_final_feedback($agreedfeedback);
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
     * @throws \coding_exception
     */
    private function automatic_grade() {

        $grades = $this->grades_as_percentages();

        // calculate average
        $avggrade = array_sum($grades) / count($grades);

        // round it according to the chosen rule
        switch ($this->roundingrule) {
            case 'mid':
                $avggrade = round($avggrade, $this->coursework->get_grade_item()->get_decimals());
                break;
            case 'up':
                $avggrade = ceil($avggrade);
                break;
            case 'down':
                $avggrade = floor($avggrade);
                break;
        }

        return $avggrade;
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
        $feedback = [
            'stageidentifier' => final_agreed::STAGE_FINAL_AGREED_1,
            'submissionid' => $this->get_allocatable()->get_submission($this->get_coursework())->id(),
            'grade' => $this->automatic_grade(),
            'lasteditedbyuser' => 0, // Grade was auto generated - zero here shows no user involved.
            'isfinalgrade' => 1, // This is an "agreed" final grade.
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
        $f = feedback::get_from_id($feedback->id);
        $f->clear_cache();
    }

    /**
     * Get grades as percentages.
     * @return array
     */
    protected function grades_as_percentages() {
        $initialfeedbacks = $this->get_allocatable()->get_initial_feedbacks($this->get_coursework());
        return array_map(function ($feedback) {
            return ($feedback->get_grade() / $this->get_coursework()->get_max_grade()) * 100;
        },
            $initialfeedbacks);
    }
}
