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

use context;
use html_writer;
use mod_coursework\ability;
use mod_coursework\allocation\allocatable;
use mod_coursework\grade_judge;
use mod_coursework\submission_files;
use mod_coursework\framework\table_base;
use moodle_database;
use moodle_url;
use stdClass;
use stored_file;
use mod_coursework\mailer;

global $CFG;
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/mod/coursework/lib.php');

defined('MOODLE_INTERNAL') || die();

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
#[\AllowDynamicProperties]
class submission extends table_base implements \renderable {

    /**
     * @var string
     */
    public static $tablename = 'coursework_submissions';

    /**
     * @var int
     */
    public $id;

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
     * Flag to show whether the submission has been marked as ready for grading by the student. 0 or 1.
     * @var int
     */
    public $finalised;

    /**
     * @var
     */
    public $firstname;

    /**
     * @var
     */
    public $lastname;

    /**
     * @var
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
        'finalised',
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
     * @param string|int|stdClass|null $dbrecord
     */
    public function __construct($dbrecord = null) {

        global $USER, $DB;

        parent::__construct($dbrecord);

        if (empty($dbrecord)) {
            // Set defaults ready to save as a new record.
            $this->userid = $USER->id;
            $this->timecreated = time();
        }

        if ($this->id > 0 && !$this->firstname && !empty($this->userid)) {
            // Get the real first and last name from the user table. We use fullname($this), which needs it,
            // so we can't lazy-load.
            $user = $DB->get_record('user', ['id' => $this->userid]);
            $allnames = \core_user\fields::get_name_fields();
            foreach ($allnames as $namefield) {
                $this->$namefield = $user->$namefield;
            }
        }
    }

    /**
     * @return submission[]
     */
    public static function unfinalised_past_deadline() {
        global $DB;

        // get all unfinalised submissions that have a deadline
        $sql = 'SELECT cs.*, co.deadline
                  FROM {coursework_submissions} cs
            INNER JOIN {coursework} co
                    ON co.id = cs.courseworkid
                 WHERE co.deadline != 0
                   AND cs.finalised = 0';

        $submissions = $DB->get_records_sql($sql);

        foreach ($submissions as &$submission) {
            $deadline = $submission->deadline;
            $submission = static::find($submission);

            if ($submission->get_coursework()->personal_deadlines_enabled()) {
                $deadline = $submission->submission_personal_deadline();
            }

            if ($deadline < time()) {
                // if deadline passed check if extension exists
                if ($submission->has_extension()) {
                    // Check if extension is valid
                    $extension = $submission->submission_extension();
                    if ($extension->extended_deadline > time()) {
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
     */
    public function get_course_id() {
        return $this->get_coursework()->get_course_id();
    }

    /**
     * Gets the course module id from the parent coursework.
     *
     * @return int
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
     */
    public function submit_plagiarism($type = null) {

        global $CFG, $USER;

        if (empty($CFG->enableplagiarism)) {
            return;
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files($this->get_context_id(), 'mod_coursework', 'submission',
            $this->id, "id", false);

        $params = [
            'context' => \context_module::instance($this->get_coursework()->get_course_module()->id),
            'courseid' => $this->get_course_id(),
            'objectid' => $this->id,
            'relateduserid' => $this->get_author_id(),
            'userid' => $USER->id,
            'other' => [
                'content' => '',
                'pathnamehashes' => array_keys($files),
            ],
        ];
        $event = \mod_coursework\event\assessable_uploaded::create($params);
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

        if ($this->id < 1 || $this->get_context_id() < 1) {
            return new submission_files([], $this);
        }

        $submissionfiles = $this->get_files();

        if ($submissionfiles) {
            $this->submissionfiles = new submission_files($submissionfiles, $this);

            return $this->submissionfiles;
        }

        $files = new submission_files([], $this);
        return $files;
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
     */
    public function get_context_id() {
        return $this->get_coursework()->get_context()->id;
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
     * Gets all attached feedbacks, fetching from DB if not already there.
     *
     * @return feedback[] array of raw db records
     */
    public function get_feedbacks() {
        if (!is_array($this->feedbacks)) {
            // Sort here is on ID so that if there's any need to get the first one chronologically, we can use reset().

            feedback::fill_pool_coursework($this->courseworkid);
            $this->feedbacks = isset(feedback::$pool[$this->courseworkid]['submissionid'][$this->id]) ?
                feedback::$pool[$this->courseworkid]['submissionid'][$this->id] : [];
        }

        return $this->feedbacks;
    }

    /**
     * This will return the feedbacks that have been added, but which are not the final feedback.
     *
     * @return feedback[]
     */
    public function get_assessor_feedbacks() {
        if (!$this->id) {
            // No submission - empty placeholder.
            return [];
        }

        if (!isset(feedback::$pool[$this->courseworkid]['submissionid-stage_identifier_index'])) {
            feedback::fill_pool_coursework($this->courseworkid);
        }
        // Get all other feedbacks whose stage_identifier is not "final_agreed_1"
        // In case of loops, we would like empty array instead of false.
        $res = feedback::$pool[$this->courseworkid]['submissionid-stage_identifier_index']["$this->id-others"] ?? [];
        return $res;
    }

    /**
     * Function to retrieve a grade for the specific stage
     * @param $stageidentifier
     * @return bool|feedback
     * @throws \dml_exception
     */
    public function get_assessor_feedback_by_stage($stageidentifier) {
        $params = [
            'submissionid' => $this->id,
            'ismoderation' => 0,
            'isfinalgrade' => 0,
            'stage_identifier' => $stageidentifier,
        ];
        feedback::fill_pool_coursework($this->courseworkid);
        $feedback = feedback::get_object($this->courseworkid, 'submissionid-ismoderation-isfinalgrade-stage_identifier', $params);
        return $feedback;
    }

    /**
     * Function to retrieve a assessor allocated for the specific stage
     * @param $stageidentifier
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_assessor_allocation_by_stage($stageidentifier) {

        $courseworkid = $this->get_coursework()->id;
        allocation::fill_pool_coursework($courseworkid);
        $allocation = allocation::get_object(
            $courseworkid,
            'allocatableid-allocatabletype-stage_identifier',
            [$this->get_allocatable()->id(), $this->get_allocatable()->type(), $stageidentifier]);
        return $allocation;

    }

    /**
     * @return mixed|feedback|string
     * @throws \dml_missing_record_exception
     * @throws \dml_multiple_records_exception
     */
    public function get_agreed_grade() {
        global $DB;

        if (!$this->id) {
            // No submission - empty placeholder.
            return [];
        }

        $params = [
            'submissionid' => $this->id,
            'ismoderation' => 0,
            'isfinalgrade' => 0,
            'stage_identifier' => 'final_agreed_1',
        ];
        feedback::fill_pool_coursework($this->courseworkid);
        $feedback = feedback::get_object($this->courseworkid, 'submissionid-ismoderation-isfinalgrade-stage_identifier', $params);
        return $feedback;
    }

    /**
     * This will return the final feedback if the record exists, or false if not.
     *
     * @throws \exception
     * @return bool|feedback
     */
    public function get_final_feedback() {

        global $DB;

        if (!$this->id) {
            // No submission yet - empty placeholder.
            return false;
        }

        // Temp - will be replaced with asking the appropriate stage for the feedback.
        if ($this->has_multiple_markers() && ($this->get_coursework()->sampling_enabled() == 0) || $this->sampled_feedback_exists()) {
            $identifier = 'final_agreed_1';
        } else {
            $identifier = 'assessor_1';
        }

        $params = [
            'submissionid' => $this->id,
            'stage_identifier' => $identifier,
        ];

        $feedback = $DB->get_record('coursework_feedbacks', $params);

        if (!$feedback) {
            return false;
        } else {
            return new feedback($feedback, $this);
        }

    }

    /**
     * Gets the final grade from the final feedback record and returns it.
     *
     * @return int|bool false if there isn't one
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
     *
     * @return int
     */
    public function get_state() {

        if ($this->get_coursework() == null) {
            return -1;
        }

        $courseworkfiles = $this->get_submission_files();

        $assessorfeedbacks = $this->get_assessor_feedbacks();

        if ($this->is_published()) {
            return self::PUBLISHED;
        }

        // Final grade is done.
        $hasfinalfeedback = (bool)$this->get_final_feedback();
        $maxfeedbacksreached = count($assessorfeedbacks) >= $this->max_number_of_feedbacks();

        if ($hasfinalfeedback) {
            return self::FINAL_GRADED;
        }

        // All feedbacks in.
        if ($maxfeedbacksreached && !$this->editable_feedbacks_exist() && !$this->editable_final_feedback_exist()) {
            return self::FULLY_GRADED;
        }

        // Submitted with only some of the required grades in place.
        if ($this->finalised &&
            count($assessorfeedbacks) > 0 &&
            (count($assessorfeedbacks) < $this->get_coursework()->numberofmarkers || $this->any_editable_feedback_exists())
        ) {

            return self::PARTIALLY_GRADED;
        }

        // Student has marked this as finalised.
        if ($this->finalised) {
            return self::FINALISED;
        }

        // Submitted, but not graded.
        if (!empty($this->id) && $courseworkfiles->has_files()) {
            return self::SUBMITTED;
        }

        // No submission yet. We count files in case they have been deleted after being earlier
        // submitted, which will leave us with an id but nothing else.
        if (empty($this->id) || !$courseworkfiles->has_files()) {
            return self::NOT_SUBMITTED;
        }

        // New submission, not in DB yet.
        return 0;
    }

    /**
     * Returns the full name or a blank string if anonymous.
     *
     * @param bool $aslink
     * @return string
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
     * @return mixed
     */
    public function get_last_updated_by_user() {
        return user::get_object($this->lastupdatedby);
    }

    /**
     * Tells us whether this has been given its final grade
     *
     * @return int|null
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
     * @throws \coding_exception
     * @return coursework
     */
    public function get_coursework() {

        if (empty($this->coursework)) {
            if (!isset(coursework::$pool['id'][$this->courseworkid])) {
                coursework::fill_pool_coursework($this->courseworkid);
            }
            $this->coursework = coursework::$pool['id'][$this->courseworkid];
            if (!$this->coursework) {
                throw new \coding_exception('Could not find the coursework for submission id '. $this->id);
            }
        }

        return $this->coursework;
    }

    /**
     * Prevents tight coupling by returning the marker status from the associated coursework.
     *
     * @return bool
     */
    public function has_multiple_markers() {
        return $this->get_coursework()->has_multiple_markers();
    }

    /**
     * Has the current user already submitted a feedback for this submission?
     *
     * @param int $userid
     * @return bool
     */
    public function user_has_submitted_feedback($userid = 0) {

        global $USER, $DB;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        $params = [
            'submissionid' => $this->id,
            'assessorid' => $userid,
        ];
        feedback::fill_pool_coursework($this->courseworkid);
        $feedback = feedback::get_object($this->courseworkid, 'submissionid-assessorid', $params);
        if ($feedback && $feedback->isfinalgrade == 0 && $feedback->ismoderation == 0) {
            return true;
        }
        return false;
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
            if ( $this->get_coursework()->is_configured_to_have_group_submissions()) {
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
     * @return array
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
     */
    public function get_status_text() {

        $statustext = '';

        switch ($this->get_state()) {

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
                $statustext = get_string('statuspartiallygraded', 'coursework');
                if ($this->any_editable_feedback_exists()) {
                    $statustext = get_string('statusfullygraded', 'coursework'). "<br>";
                    $statustext .= get_string('stilleditable', 'coursework');
                }
                break;

            case self::FULLY_GRADED:
                $statustext = get_string('statusfullygraded', 'coursework');
                break;

            case self::FINAL_GRADED:
                $spanfinalgraded = html_writer::tag('span',
                                                    get_string('statusfinalgraded', 'coursework'),
                                                    ['class' => 'highlight']);
                $spanfinalgradedsingle =
                    html_writer::tag('span',
                                     get_string('statusfinalgradedsingle', 'coursework'),
                                     ['class' => 'highlight']);
                $statustext = $this->has_multiple_markers() && $this->sampled_feedback_exists() ? $spanfinalgraded : $spanfinalgradedsingle;
                if ($this->editable_final_feedback_exist()) {
                    $statustext .= "<br>". get_string('finalgradestilleditable', 'coursework');
                }
                break;

            case self::PUBLISHED:
                $statustext = get_string('statuspublished', 'coursework');
                if (!$this->coursework->deadline_has_passed()) {
                    $statustext .= ' '.get_string('released_early', 'mod_coursework');
                }
                break;
        }

        return $statustext;
    }

    /**
     * @param user $user
     * @return bool
     * @throws \coding_exception
     */
    public function belongs_to_user($user) {
        if ($this->get_coursework()->is_configured_to_have_group_submissions()) {
            $group = $this->get_coursework()->get_student_group($user);
            return $group && $group->id == $this->allocatableid;
        } else {
            return $user->id() == $this->allocatableid;
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
    public function all_inital_graded() {
        return $this->get_state() >= self::FULLY_GRADED;
    }

    public function is_finalised() {
        return $this->get_state() == self::FINALISED;
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
        /**
         * @var table_base $classname
         */
        $classname = "\\mod_coursework\\models\\".$this->allocatabletype;
        return $classname::get_object($this->allocatableid);
    }

    /**
     * @return user[]
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
     */
    public function ready_to_publish() {

        if ($this->get_coursework()->plagiarism_flagging_enbled()) {
            // check if not stopped by plagiarism flag
            plagiarism_flag::fill_pool_coursework($this->courseworkid);
            $plagiarism = plagiarism_flag::get_object($this->courseworkid, 'submissionid', [$this->id]);
            if ($plagiarism && !$plagiarism->can_release_grades()) {
                return false;
            }
        }

        $gradejudge = new grade_judge($this->get_coursework());
        if ($gradejudge->has_feedback_that_is_promoted_to_gradebook($this) && $this->final_grade_agreed() && !$this->editable_final_feedback_exist()) {
            return true;
        }

        // Already published. Nothing has changed.
        if (!empty($this->lastpublished) && $this->timemodified <= $this->lastpublished) {
            return false;
        }

        return false;
    }

    /**
     * @throws \coding_exception
     */
    public function publish() {

        $studentgradestoupdate = $this->get_grades_to_update();
        $judge = new grade_judge($this->get_coursework());

        // Do not publish if the allocatable has disappeared.
        $allocatable = $this->get_allocatable();
        if (empty($allocatable)) {
            return;
        }

        foreach ($studentgradestoupdate as $userid => &$grade) {
            $cappedgrade = $judge->get_grade_for_gradebook($this);
            // Not sure why it needs both.
            $grade->grade = $cappedgrade;
            $grade->rawgrade = $cappedgrade;

            $grade->dategraded = $judge->get_time_graded($this);
        }

        if (coursework_grade_item_update($this->get_coursework(), $studentgradestoupdate) == GRADE_UPDATE_OK) {
            if (!$this->is_published()) {
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
     * @return user|false
     */
    public function get_last_submitter() {
        return user::get_object($this->lastupdatedby);
    }

    /**
     * @return bool
     * @throws \coding_exception
     */
    public function is_late() {
        // check if submission has personal deadline
        if ($this->get_coursework()->personaldeadlineenabled) {
            $deadline = $this->submission_personal_deadline();
        } else { // if not, use coursework default deadline
            $deadline = $this->get_coursework()->get_deadline();
        }

        return ($this->get_coursework()->has_deadline() && $this->time_submitted() > $deadline);
    }

    /**
     * @param int $filesid
     */
    public function save_files($filesid) {

        file_save_draft_area_files($filesid,
                                   $this->coursework->get_context_id(),
                                   'mod_coursework',
                                   'submission',
                                   $this->id,
                                   $this->coursework->get_file_options());

        if (!empty($this->coursework->renamefiles)) {
            $this->rename_files();
        }
    }

    /**
     * @return stored_file[]
     */
    private function get_files() {
        $fs = get_file_storage();

        $submissionfiles = $fs->get_area_files($this->get_context_id(),
                                                'mod_coursework',
                                                'submission',
                                                $this->id,
                                                "id",
                                                false);
        return $submissionfiles;
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
        if (strpos($filename, '.') === false) {
            return '';
        } else {
            return substr(strrchr($filename, '.'), 1);
        }
    }

    /**
     * @param stored_file $file
     * @param int $counter
     */
    private function rename_file($file, $counter) {

        // if a submission was made of behalf of student/group, we need to use owner's id, not the person who submitted it
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
        $filename = $this->coursework->get_username_hash($userid) . '_' . $counter . '.' . $fileextension;
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

    public function sampled_feedback_exists() {
        global $DB;
        return $DB->record_exists('coursework_sample_set_mbrs', ['courseworkid' => $this->courseworkid,
                                                                     'allocatableid' => $this->get_allocatable()->id(),
                                                                     'allocatabletype' => $this->get_allocatable()->type()]);

    }

    public function max_number_of_feedbacks() {
        global $DB;

        if ($this->get_coursework()->sampling_enabled()) {
            // calculate how many stages(markers) are enabled for this submission
            $parameters = ['courseworkid' => $this->coursework->id,
                                 'allocatableid' => $this->get_allocatable()->id(),
                                 'allocatabletype' => $this->get_allocatable()->type()];

            $sql = "SELECT count(id) as total
                    FROM {coursework_sample_set_mbrs}
                    WHERE courseworkid = :courseworkid
                    AND allocatableid = :allocatableid
                    AND allocatabletype = :allocatabletype";

            $count = $DB->get_record_sql($sql, $parameters);
            return $count->total + 1; // we add one as by default 1st stage is always marked

        } else { // if samplings are not enabled
            return $this->get_coursework()->get_max_markers();
        }
    }

    /**
     * @return array
     * @throws \coding_exception
     */
    public function students_for_gradebook(): array {
        if ($this->get_coursework()->is_configured_to_have_group_submissions()) {
            $students = groups_get_members($this->allocatableid);
            return $students;
        } else {
            $allocatable = $this->get_allocatable();
            if ($allocatable) {
                return [$allocatable->id() => $allocatable];
            }
        }
        return [];
    }

    /**
     * @return array|bool
     * @throws \coding_exception
     */
    private function students_for_gradng() {
        if ($this->get_coursework()->is_configured_to_have_group_submissions()) {
            $students = groups_get_members($this->allocatableid);
            return $students;
        } else {
            $students = [$this->get_allocatable()->id() => $this->get_allocatable()];
            return $students;
        }
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
     * @throws \coding_exception
     */

    public function get_submissions_in_sample() {
        assessment_set_membership::fill_pool_coursework($this->courseworkid);
        $allocatable = $this->get_allocatable();
        $records = isset(assessment_set_membership::$pool[$this->courseworkid]['allocatableid-allocatabletype'][$allocatable->id . '-' . $allocatable->type()]) ?
            assessment_set_membership::$pool[$this->courseworkid]['allocatableid-allocatabletype'][$allocatable->id . '-' . $allocatable->type()] : [];
        return $records;
    }

    /**
     *  Function to get samplings for the submission
     * @return array
     * @throws \coding_exception
     */

    public function get_submissions_in_sample_by_stage($stageidentifier) {
        assessment_set_membership::fill_pool_coursework($this->courseworkid);
        $record = assessment_set_membership::get_object(
            $this->courseworkid,
            'allocatableid-allocatabletype-stage_identifier',
            [$this->allocatableid, $this->allocatabletype, $stageidentifier]
        );
        return $record;
    }

    /**
     * Check if submission has an extension
     *
     * @return bool
     * @throws \coding_exception
     */
    public function has_extension() {
        deadline_extension::fill_pool_coursework($this->courseworkid);
        $extension = deadline_extension::get_object($this->courseworkid, 'allocatableid-allocatabletype', [$this->allocatableid, $this->allocatabletype]);
        return !empty($extension);
    }

    /**
     * Retrieve details of submission's extension
     *
     * @return mixed
     * @throws \coding_exception
     */
    public function submission_extension() {
        deadline_extension::fill_pool_coursework($this->courseworkid);
        $extension = deadline_extension::get_object($this->courseworkid, 'allocatableid-allocatabletype', [$this->allocatableid, $this->allocatabletype]);
        return $extension;
    }

    /**
     * Retrieve details of submission's personal deadline, if not given, use corsework default
     *
     * @return mixed
     * @throws \coding_exception
     */
    public function submission_personal_deadline() {
        $allocatableid = $this->get_allocatable()->id();
        $allocatabletype = $this->get_allocatable()->type();
        $personaldeadline = personal_deadline::get_object($this->courseworkid, 'allocatableid-allocatabletype', [$allocatableid, $allocatabletype]);

        if ($personaldeadline) {
            $personaldeadline = $personaldeadline->personal_deadline;
        } else {
            $personaldeadline = $this->get_coursework()->deadline;
        }

        return  $personaldeadline;

    }

    /**
     * Check if submission was submitted within the extension time
     *
     * @return bool
     */
    public function submitted_within_extension() {
        return $this->time_submitted() < $this->extension_deadline();
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

        feedback::fill_pool_coursework($this->courseworkid);
        $feedbacks = isset(feedback::$pool[$this->courseworkid]['submissionid-assessorid'][$this->id . '-' . $USER->id]) ?
            feedback::$pool[$this->courseworkid]['submissionid-assessorid'][$this->id . '-' . $USER->id] : [];
        foreach ($feedbacks as $feedback) {
            if ($feedback->stage_identifier != 'final_agreed_1') {
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
        if ($coursework->numberofmarkers > 1 && $this->finalised = 1) {
            $this->get_coursework()->get_grade_editing_time();
            $gradeeditingtime = $coursework->get_grade_editing_time();

            $editablefeedbacks = isset(feedback::$pool[$coursework->id]['submissionid-finalised'][$this->id . '-0']) ?
                feedback::$pool[$coursework->id]['submissionid-finalised'][$this->id . '-0'] : [];
            if ($gradeeditingtime != 0) {
                $time = time();
                $finalizedfeedbacks = isset(feedback::$pool[$coursework->id]['submissionid-finalised'][$this->id . '-1']) ?
                    feedback::$pool[$coursework->id]['submissionid-finalised'][$this->id . '-1'] : [];
                foreach ($finalizedfeedbacks as $feedback) {
                    if ($feedback->timecreated + $gradeeditingtime > $time) {
                        $editablefeedbacks[] = $feedback;
                    }
                }
            }
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
            if ($this->finalised == 1) {

                $coursework = $this->get_coursework();
                $finalfeedback = feedback::get_object($coursework->id, 'submissionid-stage_identifier', [$this->id, 'final_agreed_1']);
                if ($finalfeedback) {
                    $gradeeditingtime = $coursework->get_grade_editing_time();
                    if ($gradeeditingtime) {
                        if ($finalfeedback->timecreated + $gradeeditingtime > time()) {
                            $this->editable_final_feedback = true;
                        }
                    } else if ($finalfeedback->finalised == 0 && $finalfeedback->assessorid <> 0) {
                        $this->editable_final_feedback = true;
                    }
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

        $this->get_coursework()->get_grade_editing_time();

        $sql = "
                    SELECT  *
                    FROM 	{coursework} c,
					        {coursework_submissions} cs,
					        {coursework_feedbacks} cf
			         WHERE 	c.id = cs.courseworkid
			         AND	cs.id = cf.submissionid
			         AND	c.numberofmarkers > 1
			         AND 	cs.finalised = 1
			         AND	c.gradeeditingtime != 0
			         AND	cf.stage_identifier NOT LIKE 'final_agreed%'
			         AND	cs.id = :submissionid
			         AND    cf.timecreated + c.gradeeditingtime > :time
        ";

        $editablefeedbacks = $DB->get_records_sql($sql, ['submissionid' => $this->id, 'time' => time()]);

        return (empty($editablefeedbacks)) ? false : $editablefeedbacks;
    }

    /*
    * Determines whether the current user is able to add a turnitin grademark to this submission
    */
    public function can_add_tii_grademark() {
        $canadd = false;

        if ($this->get_coursework()->get_max_markers() == 1) {
            $canadd = (has_any_capability(['mod/coursework:addinitialgrade', 'mod/coursework:addministergrades'], $this->get_context()) && $this->ready_to_grade());
        } else {
            $canadd = (has_any_capability(['mod/coursework:addagreedgrade', 'mod/coursework:addallocatedagreedgrade', 'mod/coursework:addministergrades'], $this->get_context()) && $this->all_inital_graded());
        }

        return  $canadd;
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
     */
    public function has_valid_extension() {
        deadline_extension::fill_pool_coursework($this->courseworkid);
        $extension = deadline_extension::get_object($this->courseworkid, 'allocatableid-allocatabletype', [$this->allocatableid, $this->allocatabletype]);

        if ($extension) {
            if ($extension->extended_deadline > time()) {
                $validextension = true;
            }
        }
        return $validextension;
    }

    public function can_be_unfinalised() {
        return  ($this->get_state() == self::FINALISED);
    }

    /**
     * check if the feedback by provided assessor exists
     *
     * @param $assessorid
     * @return bool|false|mixed|stdClass
     * @throws \dml_exception
     */
    public function has_specific_assessor_feedback($assessorid) {
        global $DB;

        $feedback = $DB->get_record('coursework_feedbacks', ['submissionid' => $this->id,
                                                                        'assessorid' => $assessorid]);

        return (empty($feedback)) ? false : $feedback;
    }

    // Caching

    /**
     * cache array
     *
     * @var
     */
    public static $pool;

    /**
     *
     * @param int $courseworkid
     * @return array
     */
    protected static function get_cache_array($courseworkid) {
        global $DB;
        $records = $DB->get_records(static::$tablename, ['courseworkid' => $courseworkid]);
        $result = [
            'id' => [],
            'allocatableid' => [],
            'finalised' => [],
            'allocatableid-allocatabletype' => [],
        ];
        if ($records) {
            foreach ($records as $record) {
                $object = new self($record);
                $result['id'][$record->id] = $object;
                $result['allocatableid'][$record->allocatableid][] = $object;
                $result['finalised'][$record->finalised][] = $object;
                $result['allocatableid-allocatabletype'][$record->allocatableid . '-' . $record->allocatabletype][] = $object;
            }
        }
        return $result;
    }

    /**
     *
     * @param int $courseworkid
     * @param $key
     * @param $params
     * @return bool
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
        self::remove_cache($this->courseworkid);
    }

    /**
     *
     */
    protected function after_destroy() {
        self::remove_cache($this->courseworkid);
    }

}
