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

use mod_coursework\forms\moderator_stats_form;

require_once(dirname(__FILE__) . '/../../../config.php');

$cmid = required_param('cmid', PARAM_INT);
$confirmdelete = optional_param('confirmdelete', false, PARAM_BOOL);

// Must be logged in and have access to the course.
[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'coursework');
require_login($course, false, $cm);

// Must have the moderate capability.
$context = \core\context\module::instance($cmid);
require_capability('mod/coursework:moderate', $context);

$audit = new \mod_coursework\audit($cmid);
$title = get_string('gradingaudit', 'coursework');
$coursework = \mod_coursework\models\coursework::get_from_id($cm->instance, MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/mod/coursework/actions/audit.php', ['cmid' => $cmid]));
$PAGE->set_title($title);

$form = new moderator_stats_form($PAGE->url, [
    'courseworkid' => $coursework->id,
    'context' => $context,
]);

// If the appraisal has been finalised, you need to be a manager in order to edit it again.
// You can also edit it if there's no appraisal yet, or it's not finalised.
$appraisal = \mod_coursework\audit::get_moderator_appraisal($coursework->id, $context->id);
$canedit = (
    ($appraisal && $appraisal->finalised && has_capability('mod/coursework:administergrades', $context))
    || !$appraisal
    || !$appraisal->finalised
);

if ($canedit) {
    $audit->set_form($form);
} else {
    $audit->set_appraisal($appraisal);
}

if ($confirmdelete && data_submitted() && confirm_sesskey() && $appraisal && $canedit) {
    $audit->remove_appraisal($appraisal, $context);
    redirect($PAGE->url);
}

if (($data = $form->get_data()) && $canedit) {
    // Are we removing the appraisal?
    if (isset($data->submitremove)) {
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('confirmremoveappraisal', 'mod_coursework'),
            new moodle_url('/mod/coursework/actions/audit.php', ['cmid' => $cmid, 'confirmdelete' => 1]),
            $PAGE->url
        );
        echo $OUTPUT->footer();
        exit;
    }

    $form->process_data($data);
    // We're redirecting back here again because when you delete the appraisal, it keeps the sticky form values.
    redirect($PAGE->url);
} else {
    $form->set_data($form->get_moderator_appraisal_form_data($coursework, $context));
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo $audit->report();
echo $OUTPUT->footer();
