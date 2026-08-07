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
 * Grading Audit report showing statistics of each marker's marks.
 *
 * @package    mod_coursework
 * @copyright  2026 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Conn Warwicker <conn.warwicker@catalyst-eu.net>
 */

require_once(dirname(__FILE__) . '/../../../config.php');

$cmid = required_param('cmid', PARAM_INT);

// Must be logged in and have access to the course.
[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'coursework');
require_login($course, false, $cm);

// Must have the moderate capability.
$context = \core\context\module::instance($cmid);
require_capability('mod/coursework:moderate', $context);

$audit = new \mod_coursework\audit($cmid);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/mod/coursework/actions/audit.php', ['cmid' => $cmid]));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gradingaudit', 'coursework'));
echo $audit->report();
echo $OUTPUT->footer();
