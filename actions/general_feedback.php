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
 * @package    mod
 * @subpackage coursework
 * @copyright  2011 University of London computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\forms\general_feedback_form;

require_once(dirname(__FILE__) . '/../../../config.php');


global $CFG, $PAGE, $DB, $OUTPUT;

$course_module_id = required_param('cmid', PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
$ajax = optional_param('ajax', false, PARAM_BOOL);

$coursework = $DB->get_record('coursework', array('id' => $id));
$course = $DB->get_record('course', array('id' => $coursework->course));

require_login($course);

if (!has_capability('mod/coursework:addinitialgrade', $PAGE->context)) {
    print_error('Can\'t grade here - permission denied.');
    die();
}

$url = '/mod/coursework/actions/general_feedback.php';
$link = new moodle_url($url, array('cmid' => $course_module_id, 'id' => $id));
$PAGE->set_url($link);
$title = get_string('generalfeedback', 'mod_coursework');
$PAGE->set_title($title);

$custom_data = new stdClass();
$custom_data->ajax = $ajax;
$custom_data->id = $id;
$custom_data->cmid = $course_module_id;

$grading_form = new general_feedback_form(null, $custom_data);

$returned_data = $grading_form->get_data();

if ($grading_form->is_cancelled()) {
    redirect(new moodle_url('/mod/coursework/view.php', array('id' => $course_module_id)));
} else if ($returned_data) {
    $grading_form->process_data($returned_data);
    // TODO should not echo before header.
    echo 'General feedback updated..';
    if (!$ajax) {
        redirect(new moodle_url('/mod/coursework/view.php', array('id' => $course_module_id)),
                                get_string('changessaved'));
    }
} else {
    // Display the form.
    if (!$ajax) {
        $PAGE->navbar->add('General Feedback');
        echo $OUTPUT->header();
        echo $OUTPUT->heading('General Feedback');
    }
    $custom_data->feedbackcomment_editor['text'] = $coursework->feedbackcomment;
    $grading_form->set_data($custom_data);
    $grading_form->display();

    if (!$ajax) {
        echo $OUTPUT->footer();
    }
}
