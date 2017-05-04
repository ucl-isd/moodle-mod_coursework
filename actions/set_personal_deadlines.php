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
 * Page that prints a table of all students and their personal deadlines in order to change it one by  one or in bulk .
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2016 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use \mod_coursework\models\coursework;
use \mod_coursework\models\personal_deadline;

require_once(dirname(__FILE__).'/../../../config.php');

global $CFG, $OUTPUT, $DB, $PAGE;

require_once($CFG->dirroot.'/mod/coursework/lib.php');

$coursemoduleid = required_param('id', PARAM_INT);
$coursemodule = get_coursemodule_from_id('coursework', $coursemoduleid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $coursemodule->course), '*', MUST_EXIST);
$coursework = $DB->get_record('coursework', array('id' => $coursemodule->instance), '*', MUST_EXIST);
$coursework = coursework::find($coursework);

// SQL sort for allocation table.
$sortby = optional_param('sortby', '', PARAM_ALPHA);
$sorthow = optional_param('sorthow', '', PARAM_ALPHA);
$options = compact('sortby', 'sorthow');


require_login($course, true, $coursemodule);
require_capability('mod/coursework:editpersonaldeadline', $PAGE->context, null, true, "Can't change personal deadlines here - permission denied.");

$url = '/mod/coursework/actions/set_personal_deadlines.php';
$link = new \moodle_url($url, array('id' => $coursemoduleid));
$PAGE->set_url($link);
$title = get_string('setpersonaldeadlinesfor', 'mod_coursework', $coursework->name);
$PAGE->set_title($title);
$PAGE->set_heading($title);

$PAGE->requires->jquery();

// Will set off the function that adds listeners for onclick/onchange etc.
$jsmodule = array(
    'name' => 'mod_coursework',
    'fullpath' => '/mod/coursework/module.js',
    'requires' => array('base',
        'node-base')
);
$PAGE->requires->js_init_call('M.mod_coursework.init_personal_deadlines_page',
    array(),
    false,
    $jsmodule);

/**
 * @var mod_coursework_object_renderer $object_renderer
 */
$object_renderer = $PAGE->get_renderer('mod_coursework', 'object');
$personal_deadlines_table = new mod_coursework\personal_deadline\table\builder($coursework, $options);
$personal_deadlines_table = new mod_coursework_personal_deadlines_table($personal_deadlines_table);
echo $OUTPUT->header();

echo $object_renderer->render($personal_deadlines_table);


echo $OUTPUT->footer();
