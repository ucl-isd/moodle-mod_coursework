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
 * This file will revert the finalised state for one student's submission so that they can alter
 * files again. Not allowed if they already have feedbacks
 *
 * @package    mod_coursework
 * @copyright  2011 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\models\coursework;
use mod_coursework\models\submission;

require_once(dirname(__FILE__).'/../../../config.php');

global $DB, $PAGE, $CFG;

$cmid = required_param('cmid', PARAM_INT);
$submissionid = required_param('submissionid', PARAM_INT);

$cm = $DB->get_record('course_modules', ['id' => $cmid]);
$coursework = coursework::find($cm->instance);
$course = $DB->get_record('course', ['id' => $cm->course]);
$submissiondb = $DB->get_record('coursework_submissions', ['id' => $submissionid]);
$submission = submission::find($submissiondb);

require_login($course, false, $cm);
$url = new moodle_url('/mod/coursework/view.php', ['id' => $cm->id]);

// Bounce anyone who shouldn't be here.
if (!has_capability('mod/coursework:revertfinalised', $PAGE->context)) {
    $message = 'You do not have permission to revert submissions';
    redirect($url, $message);
}

$userallowedtosubmitlatewithoutextension = \mod_coursework\models\deadline_extension::user_allowed_to_submit_late_without_extension(
    $coursework->id(),
    $submission->get_allocatable()->type(),
    $submission->get_allocatable()->id()
);
if ($submission->is_late() && !$userallowedtosubmitlatewithoutextension) {
    redirect(
        $url,
        get_string('extendbeforerevert', 'coursework'), null, \core\output\notification::NOTIFY_ERROR
    );
}

$submission->finalised = 0;
$submission->save();

$message = get_string('changessaved');
redirect($url, $message);
