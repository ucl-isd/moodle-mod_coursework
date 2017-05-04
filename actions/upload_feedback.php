<?php
/**
 * Created by PhpStorm.
 * User: Nigel.Daley
 * Date: 11/08/2015
 * Time: 11:32
 */


use mod_coursework\models\coursework;
use mod_coursework\export;



require_once(dirname(__FILE__).'/../../../config.php');

global $CFG, $DB, $PAGE, $OUTPUT;

require_once($CFG->dirroot.'/mod/coursework/classes/forms/upload_feedback_form.php');
require_once($CFG->dirroot.'/mod/coursework/classes/file_importer.php');
$PAGE->set_url(new moodle_url('/mod/coursework/actions/upload_feedback.php'));

$coursemoduleid = required_param('cmid', PARAM_INT);

$coursemodule = $DB->get_record('course_modules', array('id' => $coursemoduleid));
$coursework = coursework::find($coursemodule->instance);
$course = $DB->get_record('course', array('id' => $coursemodule->course));

require_login($course, false, $coursemodule);

$title = get_string('feedbackupload', 'mod_coursework');

$PAGE->set_title($title);
$PAGE->set_heading($title);

$grading_sheet_capabilities = array('mod/coursework:addinitialgrade','mod/coursework:addagreedgrade','mod/coursework:administergrades');


// Bounce anyone who shouldn't be here.
if (!has_any_capability($grading_sheet_capabilities, $PAGE->context)) {
    $message = 'You do not have permission to upload feedback sheets';
    redirect(new moodle_url('mod/coursework/view.php'), $message);
}



$feedbackform    =   new upload_feedback_form($coursework,$coursemoduleid);

if ($feedbackform->is_cancelled()) {
    redirect(new moodle_url("$CFG->wwwroot/mod/coursework/view.php", array('id' => $coursemoduleid)));
}



if ($data   =   $feedbackform->get_data())   {

    //perform checks on data
    $courseworktempdir = $CFG->dataroot."/temp/coursework/";

    if (!is_dir($courseworktempdir)) 	{
        mkdir($courseworktempdir);
    }

    $filename = clean_param($feedbackform->get_new_filename('feedbackzip'), PARAM_FILE);
    $filename = md5(rand(0,1000000).$filename);
    $filepath = $courseworktempdir.'/'.$filename.".zip";
    $feedbackform->save_file('feedbackzip', $filepath);

    $stageidentifier  =   $data->feedbackstage;

    $fileimporter   =  new  mod_coursework\coursework_file_zip_importer();



    $fileimporter->extract_zip_file($filepath,$coursework->get_context_id());

    $updateresults  =   $fileimporter->import_zip_files($coursework,$stageidentifier,$data->overwrite);

    $page_renderer = $PAGE->get_renderer('mod_coursework', 'page');
    echo $page_renderer->process_feedback_upload($updateresults);

} else {
    $page_renderer = $PAGE->get_renderer('mod_coursework', 'page');
    echo $page_renderer->feedback_upload($feedbackform);
}