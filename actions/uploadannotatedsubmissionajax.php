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
 *
 *  PROTOTYPE - DO NOT ALLOW ANYWHERE NEAR PRODUCTION!
 *
 * @package mod_coursework
 * @author Andrew Hancox <andrewdchancox@googlemail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright 2025 UCL
 */

/**
 * AJAX_SCRIPT - exception will be converted into JSON
 */

use core\antivirus\manager;
use mod_coursework\ability;
use mod_coursework\models\coursework;
use mod_coursework\models\submission;
use mod_coursework\models\user;

define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->dirroot . '/webservice/lib.php');


// Authenticate the user.
require_login();

$submissionid = required_param('submissionid', PARAM_INT);
$fileid = required_param('fileid', PARAM_INT);
$filename = required_param('filename', PARAM_TEXT);

$submission = submission::find($submissionid);

if (!$submission) {
    return false;
}

$coursework = coursework::find($submission->courseworkid);

if (empty($this->coursework->enablepdfjs())) {
    throw new Exception('coursework enablepdfjs not enabled');
}

$user = user::find($USER);
$ability = new ability($user, $coursework);
if ($ability->cannot('show', $submission)) {
    return false;
}

$context = $coursework->get_context();
$PAGE->set_context($context);

$fs = get_file_storage();

$totalsize = 0;
$files = [];
foreach ($_FILES as $fieldname => $uploadedfile) {
    if (!empty($files)) {
        throw new Exception('too many files');
    }

    // Check upload errors.
    if (!empty($_FILES[$fieldname]['error'])) {
        switch ($_FILES[$fieldname]['error']) {
            case UPLOAD_ERR_INI_SIZE:
                throw new moodle_exception('upload_error_ini_size', 'repository_upload');
                break;
            case UPLOAD_ERR_FORM_SIZE:
                throw new moodle_exception('upload_error_form_size', 'repository_upload');
                break;
            case UPLOAD_ERR_PARTIAL:
                throw new moodle_exception('upload_error_partial', 'repository_upload');
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new moodle_exception('upload_error_no_file', 'repository_upload');
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new moodle_exception('upload_error_no_tmp_dir', 'repository_upload');
                break;
            case UPLOAD_ERR_CANT_WRITE:
                throw new moodle_exception('upload_error_cant_write', 'repository_upload');
                break;
            case UPLOAD_ERR_EXTENSION:
                throw new moodle_exception('upload_error_extension', 'repository_upload');
                break;
            default:
                throw new moodle_exception('nofile');
        }
    }

    // Scan for viruses.
    manager::scan_file($_FILES[$fieldname]['tmp_name'], $_FILES[$fieldname]['name'], true);

    $file = new stdClass();
    $file->filename = clean_param($_FILES[$fieldname]['name'], PARAM_FILE);
    // Check system maxbytes setting.
    if (($_FILES[$fieldname]['size'] > get_max_upload_file_size($CFG->maxbytes))) {
        // Oversize file will be ignored, error added to array to notify
        // web service client.
        throw new moodle_exception('maxbytes', 'error');
    } else {
        $file->filepath = $_FILES[$fieldname]['tmp_name'];
        // Calculate total size of upload.
        $totalsize += $_FILES[$fieldname]['size'];
        // Size of individual file.
        $file->size = $_FILES[$fieldname]['size'];
    }
    $files[] = $file;
}

$file = reset($files);

$fs = get_file_storage();

// Get any existing file size limits.
$maxupload = get_user_max_upload_file_size($context, $CFG->maxbytes);

// Check the size of this upload.
if ($maxupload !== USER_CAN_IGNORE_FILE_SIZE_LIMITS && $totalsize > $maxupload) {
    throw new file_exception('userquotalimit');
}

$filerecord = new stdClass;
$filerecord->component = 'mod_coursework';
$filerecord->contextid = $context->id;
$filerecord->userid = $USER->id;
$filerecord->filearea = 'submissionannotations';
$filerecord->filename = $filename;
$filerecord->filepath = '/';
$filerecord->itemid = $submissionid;
$filerecord->license = $CFG->sitedefaultlicense;
$filerecord->author = fullname($USER);
$filerecord->source = $fileid;
$filerecord->filesize = $file->size;

// Check if the file already exist.
$existingfile = $fs->get_file($filerecord->contextid, $filerecord->component, $filerecord->filearea,
    $filerecord->itemid, $filerecord->filepath, $filerecord->filename);

if ($existingfile && $existingfile->get_userid() == $USER->id) {
    $existingfile->delete();
}

$storedfile = $fs->create_file_from_pathname($filerecord, $file->filepath);

$url = moodle_url::make_pluginfile_url(
    $storedfile->get_contextid(),
    'mod_coursework',
    $storedfile->get_filearea(),
    $storedfile->get_itemid(),
    $storedfile->get_filepath(),
    $storedfile->get_filename()
);

echo json_encode((object)['fileid' => $storedfile->get_id(), 'url' => $url->out(false)]);
