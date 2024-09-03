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
 * Prints a particular instance of coursework
 *
 * @package    mod_coursework
 * @copyright  2011 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate"); // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache"); // HTTP/1.0
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

use mod_coursework\models\submission;
use mod_coursework\warnings;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

global $CFG, $DB, $PAGE, $COURSE, $OUTPUT, $USER, $SESSION;

require_once($CFG->dirroot . '/mod/coursework/lib.php');
require_once($CFG->dirroot . '/mod/coursework/renderer.php');
require_once($CFG->dirroot . '/lib/completionlib.php');
require_once($CFG->dirroot . '/lib/plagiarismlib.php');
require_once($CFG->dirroot . '/mod/coursework/classes/export/csv.php');

// TODO move all js to renderer.
$jsmodule = [
    'name' => 'mod_coursework',
    'fullpath' => '/mod/coursework/module.js',
    'requires' => ['base',
                        'node-base'],
    'strings' => [],
];

$PAGE->requires->yui_module('moodle-core-notification', 'notification_init');

// Course_module ID, or coursework instance ID - it should be named as the first character of the module.
$coursemoduleid = optional_param('id', 0, PARAM_INT);
$courseworkid = optional_param('e', 0, PARAM_INT);
// Hacky fix for the need for the form to self-submit to this page.
if (!$courseworkid) {
    $courseworkid = optional_param('courseworkid', 0, PARAM_INT);
}
$publish = optional_param('publishbutton', 0, PARAM_ALPHA);
$download = optional_param('download', false, PARAM_BOOL);
$resubmit = optional_param('resubmit', 0, PARAM_TEXT); // Are we resubmitting a turnitin thing?
$resubmitted = optional_param('resubmitted', 0, PARAM_INT); // Is this a post-resubmit redirect?
$submissionid = optional_param('submissionid', 0, PARAM_INT); // Which thing to resubmit.
$confirm = optional_param('confirm', 0, PARAM_INT);
$exportgrades = optional_param('export', false, PARAM_BOOL);
$downloadgradingsheet = optional_param('export_grading_sheet', false, PARAM_BOOL);
$group = optional_param('group', -1, PARAM_INT);
$resettable = optional_param('treset', 0, PARAM_INT);
$allresettable = optional_param('alltreset', 0, PARAM_INT);

if (!isset($SESSION->displayallstudents[$coursemoduleid])) {
    $SESSION->displayallstudents[$coursemoduleid] = optional_param('displayallstudents', false, PARAM_BOOL);

    $displayallstudents = $SESSION->displayallstudents[$coursemoduleid];
} else {
    $displayallstudents = optional_param('displayallstudents', $SESSION->displayallstudents[$coursemoduleid], PARAM_INT);
    $SESSION->displayallstudents[$coursemoduleid] = $displayallstudents;
}

// If a session variable holding page preference for the specific coursework is not set, set default value (0).
if (isset($SESSION->perpage[$coursemoduleid]) && optional_param('per_page', 0, PARAM_INT) != $SESSION->perpage[$coursemoduleid]
    && optional_param('per_page', 0, PARAM_INT) != 0) { // prevent blank pages if not in correct page
    $page = 0;
    $SESSION->page[$coursemoduleid] = $page;
} else if (!(isset($SESSION->page[$coursemoduleid]))) {
    $SESSION->page[$coursemoduleid] = optional_param('page', 0, PARAM_INT);
    $page = $SESSION->page[$coursemoduleid];
} else {
    $page = optional_param('page', $SESSION->page[$coursemoduleid], PARAM_INT);
    $SESSION->page[$coursemoduleid] = $page;
}

// If a session variable holding perpage preference for the specific coursework is not set, set default value (grab default value from global setting).
if (!(isset($SESSION->perpage[$coursemoduleid]))) {
    $SESSION->perpage[$coursemoduleid] = optional_param('per_page', $CFG->coursework_per_page, PARAM_INT);
    $perpage = $SESSION->perpage[$coursemoduleid];
} else {
    $perpage = optional_param('per_page', $SESSION->perpage[$coursemoduleid], PARAM_INT);
    $SESSION->perpage[$coursemoduleid] = $perpage;
}

// If a session variable holding sortby preference for the specific coursework is not set, set default value ('lastname').
if (!(isset($SESSION->sortby[$coursemoduleid]))) {
    $SESSION->sortby[$coursemoduleid] = optional_param('sortby', 'lastname', PARAM_ALPHA);
    $sortby = $SESSION->sortby[$coursemoduleid];
} else {
    $sortby = optional_param('sortby', $SESSION->sortby[$coursemoduleid], PARAM_ALPHA);
    $SESSION->sortby[$coursemoduleid] = $sortby;
}

// If a session variable holding sorthow preference for the specific coursework is not set, set default value ('ASC').
if (!(isset($SESSION->sorthow[$coursemoduleid]))) {
    $SESSION->sorthow[$coursemoduleid] = optional_param('sorthow', 'ASC', PARAM_ALPHA);
    $sorthow = $SESSION->sorthow[$coursemoduleid];
} else {
    $sorthow = optional_param('sorthow', $SESSION->sorthow[$coursemoduleid], PARAM_ALPHA);
    $SESSION->sorthow[$coursemoduleid] = $sorthow;
}

// First name alpha
if (!(isset($SESSION->coursework_firstname_alpha[$coursemoduleid]))) {
    $SESSION->coursework_firstname_alpha[$coursemoduleid] = optional_param('coursework_firstname_alpha', '', PARAM_ALPHA);
    $courseworkfirstnamealpha = $SESSION->coursework_firstname_alpha[$coursemoduleid];
} else {
    $courseworkfirstnamealpha = optional_param('coursework_firstname_alpha', $SESSION->coursework_firstname_alpha[$coursemoduleid], PARAM_ALPHA);
    $SESSION->coursework_firstname_alpha[$coursemoduleid] = $courseworkfirstnamealpha;
}

// Last name alpha
if (!(isset($SESSION->coursework_lastname_alpha[$coursemoduleid]))) {
    $SESSION->coursework_lastname_alpha[$coursemoduleid] = optional_param('coursework_lastname_alpha', '', PARAM_ALPHA);
    $courseworklastnamealpha = $SESSION->coursework_lastname_alpha[$coursemoduleid];
} else {
    $courseworklastnamealpha = optional_param('coursework_lastname_alpha', $SESSION->coursework_lastname_alpha[$coursemoduleid], PARAM_ALPHA);
    $SESSION->coursework_lastname_alpha[$coursemoduleid] = $courseworklastnamealpha;
}

// Group name alpha
if (!(isset($SESSION->coursework_groupname_alpha[$coursemoduleid]))) {
    $SESSION->coursework_groupname_alpha[$coursemoduleid] = optional_param('coursework_groupname_alpha', '', PARAM_ALPHA);
    $courseworkgroupnamealpha = $SESSION->coursework_groupname_alpha[$coursemoduleid];
} else {
    $courseworkgroupnamealpha = optional_param('coursework_groupname_alpha', $SESSION->coursework_groupname_alpha[$coursemoduleid], PARAM_ALPHA);
    $SESSION->coursework_groupname_alpha[$coursemoduleid] = $courseworkgroupnamealpha;
}

// We will use the same defaults as page (above) defaulting to page setting if no specific viewallstudents_page has been set
if (isset($SESSION->viewallstudents_perpage[$coursemoduleid]) && optional_param('viewallstudents_per_page', 0, PARAM_INT) != $SESSION->viewallstudents_perpage[$coursemoduleid]
    && optional_param('viewallstudents_per_page', 0, PARAM_INT) != 0) { // prevent blank pages if not in correct page
    $viewallstudentspage = 0;
    $SESSION->viewallstudents_page[$coursemoduleid] = $viewallstudentspage;
} else if (!(isset($SESSION->viewallstudents_page[$coursemoduleid]))) {
    $SESSION->viewallstudents_page[$coursemoduleid] = optional_param('viewallstudents_page', 0, PARAM_INT);
    $viewallstudentspage = $SESSION->viewallstudents_page[$coursemoduleid];
} else {
    $viewallstudentspage = optional_param('viewallstudents_page', $SESSION->viewallstudents_page[$coursemoduleid], PARAM_INT);
    $SESSION->viewallstudents_page[$coursemoduleid] = $viewallstudentspage;
}

// We will use the same defaults as perpage (above) defaulting to perpage setting if no specific viewallstudents_perpage has been set
if (!(isset($SESSION->viewallstudents_perpage[$coursemoduleid]))) {
    $SESSION->viewallstudents_perpage[$coursemoduleid] = optional_param('viewallstudents_per_page', $perpage, PARAM_INT);
    $viewallstudentsperpage = $SESSION->viewallstudents_perpage[$coursemoduleid];
} else {
    $viewallstudentsperpage = optional_param('viewallstudents_per_page', $SESSION->perpage[$coursemoduleid], PARAM_INT);
    $SESSION->viewallstudents_perpage[$coursemoduleid] = $viewallstudentsperpage;
}

// We will use the same defaults as sortby (above) defaulting to sortby setting if no specific viewallstudents_sortby has been set
if (!(isset($SESSION->viewallstudents_sortby[$coursemoduleid]))) {
    $SESSION->viewallstudents_sortby[$coursemoduleid] = optional_param('viewallstudents_sortby', $sortby, PARAM_ALPHA);
    $viewallstudentssortby = $SESSION->viewallstudents_sortby[$coursemoduleid];
} else {
    $viewallstudentssortby = optional_param('viewallstudents_sortby', $SESSION->sortby[$coursemoduleid], PARAM_ALPHA);
    $SESSION->viewallstudents_sortby[$coursemoduleid] = $viewallstudentssortby;
}

// We will use the same defaults as sorthow (above) defaulting to sorthow setting if no specific viewallstudents_sorthow has been set
if (!(isset($SESSION->viewallstudents_sorthow[$coursemoduleid]))) {
    $SESSION->viewallstudents_sorthow[$coursemoduleid] = optional_param('viewallstudents_sorthow', $sorthow, PARAM_ALPHA);
    $viewallstudentssorthow = $SESSION->viewallstudents_sorthow[$coursemoduleid];
} else {
    $viewallstudentssorthow = optional_param('viewallstudents_sorthow', $SESSION->sorthow[$coursemoduleid], PARAM_ALPHA);
    $SESSION->viewallstudents_sorthow[$coursemoduleid] = $viewallstudentssorthow;
}

// First name alpha
if (!(isset($SESSION->viewallstudents_firstname_alpha[$coursemoduleid]))) {
    $SESSION->viewallstudents_firstname_alpha[$coursemoduleid] = optional_param('viewallstudents_firstname_alpha', '', PARAM_ALPHA);
    $viewallstudentsfirstnamealpha = $SESSION->coursework_firstname_alpha[$coursemoduleid];
} else {
    $viewallstudentsfirstnamealpha = optional_param('viewallstudents_firstname_alpha', $SESSION->viewallstudents_firstname_alpha[$coursemoduleid], PARAM_ALPHA);
    $SESSION->viewallstudents_firstname_alpha[$coursemoduleid] = $viewallstudentsfirstnamealpha;
}

// Last name alpha
if (!(isset($SESSION->viewallstudents_lastname_alpha[$coursemoduleid]))) {
    $SESSION->viewallstudents_lastname_alpha[$coursemoduleid] = optional_param('viewallstudents_lastname_alpha', '', PARAM_ALPHA);
    $viewallstudentslastnamealpha = $SESSION->viewallstudents_lastname_alpha[$coursemoduleid];
} else {
    $viewallstudentslastnamealpha = optional_param('viewallstudents_lastname_alpha', $SESSION->viewallstudents_lastname_alpha[$coursemoduleid], PARAM_ALPHA);
    $SESSION->viewallstudents_lastname_alpha[$coursemoduleid] = $viewallstudentslastnamealpha;
}

// Group name alpha
if (!(isset($SESSION->viewallstudents_groupname_alpha[$coursemoduleid]))) {
    $SESSION->viewallstudents_groupname_alpha[$coursemoduleid] = optional_param('viewallstudents_groupname_alpha', '', PARAM_ALPHA);
    $viewallstudentsgroupnamealpha = $SESSION->viewallstudents_groupname_alpha[$coursemoduleid];
} else {
    $viewallstudentsgroupnamealpha = optional_param('viewallstudents_groupname_alpha', $SESSION->viewallstudents_groupname_alpha[$coursemoduleid], PARAM_ALPHA);
    $SESSION->viewallstudents_groupname_alpha[$coursemoduleid] = $viewallstudentsgroupnamealpha;
}

if (!($sorthow === 'ASC' || $sorthow === 'DESC')) {
    $sorthow = 'ASC';
}

$courseworkrecord = new stdClass();

if ($coursemoduleid) {
    $coursemodule = get_coursemodule_from_id('coursework',
                                              $coursemoduleid,
                                              0,
                                              false,
                                              MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $coursemodule->course], '*', MUST_EXIST);
    $courseworkrecord = $DB->get_record('coursework',
                                         ['id' => $coursemodule->instance],
                                         '*',
                                         MUST_EXIST);
} else {
    if ($courseworkid) {
        $courseworkrecord = $DB->get_record('coursework',
                                             ['id' => $courseworkid],
                                             '*',
                                             MUST_EXIST);
        $course = $DB->get_record('course',
                                  ['id' => $courseworkrecord->course],
                                  '*',
                                  MUST_EXIST);
        $coursemodule = get_coursemodule_from_instance('coursework',
                                                        $courseworkrecord->id,
                                                        $course->id,
                                                        false,
                                                        MUST_EXIST);
    } else {
        die('You must specify a course_module ID or an instance ID');
    }
}

$coursework = mod_coursework\models\coursework::find($courseworkrecord);

// Check if group is in session and use it no group available in url
if (groups_get_activity_groupmode($coursework->get_course_module()) != 0 && $group == -1) {
    // Check if a group is in SESSION
    $group = groups_get_activity_group($coursework->get_course_module());
}

// Commented out the redirection for Release1 #108535552, this will be revisited for Release2
/*if (has_capability('mod/coursework:allocate', $coursework->get_context())) {
    $warnings = new \mod_coursework\warnings($coursework);

    $percentage_allocation_not_complete = $warnings->percentage_allocations_not_complete();
    $manual_allocation_not_complete = '';
    if ($coursework->allocation_enabled()) {
        $manual_allocation_not_complete = $warnings->manual_allocation_not_completed();
    }

    if (!empty($percentage_allocation_not_complete) || !empty($manual_allocation_not_complete)) {

        $redirectdetail = new \stdClass();
        $redirectdetail->percentage = $percentage_allocation_not_complete;
        $redirectdetail->manual = $manual_allocation_not_complete;

        redirect($CFG->wwwroot.'/mod/coursework/actions/allocate.php?id='.$course_module_id, get_string('configuration_needed', 'coursework', $redirectdetail));
    }
}*/

// Change default sortby to Date (timesubmitted) if CW is set to blind marking and a user doesn't have capability to view anonymous
$viewanonymous = has_capability('mod/coursework:viewanonymous', $coursework->get_context());
if (($coursework->blindmarking && !$viewanonymous )) {
    $sortby = optional_param('sortby', 'timesubmitted', PARAM_ALPHA);
}

// Make sure we sort out any stuff that cron should have done, just in case it's not run yet.
if (($coursework->has_deadline() && $coursework->deadline_has_passed()) || $coursework->personal_deadlines_enabled()) {
    $coursework->finalise_all();
}

// This will set $PAGE->context to the coursemodule's context.
require_login($course, true, $coursemodule);

// Name of new zip file.
$filename = str_replace(' ', '_', clean_filename($COURSE->shortname . '-' . $coursework->name . '.zip'));
if ($download && $zipfile = $coursework->pack_files()) {
    send_temp_file($zipfile, $filename); // Send file and delete after sending.
}

if ($exportgrades) {

    // Headers and data for csv
    $csvcells = ['name', 'username', 'idnumber', 'email'];

    if ($coursework->personal_deadlines_enabled()) {
        $csvcells[] = 'personaldeadline';
    }

    $csvcells[] = 'submissiondate';
    $csvcells[] = 'submissiontime';
    $csvcells[] = 'submissionfileid';

    if ($coursework->extensions_enabled() && ($coursework->has_deadline()) || $coursework->personal_deadlines_enabled()) {
        $csvcells[] = 'extensiondeadline';
        $csvcells[] = 'extensionreason';
        $csvcells[] = 'extensionextrainfo';
    }

    if ($coursework->plagiarism_flagging_enbled()) {
        $csvcells[] = 'plagiarismflagstatus';
        $csvcells[] = 'plagiarismflagcomment';
    }

    $csvcells[] = 'stages';

    if ($coursework->moderation_agreement_enabled()) {
        $csvcells[] = 'moderationagreement';
    }
    $csvcells[] = 'finalgrade';

    $timestamp = date('d_m_y @ H-i');
    $filename = get_string('finalgradesfor', 'coursework'). $coursework->name .' '.$timestamp;
    $csv = new \mod_coursework\export\csv($coursework, $csvcells, $filename);
    $csv->export();

}

if ($downloadgradingsheet) {

    $csvcells = \mod_coursework\export\grading_sheet::cells_array($coursework);

    $timestamp = date('d_m_y @ H-i');
    $filename = get_string('gradingsheetfor', 'coursework'). $coursework->name .' '.$timestamp;
    $gradingsheet = new \mod_coursework\export\grading_sheet($coursework, $csvcells, $filename);
    $gradingsheet->export();
}

$cangrade = has_capability('mod/coursework:addinitialgrade', $PAGE->context);
$cansubmit = has_capability('mod/coursework:submit', $PAGE->context);
$canviewstudents = false;

// TODO this is awful.
$capabilities = ['addinstance',
                      'submitonbehalfof',
                      'addinitialgrade',
                      'editinitialgrade',
                      'addagreedgrade',
                      'editagreedgrade',
                      'publish',
                      'viewanonymous',
                      'revertfinalised',
                      'allocate',
                      'viewallgradesatalltimes',
                      'administergrades',
                      'grantextensions',
                      'canexportfinalgrades',
                      'viewextensions',
                      'grade'];

foreach ($capabilities as $capability) {

    if (has_capability('mod/coursework:' . $capability, $PAGE->context)) {
        $canviewstudents = true;
        break;
    }
}

if ((float)substr($CFG->release, 0, 5) > 2.6) { // 2.8 > 2.6
    $event = \mod_coursework\event\course_module_viewed::create([
                                                                    'objectid' => $coursework->id,
                                                                    'context' => $coursework->get_context(),
                                                                ]);
    $event->trigger();
} else {
    add_to_log($course->id,
               'coursework',
               'view',
               "view.php?id=$coursemodule->id",
               $coursework->name,
               $coursemodule->id);
}

// Print the page header.

// Sort group by groupname (default)
if ($coursework->is_configured_to_have_group_submissions()) {
    $sortby = optional_param('sortby', 'groupname', PARAM_ALPHA);
    $viewallstudentssortby = optional_param('viewallstudents_sortby', 'groupname', PARAM_ALPHA);

}
$params = ['id' => $coursemodule->id,
                'sortby' => $sortby,
                'sorthow' => $sorthow,
                'per_page' => $perpage,
                'group' => $group];

if (!empty($SESSION->displayallstudents[$coursemoduleid])) {
    $params['viewallstudents_sorthow'] = $viewallstudentssorthow;
    $params['viewallstudents_sortby'] = $viewallstudentssortby;
    $params['viewallstudents_per_page'] = $viewallstudentsperpage;
}

$PAGE->set_url('/mod/coursework/view.php', $params);
$PAGE->set_title($coursework->name);
$PAGE->set_heading($course->shortname);

// Auto publish after the deadline
if ($coursework->has_individual_autorelease_feedback_enabled() &&
    $coursework->individual_feedback_deadline_has_passed() &&
    $coursework->has_stuff_to_publish()
) {

    $coursework->publish_grades();
}

// Create automatic feedback
if ($coursework->automaticagreement_enabled()) {
    $coursework->create_automatic_feedback();

}

// Output starts here.
$html = '';

/**
 * @var mod_coursework_object_renderer $object_renderer
 */
$objectrenderer = $PAGE->get_renderer('mod_coursework', 'object');
/**
 * @var mod_coursework_page_renderer $page_renderer
 */
$pagerenderer = $PAGE->get_renderer('mod_coursework', 'page');

$html .= $objectrenderer->render(new mod_coursework_coursework($coursework));

// Allow tutors to upload files as part of the coursework task? Easily done via the main
// course thing, so not necessary.

// If this is a student, show the submission form, or their existing submission, or both
// There is scope for an arbitrary number of files to be added here, before the deadline.
if ($cansubmit && !$cangrade) {
    $html .= $pagerenderer->student_view_page($coursework, \mod_coursework\models\user::find($USER));
}

// Display the submissions table of all the students instead.
if ($canviewstudents) {

    // If the resubmit button was pressed (for plagiarism), we need to fire a new event.
    if ($resubmit && $submissionid) {

        /**
         * @var submission $submission
         */
        $submission = submission::find($submissionid);

        $params = [
            'cm' => $coursemodule->id,
            'userid' => $submission->get_author_id(),
        ];
        // Get the hash so we can retrieve the file and update timemodified.
        $filehash = $DB->get_field('plagiarism_turnitin_files', 'identifier', $params);

        // Remove the turnitin file, which may have hit the limit for retries ages ago.
        $DB->delete_records('plagiarism_turnitin_files', $params);

        // If Turnitin is enabled after a file has been submitted, it'll fail because it uses the file
        // timemodified as the submission date. We need to prevent this, so we alter the file timemodified
        // to be now and rely on the submission timemodified date to tell us when the student finalised their
        // work.
        if ($filehash) {
            $params = [
                'pathnamehash' => $filehash,
            ];
            $file = $DB->get_record('files', $params);
            $file->timemodified = time();
            $DB->update_record('files', $file);
        }
        $submission->submit_plagiarism('final'); // Must happen AFTER files have been updated.
        redirect($PAGE->url, get_string('resubmitted', 'coursework', $submission->get_allocatable_name()));
    }

    // If publish button was pressed, update the gradebook after confirmation.
    if ($publish && has_capability('mod/coursework:publish', $PAGE->context)) {

        if (!$confirm) {

            // Ask the user for confirmation.
            $confirmurl = clone $PAGE->url;
            $confirmurl->param('confirm', 1);
            $confirmurl->param('publishbutton', 'true');
            $html = $OUTPUT->confirm(get_string('confirmpublish', 'mod_coursework'), $confirmurl, $PAGE->url);
        } else {
            // Already confirmed. Publish and redirect.
            $coursework->publish_grades();
            $url = clone($PAGE->url);
            $url->remove_params(['confirm',
                                      'publishbutton']);
            redirect($url, get_string('gradespublished', 'mod_coursework'));
        }
    } else {

        if ($resettable) {
            $courseworkfirstnamealpha = $SESSION->coursework_firstname_alpha[$coursemoduleid] = "";
            $courseworklastnamealpha = $SESSION->coursework_lastname_alpha[$coursemoduleid] = "";
            $courseworkgroupnamealpha = $SESSION->coursework_groupname_alpha[$coursemoduleid] = "";
        }

        if ($allresettable) {
            $viewallstudentsfirstnamealpha = $SESSION->viewallstudents_firstname_alpha[$coursemoduleid] = "";
            $viewallstudentslastnamealpha = $SESSION->viewallstudents_lastname_alpha[$coursemoduleid] = "";
            $viewallstudentsgroupnamealpha = $SESSION->viewallstudents_groupname_alpha[$coursemoduleid] = "";
        }

        $html .= $pagerenderer->teacher_grading_page($coursework, $page, $perpage, $sortby, $sorthow, $group, $courseworkfirstnamealpha, $courseworklastnamealpha, $courseworkgroupnamealpha, $resettable);
        $html .= $pagerenderer->non_teacher_allocated_grading_page($coursework, $viewallstudentspage, $viewallstudentsperpage, $viewallstudentssortby, $viewallstudentssorthow, $group, $displayallstudents, $viewallstudentsfirstnamealpha, $viewallstudentslastnamealpha, $viewallstudentsgroupnamealpha);
        $html .= $pagerenderer->datatables_render($coursework);
        $html .= $pagerenderer->render_modal();
    }
}

echo $OUTPUT->header();
echo $html;
echo '<script src="'.$CFG->wwwroot.'/mod/coursework/datatables/js/jquery-3.3.1.min.js"></script>
<link rel="stylesheet" type="text/css" href="'. $CFG->wwwroot .'/mod/coursework/datatables/css/datatables.bootstrap.min.css"/>
    <link rel="stylesheet" type="text/css" href="'. $CFG->wwwroot .'/mod/coursework/datatables/css/jquery.datetimepicker.css"/>
<script src="'.$CFG->wwwroot.'/mod/coursework/datatables/js/jquery.datatables.js"></script>
<script src="'.$CFG->wwwroot.'/mod/coursework/datatables/js/datatables.js"></script>
<script src="'.$CFG->wwwroot.'/mod/coursework/datatables/js/php-date-formatter.min.js"></script>
<script src="'.$CFG->wwwroot.'/mod/coursework/datatables/js/edit_datatables.js"></script>
';

// $PAGE->requires->js('/mod/coursework/datatables/js/jquery-3.3.1.min.js');
// $PAGE->requires->js('/mod/coursework/datatables/js/jquery.datatables.js');
// Finish the page.
echo $OUTPUT->footer();

