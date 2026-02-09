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

namespace mod_coursework\traits;
use core\exception\coding_exception;
use core\exception\invalid_parameter_exception;
use mod_coursework\models\assessment_set_membership;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\models\submission;
use mod_coursework\models\allocation;
use mod_coursework\stages\final_agreed;

/**
 * Class allocatable
 * @package mod_coursework\traits
 */
trait allocatable_functions {
    /**
     * @param coursework $coursework
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function has_agreed_feedback($coursework) {
        global $DB;
        $sql = "
            SELECT COUNT(*)
              FROM {coursework_feedbacks} f
        INNER JOIN {coursework_submissions} s
                ON f.submissionid = s.id
             WHERE f.stageidentifier LIKE 'final%'
               AND s.allocatableid = :id
               AND s.courseworkid = :courseworkid
        ";
        $result = $DB->count_records_sql($sql, ['id' => $this->id(), 'courseworkid' => $coursework->id()]);
        return !empty($result);
    }

    /**
     * @param coursework $coursework
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_agreed_feedback($coursework) {
        global $DB;
        $sql = "
            SELECT f.*
              FROM {coursework_feedbacks} f
        INNER JOIN {coursework_submissions} s
                ON f.submissionid = s.id
             WHERE f.stageidentifier = 'final_agreed_1'
               AND s.allocatableid = :id
               AND s.courseworkid = :courseworkid";

        return $DB->get_record_sql($sql, ['id' => $this->id(), 'courseworkid' => $coursework->id()]);
    }

    /**
     * @param coursework $coursework
     * @return bool
     * @throws invalid_parameter_exception
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function has_all_initial_feedbacks($coursework) {
        if (!$this->get_submission($coursework)) {
            return false;
        }
        $feedbacks = feedback::get_all_for_submission(
            $this->get_submission($coursework)->id(),
        );
        $filteredfeedbacks = array_filter(
            $feedbacks,
            fn($f) => str_starts_with($f->stageidentifier, 'assess') && $f->finalised
        );

        // When sampling is enabled, calculate how many stages are in sample.
        if ($coursework->sampling_enabled()) {
            $expectedmarkers = assessment_set_membership::membership_count(
                $coursework->id(),
                $this->type(),
                $this->id()
            ) + 1;  // Add one as there is always a marker for stage 1.
        } else {
            $expectedmarkers = $coursework->numberofmarkers;
        }
        return count($filteredfeedbacks) == $expectedmarkers;
    }

    /**
     * @param $coursework
     * @return array
     */
    public function get_initial_feedbacks($coursework) {
        $result = [];
        $submission = $this->get_submission($coursework);
        if ($submission) {
            $feedbacks = feedback::get_all_for_submission($submission->id);
            $result = array_filter($feedbacks, fn($f) => $f->stageidentifier != final_agreed::STAGE_FINAL_AGREED_1);
        }
        return $result;
    }

    /**
     * @param $coursework
     * @return ?submission
     */
    public function get_submission($coursework) {
        return submission::get_for_allocatable($coursework->id, $this->id, $this->type());
    }
}
