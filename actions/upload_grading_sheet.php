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

use mod_coursework\export\grading_sheet;
use mod_coursework\export\import;
use mod_coursework\models\coursework;

require_once(dirname(__FILE__) . '/../../../config.php');

global $CFG, $DB, $PAGE, $OUTPUT;

require_once($CFG->dirroot . '/mod/coursework/classes/forms/upload_grading_sheet_form.php');

require_once($CFG->libdir . '/csvlib.class.php');

$coursemoduleid = required_param('cmid', PARAM_INT);

$coursemodule = $DB->get_record('course_modules', ['id' => $coursemoduleid]);
$coursework = coursework::find($coursemodule->instance);
$course = $DB->get_record('course', ['id' => $coursemodule->course]);

require_login($course, false, $coursemodule);

$csvtype = 'markingsheetupload';
$title = get_string($csvtype, 'mod_coursework');

$PAGE->set_url(new moodle_url('/mod/coursework/actions/upload_grading_sheet.php'));
$PAGE->set_title($title);
$PAGE->set_heading($title);
$gradingsheetcapabilities = ['mod/coursework:addinitialgrade', 'mod/coursework:addagreedgrade', 'mod/coursework:administergrades'];

// Bounce anyone who shouldn't be here.
if (!has_any_capability($gradingsheetcapabilities, $PAGE->context)) {
    $message = 'You do not have permission to upload grading sheets';
    redirect(new moodle_url('mod/coursework/view.php'), $message);
}

$gradinguploadform = new upload_grading_sheet_form($coursemoduleid);

if ($gradinguploadform->is_cancelled()) {
    redirect("$CFG->wwwroot/mod/coursework/view.php?id=$coursemoduleid");
}

if ($data = $gradinguploadform->get_data()) {
    // Perform checks on data

    $content = $gradinguploadform->get_file_content('gradingdata');

    $csvcells = grading_sheet::cells_array($coursework);

    $csvimport = new import($coursework, false, false);

    $procsessingresults = $csvimport->validate_csv($content, $data->encoding, $data->delimiter_name, $csvcells);

    // Process
    $csvimport->process_csv($content, $data->encoding, $data->delimiter_name, $csvcells, $procsessingresults);
    $pagerenderer = $PAGE->get_renderer('mod_coursework', 'page');
    echo $pagerenderer->process_csv_upload($procsessingresults, $content, $csvtype);
} else {
    $pagerenderer = $PAGE->get_renderer('mod_coursework', 'page');
    echo $pagerenderer->csv_upload($gradinguploadform, $csvtype);
}
