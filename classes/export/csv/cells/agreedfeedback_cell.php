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
 * Class agreedfeedback_cell
 */
class agreedfeedback_cell extends cell_base {

    /**
     * @param submission$submission
     * @param $student
     * @param $stage_identifier
     * @return string
     */

    public function get_cell($submission, $student, $stage_identifier) {
        return  $gradedata[] = $submission->get_agreed_grade() == false ? '' : strip_tags($submission->get_agreed_grade()->feedbackcomment);
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage) {
        return  get_string('agreedgradefeedback', 'coursework');
    }

    public function validate_cell($value, $submissionid, $stage_identifier='', $uploadedgradecells  = []) {

        global $DB, $PAGE, $USER;

        $stage_identifier = 'final_agreed_1';
        $agreedgradecap = array('mod/coursework:addagreedgrade', 'mod/coursework:editagreedgrade',
            'mod/coursework:addallocatedagreedgrade', 'mod/coursework:editallocatedagreedgrade');

        if (has_any_capability($agreedgradecap, $PAGE->context)
            || has_capability('mod/coursework:administergrades', $PAGE->context)) {

            $subdbrecord = $DB->get_record('coursework_submissions', array('id' => $submissionid));
            $submission = \mod_coursework\models\submission::find($subdbrecord);

            // Is the submission in question ready to grade?
            if (!$submission->all_inital_graded()  && !empty($value)) {
                return get_string('submissionnotreadyforagreedgrade', 'coursework');
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
            $feedback_params = array(
                'submissionid' => $submission->id,
                'stage_identifier' => $stage_identifier,
            );

            $feedback = feedback::find($feedback_params);

            $ability = new ability(user::find($USER), $this->coursework);

            //does a feedback exist for this stage
            if (empty($feedback)) {
                $feedback_params = array(
                    'submissionid' => $submissionid,
                    'assessorid' => $USER->id,
                    'stage_identifier' => $stage_identifier,
                );
                $new_feedback = feedback::build($feedback_params);

                // This is a new feedback check it against the new ability checks
                if (!has_capability('mod/coursework:administergrades', $PAGE->context) && !has_capability('mod/coursework:addallocatedagreedgrade', $PAGE->context) && !$ability->can('new', $new_feedback)) {
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

}
