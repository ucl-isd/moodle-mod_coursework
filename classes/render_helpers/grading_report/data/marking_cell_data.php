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
 * Data provider for marking cell in grading report.
 *
 * @package    mod_coursework
 * @copyright  2025 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */

namespace mod_coursework\render_helpers\grading_report\data;

use coding_exception;
use core\exception\moodle_exception;
use core_user;
use dml_exception;
use mod_coursework\allocation\allocatable;
use mod_coursework\assessor_feedback_row;
use mod_coursework\assessor_feedback_table;
use mod_coursework\grade_judge;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\feedback;
use mod_coursework\models\moderation;
use mod_coursework\models\null_user;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use mod_coursework\router;
use mod_coursework\stages\base as stage;
use mod_coursework\stages\final_agreed;
use stdClass;

/**
 * Class marking_cell_data provides data for marking cell templates.
 */
class marking_cell_data extends cell_data_base {
    /** @var string|null */
    protected ?string $allocatablehash;

    /**
     * Get the data for the marking cell.
     *
     * @param grading_table_row_base $rowsbase
     * @return stdClass|null The data object for template rendering.
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_table_cell_data(grading_table_row_base $rowsbase): ?stdClass {
        $table = new assessor_feedback_table($this->coursework, $rowsbase->get_allocatable(), $rowsbase->get_submission());
        $tablerows = $table->get_renderable_feedback_rows();

        // If no data is available, return null.
        if (empty($tablerows)) {
            return null;
        }

        // Process the data based on whether this is for multiple markers.
        return $this->coursework->has_multiple_markers() ?
            $this->get_marker_data($tablerows, $rowsbase, true) :
            $this->get_marker_data($tablerows, $rowsbase);
    }

    /**
     * Creates marker data object with common properties.
     *
     * @param user|null_user $assessor
     * @param int $markingstage
     * @param bool $canaddfeedback
     * @return stdClass
     * @throws coding_exception
     */
    private function create_marker_data($assessor, int $markingstage, bool $canaddfeedback): stdClass {
        global $OUTPUT;
        $marker = new stdClass();
        $marker->markingstage = $markingstage;
        if ($assessor instanceof user) {
            $marker->markerid = $assessor->id();
            $marker->markername = $assessor->name();
            $marker->markerimg = $assessor->get_user_picture_url();
            $marker->markerurl = $assessor->get_user_profile_url();
            $marker->markeridentifier = sprintf('marker-%d', $assessor->id());
        } else if ($canaddfeedback) {
            // Just a placeholder to show "Marker 1" etc. until a marker is allocated.
            $marker->markername = get_string('markerdefaultname', 'mod_coursework', $markingstage);
            $marker->markerimg = $OUTPUT->image_url('u/f2');
        }
        return $marker;
    }

    /**
     * Get marker data for both single and multiple marker scenarios.
     *
     * @param assessor_feedback_row[] $tablerows Array of feedback rows for this submission
     * @param grading_table_row_base $rowsbase Row base object containing general data relating to this <tr>
     * @param bool $ismultiple Whether this is for multiple markers
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception|moodle_exception
     */
    protected function get_marker_data(array $tablerows, grading_table_row_base $rowsbase, bool $ismultiple = false): stdClass {
        $rowdata = new stdClass();
        $rowdata->markers = [];

        $markernumber = 1;
        foreach ($tablerows as $row) {
            if ($row->get_stage()->identifier() === 'moderator') {
                $rowdata->moderation = $this->get_moderation_data($rowsbase);
                continue;
            }
            $feedback = $row->get_feedback();
            // Get the assessor to last edit user if feedback exists, otherwise use the allocated assessor.
            $assessor = !empty($feedback) ? user::find($feedback->lasteditedbyuser) : $row->get_assessor();

            $canaddfeedback = $rowsbase->get_submission()
                && $this->can_add_new_feedback($rowsbase->get_submission()->id, $row->get_stage()->identifier());
            $canshowfeedback = $feedback && $this->ability->can('show', $feedback);
            $marker = $this->create_marker_data($assessor, $markernumber, $canaddfeedback);
            if ($feedback) {
                // Get feedback mark.
                $marker->mark = $this->get_mark_for_feedback($feedback, $canshowfeedback);
                $marker->showmark = true;

                // Marker template data.
                $marker->draft = !$feedback->finalised;
                $marker->readyforrelease = $rowsbase->get_submission()->ready_to_publish();
                $marker->timemodified = $feedback->timemodified;

                // Mark URL.
                if ($canaddfeedback || $canshowfeedback) {
                    $marker->markurl = $feedback->url_edit();
                } else {
                    // User cannot see the mark.
                    $marker->markhidden = true;
                }
            } else if ($canaddfeedback) {
                $marker->addfeedback = (object)[
                    'markurl' => feedback::url_new($rowsbase->get_submission()->id(), $row->get_stage()->identifier()),
                    'allocatablehash' => $this->get_allocatable_hash($rowsbase->get_allocatable()),
                ];
            }
            $rowdata->markers[] = $marker;
            $markernumber++;
        }

        // If this is for multiple markers, agreed mark or button to add agreed mark.
        if ($ismultiple && $rowsbase->get_submission()) {
            // Skip this if sampling is enabled but no sampled feedback exists.
            $samplingrequireshidden =
                $rowsbase->get_coursework()->sampling_enabled() && !$rowsbase->get_submission()->sampled_feedback_exists();
            if (!$samplingrequireshidden) {
                $finalfeedback = $rowsbase->get_submission()->get_final_feedback();
                if ($canshowfeedback && $finalfeedback) {
                    $rowdata->agreedmark = $this->get_final_feedback_data($finalfeedback, $rowsbase->get_allocatable(), $rowsbase->get_submission());
                } else if ($this->can_add_new_feedback($rowsbase->get_submission()->id, final_agreed::STAGE_FINAL_AGREED_1)) {
                    // No feedback exists yet - add button.
                    $rowdata->agreedmark = (object)[
                        'addfinalfeedback' => (object)[
                            'url' => feedback::url_new(
                                $rowsbase->get_submission()->id(),
                                final_agreed::STAGE_FINAL_AGREED_1
                            ),
                            'allocatablehash' => $this->get_allocatable_hash($rowsbase->get_allocatable()),
                        ],
                    ];
                }
            }
        }
        return $rowdata;
    }

    /**
     * Check if the user can add a new feedback.
     *
     * @param int $submissionid
     * @param string $stage
     * @return bool
     */
    public function can_add_new_feedback(int $submissionid, string $stage): bool {
        global $USER;
        return $this->ability->can(
            'new',
            feedback::build(
                [
                    'submissionid' => $submissionid,
                    'assessorid' => $USER->id,
                    'stageidentifier' => $stage,
                ]
            )
        );
    }

    /**
     * Get the mark for a particular feedback.
     *
     * @param feedback $feedback
     * @param bool $canshowfeedback
     * @return ?string
     * @throws coding_exception
     */
    public function get_mark_for_feedback(feedback $feedback, bool $canshowfeedback): ?string {
        global $USER;

        $judge = new grade_judge($this->coursework);

        if ($canshowfeedback || is_siteadmin($USER->id)) {
            return $judge->grade_to_display($feedback->get_grade());
        }
        if (
            has_capability('mod/coursework:addagreedgrade', $this->coursework->get_context())
            ||
            has_capability('mod/coursework:addallocatedagreedgrade', $this->coursework->get_context())
        ) {
            return get_string('mark_hidden_manager', 'mod_coursework');
        }
        return get_string('mark_hidden_teacher', 'mod_coursework');
    }

    /**
     * Get the final feedback data.
     *
     * @param feedback $finalfeedback
     * @param allocatable $allocatable
     * @param submission $submission
     * @return stdClass|null
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_final_feedback_data(feedback $finalfeedback, allocatable $allocatable, submission $submission): ?stdClass {
        $finalgrade = $this->get_mark_for_feedback($finalfeedback, true);
        // If this is an auto generated feedback, lasteditedbyuser will be zero.
        $assessorname = $finalfeedback->lasteditedbyuser
            ? user::find($finalfeedback->lasteditedbyuser, false)->name()
            : get_string('automaticallyagreed', 'mod_coursework');
        return (object)[
            'mark' => (object)[
                'markvalue' => $finalgrade,
                'allocatablehash' => $this->get_allocatable_hash($allocatable),
                'url' => $finalfeedback->url_edit(),
                // Only show draft label if final feedback is not finalised and submission is not ready for release.
                // Final feedback is still unfinalised if it is agreed automatically.
                'draft' => !$finalfeedback->finalised && !$submission->ready_to_publish(),
                'readyforrelease' => !$submission->is_published() &&
                    $submission->ready_to_publish(),
                'released' => $submission->is_published(),
                'timemodified' => $finalfeedback->timemodified,
                'markername' => $assessorname,
            ],
        ];
    }

    /**
     * Get the allocatable hash.
     *
     * @param allocatable $allocatable
     * @return string
     */
    protected function get_allocatable_hash($allocatable) {
        if (empty($this->allocatablehash)) {
            $this->allocatablehash = $this->coursework->get_allocatable_identifier_hash($allocatable);
        }
        return $this->allocatablehash;
    }

    /**
     * Get moderation data for the template.
     *
     * @param grading_table_row_base $rowsbase
     * @return stdClass|null
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function get_moderation_data(grading_table_row_base $rowsbase): ?stdClass {
        global $USER;

        $submission = $rowsbase->get_submission();

        if (!$submission) {
            return null; // No submission.
        }

        $moderationstage = $this->coursework->get_moderator_marking_stage();
        $moderation = $moderationstage->get_moderation($submission);
        $moderationdata = new stdClass(); // Mustache data.

        // Existing moderation.
        if ($moderation) {
            $canseeallgrades = $this->ability->can('show', $moderation);
            if (!$canseeallgrades) {
                $gradedbyme = $rowsbase->get_single_feedback()->lasteditedbyuser == $USER->id;
                if (!$gradedbyme) {
                    return null; // Exit: Cannot view moderation data.
                }
            }

            // Mark data.
            $markdata = new stdClass();
            $markdata->markvalue = get_string($moderation->agreement, 'coursework');
            $markdata->readyforrelease = $moderation->agreement === 'agreed' && !$submission->is_published();

            if ($moderation->timemodified) {
                $markdata->moderatorname = $moderation->moderator()->name();
                $markdata->timemodified = $moderation->timemodified;
            }

            // TODO - currently there are 3 forms/urls for moderation.
            // Ideally we should just have one which contains the logic for new/edit/show.
            // It should show what the user is moderating/agreeing on.
            // Ideally this could be combined with the agree feedback process.

            if ($canseeallgrades) {
                // If user can see all grades and/or edit moderations, they may see moderation result as a link.
                // Default show url.
                $markdata->url = router::instance()->get_path('show moderation', ['moderation' => $moderation]);

                // Edit url (overwrites default if user can edit).
                if (!$submission->is_published() && $this->ability->can('edit', $moderation)) {
                    $markdata->url = router::instance()->get_path('edit moderation', ['moderation' => $moderation]);
                }
            }

            $moderationdata->mark = $markdata;

            // Return existing moderation.
            return $moderationdata;
        }

        // New moderation.
        $firstfeedback = $moderationstage->get_single_feedback($submission);
        if (!$firstfeedback || !$firstfeedback->finalised) {
            return null; // No feedback to moderate.
        }

        $newmoderation = moderation::build(['feedbackid' => $firstfeedback->id]);

        // Convoluted url builder.
        if ($this->ability->can('new', $newmoderation)) {
            $moderationdata->addmoderation = new stdClass();
            $params = [
                'submission' => $submission,
                'stage' => $moderationstage,
                'feedbackid' => $firstfeedback->id,
                'assessor' => core_user::get_user($USER->id),
            ];
            $moderationdata->addmoderation->url = router::instance()->get_path('new moderations', $params);
        }
        return $moderationdata;
    }
}
