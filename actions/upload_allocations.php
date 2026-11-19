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
 * @copyright  2016 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\allocation\marker_role;
use mod_coursework\allocation\upload;
use mod_coursework\models\coursework;

require_once(dirname(__FILE__) . '/../../../config.php');

global $CFG, $DB, $PAGE, $OUTPUT;

require_once($CFG->dirroot . '/mod/coursework/classes/forms/upload_allocations_form.php');

require_once($CFG->libdir . '/csvlib.class.php');

$coursemoduleid = required_param('cmid', PARAM_INT);

$coursemodule = $DB->get_record('course_modules', ['id' => $coursemoduleid]);
$coursework = coursework::get_from_id($coursemodule->instance);
$course = $DB->get_record('course', ['id' => $coursemodule->course]);
require_login($course, false, $coursemodule);

$csvtype = 'allocationsupload';
$title = get_string($csvtype, 'coursework');
$PAGE->set_url(new moodle_url('/mod/coursework/actions/upload_allocations.php', ['cmid' => $coursemoduleid]));
$PAGE->navbar->add($title, $PAGE->url);
$PAGE->set_title($title);
$PAGE->set_heading($title);

$gradingsheetcapabilities = ['mod/coursework:allocate'];

// Bounce anyone who shouldn't be here.
if (!has_any_capability($gradingsheetcapabilities, $PAGE->context)) {
    $message = 'You do not have permission to upload allocations';
    redirect(new moodle_url('mod/coursework/view.php'), $message);
}

$allocationsuploadform = new upload_allocations_form($coursemoduleid);
$pagerenderer = $PAGE->get_renderer('mod_coursework', 'page');
$csvimport = new upload($coursework);

if ($allocationsuploadform->is_cancelled()) {
    redirect("$CFG->wwwroot/mod/coursework/view.php?id=$coursemoduleid");
}

$markerroleconfirmed = optional_param('confirmmarkerrole', false, PARAM_BOOL);
if ($markerroleconfirmed) {
    require_sesskey();
    $draftitemid = required_param('draftitemid', PARAM_INT);
    $encoding = required_param('encoding', PARAM_ALPHANUMEXT);
    $delimiter = required_param('delimiter_name', PARAM_ALPHA);
    $content = upload::get_draft_file_content($draftitemid);
    $data = (object)['encoding' => $encoding, 'delimiter_name' => $delimiter];

    // Recalculate who needs the role from the file itself rather than trusting submitted user ids.
    $markerrole = new marker_role($coursework);
    $markerrole->assign(array_keys($markerrole->get_users_requiring_role($content, $encoding, $delimiter)));
} else if ($data = $allocationsuploadform->get_data()) {
    $content = $allocationsuploadform->get_file_content('allocationsdata');

    $markerrole = new marker_role($coursework);
    $userswithoutrole = $markerrole->can_assign()
        ? $markerrole->get_users_requiring_role($content, $data->encoding, $data->delimiter_name)
        : [];
    if ($userswithoutrole) {
        $confirmurl = new moodle_url($PAGE->url, [
            'confirmmarkerrole' => 1,
            'draftitemid' => $data->allocationsdata,
            'encoding' => $data->encoding,
            'delimiter_name' => $data->delimiter_name,
        ]);
        echo $pagerenderer->confirm_marker_role_assignment(
            $userswithoutrole,
            $markerrole->get_role_name(),
            $confirmurl,
            $PAGE->url,
        );
        exit;
    }
}

if (!empty($data)) {
    $procsessingresults = $csvimport->validate_and_process($content, $data->encoding, $data->delimiter_name);
    echo $pagerenderer->process_csv_upload($procsessingresults, $content, $csvtype);

    $cache = cache::make('mod_coursework', 'courseworkdata', ['id' => $coursework->id]);
    $cache->purge();
} else {
    echo $pagerenderer->csv_upload($allocationsuploadform, $csvtype);
}
