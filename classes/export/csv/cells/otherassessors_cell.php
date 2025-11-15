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

namespace mod_coursework\export\csv\cells;
use coding_exception;
use mod_coursework\ability;
use mod_coursework\models\submission;

/**
 * Class otherassessors_cell
 */
class otherassessors_cell extends cell_base {
    /**
     * @param submission $submission
     * @param $student
     * @param $stageidentifier
     * @return array
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function get_cell($submission, $student, $stageidentifier) {
        global $DB, $USER;
        // find out current user stage identifier

        // $stageidentifier =
        // retrieve all feedbacks without currents user feedback

        $params = [
            'submissionid' => $submission->id,
            'assessorid' => $USER->id,
            'stageidentifier' => $stageidentifier,
        ];

        $sql = "SELECT * FROM {coursework_feedbacks}
                WHERE submissionid = :submissionid
                AND assessorid <> :assessorid
                AND stageidentifier <> 'final_agreed_1'";

        $feedbacks = $DB->get_records_sql($sql, $params);
        $gradedata = [];

        foreach ($feedbacks as $feedback) {
            $grade = $submission->get_assessor_feedback_by_stage($feedback->stageidentifier);
            if ($grade) {
                // skip if you are allocated but someone else graded it
                $allocation = $submission->get_assessor_allocation_by_stage($feedback->stageidentifier);
                if ($allocation && $allocation->assessorid == $USER->id) {
                    continue;
                }
                $ability = new ability($USER->id, $this->coursework);
                if (
                    (($ability->can('show', $feedback) || has_capability('mod/coursework:addallocatedagreedgrade', $submission->get_coursework()->get_context())) &&
                    (!$submission->any_editable_feedback_exists() && count($submission->get_assessor_feedbacks()) <= $submission->max_number_of_feedbacks())) || is_siteadmin($USER->id)
                ) {
                    if ($this->coursework->is_using_rubric()) {
                        $this->get_rubric_scores_gradedata($grade, $gradedata); // multiple parts are handled here
                    } else {
                        $gradedata[] = $this->get_actual_grade($grade->grade);
                    }
                    $gradedata[] = cell_base::clean_cell($grade->feedbackcomment);
                } else {
                    $gradedata[] = get_string('mark_hidden_manager', 'mod_coursework');
                    $gradedata[] = '';
                }
            } else {
                if ($this->coursework->is_using_rubric()) {
                    $criterias = $this->coursework->get_rubric_criteria();
                    foreach ($criterias as $criteria) { // rubrics can have multiple parts, so let's create header for each of it
                        $gradedata['assessor' . $stageidentifier . '_' . $criteria['id']] = get_string('mark_hidden_manager', 'mod_coursework');
                        $gradedata['assessor' . $stageidentifier . '_' . $criteria['id'] . 'comment'] = '';
                    }
                } else {
                    $gradedata[] = '';
                }
                $gradedata[] = '';
            }
        }

        $numothereassessorfeedbacks = $submission->max_number_of_feedbacks() - 1;

        if ($numothereassessorfeedbacks - count($feedbacks) != 0) {
            $blankcolumns = $numothereassessorfeedbacks - count($feedbacks);

            for ($i = 0; $i < $blankcolumns; $i++) {
                if ($this->coursework->is_using_rubric()) {
                    $criterias = $this->coursework->get_rubric_criteria();
                    foreach ($criterias as $criteria) { // rubrics can have multiple parts, so let's create header for each of it
                        $gradedata['assessor' . $stageidentifier . $i . '_' . $criteria['id']] = '';
                        $gradedata['assessor' . $stageidentifier . $i . '_' . $criteria['id'] . 'comment'] = '';
                    }
                } else {
                    $gradedata[] = '';
                }
                $gradedata[] = '';
            }
        }

        return   $gradedata;
    }

    /**
     * @param $stage
     * @return array
     * @throws coding_exception
     */
    public function get_header($stage) {

        $fields = [];

        for ($i = 1; $i < $this->stages; $i++) {
            if ($this->coursework->is_using_rubric()) {
                $criterias = $this->coursework->get_rubric_criteria();
                foreach ($criterias as $criteria) { // rubrics can have multiple parts, so let's create header for each of it
                    $fields['otherassessorgrade' . $i . $stage . '_' . $criteria['id']] = get_string('csvothermarkermarkstage', 'mod_coursework', ['stage' => $i, 'description' => $criteria['description']]);
                    $fields['otherassessorgrade' . $i . $stage . '_' . $criteria['id'] . 'comment'] = get_string('csvothermarkermarkstagecomment', 'mod_coursework', ['stage' => $i, 'description' => $criteria['description']]);
                }
            } else {
                $fields['otherassessorgrade' . $i] = get_string('othermarkermark', 'coursework', $i);
            }
                $fields['otherassessorfeedback' . $i] = get_string('othermarkerfeedback', 'coursework', $i);
        }
        return $fields;
    }
}
