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

use mod_coursework\models\coursework;

require_once(dirname(__FILE__) . '/../../../config.php');

global $CFG, $DB, $PAGE, $OUTPUT;

require_once($CFG->dirroot . '/mod/coursework/classes/forms/upload_feedback_form.php');
require_once($CFG->dirroot . '/mod/coursework/classes/file_importer.php');
$PAGE->set_url(new moodle_url('/mod/coursework/actions/upload_feedback.php'));

$coursemoduleid = required_param('cmid', PARAM_INT);

$coursemodule = $DB->get_record('course_modules', ['id' => $coursemoduleid]);
$coursework = coursework::get_from_id($coursemodule->instance);
$course = $DB->get_record('course', ['id' => $coursemodule->course]);

require_login($course, false, $coursemodule);

$title = get_string('feedbackupload', 'mod_coursework');

$PAGE->set_title($title);
$PAGE->set_heading($title);

$gradingsheetcapabilities = ['mod/coursework:addinitialgrade', 'mod/coursework:addagreedgrade', 'mod/coursework:administergrades'];

// Bounce anyone who shouldn't be here.
if (!has_any_capability($gradingsheetcapabilities, $PAGE->context)) {
    $message = 'You do not have permission to upload feedback sheets';
    redirect(new moodle_url('mod/coursework/view.php'), $message);
}

$feedbackform = new upload_feedback_form($coursework, $coursemoduleid);

if ($feedbackform->is_cancelled()) {
    redirect(new moodle_url("$CFG->wwwroot/mod/coursework/view.php", ['id' => $coursemoduleid]));
}

if ($data = $feedbackform->get_data()) {
    // Perform checks on data
    $courseworktempdir = $CFG->dataroot . "/temp/coursework/";

    if (!is_dir($courseworktempdir)) {
        mkdir($courseworktempdir);
    }

    $filename = clean_param($feedbackform->get_new_filename('feedbackzip'), PARAM_FILE);
    $filename = md5(rand(0, 1000000) . $filename);
    $filepath = $courseworktempdir . '/' . $filename . ".zip";
    $feedbackform->save_file('feedbackzip', $filepath);

    $stageidentifier = $data->feedbackstage;

    $fileimporter = new  mod_coursework\coursework_file_zip_importer();

    $fileimporter->extract_zip_file($filepath, $coursework->get_context_id());

    $updateresults = $fileimporter->import_zip_files($coursework, $stageidentifier, $data->overwrite);

    $pagerenderer = $PAGE->get_renderer('mod_coursework', 'page');
    echo $pagerenderer->process_feedback_upload($updateresults);
} else {
    $pagerenderer = $PAGE->get_renderer('mod_coursework', 'page');
    echo $pagerenderer->feedback_upload($feedbackform);
}
