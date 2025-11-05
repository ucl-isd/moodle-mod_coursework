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

namespace mod_coursework\renderers;

use mod_coursework\ability;
use mod_coursework\models\deadline_extension;
use mod_coursework\models\personal_deadline;
use mod_coursework\grading_report;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\coursework;
use mod_coursework\models\user;
use mod_coursework\render_helpers\grading_report\data\actions_cell_data;
use mod_coursework\render_helpers\grading_report\data\marking_cell_data;
use mod_coursework\render_helpers\grading_report\data\student_cell_data;
use mod_coursework\render_helpers\grading_report\data\submission_cell_data;
use stdClass;

/**
 * Class to render the grading / submission table.
 *
 */
class grading_report_renderer extends \core\output\plugin_renderer_base {

    /**
     * Renders the grading report.
     *
     * @param grading_report $gradingreport
     * @return bool|string
     * @throws \core\exception\moodle_exception
     */
    public function render_grading_report(grading_report $gradingreport) {

        $tablerows = $gradingreport->get_table_rows_for_page();
        $participantcontextids = user::get_user_picture_context_ids(
            $gradingreport->get_coursework()->get_course_id()
        );

        // Sort the table rows.
        $tablerows = $this->sort_table_rows($tablerows);

        $template = new stdClass();
        $template->coursework = self::prepare_coursework_data($gradingreport->get_coursework());
        $template->blindmarkingenabled = $gradingreport->get_coursework()->blindmarking_enabled() &&
            !has_capability('mod/coursework:viewanonymous', $gradingreport->get_coursework()->get_context());
        $template->releasemarks = $this->prepare_release_marks_button($gradingreport->get_coursework());

        // Populate template tr data.
        $template->tr = [];
        $markersarray = []; // Collect list of allocated markers while we are iterating.
        foreach ($tablerows as $rowobject) {
            $trdata = $this->get_table_row_data($gradingreport->get_coursework(), $rowobject);

            // If this row represents a user (not a group), add the user picture.
            $allocatable = $rowobject->get_allocatable();
            if ($allocatable->type() === 'user') {
                $participantcontextid = $participantcontextids[$allocatable->id()] ?? null;
                $trdata->submissiontype->user->picture =
                    user::get_picture_url_from_context_id($participantcontextid, $allocatable->picture);
            }

            // Add allocated markers for data-marker and dropdown filter.
            if (!empty($trdata->markers)) {
                // Tr.mustache - csv list for data-marker used by js filtering.
                $trdata->markerfilter = implode(', ', array_column($trdata->markers, 'markeridentifier'));

                // Create markers array by id to ensure unique.
                foreach (array_filter($trdata->markers, fn($m) => isset($m->markerid)) as $marker) {
                    $markersarray[$marker->markerid] = $marker;
                }
            }
            $template->tr[] = $trdata;
        }

        // Sort and add markers to template.
        if ($markersarray) {
            usort($markersarray, function ($a, $b) {
                return strnatcasecmp($a->markername, $b->markername);
            });
            $template->hasmarkers = true;
            $template->markerfilter = $markersarray;
        }

        return $this->render_from_template('mod_coursework/submissions/table', $template);
    }

    /**
     * Sort table rows by submission timemodified, with null submissions last.
     *
     * @param grading_table_row_base[] $tablerows
     * @return grading_table_row_base[]
     */
    protected function sort_table_rows(array $tablerows): array {
        usort($tablerows, function($rowa, $rowb) {

            $submissiona = $rowa->get_submission();
            $submissionb = $rowb->get_submission();

            // Check if submission is not an object (i.e., it's null or false).
            $isnulloraa = !is_object($submissiona);
            $isnullorab = !is_object($submissionb);

            if ($isnulloraa && $isnullorab) {
                return 0;
            } else if ($isnulloraa) {
                return 1;
            } else if ($isnullorab) {
                return -1;
            }

            // Both submissions are objects, compare by timemodified.
            return $submissiona->timemodified <=> $submissionb->timemodified;
        });
        return $tablerows;
    }

    /**
     * Get the data for a single row in the table (representing one "allocatable").
     * @param coursework $coursework
     * @param grading_table_row_base $rowobject
     * @return stdClass
     */
    public static function get_table_row_data(coursework $coursework, grading_table_row_base $rowobject): object {
        $trdata = new stdClass();
        // Prepare data for table cells.
        self::prepare_student_cell_data($coursework, $rowobject, $trdata);
        self::prepare_submission_cell_data($coursework, $rowobject, $trdata);
        self::prepare_marking_cell_data($coursework, $rowobject, $trdata);
        self::prepare_actions_cell_data($coursework, $rowobject, $trdata);
        self::set_tr_status($trdata);
        return $trdata;
    }

    /**
     * Export the data for a single table row to make it accessible from JS.
     * Enables a table row to be re-rendered from JS when updated via modal form.
     * @param int $allocatableid
     * @param string $allocatabletype
     * @return ?object
     */
    public static function export_one_row_data(coursework $coursework, int $allocatableid, string $allocatabletype): ?object {
        global $USER;
        $classname = "\\mod_coursework\\models\\$allocatabletype";
        $allocatable = $classname::get_object($allocatableid);
        if (!$allocatable) {
            return null;
        }
        $rowclass = $coursework->has_multiple_markers()
            ? 'mod_coursework\grading_table_row_multi'
            : 'mod_coursework\grading_table_row_single';
        $ability = new ability($USER->id, $coursework);

        // New grading_table_row_base.
        $row = new $rowclass(
            $coursework,
            $allocatable,
            deadline_extension::get_for_allocatable($coursework->id, $allocatableid, $allocatabletype),
            personal_deadline::get_for_allocatable($coursework->id, $allocatableid, $allocatabletype),
        );
        if (!$ability->can('show', $row)) {
            return null;
        }
        $data = self::get_table_row_data($coursework, $row);

        // We need to add some to this because the tr and actions templates both use fields from parent as well as row.
        // Otherwise some action menu elements may be incomplete.
        $data->coursework = self::prepare_coursework_data($coursework);
        $participantcontextid = $allocatabletype === 'user' ? \context_user::instance($allocatableid)->id : null;
        $data->submissiontype->user->picture =
            user::get_picture_url_from_context_id($participantcontextid, $allocatable->picture);
        return $data;
    }

    /**
     * Prepare data relating to coursework object.
     * @param coursework $coursework
     * @return object
     */
    protected static function prepare_coursework_data(coursework $coursework): object {
        return  (object)[
            'id' => $coursework->id,
            'title' => $coursework->name,
            'personal_deadlines_enabled' => $coursework->personal_deadlines_enabled(),
            'defaultduedate' => $coursework->get_deadline(),
            'isgroupsubmission' => $coursework->is_configured_to_have_group_submissions(),
        ];
    }

    /**
     * Prepare student cell data
     *
     * @param coursework $coursework
     * @param grading_table_row_base $rowobject
     * @param stdClass $trdata
     * @return void
     */
    protected static function prepare_student_cell_data(coursework $coursework, grading_table_row_base $rowobject, stdClass $trdata) {
        $dataprovider = new student_cell_data($coursework);
        $trdata->submissiontype = $dataprovider->get_table_cell_data($rowobject);
    }

    /**
     * Prepare submission cell data
     *
     * @param coursework $coursework
     * @param grading_table_row_base $rowobject
     * @param stdClass $trdata
     * @return void
     */
    protected static function prepare_submission_cell_data(coursework $coursework, grading_table_row_base $rowobject, stdClass $trdata) {
        $dataprovider = new submission_cell_data($coursework);
        $trdata->submission = $dataprovider->get_table_cell_data($rowobject);
    }

    /**
     * Prepare marking cell data
     *
     * @param coursework $coursework
     * @param grading_table_row_base $rowobject
     * @param stdClass $trdata
     * @return void
     */
    protected static function prepare_marking_cell_data(coursework $coursework, grading_table_row_base $rowobject, stdClass $trdata) {
        $dataprovider = new marking_cell_data($coursework);
        $markingcelldata = $dataprovider->get_table_cell_data($rowobject);
        $trdata->markers = $markingcelldata->markers;
        $trdata->agreedmark = !empty($markingcelldata->agreedmark) ? $markingcelldata->agreedmark : null;
        $trdata->moderation = !empty($markingcelldata->moderation) ? $markingcelldata->moderation : null;
    }

    /**
     * Prepare actions cell data
     *
     * @param coursework $coursework
     * @param grading_table_row_base $rowobject
     * @param stdClass $trdata
     * @return void
     */
    protected static function prepare_actions_cell_data(
        coursework $coursework,
        grading_table_row_base $rowobject,
        stdClass $trdata
    ): void {
        $dataprovider = new actions_cell_data($coursework);
        $actions = $dataprovider->get_table_cell_data($rowobject);
        $trdata->actions = $actions;
    }

    /**
     * Prepare release marks button.
     *
     * @param coursework $coursework
     * @return stdClass|null
     * @throws \core\exception\moodle_exception
     * @throws coding_exception
     */
    protected function prepare_release_marks_button(coursework $coursework): ?stdClass {
        [$canrelease] = $coursework->can_release_marks();
        if (!$canrelease) {
            return null;
        }

        $releasemarks = new stdClass();
        $releasemarks->warning = '';
        $releasemarks->url = new \moodle_url(
            '/mod/coursework/actions/releasemarks.php',
            ['cmid' => $coursework->get_coursemodule_id()]
        );

        if ($coursework->blindmarking_enabled()) {
            $submissiontype = $coursework->is_configured_to_have_group_submissions() ? 'group' : 'user';
            $releasemarks->warning = get_string('anonymity_warning_' . $submissiontype, 'mod_coursework');
        }

        return $releasemarks;
    }

    /**
     * Set tr status.
     *
     * @param stdClass $trdata
     * @return void
     */
    protected static function set_tr_status(stdClass $trdata): void {
        $status = [];
        if (!empty($trdata->submission->extensiongranted)) {
            $status[] = 'extension-granted';
        }

        if (!empty($trdata->submission->submissiondata->flaggedplagiarism)) {
            $status[] = 'flagged-for-plagiarism';
        }

        if (!empty($trdata->submission->submissiondata->submittedlate)) {
            $status[] = 'late';
        }

        if (empty($trdata->submission->submissiondata)) {
            $status[] = 'not-submitted';
        }

        if (!empty($trdata->agreedmark->addfinalfeedback) || !empty($trdata->moderation->addmoderation)) {
            $status[] = 'need-agreement';
        }

        if (!empty($trdata->agreedmark->mark->readyforrelease) || !empty($trdata->moderation->mark->readyforrelease)) {
            $status[] = 'ready-for-release';
        }

        foreach ($trdata->markers as $marker) {
            if (!empty($marker->addfeedback)) {
                $status[] = 'need-marking';
            }
        }

        $trdata->status = implode(', ', $status);
    }
}
