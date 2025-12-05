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
 * Data provider for submission cell in grading report.
 *
 * @package    mod_coursework
 * @copyright  2025 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */

namespace mod_coursework\render_helpers\grading_report\data;

use mod_coursework\grading_table_row_base;
use mod_coursework\models\coursework;
use mod_coursework\models\submission;
use moodle_url;
use stdClass;
use stored_file;

/**
 * Class submission_cell_data provides data for submission cell in tr template.
 */
class submission_cell_data extends cell_data_base {
    /**
     * Get the data for the submission cell.
     *
     * @param grading_table_row_base $rowsbase
     * @return stdClass|null The data object for template rendering.
     */
    public function get_table_cell_data(grading_table_row_base $rowsbase): ?stdClass {
        $data = new stdClass();

        if ($rowsbase->has_submission() && $this->ability->can('show', $rowsbase->get_submission())) {
            $this->add_submission_data($data, $rowsbase);
        }

        $this->add_extension_data($data, $rowsbase);
        $data->duedate = null;
        if ($rowsbase->get_coursework()->get_deadline()) {
            $data->duedate = $rowsbase->get_coursework()->get_deadline();
        }
        if ($rowsbase->get_coursework()->personaldeadlines_enabled()) {
            $this->add_personaldeadline_data($data, $rowsbase);
        }
        return $data;
    }

    /**
     * Add submission related data to the data object.
     *
     * @param stdClass $data Data object to add to.
     * @param grading_table_row_base $rowobject Row object containing submission.
     */
    protected function add_submission_data(stdClass $data, grading_table_row_base $rowobject): void {
        $submission = $rowobject->get_submission();
        $data->id = $submission->id;
        $data->datemodified = $submission->time_submitted();
        $data->submissiondata = new stdClass();
        $data->submissiondata->files = $submission ? $rowobject->get_submission_files() : [];
        $data->submissiondata->finalised = $submission->is_finalised();
        $data->submissiondata->released = $submission->is_published();

        $this->add_plagiarism_data($data->submissiondata, $submission);
        $this->add_late_submission_data($data->submissiondata, $submission);
    }

    /**
     * Add plagiarism flag data to submission data.
     *
     * @param stdClass $submissiondata Data object to add plagiarism data to.
     * @param submission $submission The submission to check.
     */
    protected function add_plagiarism_data(stdClass $submissiondata, submission $submission): void {
        if (!$this->coursework->plagiarism_flagging_enbled()) {
            return;
        }
        $submissiondata->flaggedplagiarism = $this->get_flagged_plagiarism_status($submission);
    }

    /**
     * Add late submission data to submission data.
     *
     * @param stdClass $submissiondata Data object to add late submission data to.
     * @param submission $submission The submission to check.
     * @throws \coding_exception
     */
    protected function add_late_submission_data(stdClass $submissiondata, submission $submission): void {
        $submissiondata->submittedlate = ($submission->was_late() !== false);
    }

    /**
     * Add extension data to the data object.
     *
     * @param stdClass $data Data object to add extension data to.
     * @param grading_table_row_base $rowobject Row object to get extension from.
     */
    protected function add_extension_data(stdClass $data, grading_table_row_base $rowobject): void {
        $extension = $rowobject->get_extension();
        // No extension to show.
        if (!$extension) {
            return;
        }
        // User does not have permission to view extension.
        if (!$this->ability->can('show', $extension)) {
            return;
        }
        $data->extensiongranted = true;
        $data->extensiondeadline = $extension->extended_deadline;
    }

    /**
     * Add extension data to the data object.
     *
     * @param stdClass $data Data object to add extension data to.
     * @param grading_table_row_base $rowobject Row object to get extension from.
     */
    protected function add_personaldeadline_data(stdClass $data, grading_table_row_base $rowobject): void {
        if ($personaldeadline = $rowobject->get_personaldeadline_time()) {
            $data->personaldeadline = $personaldeadline;
        }
    }
}
