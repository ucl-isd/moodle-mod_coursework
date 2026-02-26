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
use core\exception\invalid_parameter_exception;
use core\exception\moodle_exception;
use core\url;
use core_user;
use dml_exception;
use mod_coursework\assessor_feedback_table;
use mod_coursework\allocation\allocatable;
use mod_coursework\assessor_feedback_row;
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
        $tablerows = [];
        foreach ($this->coursework->get_assessor_marking_stages() as $stage) {
            $tablerows[] = new assessor_feedback_row($stage, $rowsbase->get_allocatable(), $this->coursework, $rowsbase->get_submission());
        }

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
     * @param bool $canseeothermarkerdetails
     * @return stdClass
     * @throws coding_exception
     */
    private function create_marker_data($assessor, int $markingstage, bool $canseeothermarkerdetails): stdClass {
        global $OUTPUT, $USER;
        $marker = new stdClass();
        $marker->markingstage = $markingstage;
        if (
            ($canseeothermarkerdetails || $assessor->id() == $USER->id)
            &&
            $assessor instanceof user
        ) {
            $marker->markerid = $assessor->id();
            $marker->markername = $assessor->name();
            // Marker image "markerimg" is not set here as it would involve an extra DB query.
            $marker->picture = $assessor->picture;
            $marker->markerurl = $assessor->get_user_profile_url();
            $marker->markeridentifier = sprintf('marker-%d', $assessor->id());
        } else {
            // Just a placeholder to show "Marker 1" etc. until a marker is allocated.
            $marker->markername = get_string('markerdefaultname', 'mod_coursework', $markingstage);
            $marker->markerimg = $OUTPUT->image_url('u/f2')->out();
        }
        return $marker;
    }

    /**
     * Processes feedback data for a marker.
     *
     * @param stdClass $marker Marker object to update
     * @param feedback $feedback Feedback object
     * @param grading_table_row_base $rowsbase Base row data
     * @param assessor_feedback_row $row Current row being processed
     * @throws coding_exception
     * @throws dml_exception
     */
    private function process_feedback_data(stdClass $marker, feedback $feedback, grading_table_row_base $rowsbase, assessor_feedback_row $row): void {
        // Get feedback mark.

        $canshow = $feedback->can_show($this->coursework, $rowsbase->get_submission());
        $marker->mark = $this->get_mark_for_feedback($feedback, $canshow);
        // Return early if no marking.
        if (!isset($marker->mark) || ($marker->mark === '')) {
            return;
        }
        $marker->showmark = true;

        // Marker template data.
        $marker->draft = !$feedback->finalised;
        $marker->readyforrelease = $rowsbase->get_submission()->ready_to_publish();
        $marker->timemodified = $feedback->timemodified;

        // Actions - show, edit.
        $action = null;
        if ($canshow) {
            $action = 'show';
        }
        if ($feedback->can_edit($this->coursework, $rowsbase->get_submission())) {
            $action = 'edit';
            $marker->feedbackid = $feedback->id;
        }

        // Mark URL.
        if ($action) {
            $marker->markurl = $this->get_mark_url(
                $action,
                $rowsbase->get_submission(),
                $row->get_stage(),
                $feedback
            );
        } else {
            // User cannot see the mark.
            $marker->markhidden = true;
        }
    }

    /**
     * Get marker data for both single and multiple marker scenarios.
     *
     * @param assessor_feedback_row[] $tablerows Each TR row in the table
     * @param grading_table_row_base $rowsbase Row base object containing general data
     * @param bool $ismultiple Whether this is for multiple markers
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function get_marker_data(array $tablerows, grading_table_row_base $rowsbase, bool $ismultiple = false): stdClass {
        $rowdata = new stdClass();
        $rowdata->markers = [];

        // Count initial feedbacks using table rows to avoid additional DB queries.
        // Then we know from the outset if we have all initial feedbacks or not.
        $intialfeedbacks = array_filter(
            $tablerows,
            function ($row) {
                return $row->get_feedback() && !$row->get_feedback()->is_agreed_grade();
            }
        );
        $rowdata->countinitialfeedbacks = count($intialfeedbacks);
        $rowdata->hasallinitialfeedbacks = $rowdata->countinitialfeedbacks >= $this->coursework->get_max_markers();

        $markernumber = 1;
        foreach ($tablerows as $row) {
            if ($row->get_stage()->identifier() === 'moderator') {
                $rowdata->moderation = $this->get_moderation_data($rowsbase);
                continue;
            }
            $feedback = $row->get_feedback();

            $canseeothermarkerdetails = $rowdata->hasallinitialfeedbacks
                || $this->coursework->viewinitialgradeenabled
                || has_capability('mod/coursework:administergrades', $this->coursework->get_context());
            $marker = $this->create_marker_data($row->get_assessor(), $markernumber, $canseeothermarkerdetails);
            if ($feedback) {
                $this->process_feedback_data($marker, $feedback, $rowsbase, $row);
            }

            $canaddfeedback = !$feedback
                && $rowsbase->get_submission()
                && feedback::can_add_new($rowsbase->get_coursework(), $rowsbase->get_submission(), $row->get_stage()->identifier());
            if ($canaddfeedback) {
                $marker->addfeedback = (object)[
                    'markurl' => $this->get_mark_url(
                        'new',
                        $rowsbase->get_submission(),
                        $row->get_stage(),
                        null,
                        !$ismultiple
                    ),
                    'allocatablehash' => $this->get_allocatable_hash($rowsbase->get_allocatable()),
                ];
            }

            $rowdata->markers[] = $marker;
            $markernumber++;
        }
        // Set the agreed mark if this is for multiple markers.
        if ($ismultiple) {
            $rowdata->agreedmark = $this->get_final_feedback_data($rowsbase);
        }

        return $rowdata;
    }

    /**
     * Get the mark URL for a particular action.
     *
     * @param string $action 'edit', 'show' or 'new'
     * @param submission $submission the submission
     * @param stage $stage the stage of the row
     * @param feedback|null $feedback $feedback the feedback if editing existing
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_mark_url(string $action, submission $submission, stage $stage, ?feedback $feedback = null): string {
        if (!in_array($action, ['new', 'edit', 'show'])) {
            throw new invalid_parameter_exception("Unknown action $action");
        }
        return (new url(
            "/mod/coursework/actions/feedbacks/$action.php",
            ($action == 'new')
               ? ['submissionid' => $submission->id(), 'stageidentifier' => $stage->identifier()]
               : ['feedbackid' => $feedback ? $feedback->id() : null]
        ))->out();
    }

    /**
     * Get the mark for a particular feedback.
     *
     * @param feedback $feedback
     * @param bool $canshow
     * @return ?string
     * @throws coding_exception
     */
    public function get_mark_for_feedback(feedback $feedback, bool $canshow): ?string {
        global $USER;

        $judge = new grade_judge($this->coursework);

        if ($canshow || is_siteadmin($USER->id)) {
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
     * @param grading_table_row_base $rowsbase
     * @return stdClass|null
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_final_feedback_data(grading_table_row_base $rowsbase): ?stdClass {
        if (!$rowsbase->get_submission()) {
            return null;
        }
        // Early return if sampling is enabled but no sampled feedback exists.
        if (
            $rowsbase->get_coursework()->sampling_enabled() &&
            !$rowsbase->get_submission()->sampled_feedback_exists()
        ) {
            return null;
        }

        $finalstage = $this->coursework->get_final_agreed_marking_stage();
        $finalfeedback = $finalstage->get_feedback_for_allocatable($rowsbase->get_allocatable());
        if ($finalfeedback === false) {
            // Handle case when no feedback exists yet.
            return feedback::can_add_new($this->coursework, $rowsbase->get_submission(), $finalstage->identifier())
                ? (object)[
                    'addfinalfeedback' => (object)[
                        'url' => $this->get_mark_url('new', $rowsbase->get_submission(), $finalstage, null, true),
                        'allocatablehash' => $this->get_allocatable_hash($rowsbase->get_allocatable()),
                    ],
                ]
                : null;
        }

        // Handle existing feedback.
        $canshow = $finalfeedback->can_show($this->coursework, $rowsbase->get_submission());
        $canedit = $finalfeedback->can_edit($this->coursework, $rowsbase->get_submission());
        if (!$canshow && !$canedit) {
            return null;
        }

        $finalgrade = $this->get_mark_for_feedback($finalfeedback, $canshow);
        // If this is an auto generated feedback, lasteditedbyuser will be zero.
        $assessorname = $finalfeedback->assessorid
            ? user::find($finalfeedback->assessorid, false)->name()
            : get_string('automaticallyagreed', 'mod_coursework');
        return (object)[
            'mark' => (object)[
                'markvalue' => $finalgrade,
                'allocatablehash' => $this->get_allocatable_hash($rowsbase->get_allocatable()),
                'url' => $this->get_mark_url(
                    $canedit ? 'edit' : 'show',
                    $rowsbase->get_submission(),
                    $finalstage,
                    $finalfeedback,
                ),
                // Only show draft label if final feedback is not finalised and submission is not ready for release.
                // Final feedback is still unfinalised if it is agreed automatically.
                'draft' => !$finalfeedback->finalised,
                'readyforrelease' => !$rowsbase->get_submission()->is_published() &&
                    $rowsbase->get_submission()->ready_to_publish(),
                'released' => $rowsbase->get_submission()->is_published(),
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
