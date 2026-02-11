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
use coding_exception;
use context;
use context_module;
use cache;
use core\exception\invalid_parameter_exception;
use core\exception\moodle_exception;
use core_user\fields;
use dml_exception;
use dml_missing_record_exception;
use dml_multiple_records_exception;
use exception;
use file_storage;
use html_writer;
use mod_coursework\allocation\allocatable;
use mod_coursework\event\assessable_uploaded;
use mod_coursework\framework\table_base;
use mod_coursework\grade_judge;
use mod_coursework\mailer;
use mod_coursework\stages\final_agreed;
use mod_coursework\submission_files;
use moodle_url;
use renderable;
use stdClass;
use stored_file;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/mod/coursework/lib.php');

/**
 * Student submission to a coursework.
 *
 * @property mixed allocatableid
 * @property mixed allocatabletype
 * @property mixed lastupdatedby
 * @property mixed createdby
 * @property mixed timesubmitted
 * @property mixed lastpublished
 */
#[AllowDynamicProperties]
class submission extends table_base implements renderable {
    /**
     * Cache area where objects by ID are stored.
     * @var string
     */
    const CACHE_AREA_IDS = 'submissionids';

    /**
     * Cache area where objects of this class by allocatable (user or group) ID are stored.
     * @var string
     */
    const CACHE_AREA_BY_ALLOCATABLE = 'submissionsbyallocatable';

    /**
     * Possible value for mdl_submission.finalised field.
     * Submission has never been finalised, can be finalised by changing to 1.
     */
    const FINALISED_STATUS_NOT_FINALISED = 0;


    /**
     * Possible value for mdl_submission.finalised field.
     * Submission has been finalised, can be un-finalised by changing to 2.
     */
    const FINALISED_STATUS_FINALISED = 1;

    /**
     * Possible value for mdl_submission.finalised field.
     * Submission has previously been finalised, but then manually unfinalised.
     * This status indicates that it should *not* be automatically re-finalised (e.g. by cron).
     * Instead, it must be manually re-finalised by user action (when it will go back to FINALISED_STATUS_FINALISED).
     */
    const FINALISED_STATUS_MANUALLY_UNFINALISED = 2;

    /**
     * @var string
     */
    public static $tablename = 'coursework_submissions';

    /**
     * @var int
     */
    public $courseworkid;

    /**
     * @var int
     */
    public $userid;

    /**
     * @var int author (in the case of on behalf the person who the submission is being made for) #
     * in the case of a submit on behalf of this is the person with the lowest user id number in the group
     */
    public $authorid;

    /**
     * @var int unix timestamp
     */
    public $timecreated;

    /**
     * Flag to show whether the submission has been marked as ready for grading by the student.
     * Possible value for example self::FINALISED_STATUS_FINALISED.
     * @var int
     */
    public $finalisedstatus;

    /**
     * @var
     */
    public $firstname;

    /**
     * @var
     */
    public $lastname;

    /**
     * @var int courseid
     */
    private $courseid;

    /**
     * Holds a reference to the coursework that this submission is part of. Saves passing the coursework instance around.
     * @var coursework
     */
    protected $coursework;

    /**
     * @var string 8 character MD5 of courseworkid and userid
     */
    public $hash;

    /**
     * @var int Unix timestamp, updated each time the submission is saved
     */
    public $timemodified;

    /**
     * @var array Holds all of the feedback records submitted by the tutors. Not initialised so we know
     * not to do repeated DB queries if we find an empty array as cache.
     */
    public $feedbacks;

    /**
     * @var int|submission_files holds all of the files submitted by the student. Will be the draft item id if
     * we are just getting data back from the form.
     */
    public $submissionfiles = null;

    /**
     * @var array holds the allocations records for this submission, if there are any.
     */
    protected $assessorallocations;

    /**
     * @var string An optional SRS code manually entered by the student at submission time.
     */
    public $manualsrscode;

    /**
     * Holds the DB table fields
     * @var array
     */
    protected $fields = [
        'id',
        'courseworkid',
        'userid',
        'timecreated',
        'timemodified',
        'finalisedstatus',
        'manualsrscode',
    ];

    /**
     * @var int the id of the file area for the submission form
     */
    public $submissionmanager;

    // Constants representing the state that the submission is in. Exponential to enable bitmasking
    // in future if required.

    /**
     * Nothing there yet
     */
    const NOT_SUBMITTED = 1;
    /**
     * Some files submitted, but not marked as finalised, so they can still be edited
     */
    const SUBMITTED = 2;
    /**
     * Marked by student as finalised. No more files can be added. Must happen before deadline.
     */
    const FINALISED = 4;
    /**
     * Some of the required number of markers have provided feedback
     */
    const PARTIALLY_GRADED = 8;
    /**
     * All of the required number of markers have provided feedback
     */
    const FULLY_GRADED = 16;
    /**
     * The publisher has provided an aggregate final feedback and grade ready for the gradebook.
     * This does not apply if there is only one marker
     */
    const FINAL_GRADED = 32;
    /**
     * The final grades (or only grades if just one marker) have been pushed to the gradebook
     */
    const PUBLISHED = 64;

    /**
     * @var stdClass The allocation record for this moderations (if there is one)
     */
    public $moderatorallocation;

    /**
     * @var feedback
     */
    protected $moderatorfeedback;

    /**
     * Constructor: takes a DB row from the coursework_submissions table. We don't retrieve it first
     * as we may want to overwrite with submitted data or make a new one.
     *
     * @param null $dbrecord
     * @throws dml_exception
     */
    public function __construct($dbrecord = null) {

        global $USER, $DB;

        parent::__construct($dbrecord);

        if (empty($dbrecord)) {
            // Set defaults ready to save as a new record.
            $this->userid = $USER->id;
            $this->timecreated = time();
        }

        if ($this->persisted() && !$this->firstname && !empty($this->userid)) {
            // Get the real first and last name from the user table. We use fullname($this), which needs it,
            // so we can't lazy-load.
            $user = $DB->get_record('user', ['id' => $this->userid]);
            $allnames = fields::get_name_fields();
            foreach ($allnames as $namefield) {
                $this->$namefield = $user->$namefield;
            }
        }
    }

    /**
     * Get an array of submissions that need to be finalised.
     * @param null $courseworkid
     * @return submission[]
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function not_finalised_past_deadline($courseworkid = null) {
        global $DB;

        // Get all submissions that require finalisation and have a deadline.
        $sql = 'SELECT cs.*, co.deadline
                  FROM {coursework_submissions} cs
            INNER JOIN {coursework} co
                    ON co.id = cs.courseworkid
                 WHERE co.deadline != 0
                   AND cs.finalisedstatus = :notfinalised';

        // We want only never finalised submissions (submission::FINALISED_STATUS_NOT_FINALISED).
        // We do not want finalised (submission::FINALISED_STATUS_FINALISED).
        // Nor do we want manually unfinalised, previously finalised (submission::FINALISED_STATUS_MANUALLY_UNFINALISED).
        $params = ['notfinalised' => self::FINALISED_STATUS_NOT_FINALISED];

        if (isset($courseworkid)) {
            $sql .= ' AND cs.courseworkid = :courseworkid';
            $params['courseworkid'] = $courseworkid;
        }
        $submissions = $DB->get_records_sql($sql, $params);

        foreach ($submissions as &$submission) {
            $deadline = $submission->deadline;
            $submission = static::find($submission);

            if ($submission->get_coursework()->personaldeadlines_enabled()) {
                $deadline = $submission->submission_personaldeadline();
            }

            if ($deadline < time()) {
                // if deadline passed check if extension exists
                if ($submission->has_extension()) {
                    // Check if extension is valid
                    $extension = $submission->submission_extension();
                    if ($extension && $extension->extended_deadline > time()) {
                        // Unset as it doesn't need to be autofinalise yet
                        unset($submissions[$submission->id]);
                    }
                }
            } else {
                // Unset as it doesn't need to be autofinalise yet.
                unset($submissions[$submission->id]);
            }
        }
        return $submissions;
    }

    /**
     * Returns whether or not any teacher has given any feedback for this submission. We don't want
     * to allow changes to submissions be made once feedback has been given.
     *
     * @return bool
     */
    public function has_feedback() {
        return (count($this->feedbacks) > 0);
    }

    /**
     * Gets the file options for file managers and submission save operations from the parent
     * coursework.
     *
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_file_options() {
        return $this->get_coursework()->get_file_options();
    }

    /**
     * Setter for course id.
     *
     * @param $courseid
     */
    public function set_course_id($courseid) {
        $this->courseid = $courseid;
    }

    /**
     * Gets course id from the associated coursework.
     *
     * @return int
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_course_id() {
        return $this->get_coursework()->get_course_id();
    }

    /**
     * Gets the course module id from the parent coursework.
     *
     * @return int
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_course_module_id() {
        return $this->get_coursework()->get_coursemodule_id();
    }

    /**
     * Submits the files for this submission into the events queue so that the plagiarism
     * plugins can pick them up.
     *
     * @param null $type
     * @return void
     * @throws \moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function submit_plagiarism($type = null) {

        global $CFG, $USER;

        if (empty($CFG->enableplagiarism)) {
            return;
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->get_context_id(),
            'mod_coursework',
            'submission',
            $this->id,
            "id",
            false
        );

        $params = [
            'context' => context_module::instance($this->get_coursework()->get_course_module()->id),
            'courseid' => $this->get_course_id(),
            'objectid' => $this->id,
            'relateduserid' => $this->get_author_id(),
            'userid' => $USER->id,
            'anonymous' => $this->get_coursework()->blindmarking_enabled() ? 1 : 0,
            'other' => [
                'content' => '',
                'pathnamehashes' => array_keys($files),
            ],
        ];
        $event = assessable_uploaded::create($params);
        $event->trigger();
    }

    /**
     * Returns all attached files, fetching them if not already cached.
     *
     * @param bool $reset to force the cache to be ignored.
     * @return submission_files
     */
    public function get_submission_files($reset = false) {

        if (!$reset && $this->submissionfiles instanceof submission_files) {
            return $this->submissionfiles;
        }

        if (!$this->persisted() || !$this->get_context_id()) {
            return new submission_files([], $this);
        }

        $submissionfiles = $this->get_files();

        if ($submissionfiles) {
            $this->submissionfiles = new submission_files($submissionfiles, $this);

            return $this->submissionfiles;
        }

        return new submission_files([], $this);
    }

    public function get_file_annotations() {
        global $USER;

        $fs = new file_storage();

        $annotatedfiles = [];
        $files = $fs->get_area_files($this->get_context_id(), 'mod_coursework', 'submissionannotations', $this->id);
        foreach ($files as $file) {
            if ($file->get_userid() !== $USER->id) {
                continue;
            }
            $annotatedfiles[$file->get_source()] = $file;
        }

        return $annotatedfiles;
    }

    /**
     * @return stored_file|null
     */
    public function get_first_submitted_file() {
        $files = $this->get_submission_files();
        return $files->get_first_submitted_file();
    }

    /**
     * Gets the context id from the parent coursework
     *
     * @return int
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_context_id() {
        return $this->get_coursework()->get_context()->id;
    }

    /**
     * Chained getter.
     *
     * @return context
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_context() {
        return $this->get_coursework()->get_context();
    }

    /**
     * This will return the feedbacks that have been added, but which are not the final feedback.
     *
     * @return feedback[]
     * @throws dml_exception
     */
    public function get_assessor_feedbacks() {
        if (!$this->persisted()) {
            // No submission - empty placeholder.
            return [];
        }
        $feedbacks = feedback::get_all_for_submission($this->id);
        // Get all other feedbacks whose stageidentifier is not "final_agreed_1"
        // In case of loops, we would like empty array instead of false.
        return array_filter($feedbacks, fn($f) => $f->stageidentifier != final_agreed::STAGE_FINAL_AGREED_1);
    }

    /**
     * Function to retrieve a grade for the specific stage
     * @param $stageidentifier
     * @return bool|feedback
     * @throws dml_exception
     */
    public function get_assessor_feedback_by_stage($stageidentifier) {
        $feedback = feedback::get_from_submission_and_stage($this->id, $stageidentifier);
        return $feedback && $feedback->ismoderation == 0 && $feedback->isfinalgrade == 0 ? $feedback : false;
    }

    /**
     * Function to retrieve a assessor allocated for the specific stage
     * @param $stageidentifier
     * @return ?allocation
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_assessor_allocation_by_stage($stageidentifier) {
        return allocation::get_for_allocatable_at_stage(
            $this->get_coursework()->id,
            $this->get_allocatable()->id(),
            $this->get_allocatable()->type(),
            $stageidentifier
        );
    }

    /**
     * @return bool|feedback
     * @throws dml_exception
     */
    public function get_agreed_grade() {
        if (!$this->id) {
            // No submission - empty placeholder.
            return [];
        }

        $feedback = feedback::get_from_submission_and_stage($this->id, final_agreed::STAGE_FINAL_AGREED_1);
        return $feedback && !$feedback->ismoderation && !$feedback->isfinalgrade ? $feedback : false;
    }

    /**
     * This will return the final feedback if the record exists, null if not.
     *
     * @throws exception
     * @return ?feedback
     */
    public function get_final_feedback(): ?feedback {
        if (!$this->persisted()) {
            // No submission yet - empty placeholder.
            return null;
        }

        // Temp - will be replaced with asking the appropriate stage for the feedback.
        if ($this->has_multiple_markers() && ($this->get_coursework()->sampling_enabled() == 0) || $this->sampled_feedback_exists()) {
            $identifier = 'final_agreed_1';
        } else {
            $identifier = 'assessor_1';
        }
        return feedback::get_from_submission_and_stage($this->id, $identifier);
    }

    /**
     * Gets the final grade from the final feedback record and returns it.
     *
     * @return int|bool false if there isn't one
     * @throws exception
     */
    public function get_final_grade() {

        $finalfeedback = $this->get_final_feedback();
        if ($finalfeedback) {
            return $finalfeedback->get_grade();
        }

        return false;
    }

    /**
     * Getter function of the state property.
     * The submission is on one of 6 states, depending on what activity has taken place. this will
     * both set and return the current state. This is good for the renderer so it knows how to
     * display the submission.
     * @param bool $includefilescheck only include if need to distinguish between SUBMITTED and NOT_SUBMITTED (see notes below).
     * @return int
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_state(bool $includefilescheck = false): int {

        if ($this->get_coursework() == null) {
            return -1;
        }

        if ($this->is_published()) {
            return self::PUBLISHED;
        }

        // Final grade is done.
        $hasfinalfeedback = (bool)$this->get_final_feedback();

        if ($hasfinalfeedback) {
            return self::FINAL_GRADED;
        }

        // All feedbacks in.
        $countassessorfeedbacks = count($this->get_assessor_feedbacks());
        $maxfeedbacksreached = $countassessorfeedbacks >= $this->max_number_of_feedbacks();
        if ($maxfeedbacksreached && !$this->editable_feedbacks_exist() && !$this->editable_final_feedback_exist()) {
            return self::FULLY_GRADED;
        }

        // Submitted with only some of the required grades in place.
        if (
            $this->is_finalised()
            &&
            $countassessorfeedbacks > 0
            &&
            ($countassessorfeedbacks < $this->get_coursework()->numberofmarkers || $this->any_editable_feedback_exists())
        ) {
            return self::PARTIALLY_GRADED;
        }

        // Student has marked this as finalised.
        if ($this->is_finalised()) {
            return self::FINALISED;
        }

        if (empty($this->id)) {
            // Not saved / submitted yet.
            return self::NOT_SUBMITTED;
        } else if (!$includefilescheck || $this->has_files()) {
            // Submitted but not yet graded - we have files or are not bothering to check that the submission has files attached.
            return self::SUBMITTED;
        }
        debugging(
            "Submission ID '$this->id' unexpectedly has submitted state with no files attached."
                . " Form validation in mod_coursework/forms/student_submission_form::add_file_manager_to_form() should have prevented this"
        );
        // We have no files attached to this submission so we can treat it as not submitted allowing user to submit with files.
        return self::NOT_SUBMITTED;
    }

    /**
     * Check in DB - does this submission have any uploaded files?
     * Avoid checking this repeatedly (e.g. from ability class) as it will not be particularly fast.
     * @return bool
     */
    public function has_files(): bool {
        global $DB;
        if (!$this->id) {
            return false;
        }
        return $DB->record_exists_sql(
            "SELECT id FROM {files}
                WHERE component = 'mod_coursework'
                  AND filearea = 'submission' AND contextid = :contextid
                  AND itemid = :submissionid AND filename <> '.'",
            [
                'contextid' => $this->get_coursework()->get_context_id(),
                'submissionid' => $this->id,
            ]
        );
    }

    /**
     * Returns the full name or a blank string if anonymous.
     *
     * @param bool $aslink
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_allocatable_name($aslink = false) {

        $viewanonymous = has_capability('mod/coursework:viewanonymous', $this->get_coursework()->get_context());
        if (!$this->get_coursework()->blindmarking || $viewanonymous || $this->is_published() || $this->get_coursework()->is_configured_to_have_group_submissions()) {
            $fullname = $this->get_allocatable()->name();

            $allowed = has_capability('moodle/user:viewdetails', $this->get_context());
            if ($aslink && $allowed) {
                $linkparams = [
                    'id' => $this->userid,
                    'course' => $this->get_coursework()->get_course_id(),
                ];
                $url = new moodle_url('/user/view.php', $linkparams);
                return html_writer::link($url, $fullname);
            } else {
                return $fullname;
            }
        } else {
            return get_string('hidden', 'mod_coursework');
        }
    }

    /**
     * @return user
     */
    public function get_last_updated_by_user() {
        return user::get_from_id($this->lastupdatedby);
    }

    /**
     * Tells us whether this has been given its final grade
     *
     * @return bool
     */
    public function has_final_agreed_grade() {
        $stage = $this->coursework->get_final_agreed_marking_stage();
        return $stage->has_feedback($this->get_allocatable());
    }

    /**
     * Checks whether there is a grade in the gradebook for this user.
     *
     * @return bool
     */
    public function is_published() {
        return !empty($this->firstpublished);
    }

    /**
     * Getter for the coursework instance. Memoized.
     *
     * @return coursework
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_coursework() {

        if (empty($this->coursework)) {
            $this->coursework = coursework::get_from_id($this->courseworkid);
            if (!$this->coursework) {
                throw new coding_exception('Could not find the coursework for submission id ' . $this->id);
            }
        }

        return $this->coursework;
    }

    /**
     * Prevents tight coupling by returning the marker status from the associated coursework.
     *
     * @return bool
     * @throws coding_exception
     */
    public function has_multiple_markers() {
        return $this->get_coursework()->has_multiple_markers();
    }

    /*
     * As with the author id field this function was created to verify that coursework will work correctly with Turnitin
     * Plagiarism plugin that requires the author of a submission to
     */
    public function get_author_id() {
        global $USER;

        $id = $USER->id;

        // If this is a submission on behalf of the student and it is a group submission we have to make sure
        // the author is the first member of the group

        if ($this->is_submission_on_behalf()) {
            if ($this->get_coursework()->is_configured_to_have_group_submissions()) {
                $members = groups_get_members($this->allocatableid, 'u.id', 'id');
                if ($members) {
                    $id = reset($members)->id;
                }

                if ($this->get_coursework()->plagiarism_enbled()) {
                    $groupmember = $this->get_tii_group_member_with_eula($this->allocatableid);
                    if (!empty($groupmember)) {
                        $id = $groupmember->id;
                    }
                }
            } else {
                $id = $this->allocatableid;
            }
        }

        return $id;
    }

    /**
     * Returns the first group member of the given group who has accepted turnitin's user agreement
     *
     * @param $groupid
     * @return mixed a fieldset object containing the first matching record
     * @throws dml_exception
     */
    public function get_tii_group_member_with_eula($groupid) {

        global  $DB;

        $sql = "
                SELECT  gm.userid as id
                FROM 	{groups_members} gm,
	                    {turnitintooltwo_users} tu
                WHERE 	tu.userid = gm.userid
                AND  	user_agreement_accepted != 0
                AND 	gm.groupid = ?
                ORDER   BY  gm.userid
                LIMIT   1";

        return $DB->get_record_sql($sql, [$groupid]);
    }

    /**
     * Return human readable language string for the row's status.
     *
     * @return string
     * @throws coding_exception
     */
    public function get_status_text() {

        $statustext = '';

        switch ($this->get_state(true)) {
            case self::NOT_SUBMITTED:
                $statustext = get_string('statusnotsubmitted', 'coursework');
                break;

            case self::SUBMITTED:
                $allowearlyfinalisation = $this->get_coursework()->allowearlyfinalisation;
                $statustext = ($allowearlyfinalisation) ? get_string('statusnotfinalised', 'coursework') : get_string('submitted', 'coursework');

                break;

            case self::FINALISED:
                $statustext = get_string('statussubmittedfinalised', 'coursework');

                break;

            case self::PARTIALLY_GRADED:
                if ($this->any_editable_feedback_exists()) {
                    $statustext = get_string('statusfullymarked', 'coursework') . "<br>";
                    $statustext .= get_string('stilleditable', 'coursework');
                } else {
                    $statustext = get_string('statuspartiallymarked', 'coursework');
                }
                break;

            case self::FULLY_GRADED:
                $statustext = get_string('statusfullymarked', 'coursework');
                break;

            case self::FINAL_GRADED:
                $spanfinalgraded = '<span class="badge badge-warning">' . get_string('statusfinalmarked', 'coursework') . '</span>';

                $spanfinalgradedsingle = '<span class="badge badge-warning">' . get_string('statusfinalmarkedsingle', 'coursework') . '</span>';

                $statustext = $this->has_multiple_markers() && $this->sampled_feedback_exists() ? $spanfinalgraded : $spanfinalgradedsingle;
                if ($this->editable_final_feedback_exist()) {
                    $statustext .= "<br>" . get_string('finalmarkstilleditable', 'coursework');
                }
                break;

            case self::PUBLISHED:
                $statustext = '<span class="badge badge-success">' . get_string('statusreleased', 'coursework') . '</span>';
                break;
        }

        return $statustext;
    }

    /**
     * @param int $userid
     * @return bool
     * @throws coding_exception
     */
    public function belongs_to_user(int $userid): bool {
        if ($this->get_coursework()->is_configured_to_have_group_submissions()) {
            $group = $this->get_coursework()->get_coursework_group_from_user_id($userid);
            return $group && $group->id == $this->allocatableid;
        } else {
            return $userid == $this->allocatableid;
        }
    }

    /**
     * @return bool
     */
    public function ready_to_grade() {
        return $this->get_state() >= self::FINALISED;
    }

    /**
     * @return bool
     */
    public function already_published() {
        return $this->get_state() >= self::PUBLISHED;
    }

    /**
     * @return bool
     */
    public function all_initial_graded() {
        return $this->get_state() >= self::FULLY_GRADED;
    }

    /**
     * Is this submission marked as finalised?
     * @return bool
     */
    public function is_finalised(): bool {
        return $this->finalisedstatus == self::FINALISED_STATUS_FINALISED;
    }

    /**
     * @return bool
     */
    public function final_grade_agreed() {
        return $this->get_state() >= self::FINAL_GRADED;
    }

    /**
     * @return allocatable
     */
    public function get_allocatable() {
        if (!$this->allocatableid) {
            throw new \core\exception\coding_exception("Submission must have an allocatable (e.g. user)");
        }
        if ($this->allocatabletype == 'user') {
            return user::get_from_id($this->allocatableid);
        } else if ($this->allocatabletype == 'group') {
            return group::get_from_id($this->allocatableid);
        } else {
            throw new \core\exception\coding_exception("Invalid type '" . $this->allocatabletype . "'");
        }
    }

    /**
     * @return user[]
     * @throws \moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_students() {
        $allocatables = [];
        if ($this->get_coursework()->is_configured_to_have_group_submissions() && $this->allocatabletype == 'group') {
            /**
             * @var group $group
             */
            $group = $this->get_allocatable();
            $cm = $this->coursework->get_course_module();
            $allocatables = $group->get_members($this->coursework->get_context(), $cm);
        } else if (!$this->get_coursework()->is_configured_to_have_group_submissions() && $this->allocatabletype == 'user') {
            $allocatables = [$this->get_allocatable()];
        } // If neither, the settings have been changed when they shouldn't have been.

        return $allocatables;
    }

    /**
     * @return bool
     * @throws \core\exception\coding_exception
     * @throws coding_exception
     */
    public function ready_to_publish() {
        if ($this->get_coursework()->plagiarism_flagging_enabled()) {
            // check if not stopped by plagiarism flag
            $plagiarism = plagiarism_flag::get_for_submission($this->id);
            if ($plagiarism && !$plagiarism->can_release_grades()) {
                return false;
            }
        }

        // Already published. Nothing has changed.
        if (!empty($this->lastpublished) && $this->timemodified <= $this->lastpublished) {
            return false;
        }

        $gradejudge = new grade_judge($this->get_coursework());
        if ($gradejudge->has_feedback_that_is_promoted_to_gradebook($this) && $this->final_grade_agreed() && !$this->editable_final_feedback_exist()) {
            return true;
        }

        return false;
    }

    /**
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function publish() {

        $studentgradestoupdate = $this->get_grades_to_update();
        $judge = new grade_judge($this->get_coursework());

        // Do not publish if the allocatable has disappeared.
        $allocatable = $this->get_allocatable();
        if (empty($allocatable)) {
            return;
        }

        foreach ($studentgradestoupdate as &$grade) {
            $cappedgrade = $judge->get_grade_for_gradebook($this);
            // Not sure why it needs both.
            $grade->grade = $cappedgrade;
            $grade->rawgrade = $cappedgrade;

            $grade->dategraded = $judge->get_time_graded($this);
        }

        if (coursework_grade_item_update($this->get_coursework(), $studentgradestoupdate) == GRADE_UPDATE_OK) {
            if (!$this->is_published()) {
                $this->update_attribute('firstpublished', time());
                // If the agreed grade is still in draft and is an auto grade, mark it as finalised now.
                foreach (feedback::get_all_for_submission($this->id) as $feedback) {
                    if ($feedback->is_auto_grade() && !$feedback->finalised) {
                        $feedback->update_attribute('finalised', 1);
                    }
                }
                $this->update_attribute('firstpublished', time());
                // Send feedback released notification only when first published.
                $mailer = new mailer($this->get_coursework());
                $mailer->send_feedback_notification($this);
            }
            $this->update_attribute('lastpublished', time());
        }
    }

    /**
     * @return array
     * @throws coding_exception
     */
    private function get_grades_to_update() {
        $students = $this->students_for_gradebook();
        $studentids = array_keys($students);

        // Only updating, not actually creating?
        $grades = grade_get_grades($this->get_course_id(), 'mod', 'coursework', $this->get_coursework()->id, $studentids);
        $grades = $grades->items[0]->grades;
        foreach ($studentids as $userid) {
            if (!array_key_exists($userid, $grades)) {
                $grades[$userid] = new stdClass();
            }
            $grades[$userid]->userid = $userid;
        }

        return $grades;
    }

    /**
     * @return bool|int
     * @throws coding_exception
     */
    public function get_overall_deadline() {
        if (!$this->get_coursework()->has_deadline()) {
            return false;
        }

        // check if submission has personal deadline
        if ($this->get_coursework()->personaldeadlineenabled) {
            $deadline = $this->submission_personaldeadline();
        } else { // if not, use coursework default deadline
            $deadline = $this->get_coursework()->get_deadline();
        }

        if ($this->has_extension()) {
            $deadline = $this->extension_deadline();
        }

        return $deadline;
    }

    /**
     * @return bool|int
     * @throws coding_exception
     */
    public function is_late() {
        $deadline = $this->get_overall_deadline();
        $now = time();

        if (empty($deadline)) {
            return false;
        } else if ($now <= $deadline) {
            return false;
        } else {
            return $now - $deadline;
        }
    }

    /**
     * @return bool|int
     * @throws coding_exception
     */
    public function was_late() {
        $deadline = $this->get_overall_deadline();

        if (empty($deadline)) {
            return false;
        } else if ($this->time_submitted() <= $deadline) {
            return false;
        } else {
            return $this->time_submitted() - $deadline;
        }
    }

    /**
     * @param int $filesid
     * @throws coding_exception
     */
    public function save_files($filesid) {

        file_save_draft_area_files(
            $filesid,
            $this->coursework->get_context_id(),
            'mod_coursework',
            'submission',
            $this->id,
            $this->coursework->get_file_options()
        );

        if (!empty($this->coursework->renamefiles)) {
            $this->rename_files();
        }
        $this->clear_cache();
    }

    /**
     * @return stored_file[]
     * @throws coding_exception
     */
    private function get_files() {
        $fs = get_file_storage();

        return $fs->get_area_files(
            $this->get_context_id(),
            'mod_coursework',
            'submission',
            $this->id,
            "id",
            false
        );
    }

    public function rename_files() {
        $counter = 1;
        $storedfiles = $this->get_files();
        foreach ($storedfiles as $file) {
            $this->rename_file($file, $counter);
            $counter++;
        }
    }

    /**
     * @param string $filename
     * @return string
     */
    public function extract_extension_from_file_name($filename) {
        if (!str_contains($filename, '.')) {
            return '';
        } else {
            return substr(strrchr($filename, '.'), 1);
        }
    }

    /**
     * @param stored_file $file
     * @param int $counter
     * @throws \file_exception
     */
    private function rename_file($file, $counter) {

        // If a submission was made on behalf of student/group, we need to use owner's id, not the person who submitted it.
        if ($this->is_submission_on_behalf()) {
            $userid = $this->allocatableid;
        } else {
            $userid = $this->userid;
        }

        $filepath = $file->get_filepath();
        $fileextension = $this->extract_extension_from_file_name($file->get_filename());
        if (empty($fileextension)) {
            $fileextension = $this->extract_extension_from_file_name($file->get_source());
        }

        // Get the file identifier (candidate number or username hash).
        $identifier = $this->coursework->get_file_identifier_for_user($userid);

        $filename = $identifier . '_' . $counter . '.' . $fileextension;
        if ($filename !== $file->get_filename()) {
            $file->rename($filepath, $filename);
        }
    }

    /**
     * @return int
     */
    public function time_submitted() {
        return $this->timesubmitted;
    }

    /**
     * The name sampled_feedback_exists for this seems suboptimal, as it does not check existence of a feedback.
     * Rather, it checks that a sample set exists / is populated for a given allocatable.
     * @return bool
     * @throws \core\exception\coding_exception|dml_exception
     */
    public function sampled_feedback_exists(): bool {
        return assessment_set_membership::membership_count(
            $this->get_coursework()->id(),
            $this->get_allocatable()->type(),
            $this->get_allocatable()->id()
        ) > 0;
    }

    /**
     * How many feedbacks do we expect for this submission?
     * @return int
     * @throws \core\exception\coding_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function max_number_of_feedbacks(): int {
        if ($this->get_coursework()->sampling_enabled()) {
            return assessment_set_membership::membership_count(
                $this->get_coursework()->id(),
                $this->get_allocatable()->type(),
                $this->get_allocatable()->id()
            ) + 1;  // We add one as by default 1st stage is always marked.
        } else {
            return $this->get_coursework()->get_max_markers();
        }
    }

    /**
     * @return array
     * @throws coding_exception
     */
    public function students_for_gradebook(): array {
        if ($this->get_coursework()->is_configured_to_have_group_submissions()) {
            return groups_get_members($this->allocatableid);
        } else {
            $allocatable = $this->get_allocatable();
            if ($allocatable) {
                return [$allocatable->id() => $allocatable];
            }
        }
        return [];
    }

    /**
     * @return bool
     */
    private function is_submission_on_behalf() {
        global $USER;

        if (($this->allocatableid == $USER->id && $this->allocatabletype != 'group') || groups_is_member($this->allocatableid)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     *  Function to get samplings for the submission
     * @return array
     * @throws \core\exception\coding_exception
     */

    public function get_submissions_in_sample() {
        assessment_set_membership::fill_pool_coursework($this->courseworkid);
        $allocatable = $this->get_allocatable();
        return isset(assessment_set_membership::$pool[$this->courseworkid]['allocatableid-allocatabletype'][$allocatable->id . '-' . $allocatable->type()]) ?
            assessment_set_membership::$pool[$this->courseworkid]['allocatableid-allocatabletype'][$allocatable->id . '-' . $allocatable->type()] : [];
    }

    /**
     *  Function to get samplings for the submission
     * @param $stageidentifier
     * @return assessment_set_membership
     * @throws \core\exception\coding_exception
     */

    public function get_submissions_in_sample_by_stage($stageidentifier) {
        assessment_set_membership::fill_pool_coursework($this->courseworkid);
        return assessment_set_membership::get_cached_object(
            $this->courseworkid,
            [
                'allocatableid' => $this->allocatableid,
                'allocatabletype' => $this->allocatabletype,
                'stageidentifier' => $stageidentifier,
            ]
        );
    }

    /**
     * Check if submission has an extension
     *
     * @return bool
     * @throws \core\exception\coding_exception
     */
    public function has_extension() {
        if (!$this->coursework->extensions_enabled()) {
            return false;
        }
        $extension = deadline_extension::get_for_allocatable(
            $this->courseworkid,
            $this->allocatableid,
            $this->allocatabletype
        );
        return !empty($extension);
    }

    /**
     * Retrieve details of submission's extension
     *
     * @return ?deadline_extension
     * @throws \core\exception\coding_exception
     */
    public function submission_extension() {
        if (!$this->coursework->extensions_enabled()) {
            return false;
        }
        return deadline_extension::get_for_allocatable(
            $this->courseworkid,
            $this->allocatableid,
            $this->allocatabletype
        );
    }

    /**
     * Retrieve details of submission's personal deadline, if not given, use corsework default
     *
     * @return mixed
     * @throws coding_exception
     */
    public function submission_personaldeadline() {
        $allocatableid = $this->get_allocatable()->id();
        $allocatabletype = $this->get_allocatable()->type();
        $personaldeadline = personaldeadline::get_for_allocatable(
            $this->courseworkid,
            $allocatableid,
            $allocatabletype
        );

        if ($personaldeadline) {
            $personaldeadline = $personaldeadline->personaldeadline;
        } else {
            $personaldeadline = $this->get_coursework()->deadline;
        }

        return  $personaldeadline;
    }

    /**
     * Retrieve submission's extended deadline
     * @return mixed
     */
    public function extension_deadline() {
        return $this->submission_extension()->extended_deadline;
    }

    /**
     * Has assessor graded in any of the initial stages?
     */
    public function is_assessor_initial_grader() {
        global $USER;
        $feedbacks = feedback::get_all_for_submission($this->id, $USER->id);
        foreach ($feedbacks as $feedback) {
            if ($feedback->stageidentifier != final_agreed::STAGE_FINAL_AGREED_1) {
                return true;
            }
        }
        return false;
    }

    /**
     * Tells us whether any initial feedbacks for this submission are editable
     * This is only for double marked courseworks
     *
     */
    public function editable_feedbacks_exist() {

        $editablefeedbacks = [];
        $coursework = $this->get_coursework();
        if ($coursework->numberofmarkers > 1 && $this->is_finalised()) {
            $feedbacks = feedback::get_all_for_submission($this->id);
            $editablefeedbacks = array_filter($feedbacks, fn($f) => !$f->finalised);
        }

        return (empty($editablefeedbacks)) ? false : $editablefeedbacks;
    }

    /**
     * Tells us whether any final feedback for this submission is editable
     *
     */
    public function editable_final_feedback_exist() {
        if (!isset($this->editable_final_feedback)) {
            $this->editable_final_feedback = false;
            if ($this->is_finalised()) {
                $finalfeedback = feedback::get_from_submission_and_stage($this->id, final_agreed::STAGE_FINAL_AGREED_1);
                if ($finalfeedback && $finalfeedback->finalised == 0 && $finalfeedback->assessorid <> 0) {
                    $this->editable_final_feedback = true;
                }
            }
        }
        return $this->editable_final_feedback;
    }

    /**
     * Tells us whether any of the feedback for this submission are editable
     *
     */
    public function final_draft_feedbacks_exist() {

        global $DB;

        $sql = "
                    SELECT  *
                    FROM 	{coursework} c,
					        {coursework_submissions} cs,
					        {coursework_feedbacks} cf
			         WHERE 	c.id = cs.courseworkid
			         AND	cs.id = cf.submissionid
			         AND	c.numberofmarkers > 1
			         AND 	cs.finalisedstatus = :submissionfinalised
			         AND	cf.stageidentifier NOT LIKE 'final_agreed%'
			         AND	cs.id = :submissionid
";

        $editablefeedbacks = $DB->get_records_sql($sql, ['submissionid' => $this->id, 'submissionfinalised' => self::FINALISED_STATUS_FINALISED]);

        return (empty($editablefeedbacks)) ? false : $editablefeedbacks;
    }

    /*
    * Determines whether the current user is able to add a turnitin grademark to this submission
    */
    public function can_add_tii_grademark() {
        if ($this->get_coursework()->get_max_markers() == 1) {
            return (has_any_capability(['mod/coursework:addinitialgrade', 'mod/coursework:addministergrades'], $this->get_context()) && $this->ready_to_grade());
        } else {
            return (has_any_capability(['mod/coursework:addagreedgrade', 'mod/coursework:addallocatedagreedgrade', 'mod/coursework:addministergrades'], $this->get_context()) && $this->all_initial_graded());
        }
    }

    /**
     * Determines if any editable feedback still exists
     *
     * @return bool
     */
    public function any_editable_feedback_exists() {

        return count($this->get_assessor_feedbacks()) >= $this->max_number_of_feedbacks() && $this->editable_feedbacks_exist();
    }

    /**
     * Function to check if submission has a valid extension
     *
     * @return bool
     * @throws \core\exception\coding_exception
     */
    public function has_valid_extension() {
        $extension = deadline_extension::get_for_allocatable(
            $this->courseworkid,
            $this->allocatableid,
            $this->allocatabletype
        );
        return $extension && $extension->extended_deadline > time();
    }

    public function can_be_unfinalised() {
        return  ($this->get_state() == self::FINALISED);
    }

    /**
     * check if the feedback by provided assessor exists
     *
     * @param $assessorid
     * @return bool|false|mixed|stdClass
     * @throws dml_exception
     */
    public function has_specific_assessor_feedback($assessorid) {
        global $DB;

        return $DB->record_exists('coursework_feedbacks', [
            'submissionid' => $this->id,
            'assessorid' => $assessorid,
        ]);
    }

    /**
     * Get an array of data for all submission files for this coursework, by participantID-participantType.
     * Used from the grading page to get all at once / avoid getting files once for each row.
     * @param coursework $coursework
     * @param int[] $submissionids optional submission IDs if we only want data for specific users.
     * @param bool $withturnitinlinks whether to fetch and include Turnitin links (normally no as they are fetched later with AJAX)
     * @see submission::get_submission_files()
     * @return array Each user may have multiple files (i.e. nested array).
     */
    public static function get_all_submission_files_data(coursework $coursework, array $submissionids = [], bool $withturnitinlinks = false): array {
        global $DB, $CFG;
        if ($withturnitinlinks) {
            require_once("$CFG->libdir/plagiarismlib.php");
        }
        $contextid = $coursework->get_context_id();
        $sqlparams = ['ctxid' => $contextid];
        if (!empty($submissionids)) {
            [$wheresql, $inparams] = $DB->get_in_or_equal($submissionids, SQL_PARAMS_NAMED);
            $sqlparams = array_merge($sqlparams, $inparams);
            $wheresql = "AND cs.id $wheresql";
        } else {
            $wheresql = '';
        }
        $filerecords = $DB->get_recordset_sql(
            "SELECT cs.id as submissionid, cs.allocatableid, cs.allocatabletype, cs.authorid, f.*
                FROM {files} f
                JOIN {coursework_submissions} cs ON cs.id = f.itemid
                WHERE f.contextid = :ctxid
                AND f.component = 'mod_coursework'
                AND f.filearea = 'submission'
                AND f.filename != '.' $wheresql
                ORDER BY cs.id, f.id",
            $sqlparams
        );

        if (!$filerecords->valid()) {
            return [];
        }
        $results = [];
        $fs = get_file_storage();

        // We return a nested array by allocatable ID.
        foreach ($filerecords as $filerecord) {
            $submissionskey = $filerecord->allocatabletype . "-" . $filerecord->allocatableid;
            if (!isset($results[$submissionskey])) {
                $results[$submissionskey] = [];
            }
            $result = (object)[
                'submissionid' => $filerecord->submissionid,
                'allocatableid' => $filerecord->allocatableid,
                'allocatabletype' => $filerecord->allocatabletype,
                'authorid' => $filerecord->authorid,
                'file' => $fs->get_file_instance($filerecord),
                'fileid' => $filerecord->id,
                'filename' => $filerecord->filename,
                'url' => \moodle_url::make_file_url('/pluginfile.php', '/' . implode('/', [
                        $contextid,
                        'mod_coursework',
                        'submission',
                        $filerecord->submissionid,
                        $filerecord->filename,
                    ]))->out(),
            ];
            if ($withturnitinlinks && $coursework->tii_enabled()) {
                // Normally we do not get Turnitin similarity reports here as they are fetched after page load to improve load time.
                $result->tiilinksHTML = self::plagiarism_get_links(
                    $filerecord->authorid,
                    $fs->get_file_instance($filerecord),
                    $coursework
                );
                $result->tiiloadedattr = "true";
                if (!$result->tiilinksHTML) {
                    // TII returned no information.
                    $result->tiilinksHTML = "<small>" . get_string('turnitinnoreport', 'coursework') . "</small>";
                }
            }
            $results[$submissionskey][] = $result;
        }
        $filerecords->close();
        return $results;
    }
    /**
     * Remove all submissions by this coursework.
     * @param int $courseworkid
     * @return void
     * @throws dml_exception
     */
    public static function remove_submissions_by_coursework(int $courseworkid) {
        global $DB;
        $params = ['courseworkid' => $courseworkid, 'allocatabletype' => 'user'];
        $submissionids = $DB->get_fieldset(self::$tablename, 'id', $params);
        foreach ($submissionids as $submissionid) {
            $submission = self::get_from_id($submissionid);
            $submission->destroy();
        }
    }


    /**
     * Get all submissions for a coursework ID.
     * @param int $courseworkid
     * @return []submission
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function get_all_for_coursework(int $courseworkid) {
        global $DB;
        $submissionids = $DB->get_fieldset(self::$tablename, 'id', ['courseworkid' => $courseworkid]);
        $result = [];
        foreach ($submissionids as $submissionid) {
            $result[$submissionid] = self::get_from_id($submissionid);
        }
        return $result;
    }


    /**
     * Get multiple submission objects from IDs.
     * @param int[] $submissionids
     * @return array submission objects
     * @throws \core\exception\invalid_parameter_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_multiple(array $submissionids): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($submissionids, SQL_PARAMS_NAMED);
        $records = $DB->get_records_sql("SELECT * FROM {coursework_submissions} WHERE id $insql", $params);
        $submissions = [];
        foreach ($records as $record) {
            $submissions[$record->id] = self::find($record, false);
        }
        return $submissions;
    }

    /**
     * Call the core plagairism_get_links function to get turnitin links or mock response if behat testing.
     * @param int $userid User ID or, for group submissions, first member of group.
     * @param stored_file $file the submission file we are checking (could be multiple for one submission).
     * @param coursework $coursework
     * @return string
     * @throws \core\exception\coding_exception
     * @throws \moodle_exception
     * @throws dml_exception
     */
    public static function plagiarism_get_links(int $userid, stored_file $file, coursework $coursework): string {
        if (defined('BEHAT_SITE_RUNNING')) {
            return "[TURNITIN DUMMY LINKS HTML]";
        }

        return plagiarism_get_links(
            [
                'userid' => $userid,
                'file' => $file,
                'cmid' => $coursework->get_coursemodule_id(),
                'course' => $coursework->get_course(),
                'coursework' => $coursework->id(),
                'modname' => 'coursework',
            ]
        );
    }

    /**
     * Check if the submission should be flagged for plagiarism.
     *
     * @return string|bool
     */
    public function get_flagged_plagiarism_status(): string|bool {
        $flag = plagiarism_flag::get_plagiarism_flag($this);
        if (!$flag || !($flag->status == plagiarism_flag::INVESTIGATION || $flag->status == plagiarism_flag::NOTCLEARED)) {
            return false;
        }
        return get_string('plagiarism_' . $flag->status, 'mod_coursework');
    }

    /**
     * Check if the submission should be flagged for plagiarism.
     *
     * @return string|bool
     */
    public function get_flagged_plagiarism_status(): string|bool {
        $flag = plagiarism_flag::get_plagiarism_flag($this);
        if (!$flag || !($flag->status == plagiarism_flag::INVESTIGATION || $flag->status == plagiarism_flag::NOTCLEARED)) {
            return false;
        }
        return get_string('plagiarism_' . $flag->status, 'mod_coursework');
    }
}
