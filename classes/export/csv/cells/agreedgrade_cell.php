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
use mod_coursework\grade_judge;
use mod_coursework\models\feedback;
use mod_coursework\models\submission;

/**
 * Class agreedgrade_cell
 */
class agreedgrade_cell extends cell_base {

    /**
     * @param $submission
     * @param $student
     * @param $stageidentifier
     * @return array|mixed|null|string
     */

    public function get_cell($submission, $student, $stageidentifier) {

        $agreedgrade = $submission->get_agreed_grade();
        if ($this->coursework->is_using_rubric() && $this->coursework->finalstagegrading != 1) {
            $gradedata = [];
            $this->get_rubric_scores_gradedata($agreedgrade, $gradedata); // multiple parts are handled here
        } else {
            $gradedata = (!$agreedgrade) ? '' : $this->get_actual_grade($agreedgrade->grade);
        }

        return   $gradedata;
    }

    /**
     * @param $stage
     * @return array|mixed|string
     * @throws coding_exception
     */
    public function get_header($stage) {

        if ($this->coursework->is_using_rubric() && $this->coursework->finalstagegrading != 1) {
            $strings = [];
            $criterias = $this->coursework->get_rubric_criteria();
            foreach ($criterias as $criteria) { // rubrics can have multiple parts, so let's create header for each of it
                $strings['agreedgrade'.$criteria['id']] = 'Agreed grade - '.$criteria['description'];
                $strings['agreedgrade'.$criteria['id'] . 'comment'] = 'Comment for: Agreed grade - '.$criteria['description'];
            }
        } else {
            $strings = get_string('agreedgrade', 'coursework');
        }

        return $strings;
    }

    public function validate_cell($value, $submissionid, $stageidentifier = '', $uploadedgradecells  = []) {

        global $DB, $PAGE, $USER;

        $stageident = 'final_agreed_1';
        $agreedgradecap = ['mod/coursework:addagreedgrade', 'mod/coursework:editagreedgrade',
            'mod/coursework:addallocatedagreedgrade', 'mod/coursework:editallocatedagreedgrade'];

        if (empty($value)) {
            return true;
        }

        if (has_any_capability($agreedgradecap, $PAGE->context)
            || has_capability('mod/coursework:administergrades', $PAGE->context)) {

            $errormsg = '';

            if (!$this->coursework->is_using_rubric() || ($this->coursework->is_using_rubric() && $this->coursework->finalstagegrading == 1)) {
                $gradejudge = new grade_judge($this->coursework);
                if (!$gradejudge->grade_in_scale($value)) {
                    $errormsg = get_string('valuenotincourseworkscale', 'coursework');
                    if (is_numeric($value)) {
                        // if scale is numeric get max allowed scale
                        $errormsg .= ' '. get_string('max_cw_mark', 'coursework').' '. $this->coursework->grade;
                    }
                    return $errormsg;
                }
            } else {

                // We won't be processing this line if it has no values, empty wont tell us this as it thinks that an array with
                // Keys isnt. We will use array_filter whhich will return all values from the array if this is empty then we have
                // Nothing to do

                $arrayvalues = array_filter($value);

                // If there are no values we don't need to do anything
                if (!empty($arrayvalues)) {

                    $i = 0;
                    $s = 0;

                    $criterias = $this->coursework->get_rubric_criteria();

                    foreach ($value as $data) {

                        // Check if the value is empty however it can be 0
                        if (empty($data) && $data != 0) {

                            $errormsg .= ' ' . get_string('rubric_grade_cannot_be_empty', 'coursework');

                        }

                        // Only check grades fields that will be even numbered
                        if ($i % 2 == 0) {

                            // Get the current criteria
                            $criteria = array_shift($criterias);

                            // Lets check if the value given is valid for the current rubric criteria
                            if (!$this->value_in_rubric($criteria, $data)) {
                                // if scale is numeric get max allowed scale
                                $errormsg .= ' ' . get_string('rubric_invalid_value', 'coursework') . ' ' . $data;
                            }
                            $s++;
                        }
                        $i++;
                    }
                } else {

                    // Set value to false so that a submission not ready to grade message isn't returned
                    $value = false;

                }

            }

            if (!empty($errormsg)) {
                return $errormsg;
            }

            $subdbrecord = $DB->get_record('coursework_submissions', ['id' => $submissionid]);
            $submission = submission::find($subdbrecord);

            // Is the submission in question ready to grade?
            if (!$submission->all_initial_graded() && !empty($value) && count($uploadedgradecells) < $submission->max_number_of_feedbacks()) { return get_string('submissionnotreadyforagreedgrade', 'coursework');
            }

            // Has the submission been published if yes then no further grades are allowed
            if ($submission->get_state() >= submission::PUBLISHED) {
                return $submission->get_status_text();
            }

            // If you have administer grades you can grade anything
            if (has_capability('mod/coursework:administergrades', $PAGE->context)) {
                return true;
            }

            // Has this submission been graded if yes then check if the current user graded it (only if allocation is not enabled).
            $feedbackparams = [
                'submissionid' => $submission->id,
                'stage_identifier' => $stageident,
            ];

            $feedback = feedback::find($feedbackparams);

            $ability = new ability($USER->id, $this->coursework);

            // Does a feedback exist for this stage
            if (empty($feedback)) {

                $feedbackparams = [
                    'submissionid' => $submissionid,
                    'assessorid' => $USER->id,
                    'stage_identifier' => $stageident,
                ];
                $newfeedback = feedback::build($feedbackparams);

                // This is a new feedback check it against the new ability checks
                if (!has_capability('mod/coursework:administergrades', $PAGE->context) && !has_capability('mod/coursework:addallocatedagreedgrade', $PAGE->context) && !$ability->can('new', $newfeedback)) {
                    return get_string('nopermissiontogradesubmission', 'coursework');
                }
            } else {
                // This is a new feedback check it against the edit ability checks
                if (!has_capability('mod/coursework:administergrades', $PAGE->context) && !$ability->can('edit', $feedback)) {
                    return get_string('nopermissiontoeditgrade', 'coursework');
                }
            }

        } else {
            return get_string('nopermissiontoimportgrade', 'coursework');
        }

        return true;

    }

    /***
     * Check that the given value is within the values that can be excepted by the given rubric criteria
     *
     * @param $criteria the criteria array, this must contain the levels element
     * @param $value the value that should be checked to see if it is valid
     * @return bool
     */
    public function value_in_rubric($criteria,    $value) {

        global  $DB;

        $valuefound = false;

        $levels = $criteria['levels'];

        if (is_numeric($value) ) {
            foreach ($levels as $level) {

                if ((int)$level['score'] == (int)$value) {

                    $valuefound = true;
                    break;
                }

            }
        }

        return $valuefound;
    }
}
