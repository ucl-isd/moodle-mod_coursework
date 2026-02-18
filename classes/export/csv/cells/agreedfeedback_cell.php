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
use dml_exception;
use lang_string;
use mod_coursework\models\feedback;
use mod_coursework\models\submission;

/**
 * Class agreedfeedback_cell
 */
class agreedfeedback_cell extends cell_base {
    /**
     * @param submission $submission
     * @param $student
     * @param $stageidentifier
     * @return string
     * @throws \dml_missing_record_exception
     * @throws \dml_multiple_records_exception
     */

    public function get_cell($submission, $student, $stageidentifier) {
        return $submission->get_agreed_grade() == false ? '' : cell_base::clean_cell($submission->get_agreed_grade()->feedbackcomment);
    }

    /**
     * @param $stage
     * @return string
     * @throws coding_exception
     */
    public function get_header($stage) {
        return get_string('agreedmarkfeedback', 'coursework');
    }

    /**
     * Validate cell.
     * @param string $value
     * @param int $submissionid
     * @param string $stageidentifier
     * @param array $uploadedgradecells
     * @return lang_string|mixed|string|true
     * @throws coding_exception
     * @throws dml_exception
     */
    public function validate_cell($value, $submissionid, $stageidentifier = '', $uploadedgradecells = []) {
        global $DB, $PAGE;
        $stageidentfinal = 'final_agreed_1';

        if (
            has_any_capability(
                [
                'mod/coursework:addagreedgrade',
                'mod/coursework:editagreedgrade',
                'mod/coursework:addallocatedagreedgrade',
                'mod/coursework:editallocatedagreedgrade',
                'mod/coursework:administergrades',
                ],
                $PAGE->context
            )
        ) {
            $subdbrecord = $DB->get_record('coursework_submissions', ['id' => $submissionid]);
            $submission = submission::find($subdbrecord);

            // Is the submission in question ready to grade?
            if (!$submission->all_initial_graded() && !empty($value)) {
                return get_string('submissionnotreadyforagreedmark', 'coursework');
            }

            // Has the submission been published if yes then no further grades are allowed.
            if ($submission->get_state() >= submission::PUBLISHED) {
                return $submission->get_status_text();
            }

            // If you have administer grades you can grade anything.
            if (has_capability('mod/coursework:administergrades', $PAGE->context)) {
                return true;
            }

            // Has this submission been graded if yes then check if the current user graded it (only if allocation is not enabled).
            $feedbackparams = [
                'submissionid' => $submission->id,
                'stageidentifier' => $stageidentfinal,
            ];

            $feedback = feedback::find($feedbackparams);

            // Does a feedback exist for this stage.
            if (empty($feedback)) {
                // This is a new feedback check it against the new ability checks.
                if (
                    !has_capability('mod/coursework:addallocatedagreedgrade', $PAGE->context)
                    &&
                    !feedback::can_add_new($this->coursework, $submission, $stageidentfinal)
                ) {
                    return get_string('nopermissiontomarksubmission', 'coursework');
                }
            } else {
                // This is an existing feedback check it against the edit ability checks.
                if (!$feedback->can_edit($this->coursework, submission::find($submissionid))) {
                    return get_string('nopermissiontoeditmark', 'coursework');
                }
            }
        } else {
            return get_string('nopermissiontoimportmark', 'coursework');
        }
        return true;
    }
}
