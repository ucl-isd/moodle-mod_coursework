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

namespace mod_coursework\export\csv\cells;
use mod_coursework\models\submission;
use mod_coursework\ability;
use mod_coursework\models\user;
use mod_coursework\models\feedback;
use mod_coursework\grade_judge;

/**
 * Class feedbackcomments_cell
 */
class feedbackcomments_cell extends cell_base {

    /**
     * @param submission $submission
     * @param $student
     * @param $stage_identifier
     * @return string
     */
    public function get_cell($submission, $student, $stageidentifier) {

        $stageidentifier = ($this->coursework->get_max_markers() == 1)
            ? "assessor_1" : $this->get_stage_identifier_for_assessor($submission, $student);
        $grade = $submission->get_assessor_feedback_by_stage($stageidentifier);
        return (!$grade || !isset($grade->feedbackcomment)) ? '' : strip_tags($grade->feedbackcomment);
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage) {
        return  get_string('feedbackcomment', 'coursework');
    }

    public function validate_cell($value, $submissionid, $stageidentifier='', $uploadedgradecells  = []) {

        global $PAGE, $DB, $USER;

        if (has_capability('mod/coursework:addinitialgrade', $PAGE->context) || has_capability('mod/coursework:editinitialgrade', $PAGE->context)
            || has_capability('mod/coursework:administergrades', $PAGE->context)) {

            $dbrecord = $DB->get_record('coursework_submissions', ['id' => $submissionid]);

            $submission = \mod_coursework\models\submission::find($dbrecord);

            // Is this submission ready to be graded
            if (!$submission->ready_to_grade()) {
                return get_string('submissionnotreadytograde', 'coursework');
            }

            // If you have administer grades you can grade anything
            if (has_capability('mod/coursework:administergrades', $PAGE->context)) {
                return true;
            }

            // Is the current user an assessor at any of this submissions grading stages or do they have administer grades
            if (!$this->coursework->is_assessor($USER) && !has_capability('mod/coursework:administergrades', $PAGE->context)) {
                return get_string('nopermissiontogradesubmission', 'coursework');
            }

            $ability = new ability(user::find($USER), $this->coursework);

            $feedbackparams = [
                'submissionid' => $submission->id,
                'stage_identifier' => $stageidentifier,
            ];
            $feedback = feedback::find($feedbackparams);

            //does a feedback exist for this stage
            if (empty($feedback)) {

                $feedbackparams = [
                    'submissionid' => $submissionid,
                    'assessorid' => $USER->id,
                    'stage_identifier' => $stageidentifier,
                ];
                $newfeedback = feedback::build($feedbackparams);

                // This is a new feedback check it against the new ability checks
                if (!$ability->can('new', $newfeedback)) {
                    return get_string('nopermissiontogradesubmission', 'coursework');
                }
            } else {
                // This is a new feedback check it against the edit ability checks
                if (!$ability->can('edit', $feedback)) {
                    return get_string('nopermissiontoeditgrade', 'coursework');
                }
            }
        } else {
            return get_string('nopermissiontoimportgrade', 'coursework');
        }

        return true;
    }

}
