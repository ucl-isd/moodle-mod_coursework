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
 * @copyright  2017 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\notification;

require_once(dirname(__FILE__) . '/../../../../config.php');

global $CFG, $PAGE, $USER;

$extensionid = required_param('extensionid', PARAM_INT);
$delete = optional_param('deleteextension', false, PARAM_BOOL);

// Both id and extensionid refer to the same thing and are both used in code.
// Extension ID is clearer and we should switch id to that over time.
$params = ['extensionid' => $extensionid, 'id' => $extensionid];
$url = '/mod/coursework/actions/deadline_extensions/update.php';
$link = new moodle_url($url, $params);
$PAGE->set_url($link);

if (!$delete) {
    $controller = new mod_coursework\controllers\deadline_extensions_controller($params);
    $controller->update_deadline_extension();
} else {
    $deadlineextension = mod_coursework\models\deadline_extension::get_from_id($extensionid);
    $redirecturl = new moodle_url('/mod/coursework/actions/deadline_extensions/edit.php', ['id' => $extensionid]);
    if (!$deadlineextension || !$deadlineextension->courseworkid) {
        redirect(
            $redirecturl,
            get_string('extension_not_found', 'mod_coursework'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    if (!$deadlineextension->can_be_deleted()) {
        $coursemodule = get_coursemodule_from_instance(
            'coursework',
            $deadlineextension->get_coursework()->id,
            0,
            false,
            MUST_EXIST
        );
        redirect(
            $redirecturl,
            get_string('extension_cannot_delete', 'mod_coursework'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    $courseworkid = $deadlineextension->courseworkid ?? null;
    $sure = optional_param('sure', 0, PARAM_INT);
    if ($courseworkid) {
        $cm = get_coursemodule_from_instance(
            'coursework',
            $courseworkid,
            0,
            false,
            MUST_EXIST
        );
        $courseworkurl = new moodle_url('/mod/coursework/view.php', ['id' => $cm->id]);
        if ($sure) {
            $controller = new mod_coursework\controllers\deadline_extensions_controller($params);
            $success = $controller->delete_deadline_extension();
            $message = $success
                ? get_string('extension_deleted', 'mod_coursework', $deadlineextension->get_grantee_user_name())
                : get_string('error');
            $messagetype = $success ? notification::SUCCESS : notification::ERROR;
            redirect($courseworkurl, $message, null, $messagetype);
        } else {
            $PAGE->set_context(context_module::instance($cm->id));
            $PAGE->set_cm($cm);

            $deleteurl = new moodle_url($PAGE->url);
            $deleteurl->param('sure', '1');
            $deleteurl->param('deleteextension', '1');
            $btn = new single_button(
                $deleteurl,
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
    } else {
        throw new \core\exception\invalid_parameter_exception("Coursework ID not found");
    }
}
