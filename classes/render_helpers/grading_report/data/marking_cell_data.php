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

use mod_coursework\allocation\allocatable;
use mod_coursework\assessor_feedback_row;
use mod_coursework\assessor_feedback_table;
use mod_coursework\grade_judge;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\feedback;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use mod_coursework\models\null_user;
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
     * @return stdClass
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
     * Processes feedback data for a marker.
     *
     * @param stdClass $marker Marker object to update
     * @param feedback $feedback Feedback object
     * @param grading_table_row_base $rowsbase Base row data
     * @param assessor_feedback_row $row Current row being processed
     */
    private function process_feedback_data(stdClass $marker, feedback $feedback,
        grading_table_row_base $rowsbase,
        assessor_feedback_row $row
    ): void {
        // Get feedback mark.
        $marker->mark = $this->get_mark_for_feedback($feedback);
        // Return early if no marking.
        if (empty($marker->mark)) {
            return;
        }

        // Marker template data.
        $marker->draft = !$feedback->finalised;
        $marker->readyforrelease = $rowsbase->get_submission()->ready_to_publish();
        $marker->timemodified = $feedback->timemodified;

        // Actions - show, edit.
        $action = null;
        if ($this->ability->can('show', $feedback)) {
            $action = 'show';
        }
        if ($this->ability->can('edit', $feedback)) {
            $action = 'edit';
        }

        // Mark URL.
        if ($action) {
            $marker->markurl = $this->get_mark_url(
                    $action,
                    $rowsbase->get_submission(),
                    $row->get_stage(),
                    $feedback
            );
        }
        // User cannot see the mark.
        else {
            $marker->markhidden = true;
        }
    }

    /**
     * Get marker data for both single and multiple marker scenarios.
     *
     * @param array $tablerows Each TR row in the table
     * @param grading_table_row_base $rowsbase Row base object containing general data
     * @param bool $ismultiple Whether this is for multiple markers
     * @return stdClass
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function get_marker_data(array $tablerows, grading_table_row_base $rowsbase, bool $ismultiple = false): stdClass {
        $rowdata = new stdClass();
        $rowdata->markers = [];

        $markernumber = 1;
        foreach ($tablerows as $row) {
            if ($row->get_stage()->identifier() === 'moderator') {
                // If moderation is turned on then we don't show a marker/feedback button for the moderation stage.
                continue;
            }
            $feedback = $row->get_feedback();
            // Get the assessor to last edit user if feedback exists, otherwise use the allocated assessor.
            $assessor = !empty($feedback) ? user::find($feedback->lasteditedbyuser) : $row->get_assessor();

            $canaddfeedback = $this->can_add_new_feedback($row, $rowsbase);
            $marker = $this->create_marker_data($assessor, $markernumber, $canaddfeedback);
            if ($feedback) {
                $this->process_feedback_data($marker, $feedback, $rowsbase, $row);
            }

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
     * @param feedback|null $feedback $feedback the feedback
     * @param bool $final whether this is for a final mark
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_mark_url(
        string $action,
        submission $submission,
        stage $stage,
        ?feedback $feedback = null,
        bool $final = false
    ): string {
        global $USER;

        $paths = [
            'edit' => ['path' => 'edit feedback', 'params' => ['feedback' => $feedback]],
            'show' => ['path' => 'show feedback', 'params' => ['feedback' => $feedback]],
            'new' => [
                'path' => 'new ' . ($final ? 'final ' : '') . 'feedback',
                'params' => [
                    'submission' => $submission,
                    'assessor' => user::find($USER),
                    'stage' => $stage,
                ]
            ]
        ];

        return isset($paths[$action]) ?
            router::instance()->get_path($paths[$action]['path'], $paths[$action]['params']) :
            '#';
    }

    /**
     * Check if the user can add a new feedback.
     *
     * @param assessor_feedback_row $feedbackrow
     * @param grading_table_row_base $rowsbase
     * @return bool
     */
    public function can_add_new_feedback(assessor_feedback_row $feedbackrow, grading_table_row_base $rowsbase): bool {
        global $USER;

        if (!$rowsbase->get_submission()) {
            return false;
        }

        $feedbackparams = [
            'submissionid' => $rowsbase->get_submission()->id,
            'assessorid' => $USER->id,
            'stage_identifier' => $feedbackrow->get_stage()->identifier(),
        ];

        return $this->ability->can('new', feedback::build($feedbackparams));
    }

    /**
     * Check if the user can add a new final feedback.
     *
     * @param final_agreed $finalstage
     * @param grading_table_row_base $rowsbase
     * @return bool
     */
    public function can_add_new_final_feedback(final_agreed $finalstage, grading_table_row_base $rowsbase): bool {
        global $USER;

        if (!$rowsbase->get_submission()) {
            return false;
        }

        $newfeedback = feedback::build([
            'submissionid' => $rowsbase->get_submission()->id,
            'assessorid' => $USER->id,
            'stage_identifier' => $finalstage->identifier(),
        ]);

        return $this->ability->can('new', $newfeedback);
    }

    /**
     * Get the mark for a particular feedback.
     *
     * @param feedback $feedback
     * @return ?string
     * @throws \coding_exception
     */
    public function get_mark_for_feedback(feedback $feedback): ?string {
        global $USER;

        $judge = new grade_judge($this->coursework);

        if ($this->ability->can('show', $feedback) || is_siteadmin($USER->id)) {
            return $judge->grade_to_display($feedback->get_grade());
        }

        return has_capability('mod/coursework:addagreedgrade', $this->coursework->get_context()) ||
               has_capability('mod/coursework:addallocatedagreedgrade', $this->coursework->get_context()) ?
               get_string('grade_hidden_manager', 'mod_coursework') :
               get_string('grade_hidden_teacher', 'mod_coursework');
    }

    /**
     * Get the final feedback data.
     *
     * @param grading_table_row_base $rowsbase
     * @return stdClass|null
     */
    public function get_final_feedback_data(grading_table_row_base $rowsbase): ?stdClass {
        // Early return if sampling is enabled but no sampled feedback exists.
        if ($rowsbase->get_coursework()->sampling_enabled() &&
            $rowsbase->get_submission() &&
            !$rowsbase->get_submission()->sampled_feedback_exists()) {
            return null;
        }

        $finalstage = $this->coursework->get_final_agreed_marking_stage();
        $finalfeedback = $finalstage->get_feedback_for_allocatable($rowsbase->get_allocatable());

        if ($finalfeedback === false) {
            // Handle case when no feedback exists yet.
            return $this->can_add_new_final_feedback($finalstage, $rowsbase) ?
                (object)['addfinalfeedback' => (object)[
                    'url' => $this->get_mark_url('new', $rowsbase->get_submission(), $finalstage, null, true),
                    'allocatablehash' => $this->get_allocatable_hash($rowsbase->get_allocatable()),
                ]] :
                null;
        }

        // Handle existing feedback.
        $finalgrade = $this->get_mark_for_feedback($finalfeedback);
        $action = $this->ability->can('edit', $finalfeedback) ? 'edit' :
                 ($this->ability->can('show', $finalfeedback) ? 'show' : null);

        return $action ? (object)[
            'mark' => (object)[
                'markvalue' => $finalgrade,
                'allocatablehash' => $this->get_allocatable_hash($rowsbase->get_allocatable()),
                'url' => $this->get_mark_url($action, $rowsbase->get_submission(), $finalstage, $finalfeedback),
                // Only show draft label if final feedback is not finalised and submission is not ready for release.
                // Final feedback is still unfinalised if it is agreed automatically.
                'draft' => !$finalfeedback->finalised && !$rowsbase->get_submission()->ready_to_publish(),
                'readyforrelease' => !$rowsbase->get_submission()->is_published() &&
                    $rowsbase->get_submission()->ready_to_publish(),
                'released' => $rowsbase->get_submission()->is_published(),
                'timemodified' => $rowsbase->get_submission()->lastpublished,
            ],
        ] : null;
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
}
