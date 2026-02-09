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
use mod_coursework\models\feedback;
use mod_coursework\models\submission;
use moodle_exception;

/**
 * Class assessorfeedback_cell
 */
class assessorfeedback_cell extends cell_base {
    /**
     * @param submission $submission
     * @param $student
     * @param $stageidentifier
     * @return string
     * @throws \dml_exception
     * @throws \dml_missing_record_exception
     * @throws \dml_multiple_records_exception
     * @throws coding_exception
     */

    public function get_cell($submission, $student, $stageidentifier) {

        global $USER;

        $grade = $submission->get_assessor_feedback_by_stage($stageidentifier);
        if ($grade) {
            // check if user can see initial grades before all of them are completed
            $ability = new ability($USER->id, $this->coursework);

            $feedback = feedback::get_from_submission_and_stage($submission->id, $stageidentifier);

            if ($submission->get_agreed_grade() || $ability->can('show', $feedback) || is_siteadmin($USER->id)) {
                $grade = cell_base::clean_cell($grade->feedbackcomment);
            } else {
                $grade = '';
            }
        } else {
            $grade = '';
        }

        return $grade;
    }

    /**
     * @param $stage
     * @return string
     * @throws coding_exception
     */
    public function get_header($stage) {
        return  get_string('csvmarkerfeedback', 'coursework', $stage);
    }

    public function validate_cell($value, $submissionid, $stageidentifier = '', $uploadedgradecells = []) {
        global $DB, $PAGE, $USER;

        $modulecontext = $PAGE->context;
        if ($modulecontext->contextlevel !== CONTEXT_MODULE) {
            // CTP-3559 sometimes require_login() is being called without passing a module context.
            // This means that $PAGE->context will be course context & capability checks may be wrong so check it here.
            throw new moodle_exception(
                "accesserror",
                'coursework',
                "",
                null,
                "Invalid context level " . $modulecontext->contextlevel
            );
        }
        $agreedgradecap = ['mod/coursework:addagreedgrade', 'mod/coursework:editagreedgrade'];
        $initialgradecap = ['mod/coursework:addinitialgrade', 'mod/coursework:editinitialgrade'];
        $submission = submission::get_from_id($submissionid);
        if (
            has_any_capability($agreedgradecap, $modulecontext) && has_any_capability($initialgradecap, $modulecontext)
            || has_capability('mod/coursework:administergrades', $modulecontext)
        ) {
            // Is the submission in question ready to grade?
            if (!$submission->ready_to_grade()) {
                return get_string('submissionnotreadytomark', 'coursework');
            }

            // Has the submission been published if yes then no further grades are allowed
            if ($submission->get_state() >= submission::PUBLISHED) {
                return $submission->get_status_text();
            }

            // If you have administer grades you can grade anything
            if (has_capability('mod/coursework:administergrades', $modulecontext)) {
                return true;
            }

            // Has this submission been graded if yes then check if the current user graded it (only if allocation is not enabled).
            $feedback = feedback::get_from_submission_and_stage($submission->id, $stageidentifier);

            $ability = new ability($USER->id, $this->coursework);

            // Does a feedback exist for this stage
            if (!empty($feedback)) {
                // This is a new feedback check it against the new ability checks
                if (!has_capability('mod/coursework:administergrades', $modulecontext) && !$ability->can('new', $feedback)) {
                    return get_string('nopermissiontoeditmark', 'coursework');
                }
            } else {
                // This is a new feedback check it against the edit ability checks
                if (!has_capability('mod/coursework:administergrades', $modulecontext) && !$ability->can('edit', $feedback)) {
                    return get_string('nopermissiontoeditmark', 'coursework');
                }
            }

            if (!$this->coursework->allocation_enabled() && !empty($feedback)) {
                // Was this user the one who last graded this submission if not then user cannot grade
                if ($feedback->assessorid != $USER->id || !has_capability('mod/coursework:editinitialgrade', $modulecontext)) {
                    return get_string('nopermissiontomarksubmission', 'coursework');
                }
            }

            if ($this->coursework->allocation_enabled()) {
                // Check that the user is allocated to the author of the submission
                $allocationparams = [
                    'courseworkid' => $this->coursework->id,
                    'allocatableid' => $submission->allocatableid,
                    'allocatabletype' => $submission->allocatabletype,
                    'stageidentifier' => $stageidentifier,
                ];

                if (
                    !has_capability('mod/coursework:administergrades', $modulecontext)
                    && !$DB->get_record('coursework_allocation_pairs', $allocationparams)
                ) {
                    return get_string('nopermissiontomarksubmission', 'coursework');
                }
            }

            // Check for coursework without allocations - with/without samplings
            if (
                has_capability('mod/coursework:addinitialgrade', $modulecontext) && !has_capability('mod/coursework:editinitialgrade', $modulecontext)
                && $this->coursework->get_max_markers() > 1 && !$this->coursework->allocation_enabled()
            ) {
                // check how many feedbacks for this submission
                $feedbacks = $DB->count_records('coursework_feedbacks', ['submissionid' => $submissionid]);

                if ($this->coursework->sampling_enabled()) {
                    // check how many sample assessors + add 1 that is always in sample
                    $insample = $submission->get_submissions_in_sample();
                    $assessors = ($insample) ? count($insample) + 1 : 1;
                } else {
                    // Check how many assessors for this coursework
                    $assessors = $this->coursework->get_max_markers();
                }
                if ($assessors == $feedbacks) {
                    return get_string('markalreadyexists', 'coursework');
                }
            }
        } else if (has_any_capability($agreedgradecap, $modulecontext)) {
            // If you have the add agreed or edit agreed grades capabilities then you may have the grades on your export sheet
            // We will return true as we will ignore them
            return true;
        } else {
            return get_string('nopermissiontoimportmark', 'coursework');
        }
    }
}
