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
 * Data provider for actions column in submissions table.
 *
 * @package    mod_coursework
 * @copyright  2025 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */

namespace mod_coursework\render_helpers\grading_report\data;

use mod_coursework\grading_table_row_base;
use mod_coursework\models\deadline_extension;
use mod_coursework\models\personal_deadline;
use mod_coursework\models\plagiarism_flag;
use mod_coursework\models\submission;
use mod_coursework\router;
use moodle_url;
use stdClass;

/**
 * Class marking_cell_data provides data for marking cell templates.
 */
class actions_cell_data extends cell_data_base {
    /**
     * Get the data for the marking cell.
     *
     * @param grading_table_row_base $rowsbase
     * @return stdClass|null The data object for template rendering.
     */
    public function get_table_cell_data(grading_table_row_base $rowsbase): ?stdClass {
        $data = new \stdClass();

        $identitieshidden = $this->coursework->blindmarking_enabled() &&
            !has_capability('mod/coursework:viewanonymous', $this->coursework->get_context());

        if (!$identitieshidden) {
            $this->set_extension_data($data, $rowsbase);
            $this->set_personal_deadline_data($data, $rowsbase);
        }

        // Set submission parameters.
        $this->set_submission_data($data, $rowsbase);

        // Set finalise parameters.
        $this->set_finalise_data($data, $rowsbase);

        // Set unfinalise parameters.
        $this->set_unfinalise_data($data, $rowsbase);

        // Set plagiarism parameters.
        $this->set_plagiarism_data($data, $rowsbase);

        if (empty(get_object_vars($data))) {
            // If $data has no properties here, return null and we will skip adding the actions menu at all.
            return null;
        }

        $data->allocatableid = $rowsbase->get_allocatable_id();
        $data->allocatabletype = $rowsbase->get_allocatable()->type();
        return $data;
    }

    /**
     * Set extension parameters.
     *
     * @param stdClass $data
     * @param grading_table_row_base $rowsbase
     * @return void
     */
    protected function set_extension_data(stdClass $data, grading_table_row_base $rowsbase): void {
        if (!$this->coursework->get_deadline() || !$this->coursework->extensions_enabled()) {
            return;
        }
        // Set parameters to add/update extension.
        $extensionparams = [
            'allocatableid' => $rowsbase->get_allocatable_id(),
            'allocatabletype' => $rowsbase->get_allocatable()->type(),
            'courseworkid' => $this->coursework->id,
        ];
        $extension = $rowsbase->get_extension();
        // We avoid using $this->ability->can() in this context as it creates multiple DB queries per row.
        $hascapability = has_capability('mod/coursework:grantextensions', $this->coursework->get_context());

        // If cannot grant, add no data at all to actions.
        if (!$hascapability) {
            return;
        }

        $data->extension = new stdClass();
        if ($extension) {
            $extensionparams['id'] = $extension->id;
            $data->extension->date = $extension->extended_deadline;
            $data->extension->id = $extension->id;
        } else {
            $data->extension->date = null;
        }

        $data->extension->show = true;
        $data->extension->class = $extension ? 'edit_deadline_extension' : 'new_deadline_extension';
        $data->extension->url = $extension ? router::instance()->get_path('edit deadline extension', ['id' => $extension->id]) :
            htmlspecialchars_decode(router::instance()->get_path('new deadline extension', $extensionparams));
    }

    /**
     * Set submission data for modal display.
     *
     * @param stdClass $data The data object
     * @param grading_table_row_base $rowsbase The row base object
     */
    protected function set_submission_data(stdClass $data, grading_table_row_base $rowsbase): void {
        global $USER;

        // If submission is finalised, no actions needed.
        $submission = $rowsbase->get_submission();
        if ($submission && $submission->is_finalised()) {
            return;
        }

        // Check if we can create new submission.
        if ($this->can_submit_new($rowsbase)) {
            $submissiondata = submission::build([
                'allocatableid' => $rowsbase->get_allocatable()->id(),
                'allocatabletype' => $rowsbase->get_allocatable()->type(),
                'courseworkid' => $rowsbase->get_coursework()->id,
                'createdby' => $USER->id,
            ]);
            $data->submission = new stdClass();
            $data->submission->url = router::instance()
                ->get_path('new submission', ['submission' => $submissiondata], false, false);
            $data->submission->label = get_string('submitonbehalf', 'coursework');
            return;
        }

        // Check if we can edit existing submission.
        if ($submission && $this->ability->can('edit', $submission) && !$rowsbase->has_feedback()) {
            $data->submission = new stdClass();
            $data->submission->url = router::instance()->get_path('edit submission', ['submission' => $submission], false, false);
            $entitytype = $rowsbase->get_coursework()->is_configured_to_have_group_submissions() ? 'group' : 'student';
            $data->submission->label = "Edit submission on behalf of this {$entitytype}";
        }
    }

    /**
     * Set finalise parameters.
     *
     * @param stdClass $data The data object
     * @param grading_table_row_base $rowsbase The row base object
     */
    protected function set_finalise_data(stdClass $data, grading_table_row_base $rowsbase): void {
        if (!$rowsbase->get_submission() || $rowsbase->get_submission()->is_finalised()) {
            return;
        }
        if ($this->ability->can('finalise', $rowsbase->get_submission())) {
            $data->finalise = new stdClass();
            $data->finalise->url = router::instance()
                ->get_path('finalise submission', ['submission' => $rowsbase->get_submission()], false, false);
            $data->finalise->studentname = $rowsbase->can_see_user_name()
                ? $rowsbase->get_allocatable()->name() : get_string('hidden', 'mod_coursework');
        }
    }

    /**
     * Set unfinalise parameters.
     *
     * @param stdClass $data The data object
     * @param grading_table_row_base $rowsbase The row base object
     */
    protected function set_unfinalise_data(stdClass $data, grading_table_row_base $rowsbase): void {
        if (!$rowsbase->get_submission() || !$rowsbase->get_submission()->is_finalised()) {
            return;
        }
        if ($this->ability->can('revert', $rowsbase->get_submission())) {
            $url = new moodle_url(
                '/mod/coursework/actions/revert.php',
                [
                    'cmid' => $rowsbase->get_course_module_id(),
                    'submissionid' => $rowsbase->get_submission_id(),
                ]);
            $data->unfinalise = new stdClass();
            $data->unfinalise->url = $url->out(false);
            $data->unfinalise->studentname = $rowsbase->can_see_user_name()
                ? $rowsbase->get_allocatable()->name() : get_string('hidden', 'mod_coursework');
        }
    }

    /**
     * Set plagiarism data.
     *
     * @param stdClass $data The data object
     * @param grading_table_row_base $rowsbase The row base object
     */
    protected function set_plagiarism_data(stdClass $data, grading_table_row_base $rowsbase): void {
        // Early returns for conditions where plagiarism data should not be shown.
        if (!$this->coursework->plagiarism_flagging_enbled() ||
            !$rowsbase->get_submission() ||
            !$rowsbase->get_submission()->is_finalised()) {
            return;
        }

        $submission = $rowsbase->get_submission();
        $plagiarismflag = $rowsbase->get_plagiarism_flag();
        $data->plagiarism = new stdClass();
        $data->plagiarism->url = $this->get_plagiarism_url($submission, $plagiarismflag);
        $data->plagiarismstatus = $this->get_flagged_plagiarism_status($submission);
        $data->plagiarism->flagid = $plagiarismflag->id ?? null;
    }

    /**
     * Get the appropriate plagiarism URL based on flag status.
     *
     * @param submission $submission The submission object
     * @param plagiarism_flag|bool $flag The plagiarism flag object
     * @return string The URL or empty string if no permissions
     * @throws \coding_exception
     */
    private function get_plagiarism_url(submission $submission, plagiarism_flag|bool $flag): string {
        if (!$flag) {
            return $this->get_new_flag_url($submission);
        }
        return $this->ability->can('edit', $flag) ?
            router::instance()->get_path('edit plagiarism flag', ['flag' => $flag], false, false) :
            '';
    }

    /**
     * Get URL for creating a new plagiarism flag.
     *
     * @param submission $submission The submission object
     * @return string The URL or empty string if no permissions
     */
    private function get_new_flag_url(submission $submission): string {
        $params = ['courseworkid' => $this->coursework->id, 'submissionid' => $submission->id];
        $newflag = plagiarism_flag::build($params);
        return $this->ability->can('new', $newflag) ?
            router::instance()->get_path('new plagiarism flag', ['submission' => $submission], false, false) :
            '';
    }

    /**
     * Set extension parameters.
     *
     * @param stdClass $data
     * @param grading_table_row_base $rowsbase
     * @return void
     */
    protected function set_personal_deadline_data(stdClass $data, grading_table_row_base $rowsbase): void {
        // We avoid using $this->ability->can() in this context as it creates multiple DB queries per row.
        if (
            !has_capability('mod/coursework:editpersonaldeadline', $this->coursework->get_context())
            ||
            !$rowsbase->get_coursework()->personal_deadlines_enabled()
        ) {
            return;
        }
        $personaldeadlineobject = $rowsbase->get_personal_deadline();
        if ($personaldeadlineobject) {
            $data->personaldeadline = (object)[
                'date' => $personaldeadlineobject->personal_deadline,
                'time' => userdate($personaldeadlineobject->personal_deadline, '%d-%m-%Y %I:%M', fixday: false),
                'time_content' => userdate(
                    $personaldeadlineobject->personal_deadline, get_string('strftimedaydatetime', 'langconfig'), fixday: false
                ),
                'exists' => $personaldeadlineobject->personal_deadline > 0 ? 1 : 0,
                // Disable deadline edit link if extension also exists (as ability class will throw error if edit attempted).
                'is_editable' => !$rowsbase->get_extension(),
                'deadlineid' => $personaldeadlineobject->id,
            ];
        } else {
            $data->personaldeadline = (object)['exists' => false, 'is_editable' => true];
        }
    }

    /**
     * Check if a new submission can be made
     *
     * @param grading_table_row_base $rowsbase
     * @return bool
     */
    private function can_submit_new(grading_table_row_base $rowsbase): bool {
        if (!has_capability('mod/coursework:submitonbehalfof', $this->coursework->get_context())) {
            return false;
        }

        $coursework = $rowsbase->get_coursework();
        if (!$coursework->has_deadline()) {
            return true;
        }

        if ($coursework->allow_late_submissions()) {
            return true;
        }

        if (($rowsbase->get_personal_deadline_time() || $rowsbase->get_coursework()->get_deadline()) >= $this->clock->time()) {
            return true;
        }

        if ($rowsbase->has_extension() && $rowsbase->get_extension()->extended_deadline > $this->clock->time()) {
            return true;
        }

        return false;
    }
}
