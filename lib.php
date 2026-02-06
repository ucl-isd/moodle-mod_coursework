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
 * Library of interface functions and constants for module coursework
 *
 * @package    mod_coursework
 * @copyright  2011 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\message\message;
use core_calendar\action_factory;
use core_calendar\local\event\entities\action_interface;
use core_calendar\local\event\value_objects\action;
use mod_coursework\ability;
use mod_coursework\allocation\auto_allocator;
use mod_coursework\calendar;
use mod_coursework\event\coursework_deadline_changed;
use mod_coursework\exceptions\access_denied;
use mod_coursework\models\allocation;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\models\group;
use mod_coursework\models\outstanding_marking;
use mod_coursework\models\submission;
use mod_coursework\models\user;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->dirroot . '/lib/gradelib.php');
require_once($CFG->dirroot . '/mod/coursework/renderable.php');


/**
 * @param string $feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 */
function coursework_supports(string $feature): mixed {
    $supportstatus = match ($feature) {
        FEATURE_GROUPS => true,
        FEATURE_GROUPINGS => true,
        FEATURE_MOD_INTRO => true,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_GRADE_HAS_GRADE => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_ADVANCED_GRADING => true,
        FEATURE_PLAGIARISM => true,
        FEATURE_GRADE_OUTCOMES => false,
        FEATURE_COMPLETION_HAS_RULES => false,
        FEATURE_COMPLETION_TRACKS_VIEWS => false,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_ASSESSMENT,
        default => null,
    };

    return $supportstatus;
}

/**
 * Lists all file areas current user may browse
 *
 * @param object $course
 * @param object $cm
 * @param context $context
 * @return array
 * @throws coding_exception
 */
function coursework_get_file_areas($course, $cm, $context) {
    $areas = [];

    if (has_capability('mod/coursework:submit', $context)) {
        $areas['submission'] = get_string('submissionfiles', 'coursework');
    }
    return $areas;
}

/**
 * Serves files for pluginfile.php
 * @param $course
 * @param $cm
 * @param $context
 * @param $filearea
 * @param $args
 * @param $forcedownload
 * @return bool
 * @throws access_denied
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 * @throws require_login_exception
 */
function mod_coursework_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {

    // Lifted form the assignment version.
    global $CFG, $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);

    if (!$coursework = coursework::get_from_id($cm->instance)) {
        return false;
    }

    $ability = new ability($USER->id, $coursework);

    // From assessment send_file().
    require_once($CFG->dirroot . '/lib/filelib.php');

    if (in_array($filearea, ['submission', 'submissionannotations'])) {
        $submissionid = (int)array_shift($args);

        $submission = submission::get_from_id($submissionid);
        if (!$submission) {
            return false;
        }

        if ($ability->cannot('show', $submission)) {
            return false;
        }

        $relativepath = implode('/', $args);

        $fs = get_file_storage();
        $file = $fs->get_file($context->id, 'mod_coursework', $filearea, $submission->id, "/", $relativepath);
        if (!$file || $file->is_directory()) {
            return false;
        }
        send_stored_file($file, 0, 0, false);
        return true;
    } else {
        if ($filearea === 'feedback') {
            $feedbackid = (int)array_shift($args);

            /**
             * @var feedback $feedback
             */
            $feedback = feedback::get_from_id($feedbackid);
            if (!$feedback) {
                return false;
            }

            if (!$ability->can('show', $feedback)) {
                throw new access_denied($coursework);
            }

            $relativepath = implode('/', $args);
            $fullpath = "/{$context->id}/mod_coursework/feedback/" .
                "{$feedback->id}/{$relativepath}";

            $fs = get_file_storage();
            $file = $fs->get_file_by_hash(sha1($fullpath));
            if (!$file || $file->is_directory()) {
                return false;
            }
            send_stored_file($file, 0, 0, true);
            return true;
        }
    }

    return false;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $formdata An object from the form in mod_form.php
 * @return int The id of the newly inserted coursework record
 * @throws coding_exception
 * @throws dml_exception
 * @throws invalid_parameter_exception
 */
function coursework_add_instance($formdata) {
    global $DB;

    $formdata->timecreated = time();

    // You may have to add extra stuff in here.
    // We have to check to see if this coursework has a deadline.
    // If it doesn't we need to set the deadline to zero.

    $formdata->deadline = empty($formdata->deadline) ? 0 : $formdata->deadline;
    $subnotify = '';
    $comma = '';
    if (!empty($formdata->submissionnotification)) {
        foreach ($formdata->submissionnotification as $uid) {
            $subnotify .= $comma . $uid;
            $comma = ',';
        }
    }
    $formdata->submissionnotification = $subnotify;

    // If blind marking is set we will rename files.
    if ($formdata->blindmarking == 1) {
        $formdata->renamefiles = 1;
    }

    $returnid = $DB->insert_record('coursework', $formdata);
    $formdata->id = $returnid;

    // IMPORTANT: at this point, the coursemodule will be in existence, but will
    // Not have the coursework id saved, because we only just made it.
    $coursemodule = $DB->get_record('course_modules', ['id' => $formdata->coursemodule]);
    $coursemodule->instance = $returnid;
    // This is doing what will be done later by the core routines. Makes it simpler to use existing
    // Code without special cases.
    $DB->update_record('course_modules', $coursemodule);

    // Get all the other data e.g. coursemodule.
    $coursework = coursework::get_from_id($returnid);

    // Create event for coursework deadline [due]
    if ($coursework && $coursework->deadline) {
        $event = calendar::coursework_event($coursework, 'due', $coursework->deadline);
        calendar_event::create($event);
    }

    // Create event for coursework initialmarking deadline [initialgradingdue]
    if ($coursework && $coursework->marking_deadline_enabled() && $coursework->initialmarkingdeadline) {
        $event = calendar::coursework_event($coursework, 'initialgradingdue', $coursework->initialmarkingdeadline);
        calendar_event::create($event);
    }

    // Create event for coursework agreedgrademarking deadline [agreedgradingdue]
    if ($coursework && $coursework->marking_deadline_enabled() && $coursework->agreedgrademarkingdeadline && $coursework->has_multiple_markers()) {
        $event = calendar::coursework_event($coursework, 'agreedgradingdue', $coursework->agreedgrademarkingdeadline);
        calendar_event::create($event);
    }

    coursework_grade_item_update($coursework);

    return $returnid;
}

/**
 * Is the event visible?
 *
 * This is used to determine global visibility of an event in all places throughout Moodle. For example,
 * the ASSIGN_EVENT_TYPE_GRADINGDUE event will not be shown to students on their calendar, and
 * ASSIGN_EVENT_TYPE_DUE events will not be shown to teachers.
 *
 * @param calendar_event $event
 * @return bool Returns true if the event is visible to the current user, false otherwise.
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function mod_coursework_core_calendar_is_event_visible(calendar_event $event): bool {
    global $DB, $USER;

    $cm = get_fast_modinfo($event->courseid)->instances['coursework'][$event->instance];
    $coursework = coursework::get_from_id($cm->instance);

    $cansubmit = $coursework->can_submit();
    $ismarker = $coursework->is_assessor($USER->id);

    if ($ismarker) {
        return in_array($event->eventtype, ['initialgradingdue', 'agreedgradingdue']);
    } else {
        if (
            $cansubmit &&
            (in_array($event->eventtype, [coursework::COURSEWORK_EVENT_TYPE_DUE]))
        ) {
            return true;
        }
    }
    return false;
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 *
 * @param calendar_event $event
 * @param action_factory $factory
 * @return action_interface|action|null
 * @throws \core\exception\moodle_exception
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function mod_coursework_core_calendar_provide_event_action(
    calendar_event $event,
    action_factory $factory
) {
    global $DB, $USER;

    $cm = get_fast_modinfo($event->courseid)->instances['coursework'][$event->instance];
    $submissionurl = new moodle_url('/mod/coursework/view.php', ['id' => $cm->id]);
    $name = '';
    $itemcount = 0;

    $coursework = coursework::get_from_id($cm->instance);

    $student = $coursework->can_submit();
    $marker = $coursework->is_assessor($USER->id);

    if ($marker) { // For markers
        // Check how many submissions to mark
        $outstandingmarking = new outstanding_marking();

        if ($event->eventtype == 'initialgradingdue') {
            // Initial grades
            $togradeinitialcount = $outstandingmarking->get_to_grade_initial_count($dbcoursework, $USER->id);
            $name = ($coursework->has_multiple_markers()) ? get_string('initialmark', 'coursework') : get_string('mark', 'mod_coursework');
            $itemcount = $togradeinitialcount;
        } else if ($event->eventtype == 'agreedgradingdue') {
            // Agreed grades
            $togradeagreedcount = $outstandingmarking->get_to_grade_agreed_count($dbcoursework, $USER->id);
            $name = get_string('agreedgrade', 'coursework');
            $itemcount = $togradeagreedcount;
        }

        $submissionurl = new moodle_url('/mod/coursework/view.php', ['id' => $cm->id]);
    } else if ($student) { // for students
        $user = user::get_from_id((int)$USER->id);
        // if group cw check if student is in group, if not then don't display 'Add submission' link
        if ($coursework->is_configured_to_have_group_submissions() && !$coursework->get_coursework_group_from_user_id($user->id())) {
            $submissionurl = new moodle_url('/mod/coursework/view.php', ['id' => $cm->id]);
            $itemcount = 1;
        } else {
            $submission = $coursework->get_user_submission($user);
            $newsubmission = $coursework->build_own_submission($user);
            if (!$submission) {
                $submission = $newsubmission;
            }
            // Check if user can still submit
            $ability = new ability($USER->id, $coursework);
            if (!$submission || $ability->can('new', $submission)) {
                $name = get_string('addsubmission', 'coursework');
                $itemcount = 1;
                $allocatableid = $submission->get_allocatable()->id();
                $allocatabletype = $submission->get_allocatable()->type();

                $submissionurl = new moodle_url('/mod/coursework/actions/submissions/new.php', ['allocatableid' => $allocatableid,
                                                                                                          'allocatabletype' => $allocatabletype,
                                                                                                          'courseworkid' => $coursework->id]);
            } else {
                return null;
            }
        }
    }

    return $factory->create_instance(
        $name,
        $submissionurl,
        $itemcount,
        true
    );
}

/**
 * Callback function that determines whether an action event should be showing its item count
 * based on the event type and the item count.
 *
 * @param calendar_event $event The calendar event.
 * @param int $itemcount The item count associated with the action event.
 * @return bool
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function mod_coursework_core_calendar_event_action_shows_item_count(calendar_event $event, $itemcount = 0) {
    global $DB;
    // List of event types where the action event's item count should be shown.
    $initialgradingdueeventtype = ['initialgradingdue'];
    $agreedgradingdueeventtype = ['agreedgradingdue'];
    $cm = get_fast_modinfo($event->courseid)->instances['coursework'][$event->instance];

    $coursework = coursework::get_from_id($cm->instance);
    $student = $coursework->can_submit();

    // For mod_coursework we use 'initialgrading' and 'agreedgrading' event type; item count should be shown if there is one or more item count and user is not a student.
    return (in_array($event->eventtype, $initialgradingdueeventtype) || in_array($event->eventtype, $agreedgradingdueeventtype)) && $itemcount > 0 && !$student;
}

/**
 * Create grade item for given coursework
 * @param coursework|stdClass $coursework object with extra cmid number
 * @param null $grades array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 * @throws coding_exception
 * @throws dml_exception
 * @throws invalid_parameter_exception
 */
function coursework_grade_item_update($coursework, $grades = null) {
    global $CFG;

    require_once($CFG->dirroot . '/lib/gradelib.php');

    $paramtype = gettype($coursework);
    if ($paramtype != 'object') {
        throw new invalid_parameter_exception("Invalid type '$paramtype' for coursework");
    }

    if (get_class($coursework) == 'stdClass') {
        // On activity rename, core will pass in stdClass object here.
        // Otherwise expect coursework or coursework_groups_decorator to be passed.
        $coursework = coursework::get_from_id($coursework->id);
    }

    $courseid = $coursework->get_course_id();

    $params = [
        'itemname' => $coursework->name,
        'idnumber' => $coursework->get_coursemodule_idnumber(),
    ];

    if ($coursework->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $coursework->grade;
        $params['grademin'] = 0;
    } else {
        if ($coursework->grade < 0) {
            $params['gradetype'] = GRADE_TYPE_SCALE;
            $params['scaleid'] = -$coursework->grade;
        } else {
            $params['gradetype'] = GRADE_TYPE_TEXT; // Allow text comments only.
        }
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update(
        'mod/coursework',
        $courseid,
        'mod',
        'coursework',
        $coursework->id,
        0,
        $grades,
        $params
    );
}

/**
 * Update coursework grades in the gradebook.
 * This will be called to rename the grade item when {@link core_courseformat\local\cmactions::rename()} is used.
 * Needed by {@link grade_update_mod_grades()} (Force full update of module grades in central gradebook).
 *
 * @param stdClass $moduleinstance Instance object with extra cmidnumber and modname property.
 * @param int $userid Update grade of specific user only, 0 means all participants.
 * @param bool $nullifnone If true and the user has no grade then a grade item with rawgrade == null
 * @throws invalid_parameter_exception
 */
function coursework_update_grades(stdClass $moduleinstance, int $userid = 0, $nullifnone = true) {
    // Code adapted from mod_assign.
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if ($moduleinstance->grade == 0) {
        coursework_grade_item_update($moduleinstance);
    } else if ($grades = coursework_get_user_grades($moduleinstance, $userid)) {
        foreach ($grades as $k => $v) {
            if ($v->rawgrade == -1) {
                $v->rawgrade = null;
            }
        }
        coursework_grade_item_update($moduleinstance, $grades);
    } else {
        coursework_grade_item_update($moduleinstance);
    }
}

/**
 * Return gradebook grade for given user or all users.
 *
 * @param object $moduleinstance
 * @param int $userid user ID.
 * @return array array of grades
 */
function coursework_get_user_grades(object $moduleinstance, int $userid): array {

    // If no user ID supplied, this returns information about grade_item only.
    $grades = grade_get_grades(
        $moduleinstance->course,
        'mod',
        'coursework',
        $moduleinstance->id,
        $userid != 0 ? $userid : null
    );
    return reset($grades->items)->grades ?? [];
}

/**
 * Delete grade item for given coursework
 *
 * @param coursework $coursework object
 * @return int
 */
function coursework_grade_item_delete(coursework $coursework) {
    global $CFG;
    require_once($CFG->dirroot . '/lib/gradelib.php');

    return grade_update(
        'mod/coursework',
        $coursework->get_course_id(),
        'mod',
        'coursework',
        $coursework->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $coursework An object from the form in mod_form.php
 * @return boolean Success/Fail
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function coursework_update_instance($coursework) {

    global $DB, $USER;

    $coursework->timemodified = time();
    $coursework->id = $coursework->instance;

    if ($coursework->finalstagegrading == 1) {
        $coursework->automaticagreementstrategy = 'none';
        $coursework->automaticagreementrange = 10;
    }

    $subnotify = '';
    $comma = '';
    if (!empty($coursework->submissionnotification)) {
        foreach ($coursework->submissionnotification as $uid) {
            $subnotify .= $comma . $uid;
            $comma = ',';
        }
    }

    $coursework->submissionnotification = $subnotify;

    $courseworkhassubmissions = $DB->record_exists('coursework_submissions', ['courseworkid' => $coursework->id]);

    // If the coursework has submissions then we the renamefiles setting can't be changes
    if ($courseworkhassubmissions) {
        $currentcoursework = $DB->get_record('coursework', ['id' => $coursework->id]);

        $coursework->renamefiles = $currentcoursework->renamefiles;
    } else if ($coursework->blindmarking == 1) {
        $coursework->renamefiles = 1;
    }

    $oldsubmissiondeadline = $DB->get_field('coursework', 'deadline', ['id' => $coursework->id]);
    $oldgeneraldeadline = $DB->get_field('coursework', 'generalfeedback', ['id' => $coursework->id]);
    $oldindividualdeadline = $DB->get_field('coursework', 'individualfeedback', ['id' => $coursework->id]);

    if (
        $oldsubmissiondeadline != $coursework->deadline ||
        $oldgeneraldeadline != $coursework->generalfeedback ||
        $oldindividualdeadline != $coursework->individualfeedback
    ) {
        // Fire an event to send emails to students affected by any deadline change.

        $courseworkobj = coursework::get_from_id($coursework->id);

        $params = [
            'context' => context_module::instance($courseworkobj->get_course_module()->id),
            'courseid' => $courseworkobj->get_course()->id,
            'objectid' => $coursework->id,
            'other' => [
                'courseworkid' => $coursework->id,
                'oldsubmissiondeadline' => $oldsubmissiondeadline,
                'newsubmissionsdeadline' => $coursework->deadline,
                'oldgeneraldeadline' => $oldgeneraldeadline,
                'newgeneraldeadline' => $coursework->generalfeedback,
                'oldindividualdeadline' => $oldindividualdeadline,
                'newindividualdeadline' => $coursework->individualfeedback,
                'userfrom' => $USER->id,
            ],
        ];

        $event = coursework_deadline_changed::create($params);
        $event->trigger();
    }

    // Update event for calendar(cw name/deadline) if a coursework has a deadline
    if ($coursework->deadline) {
        coursework_update_events($coursework, 'due'); // Cw deadline
        if ($coursework->initialmarkingdeadline) {
            // Update
            coursework_update_events($coursework, 'initialgradingdue'); // Cw initial grading deadine
        } else {
            // Remove it
             calendar::remove_event($coursework, 'initialgradingdue');
        }
        if ($coursework->agreedgrademarkingdeadline && $coursework->numberofmarkers > 1) {
            // Update
            coursework_update_events($coursework, 'agreedgradingdue'); // Cw agreed grade deadine
        } else {
            // Remove it
             calendar::remove_event($coursework, 'agreedgradingdue');
        }
    } else {
        // Remove all deadline events for this coursework regardless the type
         calendar::remove_event($coursework);
    }

    return $DB->update_record('coursework', $coursework);
}

/**
 *  Update coursework deadline and name in the event table
 *
 * @param $coursework
 * @param $eventtype
 * @return void
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function coursework_update_events($coursework, $eventtype) {
    global $DB;

    $coursework = coursework::get_from_id($coursework->id);

    $params = ['modulename' => 'coursework', 'instance' => $coursework->id, 'eventtype' => $eventtype];
    if ($eventtype == coursework::COURSEWORK_EVENT_TYPE_DUE) {
        // When getting the ID for the existing event, for 'due' events it's important to pass in course ID.
        // The reason is that this distinguishes it as a course level event.
        // Users with extensions or personal deadlines will have entries where courseid is 0.
        // We want to make sure the users' personal deadlines are not picked up here.
        $params['courseid'] = $coursework->course;
    }

    $eventid = $DB->get_field('event', 'id', $params);

    $event = $eventid ? calendar_event::load($eventid) : null;

    // Update/create event for coursework deadline [due].
    if ($eventtype == coursework::COURSEWORK_EVENT_TYPE_DUE) {
        $data = calendar::coursework_event($coursework, $eventtype, $coursework->deadline);
        if ($event) {
            $event->update($data); // Update if event exists.
        } else {
            calendar_event::create($data); // Create new event as it doesn't exist.
        }
    }

    // Update/create event for coursework initialmarking deadline [initialgradingdue].
    if ($eventtype == 'initialgradingdue') {
        $data = calendar::coursework_event($coursework, $eventtype, $coursework->initialmarkingdeadline);
        if ($event) {
            $event->update($data); // Update if event exists.
        } else {
            calendar_event::create($data); // Create new event as it doesn't exist.
        }
    }

    // Update/create event for coursework agreedgrademarking deadline [agreedgradingdue].
    if ($eventtype == 'agreedgradingdue') {
        $data = calendar::coursework_event($coursework, $eventtype, $coursework->agreedgrademarkingdeadline);
        if ($event) {
            $event->update($data); // Update if event exists.
        } else {
            calendar_event::create($data); // Create new event as it doesn't exist.
        }
    }
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 * @throws dml_exception
 */
function coursework_delete_instance($id) {
    global $DB;

    if (!$coursework = $DB->get_record('coursework', ['id' => $id])) {
        return false;
    }

    // Delete any dependent records here.

    // TODO delete feedbacks.
    // TODO delete allocations.
    // TODO delete submissions.

    $DB->delete_records('coursework', ['id' => $coursework->id]);

    return true;
}

/**
 * @return array
 */
function coursework_get_view_actions() {
    return ['view'];
}

/**
 * @return array
 */
function coursework_get_post_actions() {
    return ['upload'];
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param $course
 * @param $user
 * @param $mod
 * @param $coursework
 * @return null
 * @todo Finish documenting this function
 */
function coursework_user_outline($course, $user, $mod, $coursework) {
    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param $course
 * @param $user
 * @param $mod
 * @param $coursework
 * @return boolean
 * @todo Finish documenting this function
 */
function coursework_user_complete($course, $user, $mod, $coursework) {
    return true;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in coursework activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @param $course
 * @param $viewfullnames
 * @param $timestart
 * @return boolean
 * @todo Finish documenting this function
 */
function coursework_print_recent_activity($course, $viewfullnames, $timestart) {
    return false; // True if anything was printed, otherwise false.
}

/**
 * Must return an array of users who are participants for a given instance
 * of coursework. Must include every user involved in the instance,
 * independent of his role (student, teacher, admin...). The returned
 * objects must contain at least id property.
 * See other modules as example.
 *
 * @todo make this work.
 *
 * @param int $courseworkid ID of an instance of this module
 * @return false false if no participants, array of objects otherwise
 */
function coursework_get_participants($courseworkid) {
    return false;
}

/**
 * This function returns if a scale is being used by one coursework
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $courseworkid ID of an instance of this module
 * @param $scaleid
 * @return bool
 * @throws dml_exception
 */
function coursework_scale_used($courseworkid, $scaleid) {

    global $DB;

    $params = ['grade' => $scaleid,
                    'id' => $courseworkid];
    if ($scaleid && $DB->record_exists('coursework', $params)) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of coursework.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any coursework
 * @throws dml_exception
 */
function coursework_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid && $DB->record_exists('coursework', ['grade' => $scaleid])) {
        return true;
    } else {
        return false;
    }
}

/**
 * Returns all other caps used in module
 * @return array
 */
function coursework_get_extra_capabilities() {
    return ['moodle/site:accessallgroups',
                 'moodle/site:viewfullnames'];
}

/**
 * Returns submission details for a plagiarism file submission.
 *
 * @param int $cmid
 * @return array
 * @throws coding_exception
 * @throws dml_exception
 */
function coursework_plagiarism_dates($cmid) {

    $cm = get_coursemodule_from_id('coursework', $cmid);
    $coursework = coursework::get_from_id($cm->instance);

    $datesarray = ['timeavailable' => $coursework->timecreated];
    $datesarray['timedue'] = $coursework->deadline;
    $datesarray['feedback'] = (string)$coursework->get_individual_feedback_deadline();

    return $datesarray;
}

/**
 * Extend the navigation settings for each individual coursework to allow markers to be allocated, etc.
 *
 * @param settings_navigation $settings
 * @param navigation_node $navref
 * @return void
 * @throws \core\exception\moodle_exception
 * @throws coding_exception
 * @throws dml_exception
 */
function coursework_extend_settings_navigation(settings_navigation $settings, navigation_node $navref) {

    global $PAGE;

    $cm = $PAGE->cm;
    if (!$cm) {
        return;
    }

    $context = $PAGE->context;
    $course = $PAGE->course;
    $coursework = coursework::get_from_id($cm->instance);

    if (!$course) {
        return;
    }

    // Link to marker allocation screen. No point showing it if we are not using allocation or moderation.
    if (
        has_capability('mod/coursework:allocate', $context) &&
        ($coursework->allocation_enabled() || $coursework->sampling_enabled())
    ) {
        $link = new moodle_url('/mod/coursework/actions/allocate.php', ['id' => $cm->id]);
        $langstr = ($coursework->moderation_agreement_enabled()) ? 'markermoderatormarks' : 'allocatemarkers';
        $navref->add(get_string($langstr, 'mod_coursework'), $link, navigation_node::TYPE_SETTING);
    }
    // Link to personal deadlines screen
    if (has_capability('mod/coursework:editpersonaldeadline', $context) && ($coursework->personaldeadlines_enabled())) {
        $link = new moodle_url('/mod/coursework/actions/set_personaldeadlines.php', ['id' => $cm->id]);
        $navref->add(get_string('setpersonaldeadlines', 'mod_coursework'), $link, navigation_node::TYPE_SETTING);
    }

    // Link to the locally assigned roles screen.
    if (
        has_capability('moodle/role:assign', $context)
            && has_capability('mod/coursework:allocate', $context)
    ) {
        $link = new moodle_url('/admin/roles/assign.php', ['contextid' => $context->id]);
        $navref->add(get_string('addmarkers', 'mod_coursework'), $link, navigation_node::TYPE_SETTING);
    }

    // Link to feedback for all students.
    if (
        has_capability('mod/coursework:addgeneralfeedback', $context)
    ) {
        $link = new moodle_url('/mod/coursework/actions/general_feedback.php', ['cmid' => $cm->id]);
        $child = $navref->add(
            get_string('generalfeedback', 'mod_coursework'),
            $link,
            navigation_node::TYPE_SETTING,
            null,
            'generalfeedback'
        );
        $child->set_force_into_more_menu(true);
    }
}

/**
 * Auto-allocates after a new student or teacher is added to a coursework.
 *
 * @param $roleassignment - record from role_assignments table
 * @return bool
 * @throws coding_exception
 * @throws dml_exception
 */
function coursework_role_assigned_event_handler($roleassignment) {
    global $DB;

    $courseworkids = coursework_get_courseworkids_from_context_id($roleassignment->contextid);

    foreach ($courseworkids as $courseworkid) {
        $DB->set_field('coursework', 'processenrol', 1, ['id' => $courseworkid]);
    }

    return true;
}

/**
 * Auto allocates when a student or teacher leaves.
 *
 * @param $roleassignment
 * @return bool
 * @throws coding_exception
 * @throws dml_exception
 */
function coursework_role_unassigned_event_handler($roleassignment) {

    global $DB;

    $courseworkids = coursework_get_courseworkids_from_context_id($roleassignment->contextid);

    foreach ($courseworkids as $courseworkid) {
        $DB->set_field('coursework', 'processunenrol', 1, ['id' => $courseworkid]);
    }

    return true;
}

/**
 * Role may be assigned at course or coursemodule level. This gives us an array of relevant coursework
 * ids to loop through so we can re-allocate.
 *
 * @param $contextid
 * @return array
 * @throws coding_exception
 * @throws dml_exception
 */
function coursework_get_courseworkids_from_context_id($contextid) {

    global $DB;

    $courseworkids = [];

    // Is this a coursework?
    $context = context::instance_by_id($contextid);

    switch ($context->contextlevel) {
        case CONTEXT_MODULE:
            $coursemodule = get_coursemodule_from_id('coursework', $context->instanceid);
            $courseworkmoduleid = $DB->get_field('modules', 'id', ['name' => 'coursework']);

            if ($coursemodule && $coursemodule->module == $courseworkmoduleid) {
                $courseworkids[] = $coursemodule->instance;
            }
            break;

        case CONTEXT_COURSE:
            $coursemodules = $DB->get_records('coursework', ['course' => $context->instanceid]);
            if ($coursemodules) {
                $courseworkids = array_keys($coursemodules);
            }
            break;
    }

    return $courseworkids;
}

/**
 * Makes a number of seconds into a human readable string, like '3 days'.
 *
 * @param int $seconds
 * @return string
 * @throws coding_exception
 */
function coursework_seconds_to_string($seconds) {

    $units = [
        604800 => [get_string('week', 'mod_coursework'),
                        get_string('weeks', 'mod_coursework')],
        86400 => [get_string('day', 'mod_coursework'),
                       get_string('days', 'mod_coursework')],
        3600 => [get_string('hour', 'mod_coursework'),
                      get_string('hours', 'mod_coursework')],
        60 => [get_string('minute', 'mod_coursework'),
                    get_string('minutes', 'mod_coursework')],
        1 => [get_string('second', 'mod_coursework'),
                   get_string('seconds', 'mod_coursework')],
    ];

    $result = [];
    foreach ($units as $divisor => $unitame) {
        $units = intval($seconds / $divisor);
        if ($units) {
            $seconds %= $divisor;
            $name = $units == 1 ? $unitame[0] : $unitame[1];
            $result[] = "$units $name";
        }
    }

    return implode(', ', $result);
}

/**
 * Checks the DB to see how many feedbacks we already have. This is so we can stop people from setting the
 * number of markers lower than that in the mod form.
 *
 * @param int $courseworkid
 * @return int
 * @throws dml_exception
 */
function coursework_get_current_max_feedbacks($courseworkid) {

    global $DB;

    $sql = "SELECT MAX(feedbackcounts.numberoffeedbacks)
              FROM (SELECT COUNT(feedbacks.id) AS numberoffeedbacks
                      FROM {coursework_feedbacks} feedbacks
                INNER JOIN {coursework_submissions} submissions
                        ON submissions.id = feedbacks.submissionid
                     WHERE submissions.courseworkid = :courseworkid
                       AND feedbacks.ismoderation = 0
                       AND feedbacks.isfinalgrade = 0
                       AND feedbacks.stageidentifier LIKE 'assessor%'
                  GROUP BY feedbacks.submissionid) AS feedbackcounts
                      ";
    $params = [
        'courseworkid' => $courseworkid,
    ];
    $max = $DB->get_field_sql($sql, $params);

    if (!$max) {
        $max = 0;
    }

    return $max;
}

/**
 * Sends a message to a user that the deadline has now altered. Fired by the event system.
 *
 * @param  $eventdata
 * @return bool
 * @throws coding_exception
 * @throws dml_exception
 */
function coursework_send_deadline_changed_emails($eventdata) {

    if (empty($eventdata->other['courseworkid'])) {
        return true;
    }

    // No need to send emails if none of the deadlines have changed.
    $counter = 0;

    $coursework = coursework::get_from_id($eventdata->other['courseworkid']);

    if (empty($coursework) || !$coursework->is_coursework_visible()) { // check if coursework exists and is not hidden
        return true;
    }

    $users = $coursework->get_students();

    $submissionsdeadlinechanged = $eventdata->other['oldsubmissiondeadline'] != $eventdata->other['newsubmissionsdeadline'];
    $generaldeadlinechanged = $eventdata->other['oldgeneraldeadline'] != $eventdata->other['newgeneraldeadline'];
    $individualdeadlinechanged = $eventdata->other['oldindividualdeadline'] != $eventdata->other['newindividualdeadline'];

    foreach ($users as $user) {
        $submission = $coursework->get_user_submission($user);

        if (empty($submission)) {
            continue;
        }

        $hassubmitted = ($submission && !$submission->is_finalised());
        $userreleasedate = $coursework->get_student_feedback_release_date();

        if ($userreleasedate < time()) {
            // Deadlines are all passed for this user - no need to message them.
            continue;
        }

        // No point telling them if they've submitted already.
        if ($submissionsdeadlinechanged && !$generaldeadlinechanged && !$individualdeadlinechanged && $hassubmitted) {
            continue;
        }

        $messagedata = new message();
        $messagedata->component = 'mod_coursework';
        $messagedata->name = 'deadlinechanged';
        $messagedata->userfrom = is_object($eventdata->other['userfrom']) ? $eventdata->other['userfrom'] : (int)$eventdata->other['userfrom'];
        $messagedata->userto = (int)$user->id;
        $messagedata->subject = get_string('adeadlinehaschangedemailsubject', 'mod_coursework', $coursework->name);

        // Now we need a decent message that provides the relevant data and notifies what changed.
        // - Submissions deadline if it's in the future and the user has not already submitted.
        // - Feedback deadline if it's in the future and the student's personal deadline for feedback has not passed.
        // - Link to get to the view.php page.
        // - Change since last time.

        $deadlinechangedmessage = [];

        $strings = new stdClass();
        $strings->courseworkname = $coursework->name;

        if ($submissionsdeadlinechanged) {
            $strings->typeofdeadline = strtolower(get_string('submission', 'mod_coursework'));
            $strings->deadline = userdate($coursework->deadline, '%a, %d %b %Y, %H:%M');
            $deadlinechangedmessage[] = get_string('deadlinechanged', 'mod_coursework', $strings);
        }
        if ($generaldeadlinechanged) {
            $strings->typeofdeadline = strtolower(get_string('generalfeedback', 'mod_coursework'));
            $strings->deadline = userdate($coursework->generalfeedback, '%a, %d %b %Y, %H:%M');
            $deadlinechangedmessage[] = get_string('deadlinechanged', 'mod_coursework', $strings);
        }
        if ($individualdeadlinechanged) {
            $strings->typeofdeadline = strtolower(get_string('individualfeedback', 'mod_coursework'));
            $strings->deadline = userdate($userreleasedate, '%a, %d %b %Y, %H:%M');
            $deadlinechangedmessage[] = get_string('deadlinechanged', 'mod_coursework', $strings);
        }

        $messagedata->fullmessage = implode("\n", $deadlinechangedmessage);
        $messagedata->fullmessageformat = FORMAT_HTML;
        // TODO add HTML stuff?
        $messagedata->fullmessagehtml = '';
        $messagedata->smallmessage = '';
        $messagedata->courseid = $coursework->id();
        $messagedata->notification = 1; // This is only set to 0 for personal messages between users.
        message_send($messagedata);
    }

    return true;
}
/**
 * Checks whether the files of the given function exist
 * @param $plugintype
 * @param $pluginname
 * @return bool
 */
function coursework_plugin_exists($plugintype, $pluginname) {
    global  $CFG;
    return (is_dir($CFG->dirroot . "/{$plugintype}/{$pluginname}")) ? true : false;
}

/**
 * Utility function which makes a recordset into an array
 * Similar to recordset_to_menu. Array is keyed by the specified field of each record and
 * has the second specified field as the value
 *
 * @param $records
 * @param $field1
 * @param $field2
 * @return array
 */
function coursework_records_to_menu($records, $field1, $field2) {

    $menu = [];

    if (!empty($records)) {
        foreach ($records as $record) {
             $menu[$record->$field1] = $record->$field2;
        }
    }
    return $menu;
}

/**
 * @param $eventdata
 * @return bool
 * @throws coding_exception
 * @throws dml_exception
 */
function coursework_mod_updated($eventdata) {
    if ($eventdata->other['modulename'] == 'coursework') {
        $coursework = coursework::get_from_id($eventdata->other['instanceid']);
        /**
         * @var coursework $coursework
         */
        $allocator = new auto_allocator($coursework);
        $allocator->process_allocations();
    }

    return true;
}

/**
 *
 *  * Function to process allocation of new group members (student/group - assign to a group assessor or assessor - assign to students/group) - when a user is added to a group

 * @param $eventdata
 * @return bool
 * @throws coding_exception
 * @throws dml_exception
 */

function course_group_member_added($eventdata) {
    global $DB;

    $groupid = $eventdata->objectid;
    $courseid = $eventdata->courseid;
    $addeduserid = $eventdata->relateduserid;

    // get all courseworks with group_assessor allocation strategy
    $courseworks = $DB->get_records('coursework', ['course' => $courseid, 'assessorallocationstrategy' => 'group_assessor']);

    foreach ($courseworks as $coursework) {
        $coursework = coursework::get_from_id($coursework->id);
        $stage = $coursework->marking_stages();
        $stage1 = $stage['assessor_1']; // this allocation is only for 1st stage, we don't touch other stages
        $student = $coursework->can_submit(); // check if user is student in this course
        $initialstageassessor = has_capability('mod/coursework:addinitialgrade', $coursework->get_context(), $addeduserid); // check if user is initial stage assessor in this course

        if ($initialstageassessor) {
            // check if any assessor already exists in the group except currently added one
            $assessorsingroup = get_enrolled_users($coursework->get_context(), 'mod/coursework:addinitialgrade', $groupid);
            unset($assessorsingroup[$addeduserid]); // Remove added assessor as at this point they will be already in the group

            if ($assessorsingroup) {// yes - do nothing as other assessor is already assigned to group members, return true
                break;
            } else { // No - check if CW is a group coursework
                if ($coursework->is_configured_to_have_group_submissions()) {// yes - assign the tutor to a allocatable group
                    $stage1->make_auto_allocation_if_necessary(group::get_from_id($groupid));
                } else {  // no, check if group has any student members
                    $allocatables = $coursework->get_allocatables();
                    if ($allocatables) {
                        // yes - assign this assessor to every allocatable student in the appropriate course group - at this point assessor should already be a member
                        foreach ($allocatables as $allocatable) {
                            // process students allocations
                            if ($coursework->student_is_in_any_group($allocatable)) { // student must belong to a group
                                $stage1->make_auto_allocation_if_necessary($allocatable);
                            }
                        }
                    } else {// no - do nothing, return true
                        continue;
                    }
                }
            }
        } else if ($student) {
            if ($coursework->is_configured_to_have_group_submissions()) {
                $allocatable = group::get_from_id($groupid);
            } else {
                $allocatable = user::get_from_id($addeduserid);
            }
            // process allocatables (group or student) allocation
            $stage1->make_auto_allocation_if_necessary($allocatable);
        }
    }
    return true;
}

/**
 * * Function to process allocation of new group members (student/group - assign to a group assessor or assessor - assign to students/group) when a group member is deleted
 *
 * @param $eventdata
 * @return bool
 * @throws coding_exception
 * @throws dml_exception
 */
function course_group_member_removed($eventdata) {
    global $DB;

    $groupid = $eventdata->objectid;
    $courseid = $eventdata->courseid;
    $removeduserid = $eventdata->relateduserid;

    // get all courseworks with group_assessor allocation strategy
    $courseworks = $DB->get_records('coursework', ['course' => $courseid, 'assessorallocationstrategy' => 'group_assessor']);

    foreach ($courseworks as $coursework) {
        $coursework = coursework::get_from_id($coursework->id);
        $stage = $coursework->marking_stages();
        $stage1 = $stage['assessor_1']; // this allocation is only for 1st stage, we don't touch other stages

        $student = $coursework->can_submit(); // check if user is student in this course
        $initialstageassessor = has_capability('mod/coursework:addinitialgrade', $coursework->get_context(), $removeduserid); // check if user was initial stage assessor in this course

        if ($initialstageassessor) {
            // remove all assessor allocations for this group
            $groupallocation = $coursework->is_configured_to_have_group_submissions()
                ? allocation::find(
                    [
                        'courseworkid' => $coursework->id(),
                        'assessorid' => $removeduserid,
                        'allocatableid' => $groupid,
                        'allocatabletype' => 'group',
                        'stageidentifier' => 'assessor_1',
                    ]
                )
                : null;
            if ($groupallocation && can_delete_allocation($coursework->id(), $groupid)) {
                $groupallocation->destroy();
            } else {
                // find all individual students in the group
                $students = get_enrolled_users($coursework->get_context(), 'mod/coursework:submit', $groupid);
                if ($students) {
                    foreach ($students as $student) {
                        if (can_delete_allocation($coursework->id(), $student->id)) {
                            $userallocation = allocation::find(
                                [
                                    'courseworkid' => $coursework->id(),
                                    'assessorid' => $removeduserid,
                                    'allocatableid' => $student->id,
                                    'allocatabletype' => 'user',
                                    'stageidentifier' => 'assessor_1',
                                ]
                            );
                            if ($userallocation) {
                                $userallocation->destroy();
                            }
                        }
                    }
                } else {
                    continue;
                }
            }

            // check if there are any other assessor in the group, at this point the removed member should no longer be in the group
            $assessorsingroup = get_enrolled_users($coursework->get_context(), 'mod/coursework:addinitialgrade', $groupid);

            if ($assessorsingroup) { // if another assessor found, assign all allocatables in this group to the other assessor
                if ($coursework->is_configured_to_have_group_submissions()) {// yes - assign the assessor to a allocatable group
                    $stage1->make_auto_allocation_if_necessary(group::get_from_id($groupid));
                } else {
                    $allocatables = $coursework->get_allocatables();
                    if ($allocatables) {
                        // yes - assign this assessor to every allocatable student in the appropriate course group
                        foreach ($allocatables as $allocatable) {
                            // process students allocations
                            $stage1->make_auto_allocation_if_necessary($allocatable);
                        }
                    } else {// no - do nothing, return true
                        continue;
                    }
                }
            } else {
                continue;
            }
        } else if ($student) {
            if ($coursework->is_configured_to_have_group_submissions()) {
                // check if student was the only student member in the group
                $students = get_enrolled_users($coursework->get_context(), 'mod/coursework:submit', $groupid); // at this point student should be already removed from the group

                if (!$students) { // if no students in group, then remove group allocation
                    $allocatableid = $groupid;
                } else {
                    continue; // continue as we store group allocatableid, so removing student from the group with many students doesn't affect allocations
                }
            } else {
                // If individual coursework
                $allocatableid = $removeduserid;
            }

            if (can_delete_allocation($coursework->id(), $allocatableid)) {
                $DB->delete_records('coursework_allocation_pairs', ['courseworkid' => $coursework->id(), 'allocatableid' => $allocatableid, 'stageidentifier' => 'assessor_1']);
                allocation::remove_cache($coursework->id);
            }

            // check if the student was in a different group and allocate them to the first found group
            if (!$coursework->is_configured_to_have_group_submissions()) {
                $allocatable = user::get_from_id($allocatableid);
                if ($coursework->student_is_in_any_group($allocatable)) {
                    $stage1->make_auto_allocation_if_necessary($allocatable);
                }
            }
        }
    }
    return true;
}

/**
 * Function to check the allocation if it is not pinned or its submission has not been marked yet
 *
 * @param int $courseworkid
 * @param $allocatableid
 * @return mixed
 * @throws dml_exception
 */
function can_delete_allocation($courseworkid, $allocatableid) {
    global $DB;

    // Check if allocation is pinned or already graded by an assessor / 1st stage only!
    $sql = "SELECT *
        FROM {coursework_allocation_pairs} p
        WHERE courseworkid = :courseworkid
        AND p.ismanual = 0
        AND stageidentifier = 'assessor_1'
        AND allocatableid = :allocatableid
        AND NOT EXISTS (
            SELECT 1
            FROM {coursework_submissions} s
            INNER JOIN {coursework_feedbacks} f ON f.submissionid = s.id
            WHERE s.allocatableid = p.allocatableid
            AND s.allocatabletype = p.allocatabletype
            AND s.courseworkid = p.courseworkid
            AND f.stageidentifier = p.stageidentifier
        )";

    return $DB->get_record_sql($sql, ['courseworkid' => $courseworkid, 'allocatableid' => $allocatableid]);
}

/**
 * @param $coursemodule
 * @return string
 */
function plagiarism_similarity_information($coursemodule) {
    $html = '';

    ob_start();
    echo   plagiarism_print_disclosure($coursemodule->id);
    $html .= ob_get_clean();

    return $html;
}

/**
 * @return bool
 * @throws ddl_exception
 * @throws dml_exception
 */
function has_user_seen_tii_eula_agreement() {
    global $CFG, $DB, $USER;

    // if TII plagiarism enabled check if user agreed/disagreed EULA
    $shouldseeeula = false;
    if ($CFG->enableplagiarism) {
        $plagiarismsettings = (array)get_config('plagiarism_turnitin');
        if (!empty($plagiarismsettings['enabled'])) {
            if ($DB->get_manager()->table_exists('plagiarism_turnitin_users')) {
                $sql = "SELECT * FROM {plagiarism_turnitin_users}
                        WHERE userid = :userid
                        AND user_agreement_accepted <> 0";
            } else {
                $sql = "SELECT * FROM {turnitintooltwo_users}
                        WHERE userid = :userid
                        AND user_agreement_accepted <> 0";
            }

            $shouldseeeula = $DB->record_exists_sql($sql, ['userid' => $USER->id]);
        }
    } else {
        $shouldseeeula = true;
    }
    return $shouldseeeula;
}

function coursework_is_ulcc_digest_coursework_plugin_installed() {

    global  $DB;

    $pluginexists = false;
    $disgestblockexists = $DB->record_exists_sql("SELECT id FROM {block} WHERE name = 'ulcc_digest' AND visible = 1");

    if (!empty($disgestblockexists)) {
         $pluginexists = $DB->record_exists('block_ulcc_digest_plgs', ['module' => 'coursework', 'status' => 1]);
    }

    return $pluginexists;
}

/**
 * @param int $courseworkid
 * @return bool
 * @throws dml_exception
 */
function coursework_personaldeadline_passed($courseworkid) {
    global $DB;

    $sql = "SELECT *
            FROM {coursework_person_deadlines}
            WHERE courseworkid = :courseworkid
            AND personaldeadline < :now";

    return $DB->record_exists_sql($sql, ['courseworkid' => $courseworkid, 'now' => time()]);
}

/**
 * Purge coursework cache if a role with specific capability passed
 *
 * @param $eventdata
 * @return bool
 */
function teacher_allocation_cache_purge($eventdata) {
    $roleid = $eventdata->objectid;

    // get roles with a specific capability
    $roles = get_roles_with_capability('mod/coursework:addinitialgrade');
    if (in_array($roleid, array_keys($roles))) { // if any role with above capability is in array, purge cache
        // purge only coursework cache
        $cache = cache::make('mod_coursework', 'courseworkdata');
        $cache->purge();
    }
    return true;
}

/**
 * Function to remove teacher allocation (also if pinned), don't remove if teacher already graded
 *
 * @param $eventdata
 * @return bool
 * @throws coding_exception
 * @throws dml_exception
 */
function teacher_removed_allocated_not_graded($eventdata) {
    global $DB;

    $userid = $eventdata->relateduserid;
    $courseid = $eventdata->courseid;

    $courseworks = coursework_get_courseworks_by_courseid($courseid);
    foreach ($courseworks as $cw) {
        $coursework = coursework::get_from_id($cw->id);
        if ($coursework->allocation_enabled()) {
            $assessorallocations = $DB->get_records('coursework_allocation_pairs', ['courseworkid' => $coursework->id,
                                                                                                'assessorid' => $userid]);
            foreach ($assessorallocations as $allocation) {
                if ($allocation->allocatabletype == 'user') {
                    $allocatable = user::get_from_id($allocation->allocatableid);
                } else {
                    $allocatable = group::get_from_id($allocation->allocatableid);
                }

                $submission = $coursework->get_allocatable_submission($allocatable);
                // if assessor grade the submission already, skip it
                if ($submission && $submission->has_specific_assessor_feedback($userid)) {
                    continue;
                }

                $DB->delete_records('coursework_allocation_pairs', ['courseworkid' => $coursework->id,
                                                                              'assessorid' => $userid,
                                                                              'allocatableid' => $allocatable->id]);
            }
            allocation::remove_cache($coursework->id);
        }
    }

    return true;
}

/**
 * Get all courseworks in the given course
 *
 * @param $courseid
 * @return array
 * @throws dml_exception
 */
function coursework_get_courseworks_by_courseid($courseid) {
    global $DB;

    return $DB->get_records('coursework', ['course' => $courseid]);
}
