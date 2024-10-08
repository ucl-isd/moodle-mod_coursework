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
use mod_coursework\models\submission;

/**
 * Class stages_cell
 */
class stages_cell extends cell_base {

    /**
     * @param submission $submission
     * @param $student
     * @param $stageidentifier
     * @return array
     */
    public function get_cell($submission, $student, $stageidentifier) {
        global $DB;

        $timecreated = 0;
        $timemodified = 0;

        $feedback = $DB->get_record('coursework_feedbacks', ['submissionid' => $submission->id, 'assessorid' => 0]);
        if ($feedback) {
            $timecreated = $feedback->timecreated;
            $timemodified = $feedback->timemodified;
        }

        $gradedata = [];
        // go through each stage and get a grade, if grade not present then put  a placeholder
        for ($i = 1; $i <= $this->stages; $i++) {
            $stageident = 'assessor_'.$i;
            $grade = $submission->get_assessor_feedback_by_stage($stageident);

            if ($this->coursework->allocation_enabled()) {
                $allocation = $submission->get_assessor_allocation_by_stage($stageident);
                if ($allocation) {
                    $gradedata[] = $this->get_assessor_name($allocation->assessorid);
                    $gradedata[] = $this->get_assessor_username($allocation->assessorid);
                } else if ($i != 1 && $this->coursework->sampling_enabled() && !$submission->sampled_feedback_exists()) {
                    $gradedata[] = get_string('notincludedinsample', 'mod_coursework');
                    $gradedata[] = get_string('notincludedinsample', 'mod_coursework');
                } else {
                    $gradedata[] = get_string('assessornotallocated', 'mod_coursework');
                    $gradedata[] = get_string('assessornotallocated', 'mod_coursework');
                }
            }

            if ($this->coursework->is_using_advanced_grading() && $this->coursework->is_using_rubric()) {
                $this->get_rubric_scores_gradedata($grade, $gradedata);
            }

            if ($grade) {
                $gradedata[] = $this->get_actual_grade($grade->grade);
                $gradedata[] = $this->get_assessor_name($grade->assessorid);
                $gradedata[] = $this->get_assessor_username($grade->assessorid);
                $gradedata[] = userdate($grade->timemodified, $this->dateformat);
            } else {
                $gradedata[] = '';
                $gradedata[] = '';
                $gradedata[] = '';
                $gradedata[] = '';
            }
        }

        if ($this->stages >= 2) { // if there are two or more stages for a submission, we will have agreed grade
            $grade = $submission->get_assessor_feedback_by_stage('final_agreed_1');
            if ($this->coursework->is_using_advanced_grading() && $this->coursework->is_using_rubric()) {
                if ($this->coursework->is_using_advanced_grading()) {
                    $this->get_rubric_scores_gradedata($grade, $gradedata);
                }
            }

            $gradedata[] = $submission->get_agreed_grade() == false ? '' : $this->get_actual_grade($submission->get_agreed_grade()->grade);

            if ((!$this->coursework->sampling_enabled() || $submission->sampled_feedback_exists()) && $feedback && (($feedback->assessorid == 0 && $timecreated == $timemodified) || $feedback->lasteditedbyuser == 0 )) {
                $gradedata[] = get_string('automaticagreement', 'coursework');
                $gradedata[] = get_string('automaticagreement', 'coursework');
            } else {
                $gradedata[] = $submission->get_agreed_grade() == false ? '' : $this->get_assessor_name($submission->get_agreed_grade()->lasteditedbyuser);
                $gradedata[] = $submission->get_agreed_grade() == false ? '' : $this->get_assessor_username($submission->get_agreed_grade()->lasteditedbyuser);
            }

            $gradedata[] = $submission->get_agreed_grade() == false ? '' : userdate($submission->get_agreed_grade()->timemodified, $this->dateformat);
        }

        return $gradedata;
    }

    /**
     * @param $stage
     * @return array
     * @throws \coding_exception
     */
    public function get_header($stage) {

        $fields = [];

        for ($i = 1; $i <= $this->stages; $i++) {
            if ($this->coursework->allocation_enabled()) {
                $fields['allocatedassessor' . $i . 'name'] = 'Allocated assessor ' . $i . ' name';
                $fields['allocated' . $i . 'userame'] = 'Allocated assessor ' . $i . ' username';
            }

            if ($this->coursework->is_using_advanced_grading() && $this->coursework->is_using_rubric()) {
                $criteria = $this->coursework->get_rubric_criteria();
                foreach ($criteria as $id => $record) {
                    $fields['assessor' .$i. 'description' . $id] = 'Assessor '. $i . ' - '.$record['description'];
                    $fields['assessor' .$i. 'description' . $id . 'comment'] = 'Comment for: Assessor '. $i . ' - '.$record['description'];
                }
            }

            $fields['assessor' . $i] = 'Assessor ' . $i . ' grade';
            $fields['assessor' . $i . 'name'] = 'Assessor ' . $i . ' name';
            $fields['assessor' . $i . 'username'] = 'Assessor ' . $i . ' username';
            $fields['assessor' . $i . 'markingtime'] = 'Assessor ' . $i . ' marked on';
        }
        if ($this->stages >= 2) { // if there are two or more stages for a submission, we will have agreed grade

            if ($this->coursework->is_using_advanced_grading() && $this->coursework->is_using_rubric()) {
                $criteria = $this->coursework->get_rubric_criteria();
                foreach ($criteria as $id => $record) {
                    $fields['agreedgrade_description_' . $id] = 'Agreed grade - '.$record['description'];
                    $fields['agreedgrade_description_' . $id. 'comment'] = 'Comment for: Agreed grade - '.$record['description'];
                }
            }

            $fields['agreedgrade'] = get_string('agreedgrade', 'coursework');
            $fields['agreedgradeby'] = get_string('agreedgradeby', 'coursework');
            $fields['agreedgradebyusername'] = get_string('agreedgradebyusername', 'coursework');
            $fields['agreedgradeon'] = get_string('agreedgradeon', 'coursework');
        }

        return $fields;
    }

}
