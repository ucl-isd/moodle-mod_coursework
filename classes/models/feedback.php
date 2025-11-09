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

namespace mod_coursework\models;

use AllowDynamicProperties;
use cache;
use coding_exception;
use context;
use dml_exception;
use mod_coursework\allocation\allocatable;
use mod_coursework\feedback_files;
use mod_coursework\framework\table_base;
use mod_coursework\stages\assessor;
use mod_coursework\stages\base as stage_base;
use mod_coursework\stages\final_agreed;
use stdClass;

/**
 * Class to represent a single item of feedback that a tutor will provide for a submission.
 *
 * @property mixed stageidentifier
 * @property int feedback_manager
 */
#[AllowDynamicProperties]
class feedback extends table_base {
    /**
     * @var string
     */
    protected static $tablename = 'coursework_feedbacks';

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $submissionid;

    /**
     * @var int
     */
    public $assessorid;

    /**
     * @var int
     */
    public $timecreated;

    /**
     * @var int
     */
    public $timemodified;

    /**
     * @var string
     */
    public $grade;

    /**
     * @var string
     */
    public $feedbackcomment;

    /**
     * @var int
     */
    public $feedbackcommentformat;

    /**
     * @var int
     */
    public $timepublished;

    /**
     * @var int
     */
    public $lasteditedbyuser;

    /**
     * @var int
     */
    public $feedbackfiles;

    /**
     * @var string
     */
    public $firstname;

    /**
     * @var string
     */
    public $lastname;

    /**
     * @var int
     */
    public $courseworkid;

    /**
     * @var stdClass hold all of the custom form data associated with this feedback.
     * Needs further processing. {@see feedback->set_feedback_data()}
     */
    public $formdata;

    /**
     * @var stdClass
     */
    public $student;

    /**
     * @var submission
     */
    public $submission;

    /**
     * @var int 1 = it is a final grade, 0 is default in the DB. Used only for multiple marked things.
     */
    public $isfinalgrade;

    /**
     * @var int 1 = it is a feedback left by a moderator, 0 (default) means it's not.
     */
    public $ismoderation;

    /**
     * @var int the id of the entry (in the ULCC form library) attached to this feedback
     */
    public $entryid;

    /**
     * @var int tells us what number this feedback was so we can easily link the feedback table to submissions for
     * generating reports.
     */
    public $markernumber;

    /**
     * This allows up to loop through the properties of the object which correspond to fields
     * in the DB table, ignoring the others.
     * @var array
     */
    protected $fields = [
        'id',
        'submissionid',
        'timecreated',
        'timemodified',
        'assessorid',
        'grade',
        'feedbackcomment',
        'feedbackcommentformat',
        'timepublished',
        'lasteditedbyuser',
        'isfinalgrade',
        'ismoderation',
        'entry_id',
        'markernumber',
    ];

    /**
     * @var stdClass
     */
    public $assessor;

    /**
     * @var bool Tells renderer whether to show the comment
     */
    private $showcomment = true;

    /**
     * @var bool Tells renderer whether to show the comment
     */
    private $showgrade = true;

    /**
     * This function is used for student view, it determines if assessors' names should be displayed or should be hidden
     * @return string assessor's name
     */
    public function display_assessor_name() {

        // check if assessor's name in this CW is set to hidden.
        if ($this->is_assessor_anonymity_enabled()) {
            $assessorname = '';
        } else {
            $assessorname = $this->get_assesor_username();
        }

        return $assessorname;
    }

    /**
     * Real name for display. Allows us to defer the DB call to retrieve first and last name
     * in case we don't need it.
     */
    public function get_assesor_username() {
        if (!$this->firstname && !empty($this->lasteditedbyuser)) {
            $this->assessor = user::get_object($this->lasteditedbyuser);
        }

        return $this->assessor->name();
    }

    public function get_assessor_id() {
        return $this->assessor->id;
    }

    /**
     * @return string
     */
    public function get_assessor_stage_no() {
        $no = '';
        if (str_starts_with($this->stageidentifier, 'assessor_')) {
            $no = substr($this->stageidentifier, -1);
        }
        return $no;
    }

    public function get_feedbacks_assessorid() {
        return $this->assessorid;
    }

    /**
     * Chained getter for loose coupling.
     *
     * @return coursework
     * @throws \core\exception\coding_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_coursework() {
        return $this->get_submission()->get_coursework();
    }

    /**
     * Chained getter for loose coupling.
     *
     * @return int
     */
    public function get_coursemodule_id() {
        return $this->get_submission()->get_course_module_id();
    }

    /**
     * Returns a feedback instance
     * @param submission $submission
     * @param int $isfinalgrade do we want the final grade (in case this assessor did a component
     * one and a final one
     * @param int $assessorid
     * @return feedback|null
     * @throws dml_exception
     * @todo get rid of this.
     */
    public static function get_teacher_feedback(
        submission $submission,
        $isfinalgrade = 0,
        $assessorid = 0
    ) {
        global $DB;

        $params = ['submissionid' => $submission->id];
        // If it's single marker, we just get the only one.
        if ($assessorid && $submission->has_multiple_markers()) {
            $params['assessorid'] = $assessorid;
            $params['ismoderation'] = 0;
        }

        $params['isfinalgrade'] = $isfinalgrade ? 1 : 0;

        // Should only ever be one that has the particular combination of these three options.
        $feedback = $DB->get_record('coursework_feedbacks', $params);

        if (is_object($feedback)) {
            return new feedback($feedback);
        }
        return null;
    }

    /**
     * Check if assessor is allocated to the user in this stage
     * @return bool
     */
    public function is_assessor_allocated() {
        return $this->get_stage()->assessor_has_allocation($this->get_allocatable());
    }

    /**
     * @param $contextid
     * @return void
     * @throws coding_exception
     */
    public function set_feedback_files($contextid) {

        if (is_array($this->feedbackfiles)) {
            return;
        }

        if (!$contextid) {
            return;
        }

        $fs = get_file_storage();
        $this->feedbackfiles = $fs->get_area_files(
            $contextid,
            'mod_coursework',
            'feedback',
            $this->id,
            "id",
            false
        );
    }

    /**
     * Fetches all the files for this feedback and returns them as an array
     *
     * @return false|feedback_files
     */
    public function get_feedback_files() {

        $this->set_feedback_files($this->get_context_id());
        if ($this->feedbackfiles != null) {
            $this->feedback_files = new feedback_files($this->feedbackfiles, $this);
            return $this->feedback_files;
        }

        return false;
    }

    /**
     * @return void
     * @throws dml_exception
     */
    public function set_student() {
        global $DB;

        if (!$this->submissionid) {
            return;
        }
        $sql = "SELECT u.id,
                       u.id AS userid,
                       u.firstname,
                       u.lastname
                  FROM {user} u
            INNER JOIN {coursework_submissions} s
                    ON u.id = s.userid
                 WHERE s.id = :sid
                    ";
        $params = ['sid' => $this->submissionid];
        $this->student = $DB->get_record_sql($sql, $params);
    }

    /**
     * Makes sure we have the correct user recorded as having edited it. Timemodified is dealt
     * with by the parent. We also need to make sure than when a new feedback is saved, we end up getting the marker number
     * which is next up from the last one.
     */
    public function pre_save_hook() {

        global $USER, $DB;
        if (!isset($this->lasteditedbyuser)) {
            $this->lasteditedbyuser = $USER->id;
        }

        if ($this->ismoderation == 0 && $this->isfinalgrade == 0 && empty($this->id)) {
            $sql = 'SELECT MAX(feedbacks.markernumber)
                      FROM {coursework_feedbacks} feedbacks
                     WHERE feedbacks.submissionid = :subid';
            $params = ['subid' => $this->submissionid];
            $maxmarkernumber = $DB->get_field_sql($sql, $params);

            if (empty($maxmarkernumber)) {
                $maxmarkernumber = 0;
            }

            $this->markernumber = $maxmarkernumber + 1;
        }
    }

    /**
     * Tells us whether the feedback is the one holding the final agreed grade for a multiple marked
     * coursework.
     *
     * @return bool
     */
    public function is_agreed_grade() {
        $identifier = $this->get_stage()->identifier();
        if ($this->get_coursework()->has_multiple_markers()) {
            return $identifier == final_agreed::STAGE_FINAL_AGREED_1;
        } else {
            return $identifier == assessor::STAGE_ASSESSOR_1;
        }
    }

    /**
     * Tells us whether this is a feedback added by a moderator.
     *
     * @return bool
     */
    public function is_moderation() {
        return $this->get_stage()->identifier() == 'moderator_1';
    }

    /**
     * Chained getter.
     *
     * @return int
     */
    public function get_courseworkid() {
        return $this->get_coursework()->id;
    }

    /**
     * Chained getter.
     *
     * @return context
     */
    public function get_context() {
        return $this->get_coursework()->get_context();
    }

    /**
     * Is this feedback one of the component grades in a multiple marking scenario?
     *
     */
    public function is_initial_assessor_feedback() {
        return $this->get_stage()->is_initial_assesor_stage();
    }

    /**
     * Has the general deadline for individual feedback passed?
     *
     * @return bool
     */
    public function individual_deadline_passed() {
        return time() > $this->get_coursework()->get_individual_feedback_deadline();
    }

    /**
     * Tells us what state the submission is in e.g. submission::PUBLISHED
     * @return int
     */
    protected function get_submission_state() {
        return $this->get_submission()->get_state();
    }

    /**
     * Memoized getter
     *
     * @return bool|submission
     * @throws \core\exception\coding_exception
     * @throws dml_exception
     */
    public function get_submission() {

        if (!isset($this->submission) && !empty($this->submissionid)) {
            global $DB;
            $courseworkid = $this->courseworkid
                ?? $DB->get_field(submission::$tablename, 'courseworkid', ['id' => $this->submissionid]);
            if (!$courseworkid) {
                return false;
            }
            if (!isset(submission::$pool[$courseworkid])) {
                submission::fill_pool_coursework($courseworkid);
            }
            $this->submission = submission::$pool[$courseworkid]['id'][$this->submissionid] ?? false;
        }

        return $this->submission;
    }

    /**
     * Getter for the feedback grade.
     *
     * @return string
     */
    public function get_grade() {
        return $this->grade;
    }

    /**
     * Lets us specify which assessor is dealing with this. Only used after we instantiate without a DB record.
     *
     * @param int $assessorid
     */
    public function set_assessor_id($assessorid) {
        $this->assessorid = $assessorid;
    }

    /**
     * @param $userid
     * @return int
     */
    public function get_user_deadline($userid) {
        return $this->get_coursework()->get_user_deadline($userid);
    }

    /**
     * @return int
     */
    private function get_context_id() {
        return $this->get_submission()->get_context_id();
    }

    /**
     * This takes various settings from the visibility grid, depending on what type of feedback this is.
     * @todo Needs replacing with some sort of polymorphism
     *
     * @param bool $show
     */
    public function set_allowed_to_show_grade($show) {
        $this->showgrade = $show;
    }

    /**
     * @param bool $show
     */
    public function set_allowed_to_show_comment($show) {
        $this->showcomment = $show;
    }

    /**
     * @return bool
     */
    public function is_allowed_to_show_grade() {
        return $this->showgrade;
    }

    /**
     * @return bool
     */
    public function is_allowed_to_show_comment() {
        return $this->showcomment;
    }

    /**
     * @return user
     */
    public function assessor() {
        return user::get_object($this->assessorid);
    }

    /**
     * @return stage_base
     */
    public function get_stage() {
        return $this->get_coursework()->get_stage($this->stageidentifier);
    }

    /**
     * @return allocatable
     */
    public function get_allocatable() {
        return $this->get_submission()->get_allocatable();
    }

    public function is_assessor_anonymity_enabled() {
        return $this->get_coursework()->assessoranonymity;
    }

    /**
     * Does the current grading stage for the supplied coursework and feedback (may be null)
     * use advanced grading or simple grading?
     *
     * @param coursework $coursework
     * @param ?feedback $feedback may be null if a new feedback object is being created.
     * @return bool False if the current stage for the supplied coursework and feedback uses simple
     * grading (for example, 55/100), true if it uses a grading form or rubric.
     */
    public static function is_stage_using_advanced_grading(coursework $coursework, ?feedback $feedback) {
        return $coursework->is_using_advanced_grading()
            && (
                $coursework->finalstagegrading == 0 ||
                // If $coursework->finalstagegrading == 1 then $feedback must now be initialised.
                ($coursework->finalstagegrading == 1 && $feedback->stageidentifier != final_agreed::STAGE_FINAL_AGREED_1)
            );
    }

    /**
     * cache array
     *
     * @var
     */
    public static $pool;

    /**
     *
     * @param int $courseworkid
     * @throws dml_exception
     */
    public static function fill_pool_coursework($courseworkid) {
        global $DB;
        if (isset(self::$pool[$courseworkid])) {
            return;
        }
        if (submission::$pool[$courseworkid] ?? null) {
            $submissionids = submission::$pool[$courseworkid]['id'];
        } else {
            $submissionids = $DB->get_records(submission::$tablename, ['courseworkid' => $courseworkid], '', 'id');
        }
        self::fill_pool_submissions($courseworkid, array_keys($submissionids));
    }

    /**
     * @param int $courseworkid
     * @param $submissionids
     * @throws \core\exception\coding_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function fill_pool_submissions($courseworkid, $submissionids) {
        global $DB;

        if (isset(self::$pool[$courseworkid])) {
            return;
        }

        $key = self::$tablename;
        $cache = cache::make('mod_coursework', 'courseworkdata', ['id' => $courseworkid]);

        $data = $cache->get($key);
        if ($data === false) {
            $data = [
                'id' => [],
                'submissionid-stageidentifier' => [],
                'submissionid-stageidentifier_index' => [],
                'submissionid-finalised' => [],
                'submissionid-ismoderation-isfinalgrade-stageidentifier' => [],
                'submissionid-assessorid' => [],
                'submissionid' => [],
            ];
            if ($submissionids) {
                [$submissionidsql, $submissionidparams] = $DB->get_in_or_equal($submissionids, SQL_PARAMS_NAMED);
                $feedbacks = $DB->get_records_sql("SELECT * FROM {coursework_feedbacks} WHERE submissionid $submissionidsql", $submissionidparams);
                foreach ($feedbacks as $record) {
                    $object = new self($record);
                    $stageidentifier = $record->stageidentifier;
                    $stageidentifierindex = ($stageidentifier == final_agreed::STAGE_FINAL_AGREED_1) ? $stageidentifier : 'others';
                    $data['id'][$record->id] = $object;
                    $data['submissionid-stageidentifier'][$record->submissionid . '-' . $stageidentifier][] = $object;
                    $data['submissionid-stageidentifier_index'][$record->submissionid . '-' . $stageidentifierindex][] = $object;
                    $data['submissionid-finalised'][$record->submissionid . '-' . $record->finalised][] = $object;
                    $data['submissionid-ismoderation-isfinalgrade-stageidentifier'][$record->submissionid . '-' . $record->ismoderation . '-' . $record->isfinalgrade . '-' . $stageidentifier][] = $object;
                    $data['submissionid-assessorid'][$record->submissionid . '-' . $record->assessorid][] = $object;
                    $data['submissionid'][$record->submissionid][] = $object;
                }
            }
            $cache->set($key, $data);
        }
        self::$pool[$courseworkid] = $data;
    }

    /**
     *
     * @param int $courseworkid
     * @param $key
     * @param $params
     * @return self|bool
     * @throws dml_exception
     */
    public static function get_object($courseworkid, $key, $params) {
        if (!isset(self::$pool[$courseworkid])) {
            self::fill_pool_coursework($courseworkid);
        }
        $valuekey = implode('-', $params);
        return self::$pool[$courseworkid][$key][$valuekey][0] ?? false;
    }

    /**
     *
     */
    protected function post_save_hook() {
        $submission = $this->get_submission();
        if ($submission && $submission->courseworkid ?? false) {
            self::remove_cache($submission->courseworkid);
        }
    }

    /**
     *
     */
    protected function after_destroy() {
        $courseworkid = $this->get_submission()->courseworkid;
        self::remove_cache($courseworkid);
    }
}
