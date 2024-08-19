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
 * @copyright  2011 University of London computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\forms\general_feedback_form;

require_once(dirname(__FILE__) . '/../../../config.php');

global $CFG, $PAGE, $DB, $OUTPUT;

$cmid = required_param('cmid', PARAM_INT);
$ajax = optional_param('ajax', false, PARAM_BOOL);

$cm = get_coursemodule_from_instance('coursework', $cmid);
$course = $DB->get_record('course', array('id' => $cm->course));
require_login($course, false, $cm);

$coursework = $DB->get_record('coursework', array('id' => $cm->instance));

if (!has_capability('mod/coursework:addinitialgrade', $PAGE->context)) {
    throw new \moodle_exception('access_denied', 'coursework');
}

$url = '/mod/coursework/actions/general_feedback.php';
$link = new moodle_url($url, array('cmid' => $cmid));
$PAGE->set_url($link);
$title = get_string('generalfeedback', 'mod_coursework');
$PAGE->set_title($title);

$customdata = new stdClass();
$customdata->ajax = $ajax;
$customdata->id = $coursework->id;
$customdata->cmid = $cmid;

$gradingform = new general_feedback_form(null, $customdata);

$returneddata = $gradingform->get_data();

if ($gradingform->is_cancelled()) {
    redirect(new moodle_url('/mod/coursework/view.php', array('id' => $cmid)));
} else if ($returneddata) {
    $gradingform->process_data($returneddata);
    // TODO should not echo before header.
    echo 'General feedback updated..';
    if (!$ajax) {
        redirect(new moodle_url('/mod/coursework/view.php', array('id' => $cmid)),
                                get_string('changessaved'));
    }
} else {
    // Display the form.
    if (!$ajax) {
        $PAGE->navbar->add('General Feedback');
        echo $OUTPUT->header();
        echo $OUTPUT->heading('General Feedback');
    }
    $customdata->feedbackcomment_editor['text'] = $coursework->feedbackcomment;
    $gradingform->set_data($customdata);
    $gradingform->display();

    if (!$ajax) {
        echo $OUTPUT->footer();
    }
}
