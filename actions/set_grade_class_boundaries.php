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

require_once("../../../config.php");

$courseworkid = required_param('courseworkid', PARAM_INT);
$coursework = $courseworkid ? \mod_coursework\models\coursework::find($courseworkid) : null;
$urlparams = $coursework ? ['courseworkid' => $courseworkid] : null;

$url = '/mod/coursework/actions/set_grade_class_boundaries.php';
$context = $coursework ? $coursework->get_context() : \context_system::instance();
if ($coursework) {
    require_course_login($coursework->get_course(), true, $coursework->get_course_module());
    $PAGE->set_url(new moodle_url($url, $urlparams));
    $PAGE->set_pagelayout('standard');
    require_capability('moodle/course:manageactivities', $context);
} else {
    require_login();
    // Same setup as done on /admin/settings.php to mirror the navigation.
    $PAGE->set_url('/admin/settings.php', ['section' => 'modsettingcoursework']);
    $PAGE->set_pagetype('admin-setting-modsettingcoursework');
    $PAGE->set_pagelayout('admin');
    $PAGE->navigation->clear_cache();
    navigation_node::require_admin_tree();
    require_capability('moodle/site:config', $context);
}

$PAGE->set_context($context);
$form = new \mod_coursework\forms\grade_class_boundaries(new \moodle_url($url), $urlparams);
$redirecturl = $coursework
    ? new \moodle_url('/course/modedit.php', ['update' => $coursework->get_course_module()->id], 'id_markingworkflow')
    : new \moodle_url('/admin/settings.php', ['section' => 'modsettingcoursework']);
if ($form->is_cancelled()) {
    redirect($redirecturl);
} else if ($data = $form->get_data()) {
    \mod_coursework\auto_grader\average_grade_no_straddle::save_grade_class_boundaries(
        $courseworkid,
        $form::parse_form_data((array)$data)
    );
    redirect($redirecturl, get_string('changessavedclassboundaries', 'coursework', \core\output\notification::NOTIFY_SUCCESS));
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
