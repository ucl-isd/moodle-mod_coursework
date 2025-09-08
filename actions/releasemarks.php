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
 * This file will release marks for a coursework
 *
 * @package    mod_coursework
 * @copyright  2025 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */

use core\output\notification;
use mod_coursework\models\coursework;
require_once(dirname(__FILE__).'/../../../config.php');

$cmid = required_param('cmid', PARAM_INT);
$returnurl = new moodle_url('/mod/coursework/view.php', array('id' => $cmid));

$cm = get_coursemodule_from_id('coursework', $cmid);
if (!$cm) {
    redirect($returnurl, get_string('course_module_not_found', 'mod_coursework'));
}

$coursework = coursework::find($cm->instance);
if (!$coursework) {
    redirect($returnurl, get_string('coursework_not_found', 'mod_coursework'));
}

require_login($coursework->get_course(), false, $cm);

[$canrelease, $reason] = $coursework->can_release_marks();
if (!$canrelease) {
    redirect($returnurl, $reason, null, notification::NOTIFY_ERROR);
}

try {
    $coursework->publish_grades();
    redirect($returnurl, get_string('marks_released', 'mod_coursework'));
} catch (\Exception $e) {
    redirect($returnurl, $e->getMessage(), null, notification::NOTIFY_ERROR);
}
