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

namespace mod_coursework\export\csv\cells;
use coding_exception;
use core_text;
use gradingform_rubric_instance;
use mod_coursework\grade_judge;
use mod_coursework\models\coursework;
use mod_coursework\models\deadline_extension;
use mod_coursework\models\plagiarism_flag;
use mod_coursework\models\submission;

/**
 * Class cell_base
 */
abstract class cell_base implements cell_interface {
    /**
     * @var coursework
     */
    protected $coursework;
    protected $dateformat;
    protected $stages;
    protected $extension;
    protected $plagiarismflag;

    /**
     * @param $coursework
     */
    public function __construct($coursework) {

        $this->coursework = new coursework($coursework->id);
        $this->dateformat = '%a, %d %b %Y, %H:%M';
        $this->stages = $this->coursework->get_max_markers();
        $this->extension = new deadline_extension();
        $this->plagiarismflag = new plagiarism_flag();
    }

    /**
     * Function to check if a user can see real names/usernames even if blind marking is enabled
     * @return bool
     * @throws coding_exception
     */
    public function can_view_hidden() {
        return
            !$this->coursework->blindmarking
            ||
            has_any_capability(['mod/coursework:viewanonymous', 'mod/coursework:canexportfinalgrades'], $this->coursework->get_context());
    }

    /**
     * Function to check if the student was given an extension
     * @param $student
     * @return bool
     */
    public function extension_exists($student) {
        if (empty($this->coursework->extensions_enabled())) {
            return false;
        }

        $extension = $this->extension->get_extension_for_student($student, $this->coursework);

        return ($this->coursework->extensions_enabled() && !empty($extension));
    }

    /**
     * Function to get student's extension date
     * @param $student
     * @return string
     */
    public function get_extension_date_for_csv($student) {
        if (empty($this->coursework->extensions_enabled())) {
            return '';
        }

        $extension = $this->extension->get_extension_for_student($student, $this->coursework);

        return userdate($extension->extended_deadline, $this->dateformat);
    }

    /**
     * Function to get extra information about student's extension
     * @param $student
     * @return string
     */
    public function get_extension_extra_info_for_csv($student) {
        if (empty($this->coursework->extensions_enabled())) {
            return '';
        }

        $extension = $this->extension->get_extension_for_student($student, $this->coursework);

        return self::clean_cell($extension->extrainformationtext);
    }

    /**
     * Function to get student's extension pre-defined reason
     * @param $student
     * @return string
     */
    public function get_extension_reason_for_csv($student) {
        if (empty($this->coursework->extensions_enabled())) {
            return '';
        }

        $extension = $this->extension->get_extension_for_student($student, $this->coursework);
        $extensionreasons = $this->get_extension_predefined_reasons();

        return (!empty($extensionreasons[$extension->pre_defined_reason])) ?
            self::clean_cell($extensionreasons[$extension->pre_defined_reason]) : "";
    }

    /**
     * Function to get all pre-defined extension reasons
     * @return array
     */
    public function get_extension_predefined_reasons() {
        if (empty($this->coursework->extensions_enabled())) {
            return [];
        }

        return $this->coursework->extension_reasons();
    }

    /**
     * Function to check if the plagiarism has been flagged for the given submission
     * @param $submission
     * @return bool
     */
    public function plagiarism_flagged($submission) {

        $flag = $this->plagiarismflag->get_plagiarism_flag($submission);

        return ($this->coursework->plagiarism_flagging_enabled() && !empty($flag));
    }

    /**
     * Function to get student's plagiarism status
     * @param $submission
     * @return string
     * @throws coding_exception
     */
    public function get_plagiarism_flag_status_for_csv($submission) {

        $flag = $this->plagiarismflag->get_plagiarism_flag($submission);

        return get_string('plagiarism_' . $flag->status, 'mod_coursework');
    }

    /**
     * Function to get comment about student's plagiarism status
     * @param $submission
     * @return string
     */
    public function get_plagiarism_flag_comment_for_csv($submission) {

        $flag = $this->plagiarismflag->get_plagiarism_flag($submission);

        return self::clean_cell($flag->comment);
    }

    /**
     * Function to get a grade that should be displayed
     * @param $grade
     * @return null
     */
    public function get_actual_grade($grade) {

        $judge = new grade_judge($this->coursework);

        return $judge->grade_to_display($grade);
    }

    /**
     * Function to get assessor's full name
     * @param $assessorid
     * @return string
     * @throws \dml_exception
     */
    public function get_assessor_name($assessorid) {
        global $DB;

        $assessor = $DB->get_record('user', ['id' => $assessorid], 'firstname, lastname');

        return $assessor->lastname . ' ' . $assessor->firstname;
    }

    /**
     * Function to get assessor's username
     * @param $assessorid
     * @return string
     * @throws \dml_exception
     */
    public function get_assessor_username($assessorid) {
        global $DB;

        $assessor = $DB->get_record('user', ['id' => $assessorid], 'username');

        return $assessor->username;
    }

    /**
     * Function to get a message if submission was made withihn the deadline
     * @param submission $submission
     * @return \lang_string|string
     * @throws coding_exception
     */
    protected function submission_time($submission) {

        if ($submission->was_late()) {
            $time = get_string('late', 'coursework');
        } else {
            $time = get_string('ontime', 'mod_coursework');
        }

        return $time;
    }

    /**
     * Function to get stageidentifier for the current assessor
     * @param $submission
     * @param $student
     * @return string
     * @throws \dml_exception
     */
    public function get_stageidentifier_for_assessor($submission, $student) {
        global $DB, $USER;

        $stageidentifier = '';
        if ($this->coursework->allocation_enabled()) {
            $stageidentifier = $this->coursework->get_assessors_stageidentifier($student->id, $USER->id);
        } else if ($this->coursework->get_max_markers() > 1) {
            // get existing feedback

            $sql = "SELECT * FROM {coursework_feedbacks}
                  WHERE submissionid= $submission->id
                  AND assessorid = $USER->id
                  AND stageidentifier <> 'final_agreed_1'";

            $feedback = $DB->get_record_sql($sql);
            if ($feedback) {
                $stageidentifier = $feedback->stageidentifier;
            }
        } else { // 1 marker only
            $stageidentifier = 'assessor_1';
        }

        return $stageidentifier;
    }

    /**
     * Function to validate cell for the file upload
     * @return mixed
     */
    public function validate_cell($value, $submissions, $stageidentifier = '', $uploadedgradecells = []) {
        return true;
    }

    /**
     * @param $grade
     * @param $gradedata
     * @throws \dml_exception
     */
    public function get_rubric_scores_gradedata($grade, &$gradedata) {

        if ($grade) {
            $controller = $this->coursework->get_advanced_grading_active_controller();
            $gradinginstance = $controller->get_or_create_instance(0, $grade->assessorid, $grade->id);
            /**
             * @var gradingform_rubric_instance $grade
             */
            $rubricmarks = $gradinginstance->get_rubric_filling();

            foreach ($rubricmarks['criteria'] as $id => $record) {
                $gradedata[] = $controller->get_definition()->rubric_criteria[$id]['levels'][$record['levelid']]['score'];
                $gradedata[] = $record['remark'];
            }
        } else {
            $criterias = $this->coursework->get_rubric_criteria();
            foreach ($criterias as $unused) { // if no marks we need same amount of empty holders
                $gradedata[] = '';
                $gradedata[] = '';
            }
        }
    }

    public static function clean_cell($contents) {
        return trim(core_text::specialtoascii(html_to_text($contents, 0)));
    }
}
