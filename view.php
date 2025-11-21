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
 * @copyright  2011 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\event\course_module_viewed;
use mod_coursework\export\csv;
use mod_coursework\export\grading_sheet;
use mod_coursework\models\submission;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate"); // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache"); // HTTP/1.0
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

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

// Course_module ID, or coursework instance ID - it should be named as the first character of the module.
$coursemoduleid = optional_param('id', 0, PARAM_INT);
$courseworkid = optional_param('e', 0, PARAM_INT);
// Hacky fix for the need for the form to self-submit to this page.
if (!$courseworkid) {
    $courseworkid = optional_param('courseworkid', 0, PARAM_INT);
}
$download = optional_param('download', false, PARAM_BOOL);
$resubmit = optional_param('resubmit', 0, PARAM_TEXT); // Are we resubmitting a turnitin thing?
$resubmitted = optional_param('resubmitted', 0, PARAM_INT); // Is this a post-resubmit redirect?
$submissionid = optional_param('submissionid', 0, PARAM_INT); // Which thing to resubmit.
$confirm = optional_param('confirm', 0, PARAM_INT);
$exportgrades = optional_param('export', false, PARAM_BOOL);
$downloadgradingsheet = optional_param('export_grading_sheet', false, PARAM_BOOL);
$group = optional_param('group', -1, PARAM_INT);

$courseworkrecord = new stdClass();

if ($coursemoduleid) {
    $coursemodule = get_coursemodule_from_id(
        'coursework',
        $coursemoduleid,
        0,
        false,
        MUST_EXIST
    );
    $course = $DB->get_record('course', ['id' => $coursemodule->course], '*', MUST_EXIST);
    $courseworkrecord = $DB->get_record(
        'coursework',
        ['id' => $coursemodule->instance],
        '*',
        MUST_EXIST
    );
} else {
    if ($courseworkid) {
        $courseworkrecord = $DB->get_record(
            'coursework',
            ['id' => $courseworkid],
            '*',
            MUST_EXIST
        );
        $course = $DB->get_record(
            'course',
            ['id' => $courseworkrecord->course],
            '*',
            MUST_EXIST
        );
        $coursemodule = get_coursemodule_from_instance(
            'coursework',
            $courseworkrecord->id,
            $course->id,
            false,
            MUST_EXIST
        );
    } else {
        die('You must specify a course_module ID or an instance ID');
    }
}

// This will set $PAGE->context to the coursemodule's context.
require_login($course, true, $coursemodule);

$coursework = mod_coursework\models\coursework::find($courseworkrecord);

// Check if group is in session and use it no group available in url
if (groups_get_activity_groupmode($coursework->get_course_module()) != 0 && $group == -1) {
    // Check if a group is in SESSION
    $group = groups_get_activity_group($coursework->get_course_module());
}

// Make sure we sort out any stuff that cron should have done, just in case it's not run yet.
$coursework->finalise_all();

// Name of new zip file.
$filename = str_replace(' ', '_', clean_filename($COURSE->shortname . '-' . $coursework->name . '.zip'));
if ($download && $zipfile = $coursework->pack_files()) {
    send_temp_file($zipfile, $filename); // Send file and delete after sending.
}

if ($exportgrades) {
    // Headers and data for csv.
    $csvcells = ['name', 'username', 'idnumber', 'email'];

    if ($coursework->personaldeadlines_enabled()) {
        $csvcells[] = 'personaldeadline';
    }

    $csvcells[] = 'submissiondate';
    $csvcells[] = 'submissiontime';
    $csvcells[] = 'submissionfileid';

    if ($coursework->extensions_enabled() && ($coursework->has_deadline()) || $coursework->personaldeadlines_enabled()) {
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
    $filename = get_string('finalmarksfor', 'coursework') . $coursework->name . ' ' . $timestamp;
    $csv = new csv($coursework, $csvcells, $filename);
    $csv->export();
}

if ($downloadgradingsheet) {
    $csvcells = grading_sheet::cells_array($coursework);

    $timestamp = date('d_m_y @ H-i');
    $filename = get_string('markingsheetfor', 'coursework') . $coursework->name . ' ' . $timestamp;
    $gradingsheet = new grading_sheet($coursework, $csvcells, $filename);
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

$event = course_module_viewed::create(
    ['objectid' => $coursework->id, 'context' => $coursework->get_context()]
);
$event->trigger();

$params = ['id' => $coursemodule->id,
                'sortby' => '',
                'group' => $group];

$PAGE->set_url('/mod/coursework/view.php', $params);
$PAGE->set_title($coursework->name);
$PAGE->set_heading($course->shortname);

// Auto publish after the deadline.
if (
    $coursework->has_individual_autorelease_feedback_enabled() &&
    $coursework->individual_feedback_deadline_has_passed() &&
    $coursework->has_stuff_to_publish()
) {
    $coursework->publish_grades();
}

// Create automatic feedback.
if ($coursework->automaticagreement_enabled()) {
    $coursework->create_automatic_feedback();
}

// TODO - I don't know what this is.
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
}

if ($cangrade || $canviewstudents) {
    $PAGE->requires->js_call_amd('mod_coursework/coursework_edit', 'init');
}

$objectrenderer = $PAGE->get_renderer('mod_coursework', 'object');
echo $OUTPUT->header();
echo $objectrenderer->render(new mod_coursework_coursework($coursework));
echo $OUTPUT->footer();
