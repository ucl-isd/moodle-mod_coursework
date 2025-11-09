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
 * @copyright  2011 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace coursework with the name of your module and remove this line

use mod_coursework\event\course_module_instance_list_viewed;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

global $CFG, $DB, $PAGE, $OUTPUT;

$id = required_param('id', PARAM_INT);   // course

if (! $course = $DB->get_record('course', ['id' => $id])) {
    throw new \core\exception\moodle_exception('Course ID is incorrect');
}

require_course_login($course);

$event = course_module_instance_list_viewed::create(['context' => context_course::instance($course->id)]);
$event->trigger();

// Print the header.

$PAGE->set_url('/mod/coursework/view.php', ['id' => $id]);
$PAGE->set_title($course->fullname);
$PAGE->set_heading($course->shortname);
$PAGE->set_pagelayout('incourse');
echo $OUTPUT->header();

// Get all the appropriate data.

if (! $courseworks = get_all_instances_in_course('coursework', $course)) {
    echo $OUTPUT->heading(get_string('nocourseworks', 'coursework'), 2);
    echo $OUTPUT->continue_button("view.php?id=$course->id");
    echo $OUTPUT->footer();
    die();
}

echo $OUTPUT->heading(get_string('modulenameplural', 'coursework'), 2);
$pagerenderer = $PAGE->get_renderer('mod_coursework', 'page');
echo $pagerenderer->view_course_index($course->id);
echo $OUTPUT->footer();
