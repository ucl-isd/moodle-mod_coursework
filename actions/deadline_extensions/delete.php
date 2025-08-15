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
 * @copyright  2025 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\single_button;
use mod_coursework\models\deadline_extension;

require_once(dirname(__FILE__) . '/../../../../config.php');
require_login();
require_sesskey();

global $CFG, $PAGE, $USER, $OUTPUT;

$id = required_param('id', PARAM_INT);

$params = [
    'id' => $id,
];
$url = '/mod/coursework/actions/deadline_extensions/delete.php';
$pageurl = new \moodle_url($url, $params);
$PAGE->set_url($pageurl);

$deadlineextension = deadline_extension::find(['id' => $id]);
if (!$deadlineextension) {
    throw new \core\exception\invalid_parameter_exception();
}

$courseworkid = $deadlineextension->courseworkid ?? null;
$sure = optional_param('sure', 0, PARAM_INT);
if ($courseworkid) {
    $cm = get_coursemodule_from_instance(
        'coursework', $courseworkid, 0, false, MUST_EXIST
    );
    $courseworkurl = new moodle_url('/mod/coursework/view.php', ['id' => $cm->id]);
    if ($sure) {
        $controller = new mod_coursework\controllers\deadline_extensions_controller($params);
        $success = $controller->delete_deadline_extension();
        $message = $success
            ? get_string('extension_deleted', 'mod_coursework', $deadlineextension->get_grantee_user_name())
            : get_string('error');
        $messagetype = $success ? \core\notification::SUCCESS : \core\notification::ERROR;
        redirect($courseworkurl, $message, null, $messagetype);
    } else {
        $PAGE->set_context(\context_module::instance($cm->id));
        $PAGE->set_cm($cm);

        $pageurl->param('sure', '1');
        $btn = new single_button(
            $pageurl,
            get_string('delete'),
            'post',
            $displayoptions['type'] ?? single_button::BUTTON_DANGER
        );
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('confirmremoveextension', 'mod_coursework', $deadlineextension->get_grantee_user_name()),
            $btn,
            $courseworkurl
        );
        echo $OUTPUT->footer();
    }
}
