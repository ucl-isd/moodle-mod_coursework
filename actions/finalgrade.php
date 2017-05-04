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
 * This file displays a moodle form used to create a final grade for a submission
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2012 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\forms\assessor_feedback_mform;
use mod_coursework\models\submission;

require_once(dirname(__FILE__).'/../../../config.php');

global $CFG, $USER, $DB, $PAGE, $SITE, $OUTPUT;

require_once($CFG->dirroot.'/lib/adminlib.php');
require_once($CFG->dirroot.'/lib/formslib.php');
require_once($CFG->dirroot.'/mod/coursework/renderer.php');

$submission_id = optional_param('submissionid', 0, PARAM_INT);
$assessorid = optional_param('assessorid', $USER->id, PARAM_INT);
$feedbackid = optional_param('feedbackid', 0, PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$isfinalgrade = optional_param('isfinalgrade', 1, PARAM_INT);;

// Determines whether the current user is the owner of the grade.
$gradeowner = true;

$course_module = get_coursemodule_from_id('coursework', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $course_module->course), '*', MUST_EXIST);
require_login($course, false, $course_module);

$coursework = mod_coursework\models\coursework::find($course_module->instance);
$submission = submission::find($submission_id);
$teacherfeedback = $DB->get_record('coursework_feedbacks', array('id' => $feedbackid));

// This is where stuff used to construct the dynamic form is fed in.

// Can the user final grade in this course?
$canfinalgrade = has_capability('mod/coursework:addagreedgrade', $PAGE->context);

// TODO shift into custom data and set via somewhere else.
$coursework->submissionid = $submission_id;
$coursework->cmid = $cmid;

$gradeform = new assessor_feedback_mform();

// Was the form cancelled?
if ($gradeform->is_cancelled()) {
    redirect($CFG->wwwroot.'/mod/coursework/view.php?id='.$cmid);
}

// Was the form submitted?
// has the form been submitted?
if ($gradeform->is_submitted()) {
    // Check the validation rules.
    if ($gradeform->is_validated()) {

        // Get the form data submitted.
        $formdata = $gradeform->get_data();

        // Process the data.
        $success = $gradeform->process_data($formdata);

        // If saving the data was not successful.
        if (!$success) {
            // TODO should stay on same page with an error message.
        }

        // Recalculate moderation set now that we have a new grade, which may determine who gets moderated.
        $coursework->grade_changed_event();

        redirect($CFG->wwwroot.'/mod/coursework/view.php?id='.$cmid);
    }
}

$params = array('submissionid' => $submission_id,
                'assessorid' => $assessorid,
                'isfinalgrade' => $isfinalgrade);
$oldfinalgrade = $DB->get_record('coursework_feedbacks', $params);

// If the no old final grade exists and the current user is a manager lets see if another user has created
// a final grade.

if (empty($oldfinalgrade) && $canfinalgrade) {
    $params = array('submissionid' => $submission_id,
                    'isfinalgrade' => $isfinalgrade);
    $oldfinalgrade = $DB->get_record('coursework_feedbacks', $params);
    if ($oldfinalgrade) {
        $gradeowner = false;
    }
}

if (!empty($oldfinalgrade)) {
    $feedbackdata = new stdClass();
    $feedbackdata->feedbackcomment['text'] = $oldfinalgrade->feedbackcomment;
    $feedbackdata->feedbackcomment['format'] = $oldfinalgrade->feedbackcommentformat;
    $feedbackdata->grade = $oldfinalgrade->grade;
    $feedbackdata->id = $oldfinalgrade->id;
}

if (!empty($feedbackdata)) {
    $gradeform->set_data($feedbackdata);
}

$PAGE->set_url('/mod/coursework/actions/finalgrade.php');

$gradingstring = get_string('gradingfor', 'coursework',
                            $submission->get_allocatable_name());

$PAGE->set_pagelayout('standard');
$PAGE->navbar->add($gradingstring);
$PAGE->set_title($SITE->fullname);
$PAGE->set_heading($SITE->fullname);

echo $OUTPUT->header();

echo $OUTPUT->heading($gradingstring);
$assessor = $DB->get_record('user', array('id' => $assessorid));
echo html_writer::tag('p', get_string('assessor', 'coursework').' '.fullname($assessor));
echo html_writer::tag('p', get_string('gradingoutof', 'coursework', round($coursework->grade)));

// In case we have an editor come along, we want to show that this has happened.
if (!empty($teacherfeedback)) { // May not have been marked yet.
    if ($submission_id && !empty($teacherfeedback->lasteditedbyuser)) {
        $editor = $DB->get_record('user', array('id' => $teacherfeedback->lasteditedbyuser));
    } else {
        $editor = $assessor;
    }
    $details = new stdClass();
    $details->name = fullname($editor);
    $details->time = userdate($teacherfeedback->timemodified,'%a, %d %b %Y, %H:%M');
    echo html_writer::tag('p', get_string('lastedited', 'coursework', $details));
}

$files = $submission->get_submission_files();
$files_string = $files->has_multiple_files() ? 'submissionfiles' : 'submissionfile';

echo html_writer::start_tag('h1');
echo get_string($files_string, 'coursework');
echo html_writer::end_tag('h1');

/**
 * @var mod_coursework_object_renderer $object_renderer
 */
$object_renderer = $PAGE->get_renderer('mod_coursework', 'object');

echo $object_renderer->render_submission_files_with_plagiarism_links(new mod_coursework_submission_files($files));

$gradeform->display();

echo $OUTPUT->footer();


