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

require_once(dirname(__FILE__) . '/../../../config.php');
$pageurl = '/mod/coursework/actions/grade_class_boundary_menu.php';
require_login();

$context = \context_system::instance();
require_capability('moodle/site:config', $context);
$templateid = optional_param('templateid', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url('/mod/coursework/actions/grade_class_boundary_menu.php', $templateid ? ['templateid' => $templateid] : null);
$PAGE->set_pagetype('admin-setting-modsettingcoursework');
$PAGE->set_pagelayout('admin');

$action = optional_param('action', '', PARAM_TEXT);

$PAGE->set_title(get_string('gradeclasssetboundaries', 'mod_coursework'));

if ($action) {
    require_sesskey();
    if ($action == 'add') {
        $title = required_param('title', PARAM_TEXT);
        $id = $DB->insert_record('coursework_class_boundary_templates', ['name' => $title]);
        // Set default boundaries for now.
        \mod_coursework\auto_grader\average_grade_no_straddle::save_grade_class_boundaries(
            $id,
            $title,
            \mod_coursework\auto_grader\average_grade_no_straddle::get_grade_class_boundaries(0)
        );
        redirect(
            new \moodle_url($pageurl, ['templateid' => $id]),
            get_string('changessavedclassboundaries', 'coursework', $title),
            null,
            core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$form = null;

if ($templateid) {
    // We have selected a template so display it for editing boundaries, or process data if submitted.
    $form = new \mod_coursework\forms\grade_class_boundaries(new \moodle_url($pageurl), ['templateid' => $templateid]);
    if ($form->is_cancelled()) {
        redirect($pageurl);
    } else if ($data = $form->get_data()) {
        $data = (array)$data;
        \mod_coursework\auto_grader\average_grade_no_straddle::save_grade_class_boundaries(
            $templateid,
            $data['templatename'],
            $form::parse_form_data($data)
        );
        redirect(
            new \moodle_url($pageurl, ['templateid' => $templateid]),
            get_string('changessavedclassboundaries', 'coursework', $data['templatename']),
            null,
            core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$renderable = new \mod_coursework\output\grade_class_boundaries();

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_coursework/grade_class_boundaries', $data = $renderable->export_for_template($OUTPUT));
if ($form) {
    $form->display();
}
echo $OUTPUT->footer();
