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
 * This file provides an interface for someone with the appropriate permissions to submit a file on behalf of a student
 * who may have problems with their internet access, or who cannot for some reason work out how to use the submission form.
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2012 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\models\coursework;
use mod_coursework\models\submission;

require_once(dirname(__FILE__).'/../../../config.php');

global $CFG, $DB, $PAGE, $OUTPUT;


$coursemoduleid = required_param('cmid', PARAM_INT);
$coursemodule = $DB->get_record('course_modules', array('id' => $coursemoduleid));
$coursework = coursework::find($coursemodule->instance);
$course = $DB->get_record('course', array('id' => $coursemodule->course));
$studentid = optional_param('userid', 0, PARAM_INT);
$student = \mod_coursework\models\user::find($studentid);

// Get an empty submission for the form even if we know there won't be one.
$submission = $coursework->get_user_submission($student);
if (empty($submission)) {
    $submission = $coursework->build_own_submission($student);
}
// The submission defaults to the current user's id, which we don't want.

require_login($course, false, $coursemodule);

require_capability('mod/coursework:submitonbehalfof', $PAGE->context);

$params = array('cmid' => $coursemoduleid);
$url = new moodle_url('/mod/coursework/actions/submitonbehalfofstudent.php', $params);
$PAGE->set_url($url, $params);

// In case we had the choose student form cancelled.
$customdata = new stdClass();
$customdata->coursework = $coursework;
$chooseform = new \mod_coursework\forms\choose_student_for_submission_mform($PAGE->url->out(), $customdata);
if ($chooseform->is_cancelled()) {
    redirect(new moodle_url('mod/coursework/view.php', array('id' => $coursemoduleid)));
}

$student = false;
if ($studentid) {
    $student = $DB->get_record('user', array('id' => $studentid));
    $title = get_string('submitonbehalfofstudent', 'mod_coursework', fullname($student));
} else {
    $title = get_string('submitonbehalfof', 'mod_coursework');
}
$PAGE->set_title($title);
$PAGE->set_heading($title);

$html = '';

$customdata = array(
    'coursework' => $coursework,
    'submission' => $submission,
);
$submitform = new mod_coursework\forms\student_submission_form($url->out(), $customdata);

// Save any data that's there and redirect if successful.
$submitform->handle($coursework);

// Add any existing files if they're there.
if (!$submission->persisted()) {
    $draftitemid = file_get_submitted_draft_itemid('submission');
    file_prepare_draft_area($draftitemid, $PAGE->context->id, 'mod_coursework', 'submission', $submission->id,
                            $coursework->get_file_options());
    $submission->submission_manager = $draftitemid;
    $submitform->set_data($submission);
}


/**
 * @var mod_coursework_page_renderer $page_renderer
 */
$page_renderer = $PAGE->get_renderer('mod_coursework', 'page');

if (!$studentid) {
    echo $page_renderer->choose_student_to_submit_for($coursemoduleid, $chooseform);
} else {
    echo $page_renderer->submit_on_behalf_of_student_interface($student, $submitform);
}
