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

namespace mod_coursework\export;
use mod_coursework\export\csv;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use mod_coursework\ability;
use mod_coursework\models\coursework;

class grading_sheet extends csv {

    public function get_submissions($groupid = null, $selectedsubmissionids = '') {
        global $PAGE, $USER;
        $params = [
            'courseworkid' => $this->coursework->id,
        ];

        $submissions = submission::find_all($params);

        // remove unfinalised submissions
        foreach ($submissions as $submission) {
            if ($submission->get_state() < $submission::FINALISED) {
                unset($submissions[$submission->id]);
            }
        }

        if (is_siteadmin($USER->id)) {
            return $submissions;
        }

        $ability = new ability(user::find($USER), $this->coursework);

        if (!has_capability('mod/coursework:administergrades', $PAGE->context)) {

            /**
             * @var submission[] $submissions
             */
            foreach ($submissions as $submission) {
                $stageidentifiers = [];
                // remove all submissions that a user is not supposed to see

                // double marking not allocated
                $stages = $this->coursework->get_max_markers();
                if ($stages > 1 && !$this->coursework->allocation_enabled() && !has_capability('mod/coursework:addagreedgrade', $PAGE->context)) {
                    // if samplings enabled, work out how many per submission
                    if ($this->coursework->sampling_enabled()) {
                        $stageidentifiers[] = 'assessor_1'; // always have at least one assessor
                        // check how many other assessors for this submission
                        $insample = $submission->get_submissions_in_sample();
                        foreach ($insample as $i) {
                            $stageidentifiers[] = $i->stage_identifier;
                        }
                    } else { // if sampling not enabled, everyone is marked in all stages
                        for ($i = 1; $i <= $stages; $i++) {
                            $stageidentifiers[] = 'assessor_' . $i;
                        }
                    }
                    // check if any of the submissions still requires marking
                    for ($i = 0; $i < count($stageidentifiers); $i++) {
                        $feedback = $submission->get_assessor_feedback_by_stage($stageidentifiers[$i]);
                        // if no feedback or feedback belongs to current user don't remove submission
                        if (!$feedback || $feedback->assessorid == $USER->id) {
                            break;
                        } else if ($i + 1 < count($stageidentifiers)) {
                            continue;
                        }
                        // if the last submission was already marked remove it from the array
                        unset($submissions[$submission->id]);
                    }
                }

                // TODO - decide if already marked submissions should be displayed in single marking
                // if not marked by a user than dont display it as it would allow them to edit it??
                // || $submission->get_state() == submission::FINAL_GRADED
                if (!$ability->can('show', $submission)
                   || ($stages == 1 && !has_capability('mod/coursework:addinitialgrade', $PAGE->context))
                   || ($this->coursework->allocation_enabled() && !$this->coursework
                       ->assessor_has_any_allocation_for_student($submission->reload()->get_allocatable())
                       && (has_capability('mod/coursework:addinitialgrade', $PAGE->context) && !has_capability('mod/coursework:addagreedgrade', $PAGE->context)))
                   || ($stages > 1 && $this->coursework->sampling_enabled()
                       && !$submission->sampled_feedback_exists()
                       && (!$this->coursework
                           ->assessor_has_any_allocation_for_student($submission->reload()->get_allocatable())
                            && has_capability('mod/coursework:addinitialgrade', $PAGE->context))
                       && (has_capability('mod/coursework:addagreedgrade', $PAGE->context)
                           || has_capability('mod/coursework:editagreedgrade', $PAGE->context)))
                   || ((has_capability('mod/coursework:addagreedgrade', $PAGE->context) && $submission->get_state() < submission::FULLY_GRADED ))
                ) {
                    unset($submissions[$submission->id]);
                    continue;
                }
            }
        }
        return $submissions;

    }

    /**
     * Function to add data to csv
     * @param submission $submission
     * @return array
     */
    public function add_csv_data($submission) {

        $csvdata = [];
        // groups
        if ($this->coursework->is_configured_to_have_group_submissions()) {
            $group = \mod_coursework\models\group::find($submission->allocatableid);
            $csvdata[] = $this->add_cells_to_array($submission, $group, $this->csv_cells);

        } else {
            // students
            $students = $submission->students_for_gradebook();

            foreach ($students as $student) {
                $student = \mod_coursework\models\user::find($student);
                $csvdata[] = $this->add_cells_to_array($submission, $student, $this->csv_cells);
            }
        }

        return $csvdata;
    }

    /**
     * Put together cells that will be used in the csv file
     * @param coursework $coursework
     * @return array
     * @throws \coding_exception
     */
    public static function cells_array($coursework) {
        global $PAGE;

        // headers and data for csv
        $csvcells = ['submissionid', 'submissionfileid'];

        if ($coursework->is_configured_to_have_group_submissions()) {
            $csvcells[] = 'group';
        } else {
            $csvcells[] = 'name';
            $csvcells[] = 'username';
            $csvcells[] = 'idnumber';
            $csvcells[] = 'email';
        }
        $csvcells[] = 'submissiontime';

        // based on capabilities decide what view display - singlegrade or multiplegrade
        if ((has_capability('mod/coursework:addagreedgrade', $PAGE->context) || has_capability('mod/coursework:administergrades', $PAGE->context))
           && $coursework->get_max_markers() > 1 ) {
            for ($i = 1; $i <= $coursework->get_max_markers(); $i++) {
                // extra column with allocated assessor name
                if ($coursework->allocation_enabled() && $coursework->get_max_markers() > 1
                  && (has_capability('mod/coursework:addinitialgrade', $PAGE->context)
                      || has_capability('mod/coursework:editinitialgrade', $PAGE->context))) {
                    $csvcells[] = 'assessor' . $i;
                }
                $csvcells[] = 'assessorgrade'.$i;
                $csvcells[] = 'assessorfeedback'.$i;
            }
            $csvcells[] = 'agreedgrade';
            $csvcells[] = 'agreedfeedback';

        } else if (has_capability('mod/coursework:addallocatedagreedgrade', $PAGE->context) && $coursework->get_max_markers() > 1) {
            $csvcells[] = 'singlegrade';
            $csvcells[] = 'feedbackcomments';

            // Other grades
            $csvcells[] = 'otherassessors';

            $csvcells[] = 'agreedgrade';
            $csvcells[] = 'agreedfeedback';

        } else if (has_capability('mod/coursework:addinitialgrade', $PAGE->context)
            || has_capability('mod/coursework:administergrades', $PAGE->context)) {

            // if (!$coursework->is_using_rubric()) {
                $csvcells[] = 'singlegrade';
            /*    } else {

                $criterias = $coursework->get_rubric_criteria();

                foreach ($criterias as $criteria) {
                    $csv_cells[] = $criteria['description'];
                    $csv_cells[] = $criteria['description']." comment";
                }

            }
            */
            $csvcells[] = 'feedbackcomments';
        }

        return $csvcells;
    }

}
