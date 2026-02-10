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
 * Page that prints a table of all students and all markers so that first marker, second marker, moderators
 * etc can be allocated manually or automatically.
 *
 * @package    mod_coursework
 * @copyright  2011 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\models;

use AllowDynamicProperties;
use calendar_event;
use cm_info;
use coding_exception;
use context;
use context_course;
use context_module;
use core_availability\info_module;
use core_component;
use core_plugin_manager;
use dml_exception;
use Exception;
use grade_item;
use grading_manager;
use gradingform_controller;
use html_writer;
use mod_coursework\allocation\allocatable;
use mod_coursework\allocation\auto_allocator;
use mod_coursework\allocation\manager;
use mod_coursework\allocation\strategy\base as allocation_strategy_base;
use mod_coursework\auto_grader\auto_grader;
use mod_coursework\candidateprovider_manager;
use mod_coursework\cron;
use mod_coursework\export\grading_sheet;
use mod_coursework\framework\table_base;
use mod_coursework\plagiarism_helpers\base as plagiarism_base;
use mod_coursework\stages\assessor;
use mod_coursework\stages\base as stage_base;
use mod_coursework\stages\final_agreed;
use mod_coursework\stages\moderator;
use moodle_exception;
use stdClass;
use zip_packer;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

/**
 * Class representing a coursework instance.
 *
 * @property int grouping_id
 * @property int usegroups
 * @property int allowearlyfinalisation
 * @property mixed startdate
 * @author administrator
 */
#[AllowDynamicProperties]
class coursework extends table_base {
    /**
     * Cache area where objects by ID are stored.
     * @var string
     */
    const CACHE_AREA_IDS = 'courseworkids';

    /**
     * Event type for due or extension dates in mdl_event.
     */
    const COURSEWORK_EVENT_TYPE_DUE = 'due';

    /**
     * @var string
     */
    protected static $tablename = 'coursework';

    /**
     * @var int
     */
    public $formid;

    /**
     * @var int This is the courseid, but is called course to match the DB column. By convention, all Moodle
     * modules have a column called 'course' that holds the course id and other things will break if we change that.
     * We are using the coursework object as a saveable DB row, dso this has to stay the same. Do not access this
     * directly - use get_course_id() and set_course_id() to make the code easier to read.
     */
    public $course;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $intro;

    /**
     * @var int
     */
    public $introformat;

    /**
     * @var int unix timestamp
     */
    public $timecreated;

    /**
     * @var int unix timestamp
     */
    public $timemodified;

    /**
     * @var int|string
     */
    public $grade;

    /**
     * @var int unix timestamp
     */
    public $deadline;

    /**
     * @var int
     */
    public $numberofmarkers;

    /**
     * @var int
     */
    public $finalstagegrading;

    /**
     * @var int 0 or 1
     */
    public $blindmarking;

    /**
     * @var int
     */
    public $maxbytes;

    /**
     * The general feedback release date or 0 if not restricted by date.
     * @var string
     */
    public $generalfeedback;

    /**
     * @var string
     */
    public $individualfeedback;

    /**
     * The general feedback comment
     * @var string
     */
    public $feedbackcomment;

    /**
     * @var int
     */
    public $feedbackcommentformat;

    /**
     * @var int unix timestamp
     */
    public $generalfeedbacktimepublished;

    /**
     * @var int 0 or 1
     */
    public $allowlatesubmissions;

    /**
     * @var int 0 or 1
     */
    public $enablepdfjs;

    /**
     * @var int 0 or 1
     */
    public $enablegeneralfeedback;

    /**
     * @var string uses the constants in this class for undergraduate and postgraduate.
     */
    public $courseworktype;

    /**
     * @var int 0 or 1
     */
    public $moderationenabled;

    /**
     * @var int 0 or 1
     */
    public $allocationenabled;

    /**
     * @var string
     */
    public $moderatorallocationstrategy;

    /**
     * @var array
     */
    public $submissions;

    /**
     * @var array hold all the feedbacks for this coursework so that we can have them cached
     */
    public $feedbacks;

    /**
     * @var
     */
    public $filescache;

    /**
     * @var array
     */
    protected $stages = [];

    /**
     * @var context Instance of a moodle context, i.e. that of the coursemodule for this coursework.
     */
    private $context;

    /**
     * @var stdClass
     */
    public $student;

    /**
     * @var $coursework_submission submission
     */
    public $courseworksubmission;

    /**
     * @var stdClass
     */
    public $coursemodule;

    /**
     * @var int
     */
    public $numberofparticipants;

    /**
     * @var string name of the class used to manage allocations
     */
    public $assessorallocationstrategy;

    /**
     * @var manager
     */
    private $allocationmanager;

    /**
     * If set to 1, the feedback will be available to students as soon as the deadline passes, even if it's not published.
     *
     * @var int
     */
    public $autoreleasefeedback;

    /**
     * @var int 1 or 0
     */
    public $studentviewcomponentfeedbacks;

    /**
     * @var int 1 or 0
     */
    public $studentviewmoderatorfeedbacks;

    /**
     * @var int 1 or 0
     */
    public $showallfeedbacks;

    /**
     * @var 1 or 0
     */
    public $preventlatesubmissions;

    /**
     * @var int 1 or 0
     */
    public $studentviewfinalfeedback;

    /**
     * @var int 1 or 0
     */
    public $studentviewcomponentgrades;

    /**
     * @var int 1 or 0
     */
    public $studentviewfinalgrade;

    /**
     * @var int 1 or 0
     */
    public $studentviewmoderatorgrade;

    /**
     * Defines the prefix used for form names and DB tables and fields so we can dynamically use allocation
     * strategies for more purposes.
     */
    const ASSESSOR = 'assessor';

    /**
     * Defines the prefix used for form names and DB tables and fields so we can dynamically use allocation
     * strategies for more purposes.
     */
    const MODERATOR = 'moderator';

    /**
     * @var int
     */
    public $maxfiles;

    /**
     * @var int
     */
    public $renamefiles;

    /**
     * @var int
     */
    public int $usecandidate;

    /**
     * @var string
     */
    public $filetypes;

    /**
     * @var stdClass We use this because course in the module tables is always a course id integer and the active record pattern
     * need it to stay this way.
     */
    protected $courseobject;

    /**
     * @var
     */
    protected $gradebookgrades;

    /**
     * @var int
     */
    public $courseworkid;

    /**
     * @var int
     */
    public $submissionid;

    /**
     * @var int
     */
    public $cmid;

    /**
     * @var int 0 or 1
     */
    public $extensionsenabled;

    /**
     * @var int
     */
    public $markingdeadlineenabled;

    /**
     * @var int
     */
    public $initialmarkingdeadline;

    /**
     * @var int
     */
    public $agreedgrademarkingdeadline;

    /**
     * @var int 0 or 1
     */
    public $personaldeadlineenabled;



    /**
     * Is turnitin enabled?
     * @var bool
     */
    protected $turnitinenabled;

    /**
     * Gets the relevant course module and caches it.
     *
     * @return mixed|stdClass
     * @throws moodle_exception
     */
    public function get_course_module() {

        global $DB;

        if (!isset($this->coursemodule)) {
            if (empty($this->id)) {
                throw new moodle_exception('Trying to get course module for a coursework that has not yet been saved');
            }
            $modulerecord = module::$pool['name']['coursework'] ?? $DB->get_record('modules', ['name' => 'coursework']);
            $moduleid = $modulerecord->id;
            $courseid = $this->get_course_id();
            if (!isset(course_module::$pool['course-module-instance'][$courseid][$moduleid][$this->id]->id)) {
                course_module::$pool['course-module-instance'][$courseid][$moduleid][$this->id] =
                    $DB->get_record('course_modules', ['course' => $courseid, 'module' => $moduleid, 'instance' => $this->id], '*', MUST_EXIST);
            }
            $this->coursemodule = course_module::$pool['course-module-instance'][$courseid][$moduleid][$this->id];
        }

        return $this->coursemodule;
    }

    /**
     * @param $coursemodule
     * @return cm_info
     * @throws moodle_exception
     */
    public function cm_object($coursemodule) {

        $modinfo = get_fast_modinfo($this->get_course_id());
        return $this->coursemodule = $modinfo->get_cm($coursemodule->id);
    }

    /**
     * @return bool
     */
    public function allow_late_submissions() {
        return (bool)$this->allowlatesubmissions;
    }

    /**
     * @return bool
     * @throws dml_exception
     */
    public function enablepdfjs() {
        static $enabled;

        if (!isset($enabled)) {
            $enabled = (bool)get_config('core', 'coursework_enablepdfjs');
        }
        return $enabled;
    }

    /**
     * Returns the id of the associated coursemodule, if there is one. Otherwise false.
     *
     * @return int
     * @throws moodle_exception
     */
    public function get_coursemodule_id() {
        $coursemodule = $this->get_course_module();
        return (int)$coursemodule->id;
    }

    /**
     * Returns the idnumber of the associated coursemodule, if there is one. Otherwise false.
     *
     * @return int
     * @throws moodle_exception
     */
    public function get_coursemodule_idnumber() {
        $coursemodule = $this->get_course_module();
        return (int)$coursemodule->idnumber;
    }

    /**
     * Getter function for the coursework's course object.
     *
     * @return object
     * @throws dml_exception
     */
    public function get_course() {
        return get_course($this->get_course_id());
    }

    /**
     * Getter function for the coursework's course object.
     *
     * @return context
     */
    public function get_course_context() {
        return context_course::instance($this->get_course_id());
    }

    /**
     * Getter function for the course id. Avoids confusion because it needs to be called course (see docs for
     * that property).
     *
     * @return int
     */
    public function get_course_id() {
        return (int)$this->course;
    }

    /**
     * Setter function for the course id. Avoids confusion because it needs to be called course (see docs for
     * that property).
     *
     * @param $courseid
     * @return void
     */
    public function set_course_id($courseid) {
        $this->course = $courseid;
    }

    /**
     * Getter for DB deadline field.
     *
     * @return int
     */
    public function get_deadline() {
        return $this->deadline;
    }

    /**
     * Getter for DB deadline field.
     *
     * @return bool
     */
    public function deadline_has_passed() {
        return ($this->has_deadline() && $this->deadline < time());
    }

    public function has_deadline() {
        return !empty($this->deadline);
    }

    /**
     * Gets all the submissions for this coursework, as raw DB rows. This is a more efficient way, compared to
     * multiple calls to the DB, once for every row. Later functions will filter many of these out due to permissions
     * issues.
     *
     * @param array $groups array of group ids
     * @return stdClass[] array of objects
     */
    public function get_participants($groups = [0]) {

        if (is_array($this->submissions)) {
            return $this->submissions;
        }

        // Fetch the list of ids of all participants - this may get really long so fetch just id.
        $groups = (array)$groups;
        $allusers = [];
        foreach ($groups as $groupdid) {
            $allusers = array_merge($allusers, get_enrolled_users($this->get_context(), 'mod/coursework:submit', $groupdid, 'u.id'));
        }

        return $allusers;
    }

    /**
     * Find out how many markers there should be.
     *
     * @return int Cast to int so we can do strict comparisons elsewhere
     */
    public function get_max_markers() {
        return (int)$this->numberofmarkers;
    }

    /**
     * Returns the context object for this coursework.
     *
     * @return context
     */
    public function get_context() {

        if (empty($this->context)) {
            $this->context = context_module::instance($this->get_coursemodule_id());
        }
        return $this->context;
    }

    /**
     * Returns the context object for this coursework.
     *
     * @return int
     */
    public function get_context_id() {

        return $this->get_context()->id;
    }

    /**
     * Returns the initial marking deadline timestamp
     *
     * @return int
     */
    public function get_initial_marking_deadline() {
        return  (!empty($this->initialmarkingdeadline)) ? $this->initialmarkingdeadline : 0;
    }

    /**
     * Returns the initial marking deadline timestamp
     *
     * @return int
     */
    public function get_agreed_grade_marking_deadline() {
          return  (!empty($this->agreedgrademarkingdeadline)) ? $this->agreedgrademarkingdeadline : 0;
    }

    /**
     * Returns array of file storage options to be used across the whole coursework
     *
     * @return array
     * @throws dml_exception
     */
    public function get_file_options() {
        global $CFG;

        require_once($CFG->dirroot . '/repository/lib.php');

        $turnitinenabled = $this->tii_enabled();

        // Turn it in only allows one file.
        $maxfiles = $this->maxfiles;

        // Turn it in only likes some file types.
        /* DOC, DOCX, Corel
         * WordPerfect, HTML, Adobe
         * PostScript, TXT, RTF, and PDF
         */
        if ($turnitinenabled) {
            $filetypes = ['.doc',
                                '.docx',
                                '.txt',
                                '.html',
                                '.eps',
                                '.pdf',
                                '.htm',
                                '.rtf'];
        } else if (!empty($this->filetypes)) {
            $filetypes = preg_split('/,+\s?|\s+/', $this->filetypes);
            foreach ($filetypes as $key => $filetype) {
                $filetype = trim($filetype); // Remove whitespace
                if (empty($filetype)) {
                    unset($filetypes[$key]);
                    continue;
                }
                if (!str_contains($filetype, '.')) {
                    $filetype = '.' . $filetype; // Add dot if not there
                } else {
                    $filetype = strrchr($filetype, '.'); // Remove leading characters e.g. *.doc => .doc
                }
                $filetypes[$key] = $filetype;
            }
        } else {
            $filetypes = '*';
        }

        return [
            'subdirs' => false,
            'maxbytes' => $this->get_max_bytes(),
            'maxfiles' => $maxfiles,
            'accepted_types' => $filetypes,
            'return_types' => FILE_INTERNAL,
        ];
    }

    /**
     * Finds out how large an uploaded file is allowed to be, returning coursemodule, course or site
     * defaults in that order of preference.
     *
     * @return int
     */
    public function get_max_bytes() {
        global $CFG;

        if (!empty($this->maxbytes)) {
            return $this->maxbytes;
        } else {
            $course = $this->get_course();
            if (!empty($course->maxbytes)) {
                return $this->maxbytes = $course->maxbytes;
            } else {
                return $this->maxbytes = $CFG->maxbytes;
            }
        }
    }

    /**
     * This will fetch and cache all the grades form the gradebook for this coursework.
     *
     * @return bool
     */
    public function get_grading_info() {

        if (!is_null($this->gradebookgrades)) {
            return $this->gradebookgrades;
        }
        return false;
        // Get them for this whole coursework.
    }

    /**
     * If not set for this coursework, use the one from the site settings.
     *
     * @return int timestamp
     */
    public function get_individual_feedback_deadline() {

        if ($this->individualfeedback > 0) {
            return $this->individualfeedback;
        } else {
            // This was previously set to default to two weeks if not set, but this conflicts with the requirement to
            // sometimes allow the feedback to be released whenever it is provided.
            return $this->deadline;
        }
    }

    /**
     * @param allocatable $allocatable
     * @return string
     */
    public function get_allocatable_identifier_hash($allocatable) {
        if ($allocatable->type() == 'user' && $this->blindmarking) {
            return $this->get_username_hash($allocatable->id());
        }
        return $allocatable->id();
    }

    /**
     * @return plagiarism_base[]
     */
    public function get_plagiarism_helpers() {
        $enabledplagiarismplugins = array_keys(core_component::get_plugin_list('plagiarism'));
        $objects = [];
        foreach ($enabledplagiarismplugins as $pluginname) {
            $classname = "\\mod_coursework\\plagiarism_helpers\\{$pluginname}";
            if (class_exists($classname)) {
                /**
                 * @var plagiarism_base $helper
                 */
                $helper = new $classname($this);
                if ($helper->enabled()) {
                    $objects[] = $helper;
                }
            }
        }
        return $objects;
    }

    /**
     * @param int $userid
     * @return bool
     */
    public function is_assessor(int $userid) {
        foreach ($this->marking_stages() as $stage) {
            if ($stage->user_is_assessor($userid)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    public function plagiarism_enbled() {
        $plagiarismhelpers = $this->get_plagiarism_helpers();
        foreach ($plagiarismhelpers as $helper) {
            if ($helper->enabled()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    public function plagiarism_flagging_enabled() {
        return (bool)$this->plagiarismflagenabled;
    }

    /**
     * @return bool
     */
    public function early_finalisation_allowed(): bool {
        return (bool)$this->allowearlyfinalisation;
    }

    /**
     * Pushes all grades form the coursework into the gradebook. Will overwrite any older grades.
     *
     * @return void
     * @throws \invalid_parameter_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function publish_grades() {
        $submissions = $this->get_submissions_to_publish();
        foreach ($submissions as $submission) {
            $submission->publish();
        }
    }

    /**
     * @param $params
     * @return array
     * @throws coding_exception
     */
    public static function get_view_params($params) {
        if (optional_param('page', 0, PARAM_INT) > 0) {
            $params['page'] = optional_param('page', 0, PARAM_INT);
        }
        if (optional_param('sortby', 0, PARAM_TEXT) !== '') {
            $params['sortby'] = optional_param('sortby', '', PARAM_TEXT);
        }
        if (optional_param('sorthow', 0, PARAM_TEXT) !== '') {
            $params['sorthow'] = optional_param('sorthow', '', PARAM_TEXT);
        }
        return $params;
    }

    /**
     * Generate zip file from array of given files
     *
     * @return bool | string path of temp file - note this returned file does not have a .zip
     * extension - it is a temp file.
     * @throws coding_exception
     * @throws dml_exception
     */
    public function pack_files() {
        global $CFG, $DB;

        $context = context_module::instance($this->get_coursemodule_id());

        $submissions = $DB->get_records(
            'coursework_submissions',
            ['courseworkid' => $this->id, 'finalisedstatus' => submission::FINALISED_STATUS_FINALISED]
        );
        if (!$submissions) {
            return false;
        }
        $filesforzipping = [];
        $fs = get_file_storage();

        $gradingsheet = new grading_sheet($this, null, null);
        // get only submissions that user can grade
        $submissions = $gradingsheet->get_submissions();

        foreach ($submissions as $submission) {
            // If allocations are in use, then we don't supply files that are not allocated.
            $submission = submission::get_from_id($submission->id);

            $files = $fs->get_area_files(
                $context->id,
                'mod_coursework',
                'submission',
                $submission->id,
                "id",
                false
            );
            foreach ($files as $f) {
                $filename = basename($f->get_filename());

                $foldername = '';

                if ($this->blindmarking == 0 || has_capability('mod/coursework:viewanonymous', $this->get_context())) {
                    $submissionuser = $submission->get_allocatable();
                    if ($this->is_configured_to_have_group_submissions() && $submissionuser->name) {
                        $foldername = $submissionuser->name . '_';
                    } else if (!$this->is_configured_to_have_group_submissions()) {
                        $foldername = $submissionuser->firstname . ' ' . $submissionuser->lastname . '_';
                    }
                }

                $foldername .= $this->get_username_hash($submission->get_allocatable()->id());

                $filename = $foldername . '/' . $filename;

                $filesforzipping[$filename] = $f;
            }
        }

        // Create path for new zip file.
        $tempzip = tempnam($CFG->dataroot . '/temp/', 'ocm_');
        // Zip files.
        $zipper = new zip_packer();
        if ($zipper->archive_to_pathname($filesforzipping, $tempzip)) {
            return $tempzip;
        }
        return false;
    }

    /**
     * Makes code cleaner than writing MATHS all over the place!!
     * @return bool
     */
    public function has_multiple_markers() {
        return $this->numberofmarkers > 1;
    }

    /**
     * Does this coursework use a numeric grade or something else (e.g. no grade, scale)?
     * @see make_grades_menu() in core which shows the logic behind this.
     * @return bool
     */
    public function uses_numeric_grade(): bool {
        return $this->grade > 0;
    }

    /**
     * Returns the maximum grade a student can achieve.
     *
     * @return int
     */
    public function get_max_grade(): int {
        if (!$this->uses_numeric_grade()) {
            throw new \core\exception\coding_exception("This coursework does not use numeric grades");
        }
        return $this->grade;
    }

    /**
     * @param $strategyname
     * @return bool|void
     */
    public function set_assessor_allocation_strategy($strategyname) {

        if ($this->allocation_strategy_is_valid($strategyname)) {
            $this->update_attribute('assessorallocationstrategy', $strategyname);
            return;
        }
        return false;
    }

    /**
     * Tests that the name of the supplied strategy is actually a real allocation strategy class.
     *
     * @param string $strategyname
     * @return bool
     */
    protected function allocation_strategy_is_valid($strategyname) {
        $classname = '\mod_coursework\allocation\strategy\\' . $strategyname;
        if (class_exists($classname)) {
            $class = new $classname($this);
            if ($class instanceof allocation_strategy_base) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ensures we only have one. Sort-of singleton pattern.
     *
     * @return manager
     * @throws coding_exception
     */
    public function get_allocation_manager() {

        if (!isset($this->allocationmanager)) {
            $this->allocationmanager = new manager($this);
        }

        if (!($this->allocationmanager instanceof manager)) {
            throw new coding_exception('Cannot instantiate allocation manager');
        }

        return $this->allocationmanager;
    }

    /**
     * Returns the user that was supplied if groups are not enabled, or the correct group
     * if groups are enabled.
     *
     * @param user $user
     * @return allocatable
     * @throws coding_exception
     * @throws dml_exception
     */
    public function submiting_allocatable_for_student($user) {
        if ($this->is_configured_to_have_group_submissions()) {
            return $this->get_coursework_group_from_user_id($user->id());
        } else {
            return $user;
        }
    }

    /**
     * Tells us if moderators are being asked to remark this assessment.
     *
     * @return bool
     */
    public function moderation_enabled() {
        return (bool)$this->moderationenabled;
    }

    /**
     * Lets us know if the strategy in use uses allocations at all.
     * @return bool
     */
    public function allocation_enabled() {
        return (bool)$this->allocationenabled;
    }

    /**
     * Lets us know if the moderations agreement has been enabled.
     * @return bool
     */
    public function moderation_agreement_enabled() {
        return (bool)$this->moderationagreementenabled;
    }

    /**
     * Will delegate the save operation to the strategy class.
     *
     * @param string $shortname
     * @return void
     */
    public function save_allocation_strategy_options($shortname) {
        $classname = '\mod_coursework\allocation\strategy\\' . $shortname;

        $strategy = new $classname($this);

        $strategy->save_allocation_strategy_options();
    }

    /**
     * Tells us the deadline for a specific user.
     *
     * @param int $userid
     * @return int
     */
    public function get_user_deadline($userid) {

        return $this->deadline;
    }

    /**
     * Is there any submission that has not been moderated, but which should have been? This is used to prevent publishing if more
     * work needs moderating.
     *
     */
    public function unmoderated_work_exists() {

        global $DB;

        $sql = "
            SELECT 1
              FROM {coursework_submissions} s
             WHERE NOT EXISTS (SELECT 1
                                 FROM {coursework_feedbacks} f
                                WHERE s.id = f.submissionid
                                  AND f.ismoderation = 1)
               AND s.courseworkid = :courseworkid
        ";

        // If moderator allocations are in use, we want to allow submissions without moderations if they
        // have no allocation.
        $rules = $this->get_allocation_manager()->get_moderation_set_rules();
        if (!empty($rules)) {
            $sql .= " AND EXISTS (SELECT 1 FROM {coursework_allocation_pairs} p
                                         WHERE s.userid = p.studentid
                                           AND p.moderator = 1
                                           AND p.courseworkid = s.courseworkid)";
        }
        $params = [
            'courseworkid' => $this->id,
        ];

        return $DB->record_exists_sql($sql, $params);
    }

    /**
     * Check if current assessor is not already allocated for this submission in different stage
     *
     * @param allocatable $allocatable
     * @param $userid
     * @param $stage
     * @return bool
     * @throws \core\exception\coding_exception
     */
    public function assessor_has_allocation_for_student_not_in_current_stage($allocatable, $userid, $stage) {

        global $USER;
        if (!$userid) {
            $userid = $USER->id;
        }
        allocation::fill_pool_coursework($this->id);
        $records = isset(allocation::$pool[$this->id]['allocatableid-allocatabletype-assessorid'][$allocatable->id() . '-' . $allocatable->type() . "-$userid"]) ?
            allocation::$pool[$this->id]['allocatableid-allocatabletype-assessorid'][$allocatable->id() . '-' . $allocatable->type() . "-$userid"] : [];

        foreach ($records as $record) {
            if ($record->stageidentifier != $stage) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets all the submissions at once for the grading table.
     *
     * @return submission[]
     * @throws \core\exception\coding_exception
     */
    public function get_all_submissions(): array {
        return submission::get_all_for_coursework($this->id);
    }

    /**
     * Returns a hash of this user's id. Not username as this would be non-unique across
     * different courseworks, compromising anonymity.
     *
     * TODO: Fix broken hash in a backward compatible way.
     *       Should be "$id|userid" or similar
     * TODO: Fix all calls to prefix "aa" or something to keep Excel happy
     * @param int $userid
     * @return string
     */
    public static function get_name_hash($id, $userid, $time = 1440000609) {
        if ($id < 1) {
            return '';
        }

        $uhash = $id . $userid;

        // Hash with zero have the potential to become changed in outside programs
        // So we generate a hash without a leading zero
        $uhash = substr(md5($uhash), 0, 8);
        return 'X' . $uhash;
    }

    public function get_username_hash($userid) {
        return static::get_name_hash($this->id, $userid, $this->timecreated);
    }

    /**
     * Returns the allocation record if there is one.
     *
     * @param int $studentid
     * @return bool|allocation
     * @throws dml_exception
     */
    public function get_moderator_allocation($studentid) {

        global $DB;

        $params = [
            'courseworkid' => $this->id,
            'studentid' => $studentid,
            'moderator' => 1,
        ];
        $moderatorallocation = $DB->get_record('coursework_allocation_pairs', $params);
        if ($moderatorallocation) {
            $moderatorallocation = new allocation($moderatorallocation, $this);
        }

        return $moderatorallocation;
    }

    /**
     * Tells us whether this submission is set to be moderated by looking for an allocation.
     *
     * @param int $studentid
     * @return bool
     */
    public function moderator_is_allocated($studentid) {
        return (bool)$this->get_moderator_allocation($studentid);
    }

    /**
     * Fetches the allocations for this submission so we know who is to mark it.
     *
     * @param $allocatable
     * @param $stageidentifier
     * @return allocation $allocation
     * @throws dml_exception
     */
    public function get_assessor_allocation($allocatable, $stageidentifier) {
        global $DB;

        $params = [
            'courseworkid' => $this->id,
            'allocatableid' => $allocatable->allocatableid,
            'stageidentifier' => $stageidentifier,
            'allocatabletype' => $allocatable->allocatabletype,
        ];
        return $DB->get_record('coursework_allocation_pairs', $params);
    }

    public function get_assessors_stageidentifier($allocatableid, $assessorid) {
        global $DB;

        $params = [
            'courseworkid' => $this->id,
            'assessorid' => $assessorid,
            'allocatableid' => $allocatableid,
        ];

        $stageidentifier = $DB->get_record('coursework_allocation_pairs', $params);

        return $stageidentifier->stageidentifier;
    }

    /**
     * Tells us when a student should be able to see the feedback for their submission.
     *
     * @return int
     */
    public function get_student_feedback_release_date() {

        $normalsubmissiondeadline = $this->deadline;
        $normalfeedbackdeadline = $this->get_individual_feedback_deadline();
        $normaloffset = $normalfeedbackdeadline - $normalsubmissiondeadline;

        return $this->deadline + $normaloffset;
    }

    /**
     * Gives us an array of students who have not yet submitted their work and finalised it so that they can maybe have
     * work submitted on their behalf by others.
     *
     * @param string $fields
     * @return array
     * @throws \core\exception\coding_exception
     */
    public function get_unfinalised_students($fields = 'u.id, u.firstname, u.lastname') {

        $students = get_enrolled_users(context_course::instance($this->get_course_id()), 'mod/coursework:submit', 0, $fields);
        $submissions = submission::get_all_for_coursework($this->id);
        foreach ($submissions as $submission) {
            if ($submission->finalisedstatus == submission::FINALISED_STATUS_FINALISED) {
                unset($students[$submission->userid]);
            }
        }
        return $students;
    }

    /**
     * If the grades are saved, then we may have to re-do the moderator allocation set based on the final grade
     * that the student got e.g. if we want to moderate only those who have scored below 30%.
     */
    public function grade_changed_event() {

        global $DB;

        // Are we using graded boundaries for the moderations set?
        $params = [
            'courseworkid' => $this->id,
        ];
        $sql = "
            SELECT id
              FROM {coursework_mod_set_rules} rules
             WHERE courseworkid = :courseworkid
               AND rulename LIKE '%grade%'
        ";
        if ($DB->record_exists_sql($sql, $params)) {
            // Re-do it.
            $manager = $this->get_allocation_manager();
            $manager->auto_generate_moderation_set();

            $allocator = new auto_allocator($this);
            $allocator->process_allocations();
            // Possibly, the moderation set now does not include someone who used to be in it (grade changed up or down).
            // This means we need to wipe them and start again.
        }
    }

    /**
     * @return int
     */
    public function students_can_view_all_feedbacks() {
        return $this->showallfeedbacks;
    }

    /**
     * Tells us whether the deadline has passed and late submissions are prevented. Specific to the current user,
     * who needs to be a student.
     */
    public function allowed_to_submit() {
        global $USER;

        return (time() < $this->get_user_deadline($USER->id)) || $this->allow_late_submissions();
    }

    /**
     * Get the coursework group from a user object.
     * We pass this directly to get_coursework_group_from_user_id(), but keep this because plagiarism/turnitin/lib.php uses it.
     * @param user $student
     * @return bool|table_base
     * @throws dml_exception
     */
    public function get_student_group($student) {
        return $this->get_coursework_group_from_user_id($student->id());
    }

    /**
     * Get the coursework group from a user ID.
     * @param int $userid
     * @return bool|table_base
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_coursework_group_from_user_id(int $userid) {
        global $DB;

        if (!$this->is_configured_to_have_group_submissions() && $this->assessorallocationstrategy != 'group_assessor') {
            throw new coding_exception('Asking for a student group when groups are disabled.');
        }

        if ($this->grouping_id) {
                $sql = "SELECT g.*
                  FROM {groups} g
            INNER JOIN {groupings_groups} groupings
                    ON g.id = groupings.groupid
            INNER JOIN {groups_members} gm
                    ON gm.groupid = g.id
                 WHERE gm.userid = :userid
                   AND g.courseid = :courseid
                   AND groupings.groupingid = :grouping_id
                 LIMIT 1";
            $params = [
                'grouping_id' => $this->grouping_id,
                'courseid' => $this->get_course()->id,
                'userid' => $userid];
        } else {
            $sql = "SELECT g.*
                  FROM {groups} g
            INNER JOIN {groups_members} gm
                    ON gm.groupid = g.id
                 WHERE gm.userid = :userid
                   AND g.courseid = :courseid
                 LIMIT 1";
            $params = ['userid' => $userid, 'courseid' => $this->get_course()->id];
        }
        $group = $DB->get_record_sql($sql, $params);
        return $group ? group::get_from_id($group->id) : false;
    }

    /**
     * @param user|null $user
     * @return submission|null;
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_user_submission(?user $user): ?submission {
        if (!$user) {
            return null;
        }
        if ($this->is_configured_to_have_group_submissions()) {
            $allocatable = $this->get_coursework_group_from_user_id($user->id());
            return $allocatable
                ? submission::get_for_allocatable($this->id, $allocatable->id(), $allocatable->type())
                : null;
        } else {
            return submission::get_for_allocatable($this->id, $user->id(), 'user');
        }
    }

    /**
     * @param user $user
     * @return bool
     */
    public function has_user_submission($user): bool {
        return (bool)$this->get_user_submission($user);
    }

    /**
     * @param user $user
     * @return table_base
     * @throws coding_exception
     */
    public function build_own_submission($user) {
        if ($this->is_configured_to_have_group_submissions()) {
            $allocatable = $this->get_coursework_group_from_user_id($user->id());
        } else {
            $allocatable = $user;
        }
        $ownsubmissionparams = [
            'courseworkid' => $this->id,
            'allocatableid' => $allocatable->id(),
            'allocatabletype' => $allocatable->type(),
        ];
        return submission::build($ownsubmissionparams);
    }

    /**
     * @return bool
     */
    public function is_configured_to_have_group_submissions(): bool {
        return (bool)$this->usegroups;
    }

    /**
     * @return string user or group
     */
    public function get_allocatable_type() {
        if ($this->is_configured_to_have_group_submissions()) {
            return 'group';
        } else {
            return 'user';
        }
    }

    /**
     * @return bool
     */
    public function students_can_still_submit() {
        return $this->deadline >= time();
    }

    /**
     * @param $deadline - coursework deadline, individually extended deadline
     * @return bool
     */
    public function due_to_send_first_reminders($deadline) {
        global $CFG;

        return ($deadline - $CFG->coursework_day_reminder * 86400) < time();
    }

    /**
     * @param $deadline - courseworkindividually extended deadline
     * @return bool
     */
    public function due_to_send_second_reminders($deadline) {
        global $CFG;

        return ($deadline - $CFG->coursework_day_second_reminder * 86400) < time();
    }

    /**
     * @return user[]
     * @throws moodle_exception
     */
    public function get_students() {
        $users = [];
        $rawusers = get_enrolled_users($this->get_context(), 'mod/coursework:submit');

        // filter students who are restricted from the coursework
        $cm = $this->get_course_module();
        $cmobject = $this->cm_object($cm);

        $info = new info_module($cmobject);
        $rawusers = $info->filter_user_list($rawusers);

        foreach ($rawusers as $rawuser) {
            $users[$rawuser->id] = new user($rawuser);
        }
        return $users;
    }

    /**
     * @return int[]|string[]
     */
    public function get_student_ids() {
        $rawusers = get_enrolled_users($this->get_context(), 'mod/coursework:submit', 0, 'u.id');
        return array_keys($rawusers);
    }

    /**
     * @return user[]
     */
    public function get_students_who_have_not_yet_submitted() {
        $students = $this->get_students();
        foreach ($students as $key => $student) {
            if ($this->has_user_submission($student)) {
                unset($students[$key]);
            }
        }
        return $students;
    }

    /**
     * @param allocatable $student
     * @return user[]
     */
    public function initial_assessors($student) {
        $assessors = [];
        $stages = $this->get_assessor_marking_stages();
        // If allocations, send the allocated teachers.
        // Otherwise send everyone.
        foreach ($stages as $stage) {
            if ($this->allocation_enabled()) {
                $teacher = $stage->allocated_teacher_for($student);
                if ($teacher) {
                    $assessors[] = $teacher;
                }
            } else {
                $assessors = array_merge($assessors, $stage->get_teachers());
            }
        }
        return array_unique($assessors);
    }

    public function disable_general_feedback() {
        $this->update_attribute('generalfeedback', 0);
    }

    public function is_general_feedback_enabled() {
        return !empty($this->generalfeedback);
    }

    public function enable_general_feedback() {
        $this->update_attribute('generalfeedback', strtotime('1 week ago'));
    }

    /**
     * @return bool
     */
    public function blindmarking_enabled() {
        return (bool)$this->blindmarking;
    }

    /**
     * @return bool
     */
    public function viewinitialgradeenabled() {
        return (bool)$this->viewinitialgradeenabled;
    }
    /**
     * @return bool
     */
    public function individual_feedback_deadline_has_passed() {
        return $this->individualfeedback <= time();
    }

    /**
     * @return bool
     */
    public function percentage_allocations_enabled() {
        return $this->allocation_enabled() && $this->assessorallocationstrategy == 'percentages';
    }

    /**
     * @param user $student
     *
     * @return bool
     * @throws dml_exception
     */
    public function student_is_in_any_group($student) {
        global $DB;

        if ($this->grouping_id != 0) {
            $sql = "SELECT 1
                      FROM {groups_members} m
                INNER JOIN {groups} g
                        ON g.id = m.groupid
                INNER JOIN {groupings_groups} gr
                        ON gr.groupid = g.id
                     WHERE m.userid = :userid
                       AND g.courseid = :courseid
                       AND gr.groupingid = :groupingid
                ";

            $params = [
                'userid' => $student->id,
                'courseid' => $this->get_course()->id,
                'groupingid' => $this->grouping_id,
            ];
            return $DB->record_exists_sql($sql, $params);
        } else {
            $sql = "SELECT 1
                      FROM {groups_members} m
                INNER JOIN {groups} g
                        ON g.id = m.groupid
                     WHERE m.userid = :userid
                       AND g.courseid = :courseid
                ";

            return $DB->record_exists_sql(
                $sql,
                ['userid' => $student->id,
                'courseid' => $this->get_course()->id]
            );
        }
    }

    /**
     * @return bool
     */
    public function sampling_enabled() {
        return  (bool)$this->samplingenabled;
    }

    /**
     * @return bool
     */
    public function automaticagreement_enabled() {
        return $this->automaticagreementstrategy != null;
    }

    /**
     * @return bool
     */
    public function autopopulatefeedbackcomment_enabled() {
        return (bool)$this->autopopulatefeedbackcomment;
    }

    /**
     * @return bool
     */
    public function is_using_advanced_grading(): bool {
        $gradingmanager = $this->get_advanced_grading_manager();
        if ($gradingmanager) {
            return (bool)$gradingmanager->get_active_controller();
        }
        return false;
    }

    /**
     * @return bool|gradingform_controller|null
     */
    public function get_advanced_grading_active_controller() {
        $gradingmanager = $this->get_advanced_grading_manager();
        if ($gradingmanager) {
            $controller = $gradingmanager->get_active_controller();
            $menu = make_grades_menu($this->grade);
            $controller->set_grade_range($menu, $this->grade > 0);
            return $controller;
        }
        return false;
    }

    /**
     * @return grading_manager
     */
    protected function get_advanced_grading_manager() {
        return get_grading_manager($this->get_context(), 'mod_coursework', 'submissions');
    }

    /**
     * Check if rubric is used in the current coursework.
     *
     * @return bool
     */
    public function is_using_rubric(): bool {
        return self::get_advanced_grading_method() === 'rubric';
    }

    /**
     * Check if marking guide is used in the current coursework.
     *
     * @return bool
     */
    public function is_using_marking_guide(): bool {
        return self::get_advanced_grading_method() === 'guide';
    }

    /**
     * Get advanced grading method used in the current coursework.
     *
     * @return string|null
     */
    public function get_advanced_grading_method(): ?string {
        $gradingmanager = $this->get_advanced_grading_manager();
        return $gradingmanager ? $gradingmanager->get_active_method() : null;
    }

    /**
     * Function to get all coursework's rubric criteria
     *
     * @return mixed
     */
    public function get_rubric_criteria() {
        $controller = $this->get_advanced_grading_active_controller();
        return $controller->get_definition()->rubric_criteria;
    }

    /**
     * Returns an array of marking stages that define the different marking events for each submission.
     *
     * @return stage_base[]
     */
    public function marking_stages() {

        if (empty($this->stages)) {
            for ($i = 1; $i <= $this->get_max_markers(); $i++) {
                $identifier = 'assessor_' . $i;
                $this->stages[$identifier] = new assessor($this, $identifier);
            }

            if ($this->get_max_markers() > 1) {
                $identifier = 'final_agreed_1';
                $this->stages[$identifier] = new final_agreed($this, $identifier);
            }

            if ($this->moderation_agreement_enabled()) {
                $identifier = 'moderator';
                $this->stages[$identifier] = new moderator($this, $identifier);
            }
        }

        return $this->stages;
    }

    /**
     * Temporary messy solution only used in the bit that makes the grading report cells.
     *
     * @return stage_base[]
     */
    public function get_assessor_marking_stages() {
        $stages = [];

        for ($i = 1; $i <= $this->get_max_markers(); $i++) {
            $stages[] = new assessor($this, 'assessor_' . $i);
        }
        if ($this->moderation_agreement_enabled()) {
            $stages[] = new moderator($this, 'moderator');
        }

        return $stages;
    }

    public function get_submission_notification_users() {
        return $this->submissionnotification;
    }

    /**
     * @return allocatable[]
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function get_allocatables() {
        global $DB;

        $allocatables = [];

        if ($this->is_configured_to_have_group_submissions()) {
            if ($this->grouping_id) {
                $sql = "
                SELECT g.*
                  FROM {groups} g
            INNER JOIN {groupings_groups} groupings
                    ON g.id = groupings.groupid
                 WHERE g.courseid = :courseid
                   AND groupings.groupingid = :grouping_id
            ";
                $params = [
                    'grouping_id' => $this->grouping_id,
                    'courseid' => $this->get_course()->id,
                ];
            } else {
                $sql = "SELECT * FROM {groups} WHERE courseid = :courseid";
                $params = [
                    'courseid' => $this->get_course()->id,
                ];
            }

            $groups = $DB->get_records_sql($sql, $params);
            foreach ($groups as $group) {
                $group = group::get_from_id($group->id);
                // Find out if members of this group can access this coursework, if group is left without members then remove it
                $cm = $this->get_course_module();
                $cmobject = $this->cm_object($cm);
                /**
                 * @var group $group
                 */
                $members = $group->get_members($this->get_context(), $cmobject);
                if (empty($members)) {
                    continue;
                }
                $allocatables[$group->id] = $group;
            }
        } else {
            $allocatables = $this->get_students();
        }
        return $allocatables;
    }

    /**
     * @return array
     */
    public function get_allocatable_ids() {
        return array_keys($this->get_allocatables());
    }

    /**
     * @return int
     */
    public function number_of_allocatables() {
        return  count($this->get_allocatables());
    }

    /**
     * We cache these so that we can cache result sets inside the stages for calls like has_alloction()
     *
     * @param $identifier
     * @return stage_base
     */
    public function get_stage($identifier) {

        if (!array_key_exists($identifier, $this->stages)) {
            if ($identifier != 'moderator') {
                $stagename = substr($identifier, 0, strripos($identifier, '_'));
            } else {
                $stagename = $identifier;
            }
            $classname = "\\mod_coursework\\stages\\" . $stagename;
            return new $classname($this, $identifier);
        }

        return $this->stages[$identifier];
    }

    /**
     * @return submission[]
     * @throws coding_exception
     */
    public function get_submissions_to_publish() {
        $params = [
            'courseworkid' => $this->id,
        ];

        $submissions = submission::find_all($params);
        /**
         * @var submission $submission
         */
        foreach ($submissions as $key => $submission) {
            if (!$submission->ready_to_publish()) {
                unset($submissions[$key]);
            }
        }

        return $submissions;
    }

    /**
     * @return final_agreed
     */
    public function get_final_agreed_marking_stage() {
        return new final_agreed($this, 'final_agreed_1');
    }

    /**
     * @return moderator
     */
    public function get_moderator_marking_stage() {
        return new moderator($this, 'moderator_1');
    }

    /**
     * @return bool
     * @throws coding_exception
     */
    public function has_stuff_to_publish() {
        $submissions = $this->get_submissions_to_publish();
        return !empty($submissions);
    }

    /**
     * Is there general feedback available to students?
     *
     * @return bool True if there's general feedback and "General feedback
     * release date" is not enabled or it is enabled and the date has passed,
     * false otherwise.
     */
    public function is_general_feedback_released() {
        if ($this->get_general_feedback() && ($this->generalfeedback == 0 || time() > $this->generalfeedback)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the general feedback comment if set.
     * Return null if it's just HTML tags.
     * @return string|null
     */
    public function get_general_feedback(): ?string {
        if (!$this->feedbackcomment || !trim(strip_tags($this->feedbackcomment))) {
            return null;
        }
        return trim($this->feedbackcomment);
    }

    /**
     * @return bool
     */
    public function has_individual_autorelease_feedback_enabled() {
        return ($this->individualfeedback != 0);
    }

    /**
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_finalised_submissions() {
        global $DB;

        $submissions = $DB->get_records(
            'coursework_submissions',
            ['courseworkid' => $this->id, 'finalisedstatus' => submission::FINALISED_STATUS_FINALISED],
            '',
            'id'
        );

        foreach ($submissions as &$submission) {
            $submission = submission::get_from_id($submission->id);
        }

        return $submissions;
    }

    /**
     * @return bool
     */
    public function start_date_has_passed() {
        return empty($this->startdate) || $this->startdate < time();
    }

    public function finalise_all() {
        if (
            (!$this->has_deadline() || !$this->deadline_has_passed()) // There is no deadline or it is in the future.
            &&
            !$this->personaldeadlines_enabled() // Personal deadlines are disabled.
        ) {
            return;
        }

        cron::finalise_any_submissions_where_the_deadline_has_passed($this->id);
    }

    /**
     * @return array
     */
    public static function extension_reasons() {
        global $CFG;

        $extensionreasons = [];
        if (!empty($CFG->coursework_extension_reasons_list)) {
            $extensionreasons = $CFG->coursework_extension_reasons_list;
            $extensionreasons = explode("\n", $extensionreasons);
            $extensionreasons = array_map(function ($i) {
                    return trim($i);
            },
                $extensionreasons);
            $extensionreasons = array_merge(['' => 'Not specified'], $extensionreasons);
        }
        return $extensionreasons;
    }

    /**
     * Lets us know if extensions are enabled in the coursework.
     * @return bool
     */
    public function extensions_enabled() {
        return (bool)$this->extensionsenabled;
    }
    public function extension_exists() {

        global $DB;
        return $DB->record_exists('coursework_extensions', ['courseworkid' => $this->id]);
    }

    public function get_submissions_with_extensions() {
        global $DB;

        $sql = "SELECT *, cs.id as submissionid
                FROM {coursework_extensions} ce
                JOIN {coursework_submissions} cs
                ON ce.courseworkid = cs.courseworkid
                AND ce.allocatableid = cs.allocatableid
                AND ce.allocatabletype = cs.allocatabletype
                AND cs.courseworkid = :courseworkid";

        return $DB->get_records_sql($sql, ['courseworkid' => $this->id]);
    }

    /**
     * Get all personal deadline in coursework, they can be coursework deadline-(default) or personal
     */
    public function get_coursework_submission_personaldeadlines() {
        global $DB;

        $sql = "SELECT cs.id as submissionid, personaldeadline
                FROM {coursework_submissions} cs
                LEFT JOIN {coursework_person_deadlines} pd ON cs.courseworkid = pd.courseworkid
                AND pd.allocatableid = cs.allocatableid
                AND pd.allocatabletype = cs.allocatabletype
                WHERE cs.courseworkid = :courseworkid";

        $submissions = $DB->get_records_sql($sql, ['courseworkid' => $this->id]);

        // for submissions that don't have a set personal deadline give coursework's default deadline
        if ($submissions) {
            foreach ($submissions as $submission) {
                if (is_null($submission->personaldeadline)) {
                    $submission->personaldeadline = $this->deadline;
                }
            }
        }
        return $submissions;
    }

    /**
     * Has the given stage got a an automatic sampling rule
     *
     * @param $stage
     * @return bool
     * @throws dml_exception
     */
    public function has_automatic_sampling_at_stage($stage) {
        global  $DB;

        return $DB->record_exists('coursework_sample_set_rules', ['courseworkid' => $this->id, 'stageidentifier' => $stage]);
    }

    /**
     * Returns all allocatables in the current coursework that have feedback
     *
     * @param $stage
     * @param bool $random
     * @return allocatable[]
     * @throws dml_exception
     */
    public function get_allocatables_with_feedback($stage, $random = false) {
        global $DB, $CFG;

        $sql = "SELECT   cwrsub.allocatableid, cwrfb.*
                         FROM       {coursework_submissions}  cwrsub,
                                    {coursework_feedbacks}    cwrfb
                         WHERE      cwrsub.id = cwrfb.submissionid
                         AND        cwrsub.courseworkid = :courseworkid
                         AND        stageidentifier = :stage";

        if ($random) {
            $sql .= ($CFG->dbtype == 'pgsql') ? " ORDER BY RANDOM() " : " ORDER BY RAND() ";
        }

        return $DB->get_records_sql(
            $sql,
            ['courseworkid' => $this->id, "stage" => $stage]
        );
    }

    /*
     * Creates automatic feedback
     *
     */
    public function create_automatic_feedback() {
        global $SESSION;

        if ($this->numberofmarkers <= 1 || $this->automaticagreementstrategy == 'null') {
            return;
        }
        $submissions = submission::get_all_for_coursework($this->id);
        $submissions = array_filter($submissions, fn($s) => $s->finalisedstatus == submission::FINALISED_STATUS_FINALISED);
        if (empty($submissions)) {
            return;
        }
        $SESSION->keep_cache_data = 1;
        foreach ($submissions as $submission) {
            if (!$submission->editable_feedbacks_exist()) {
                // this submission needs automatic agreement
                $autofeedbackclassname = '\mod_coursework\auto_grader\\' . $submission->get_coursework()->automaticagreementstrategy;
                /**
                 * @var auto_grader $auto_grader
                 */
                $autograder = new $autofeedbackclassname(
                    $submission->get_coursework(),
                    $submission->get_allocatable(),
                    $submission->get_coursework()->automaticagreementrange
                );
                $autograder->create_auto_grade_if_rules_match();
            }
        }
        unset($SESSION->keep_cache_data);
    }

    /** Function to check it Turnitin is enabled for the particular coursework
     * @return bool
     * @throws dml_exception
     */
    public function tii_enabled(): bool {
        global $DB;
        if (
            !get_config('core', 'enableplagiarism')
            || !isset(plagiarism_load_available_plugins()['turnitin'])
            || !get_config('plagiarism_turnitin', 'plagiarism_turnitin_mod_coursework')
        ) {
            return false;
        }

        if (!isset($this->turnitinenabled)) {
            $this->turnitinenabled = $DB->record_exists(
                'plagiarism_turnitin_config',
                [
                    'cm' => $this->get_coursemodule_id(),
                    'name' => 'use_turnitin',
                    'value' => 1,
                ]
            );
        }
        return $this->turnitinenabled;
    }

    /**
     * Lets us know if personal deadlines are enabled in the coursework.
     * @return bool
     */
    public function personaldeadlines_enabled() {
        return (bool)$this->personaldeadlineenabled;
    }

    /**
     * Return all allocatables and the allocatables deadline
     * Note! allocatables are returned irrespective of whether they have submitted.
     *
     * @return array
     */
    public function get_allocatables_and_deadline() {

        $allocatables = $this->get_allocatables();

        if (!empty($allocatables)) {
            $allocatables = array_map([$this, "get_allocatable_personaldeadline"], $allocatables);
        }

        return $allocatables;
    }

    /**
     * This function adds the allocatables personal deadline in the current coursework to the allocatable record
     *
     * @param $allocatable
     *
     * @return array
     * @throws \core\exception\coding_exception
     */
    private function get_allocatable_personaldeadline($allocatable) {
        $allocatable->deadline = $this->deadline;
        $allocatable->courseworkid = $this->id;

        if ($this->personaldeadlines_enabled()) {
            $deadlinerecord = personaldeadline::get_for_allocatable(
                $this->id,
                $allocatable->id,
                $allocatable->type()
            );

            if (!empty($deadlinerecord)) {
                $allocatable->deadline = $deadlinerecord->personaldeadline;
            }
        }

        return $allocatable;
    }

    /** Check is courseowrk kas any users added to sample
     *
     * @return bool
     * @throws dml_exception
     */
    public function has_samples() {
        global $DB;

        return $DB->record_exists('coursework_sample_set_mbrs', ['courseworkid' => $this->id]);
    }

    /**
     * Check if the user in this coursework is a student (has capability to submit)
     *
     * @return bool
     * @throws coding_exception
     */
    public function can_submit() {
        if (has_capability('mod/coursework:submit', $this->get_context())) {
            return true;
        }
        return false;
    }

    /**
     * Check if the user in this coursework is a marker (has any capability to grade)
     *
     * @return bool
     * @throws coding_exception
     */
    public function can_grade() {
        if (
            has_capability('mod/coursework:addinitialgrade', $this->get_context()) || has_capability('mod/coursework:addagreedgrade', $this->get_context())
            || has_capability('mod/coursework:addallocatedagreedgrade', $this->get_context())
        ) {
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    public function marking_deadline_enabled() {
        return (bool)$this->markingdeadlineenabled;
    }

    /**
     * Function to check if a given individual is in the given group
     *
     * @param $studentid
     * @param $groupid
     * @return bool
     * @throws dml_exception
     */
    public function student_in_group($studentid, $groupid): bool {
        global $DB;
        $sql = "SELECT g.id
                  FROM {groups} g
            INNER JOIN {groups_members} gm
                    ON gm.groupid = g.id
                 WHERE gm.userid = :userid
                   AND g.courseid = :courseid
                   AND g.id = :groupid";
        $params = ['userid' => $studentid,
            'courseid' => $this->get_course()->id,
            'groupid' => $groupid];
        return $DB->record_exists_sql($sql, $params);
    }

    /**
     * Function to retrieve all submissions by coursework
     *
     * @return array
     * @throws dml_exception
     */
    public function retrieve_submissions_by_coursework() {
        global $DB;
        return $DB->get_records('coursework_submissions', ['courseworkid' => $this->id, 'allocatabletype' => 'user']);
    }

    /**
     * Function to retrieve all submissions submitted by a user
     *
     * @param $userid
     * @return array
     * @throws dml_exception
     */
    public function retrieve_submissions_by_user($userid) {
        global $DB;
        return $DB->get_records('coursework_submissions', ['courseworkid' => $this->id, 'authorid' => $userid, 'allocatabletype' => 'user']);
    }

    /**
     * Function to remove all submissions submitted by a user
     *
     * @param $userid
     * @throws dml_exception
     */
    public function remove_submissions_by_user($userid) {
        global $DB;
        $params = ['courseworkid' => $this->id, 'authorid' => $userid, 'allocatabletype' => 'user'];
        $ids = $DB->get_fieldset('coursework_submissions', 'id', $params);
        foreach ($ids as $id) {
            $submission = submission::get_from_id($id);
            $submission->destroy();
        }
    }

    /**
     * Function to Remove the corresponding file by context, item-id and fielarea
     *
     * @param $contextid
     * @param $itemid
     * @param $filearea
     */
    public function remove_corresponding_file($contextid, $itemid, $filearea) {
        $component = 'mod_coursework';
        $fs = get_file_storage();
        $fs->delete_area_files($contextid, $component, $filearea, $itemid);
    }

    /**
     * Function to check if Coursework has any final feedback
     *
     * @return bool
     * @throws dml_exception
     */
    public function has_any_final_feedback() {
        global $DB;

        $sql = "SELECT *
                FROM {coursework_feedbacks} cf
                JOIN {coursework_submissions} cs ON cs.id = cf.submissionid
                WHERE cs.courseworkid = :courseworkid
                AND cf.stageidentifier = 'final_agreed_1'";

        return $DB->record_exists_sql($sql, ['courseworkid' => $this->id]);
    }

    /**
     * Tells us the deadline for a specific allocatable.
     *
     * @param int $allocatableid
     * @return int
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_allocatable_deadline($allocatableid) {
        $deadline = $this->deadline;

        if ($this->usegroups) {
            $allocatable = group::get_from_id($allocatableid);
        } else {
            $allocatable = user::get_from_id($allocatableid);
        }

        if ($this->personaldeadlines_enabled()) {
            // find personal deadline for a user if this option enabled
            $personal = $this->get_allocatable_personaldeadline($allocatable);
            if (!empty($personal)) {
                $deadline = $personal->deadline;
            }
        }

        if ($this->extensions_enabled()) { // check if coursework allows extensions
            // check if extension for this user exists
            $extension = $this->get_allocatable_extension($allocatable);
            if (!empty($extension)) {
                $deadline = $extension;
            }
        }

        return $deadline;
    }

    /**
     * @param int $allocatableid
     * @return allocatable
     */
    public function get_allocatable_from_id($allocatableid): allocatable {
        if ($this->is_configured_to_have_group_submissions()) {
            return group::get_from_id($allocatableid);
        } else {
            return user::get_from_id($allocatableid);
        }
    }

    /**
     * * This function returns allocatable extension if given
     * @param $allocatable
     * @return bool/int
     * @throws dml_exception
     */
    private function get_allocatable_extension($allocatable) {

        global  $DB;
        $extension = false;

        if ($this->extensions_enabled()) {
            $extensionrecord = $DB->get_record('coursework_extensions', ['courseworkid' => $this->id, 'allocatableid' => $allocatable->id]);

            if (!empty($extensionrecord)) {
                $extension = $extensionrecord->extended_deadline;
            }
        }

        return $extension;
    }

    /**
     * Function to check if Coursework has any submission
     *
     * @return bool
     * @throws dml_exception
     */
    public function has_any_submission() {
        global $DB;

        $sql = "SELECT *
                FROM {coursework_submissions} cs
                WHERE cs.courseworkid = :courseworkid";

        return $DB->record_exists_sql($sql, ['courseworkid' => $this->id]);
    }

    /**
     * Function to check if coursework or course that a coursework belongs to is hidden
     *
     * @return bool
     * @throws moodle_exception
     */
    public function is_coursework_visible() {

        $visible = true;
        if ($this->get_course_module()->visible == 0 || $this->get_course()->visible == 0) {
            $visible = false;
        }
        return $visible;
    }

    /**
     * @param null $stageindex
     */
    public function clear_stage($stageindex = null) {
        if ($stageindex) {
            if (isset($this->stages[$stageindex])) {
                unset($this->stages[$stageindex]);
            }
        } else {
            $this->stages = [];
        }
    }

    /**
     * Get the primary grade item for this coursework instance.
     *
     * @return grade_item The grade_item record
     * @throws coding_exception
     */
    public function get_grade_item() {
        $params = ['itemtype' => 'mod',
                        'itemmodule' => 'coursework',
                        'iteminstance' => $this->id,
                        'courseid' => $this->get_course_id(),
                        'itemnumber' => 0];
        $gradeitem = grade_item::fetch($params);

        if (!$gradeitem) {
            throw new coding_exception('Cannot load the grade item.');
        }

        return $gradeitem;
    }

    /**
     * Check if we can release marks for this coursework instance.
     *
     * @return array
     * @throws coding_exception
     */
    public function can_release_marks() {
        if (!has_capability('mod/coursework:publish', $this->get_context())) {
            return [
                false,
                get_string('no_permission_to_release_marks', 'mod_coursework'),
            ];
        }

        if (!$this->has_stuff_to_publish()) {
            return [
                false,
                get_string('nofinalmarkedworkyet', 'mod_coursework'),
            ];
        }

        if ($this->blindmarking_enabled() && $this->moderation_enabled() && $this->unmoderated_work_exists()) {
            return [
                false,
                get_string('unmoderatedworkexists', 'mod_coursework'),
            ];
        }

        return [true, ''];
    }

    /**
     * Check if coursework has any submissions with actual files.
     * This determines if the candidate number setting can be changed.
     *
     * @return bool
     * @throws dml_exception
     */
    public function has_submissions_with_files(): bool {
        global $DB;

        if (!$this->has_any_submission()) {
            return false; // No submissions at all.
        }

        // Get all submissions for this coursework.
        $submissions = $DB->get_records('coursework_submissions', ['courseworkid' => $this->id]);

        foreach ($submissions as $submissionrecord) {
            $submission = new submission($submissionrecord);
            $submissionfiles = $submission->get_submission_files();

            if ($submissionfiles->has_files()) {
                return true; // Found at least one submission with files.
            }
        }

        return false; // No submissions have files.
    }

    /**
     * Check if the candidate number setting can be changed.
     * Setting can only be changed when there are no file submissions.
     *
     * @return bool
     */
    public function can_change_candidate_number_setting(): bool {
        return !$this->has_submissions_with_files();
    }

    /**
     * Validate candidate number settings.
     * Throws exception if prerequisites are not met but setting is enabled.
     *
     * @return void
     * @throws moodle_exception
     */
    public function validate_candidate_number_settings(): void {
        if (!$this->usecandidate) {
            return; // No validation needed if feature disabled.
        }

        // Check if a provider is available.
        // Use candidate number setting is enabled but no provider available, throw exception.
        if (!candidateprovider_manager::instance()->is_provider_available()) {
            throw new moodle_exception('no_candidate_provider_available', 'mod_coursework');
        }
    }

    /**
     * Get file identifier for user (candidate number or fallback to hash).
     *
     * @param int $userid
     * @return string
     * @throws moodle_exception
     */
    public function get_file_identifier_for_user(int $userid): string {
        // If candidate number feature is not enabled for this coursework or blind marking not enabled, use hash.
        if (!$this->usecandidate || !$this->blindmarking_enabled()) {
            return $this->get_username_hash($userid);
        }

        // Validate prerequisites.
        $this->validate_candidate_number_settings();

        // Try to get candidate number using the manager.
        $candidatenumber = candidateprovider_manager::instance()->get_candidate_number(
            $this->get_course_id(),
            $userid
        );

        if (!empty($candidatenumber)) {
            return $candidatenumber; // Return candidate number (ABCD123) if found.
        }

        // Cannot get candidate number from provider, fallback to hash.
        return $this->get_username_hash($userid);
    }


    /**
     * User may need to see a personal event in timeline block on dashboard, showing due time.
     * This could be personal deadline or an extension.
     * Adapted from save_user_extension()in mod/assign/locallib.php.
     * Event is only shown to user if mod_coursework_core_calendar_is_event_visible() returns true.
     * @param int $allocatableid
     * @param string $allocatabletype
     * @param int $newdate
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function update_user_calendar_event(int $allocatableid, string $allocatabletype, int $newdate): bool {
        global $DB, $CFG;
        require_once("$CFG->dirroot/calendar/lib.php");
        $modulename = 'coursework';
        $cm = $this->get_course_module();

        $sqlparams = [
            'eventtype' => self::COURSEWORK_EVENT_TYPE_DUE,
            'modulename' => $modulename,
            'instance' => $this->id,
        ];

        if ($allocatabletype == 'user') {
            $sqlparams['userid'] = $allocatableid;
            $sqlparams['groupid'] = 0;
        } else if ($allocatabletype == 'group') {
            $sqlparams['userid'] = 0;
            $sqlparams['groupid'] = $allocatableid;
        } else {
            throw new Exception("Unexpected allocatable type");
        }

        if (!$newdate) {
            $DB->delete_records('event', $sqlparams);
            return true;
        }

        $event = $DB->get_record('event', $sqlparams);

        if ($event) {
            $event->timestart = $newdate;
            $event->timesort = $newdate;
            return $DB->update_record('event', $event);
        } else {
            $event = (object)$sqlparams;
            $event->type = CALENDAR_EVENT_TYPE_ACTION;
            $event->name = get_string('courseworkisdue', $modulename, $this->name);
            $event->description = format_module_intro($modulename, $this, $cm->id);
            $event->format = FORMAT_HTML;
            // Do not set course ID here otherwise this personal item will appear to other users in course.
            $event->courseid = 0;
            $event->timestart = $newdate;
            $event->timesort = $newdate;
            $event->timeduration = 0;
            $event->visible = instance_is_visible($modulename, $this);
            // Priority set to override default deadline for this coursework in user calendar/timeline.
            $event->priority = CALENDAR_EVENT_USER_OVERRIDE_PRIORITY;
            return (bool)calendar_event::create($event, false);
        }
    }
}
