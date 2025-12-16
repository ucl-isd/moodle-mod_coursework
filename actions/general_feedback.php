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
 * @copyright  2011 University of London computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\forms\general_feedback_form;
use mod_coursework\models\coursework;

require_once(dirname(__FILE__) . '/../../../config.php');

global $PAGE, $DB, $OUTPUT;

$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('coursework', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course]);
require_login($course, false, $cm);

$courseworkrecord = $DB->get_record('coursework', ['id' => $cm->instance], '*', MUST_EXIST);
$coursework = coursework::find($courseworkrecord, false);

if (!has_capability('mod/coursework:addinitialgrade', $PAGE->context)) {
    throw new moodle_exception('access_denied', 'coursework');
}

$url = '/mod/coursework/actions/general_feedback.php';
$link = new moodle_url($url, ['cmid' => $cmid]);
$PAGE->set_url($link);
$title = get_string('generalfeedback', 'mod_coursework');
$PAGE->set_title($title);

$customdata = new stdClass();
$customdata->id = $coursework->id();
$customdata->cmid = $cmid;

$gradingform = new general_feedback_form(null, $customdata);

$returneddata = $gradingform->get_data();

if ($gradingform->is_cancelled()) {
    redirect(new moodle_url('/mod/coursework/view.php', ['id' => $cmid]));
} else if ($returneddata) {
    $gradingform->process_data($returneddata);
    redirect(new moodle_url('/mod/coursework/view.php', ['id' => $cmid]), get_string('changessaved'));
} else {
    // Display the form.
    $PAGE->navbar->add($title);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($title);

    $releasedatestring = $coursework->generalfeedback
        ? userdate($coursework->generalfeedback, get_string('strftimedatetime', 'langconfig'))
        : get_string('now');
    echo '<p>' . get_string('publishedtostudentsfrom', 'mod_coursework', $releasedatestring) . '</p>';

    echo '<p>' . get_string('generalfeedbackinfo', 'mod_coursework') . '</p>';

    $customdata->feedbackcomment_editor['text'] = $coursework->get_general_feedback() ?? '';
    $gradingform->set_data($customdata);
    $gradingform->display();
    echo $OUTPUT->footer();
}
