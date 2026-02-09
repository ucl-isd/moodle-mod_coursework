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

namespace mod_coursework;

use coding_exception;
use core\exception\moodle_exception;
use html_writer;
use mod_coursework\allocation\allocatable;
use mod_coursework\models\coursework;
use mod_coursework\models\deadline_extension;
use mod_coursework\models\feedback;
use mod_coursework\models\group;
use mod_coursework\models\personaldeadline;
use mod_coursework\models\plagiarism_flag;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use moodle_url;

/**
 * Refactoring the grading table to clarify the logic. There will be two subclasses of this -
 * one for single row tables and one for multi-row tables. These classes contain all the business
 * logic relating the what ought to be rendered. The renderer methods then decide how the decision
 * will be translated into a page.
 */
class grading_table_row_base implements user_row {
    /**
     * Using this as a delegate
     * @var submission
     */
    protected $submission;

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @var allocatable user record
     */
    protected $allocatable;

    /**
     * @var deadline_extension|null $extension
     */
    protected ?deadline_extension $extension;

    /**
     * @var ?personaldeadline
     */
    protected ?personaldeadline $personaldeadline;

    /**
     * Array of objects representing submission files this row's user (DB query results)
     * @var ?object[]
     */
    protected ?array $submissionfiles;

    /**
     * Constructor
     *
     * @param coursework $coursework $coursework
     * @param user|group $user
     * @param deadline_extension|null $extension
     * @param personaldeadline|null $personaldeadline
     * @param array $submissionfiles
     */
    public function __construct(coursework $coursework, user|group $user, ?deadline_extension $extension, ?personaldeadline $personaldeadline, array $submissionfiles) {
        $this->coursework = $coursework;
        $this->allocatable = $user;
        $this->extension = $extension;
        $this->personaldeadline = $personaldeadline;
        $this->submissionfiles = $submissionfiles;
    }

    /**
     * Gets the grade agreed by the markers based ont he component marks. Not capped!
     * Chained getter for loose coupling.
     *
     * @return int
     */
    public function get_final_grade() {

        $submission = $this->get_submission();

        if (!$submission) {
            return '';
        }
        return $submission->get_final_grade();
    }

    /**
     * @return bool
     */
    public function is_published() {
        if (!$this->get_submission()) {
            return false;
        }
        return $this->get_submission()->is_published();
    }

    /**
     * Can the current user see the row user's name?
     * @return bool
     * @throws coding_exception
     */
    public function can_see_user_name(): bool {
        if (!$this->get_coursework()->blindmarking || $this->is_published()) {
            return true;
        }
        return has_capability(
            'mod/coursework:viewanonymous',
            $this->get_coursework()->get_context()
        );
    }

    /**
     * Avoid calling repeatedly as it results in DB queries.
     * Will return the username if permissions allow, otherwise, an anonymous placeholder. Can't delegate to the similar
     * submission::get_user_name() function as there may not be a submission.
     *
     * @param bool $link
     * @return string
     * @throws moodle_exception
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function get_user_name($link = false) {

        global $DB;

        $viewanonymous = has_capability('mod/coursework:viewanonymous', $this->get_coursework()->get_context());
        if (!$this->get_coursework()->blindmarking || $viewanonymous || $this->is_published()) {
            $user = $DB->get_record('user', ['id' => $this->get_allocatable_id()]);
            $fullname = fullname($user);
            $allowed = has_capability('moodle/user:viewdetails', $this->get_coursework()->get_context());
            if ($link && $allowed) {
                $url = new moodle_url('/user/view.php', ['id' => $this->get_allocatable_id(),
                                                              'course' => $this->get_coursework()->get_course_id()]);
                return html_writer::link($url, $fullname);
            } else {
                return $fullname;
            }
        } else {
            return get_string('hidden', 'mod_coursework');
        }
    }

    /**
     * Will return the idnumber if permissions allow, otherwise, an anonymous placeholder.
     *
     * @return string
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function get_idnumber() {
        global $DB;

        $viewanonymous = has_capability('mod/coursework:viewanonymous', $this->get_coursework()->get_context());
        if (!$this->get_coursework()->blindmarking || $viewanonymous || $this->is_published()) {
            $user = $DB->get_record('user', ['id' => $this->get_allocatable_id()]);
            return $user->idnumber;
        } else {
            return get_string('hidden', 'mod_coursework');
        }
    }

    /**
     * Will return the email if permissions allow, otherwise, an anonymous placeholder.
     *
     * @return string
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function get_email() {
        global $DB;

        $viewanonymous = has_capability('mod/coursework:viewanonymous', $this->get_coursework()->get_context());
        if (!$this->get_coursework()->blindmarking || $viewanonymous || $this->is_published()) {
            $user = $DB->get_record('user', ['id' => $this->get_allocatable_id()]);
            return $user->email;
        } else {
            return '';
        }
    }

    /**
     * Returns the id of the student who's submission this is
     *
     * @return float|int|string
     */
    public function get_allocatable_id() {
        return $this->get_allocatable()->id;
    }

    /**
     * Getter for submission timesubmitted.
     *
     * @return int
     */
    public function get_time_submitted() {

        $submission = $this->get_submission();

        if (!$submission) {
            return '';
        }
        return $submission->time_submitted();
    }

    /**
     * Getter for personal deadline time
     *
     * @return ?int
     */
    public function get_personaldeadline_time(): ?int {
        return $this->personaldeadline->personaldeadline ?? null;
    }

    /**
     * Returns the hash used to name files anonymously for this user/coursework combination
     */
    public function get_filename_hash() {
        return $this->get_coursework()->get_username_hash($this->get_allocatable_id());
    }

    /**
     * Returns the id of the coursework instance.
     *
     * @return int
     */
    public function get_courseworkid() {
        return $this->get_coursework()->id;
    }

    /**
     * Returns the id of the coursework instance.
     *
     * @return coursework
     */
    public function get_coursework() {
        return $this->coursework;
    }

    /**
     * @return submission
     * @throws coding_exception
     */
    public function get_submission() {

        if (!isset($this->submission)) {
            $allocatableid = $this->get_allocatable()->id();
            $allocatabletype = $this->get_allocatable()->type();
            $this->submission = submission::get_for_allocatable($this->get_courseworkid(), $allocatableid, $allocatabletype);
        }

        return $this->submission;
    }

    /**
     * @return plagiarism_flag
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function get_plagiarism_flag() {

        $submission = $this->get_submission();
        $params = [
            'submissionid' => $submission->id,
        ];

        return plagiarism_flag::find($params);
    }

    /**
     * Chained getter to prevent tight coupling.
     *
     * @return object[] array of objects - one for each of this row's user's submission files.
     */
    public function get_submission_files(): array {
        if (!$this->get_submission()) {
            return [];
        }
        return $this->submissionfiles ?? [];
    }

    /**
     * Chained getter to prevent tight coupling.
     *
     * @return int
     */
    public function get_course_module_id() {
        return $this->get_coursework()->get_coursemodule_id();
    }

    /**
     * @return int
     */
    public function get_submission_id() {
        if (!$this->get_submission()) {
            return 0;
        }
        return $this->get_submission()->id;
    }

    /**
     * Tells us if anything has been submitted yet.
     *
     * @return bool
     */
    public function has_submission() {
        $submission = $this->get_submission();
        return !empty($submission);
    }

    /**
     * Checks to see whether we should show the current user who this student is.
     */
    public function can_view_username() {

        if (has_capability('mod/coursework:viewanonymous', $this->get_coursework()->get_context())) {
            return true;
        }

        if ($this->get_coursework()->blindmarking) {
            return false;
        }

        return true;
    }

    /**
     * @return allocatable
     */
    public function get_allocatable() {
        return $this->allocatable;
    }

    /**
     * Tells us whether this submission has any feedback
     *
     * @return false|feedback[]
     */
    public function has_feedback() {
        if (!$this->get_submission()) {
            return false;
        }
        return $this->get_submission()->get_assessor_feedbacks();
    }

    /**
     * @return feedback
     * @throws \dml_exception
     */
    public function get_single_feedback() {
        return $this->get_submission()->get_assessor_feedback_by_stage('assessor_1');
    }

    /**
     * Check if the extension is given to this row
     *
     * @return bool
     */

    public function has_extension(): bool {
        return (bool)$this->extension;
    }

    /**
     * Getter for row extension
     *
     * @return ?deadline_extension

     */
    public function get_extension(): ?deadline_extension {
        if (empty($this->coursework->extensions_enabled())) {
            return null;
        }

        return $this->extension;
    }

    /**
     * Getter for row personal deadline
     *
     * @return ?personaldeadline

     */
    public function get_personaldeadline(): ?personaldeadline {
        if (empty($this->coursework->personaldeadlineenabled)) {
            return null;
        }

        return $this->personaldeadline;
    }
}
