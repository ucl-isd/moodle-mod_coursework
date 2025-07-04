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
use mod_coursework\models\deadline_extension;
use mod_coursework\models\plagiarism_flag;
use mod_coursework\models\submission;
use moodle_url;
use stdClass;

/**
 * Class submission_cell_data provides data for submission cell in tr template.
 */
class submission_cell_data extends cell_data_base {
    /**
     * Date format used throughout the class.
     * %d - Day of month (1-31)
     * %b - Abbreviated month name in lowercase
     * %I - Hour in 12-hour format (01-12)
     * %M - Minutes (00-59)
     * %P - am/pm indicator in lowercase
     */
    private const DATE_FORMAT = '%d %b %I:%M%P';

    /**
     * Get the data for the submission cell.
     *
     * @param grading_table_row_base $rowobject
     * @return stdClass|null The data object for template rendering.
     */
    public function get_table_cell_data(grading_table_row_base $rowobject): ?stdClass {
        $data = new stdClass();

        if ($rowobject->has_submission() && $this->ability->can('show', $rowobject->get_submission())) {
            $this->add_submission_data($data, $rowobject);
        }

        $this->add_extension_data($data, $rowobject);
        $data->duedate = null;
        if ($rowobject->get_coursework()->get_deadline()) {
            $data->duedate = userdate($rowobject->get_coursework()->get_deadline(), self::DATE_FORMAT);
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
        $data->datemodified = userdate($submission->time_submitted(), self::DATE_FORMAT);
        $data->submissiondata = new stdClass();
        $data->submissiondata->files = $this->get_submission_files_data($rowobject);

        $this->add_plagiarism_data($data->submissiondata, $submission);
        $this->add_late_submission_data($data->submissiondata, $submission);
    }

    /**
     * Get data for submission files.
     *
     * @param grading_table_row_base $rowobject Row object containing submission files.
     * @return array Array of file data objects.
     */
    protected function get_submission_files_data(grading_table_row_base $rowobject): array {
        $files = [];
        $submissionfiles = $rowobject->get_submission_files();

        if ($submissionfiles) {
            $coursework = $rowobject->get_coursework();
            foreach ($submissionfiles->get_files() as $file) {
                $files[] = $this->prepare_file_data($file, $coursework, $rowobject->get_submission()->id);
            }
        }

        return $files;
    }

    /**
     * Prepare data for a single file.
     *
     * @param \stored_file $file The file to prepare data for.
     * @param coursework $coursework The coursework instance.
     * @param int $submissionid The submission id.
     * @return stdClass File data object.
     */
    protected function prepare_file_data(\stored_file $file, coursework $coursework, int $submissionid): stdClass {
        $fileinfo = new stdClass();
        $fileinfo->filename = $file->get_filename();
        $fileinfo->url = moodle_url::make_file_url('/pluginfile.php', '/' . implode('/', [
                $file->get_contextid(),
                'mod_coursework',
                'submission',
                $submissionid,
                $file->get_filename()
            ]));

        $fileinfo->plagiarismlinks = $this->get_plagiarism_links($file, $coursework);

        return $fileinfo;
    }

    /**
     * Get plagiarism links for a file.
     *
     * @param \stored_file $file The file to get plagiarism links for.
     * @param coursework $coursework The coursework instance.
     * @return string HTML of plagiarism links.
     */
    protected function get_plagiarism_links(\stored_file $file, coursework $coursework): string {
        $params = [
            'userid' => $file->get_userid(),
            'file' => $file,
            'cmid' => $coursework->get_coursemodule_id(),
            'course' => $coursework->get_course(),
            'coursework' => $coursework->id,
            'modname' => 'coursework',
        ];

        return plagiarism_get_links($params);
    }

    /**
     * Add plagiarism flag data to submission data.
     *
     * @param stdClass $submissiondata Data object to add plagiarism data to.
     * @param submission $submission The submission to check.
     */
    protected function add_plagiarism_data(stdClass $submissiondata, submission $submission): void {
        $plagiarismflag = plagiarism_flag::get_plagiarism_flag($submission);
        $submissiondata->flaggedplagiarism = $plagiarismflag &&
            ($plagiarismflag->status == plagiarism_flag::INVESTIGATION ||
                $plagiarismflag->status == plagiarism_flag::NOTCLEARED);
    }

    /**
     * Add late submission data to submission data.
     *
     * @param stdClass $submissiondata Data object to add late submission data to.
     * @param submission $submission The submission to check.
     */
    protected function add_late_submission_data(stdClass $submissiondata, submission $submission): void {
        $submissiondata->submittedlate = $submission->is_late() &&
            (!$submission->has_extension() || !$submission->submitted_within_extension());
    }

    /**
     * Add extension data to the data object.
     *
     * @param stdClass $data Data object to add extension data to.
     * @param grading_table_row_base $rowobject Row object to get extension from.
     */
    protected function add_extension_data(stdClass $data, grading_table_row_base $rowobject): void {
        $extension = deadline_extension::find_or_build([
            'allocatableid' => $rowobject->get_allocatable()->id(),
            'allocatabletype' => $rowobject->get_allocatable()->type(),
            'courseworkid' => $rowobject->get_coursework()->id,
        ]);

        if ($extension->persisted()) {
            $data->extensiongranted = true;
            $data->extensiondeadline = userdate($extension->extended_deadline, self::DATE_FORMAT);
        }
    }
}
