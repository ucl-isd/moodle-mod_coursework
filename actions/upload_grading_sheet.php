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

require_once($CFG->dirroot.'/mod/coursework/classes/forms/upload_grading_sheet_form.php');

require_once($CFG->libdir.'/csvlib.class.php');


$coursemoduleid = required_param('cmid', PARAM_INT);

$coursemodule = $DB->get_record('course_modules', array('id' => $coursemoduleid));
$coursework = coursework::find($coursemodule->instance);
$course = $DB->get_record('course', array('id' => $coursemodule->course));

require_login($course, false, $coursemodule);

$csvtype = 'gradingsheetupload';
$title = get_string($csvtype, 'mod_coursework');

$PAGE->set_url(new moodle_url('/mod/coursework/actions/upload_grading_sheet.php'));
$PAGE->set_title($title);
$PAGE->set_heading($title);
$grading_sheet_capabilities = array('mod/coursework:addinitialgrade','mod/coursework:addagreedgrade','mod/coursework:administergrades');


// Bounce anyone who shouldn't be here.
if (!has_any_capability($grading_sheet_capabilities, $PAGE->context)) {
    $message = 'You do not have permission to upload grading sheets';
    redirect(new moodle_url('mod/coursework/view.php'), $message);
}



$gradinguploadform    =   new upload_grading_sheet_form($coursemoduleid);

if ($gradinguploadform->is_cancelled()) {
    redirect("$CFG->wwwroot/mod/coursework/view.php?id=$coursemoduleid");
}



if ($data   =   $gradinguploadform->get_data())   {

    //perform checks on data

    $content = $gradinguploadform->get_file_content('gradingdata');

    $csv_cells =  \mod_coursework\export\grading_sheet::cells_array($coursework);

    $csvimport   =  new \mod_coursework\export\import($coursework,false,false);

   // $csv_cells = $csvimport->csv_columns(); //all columns from spreadsheet

    $procsessingresults =  $csvimport->validate_csv($content, $data->encoding, $data->delimiter_name, $csv_cells);

    //process

    //if (!empty($procsessingresults)) {
    $csvimport->process_csv($content, $data->encoding, $data->delimiter_name, $csv_cells, $procsessingresults);
    $page_renderer = $PAGE->get_renderer('mod_coursework', 'page');
    echo $page_renderer->process_csv_upload($procsessingresults, $content, $csvtype);
    //} else {
        //
    //}









} else {
    $page_renderer = $PAGE->get_renderer('mod_coursework', 'page');
    echo $page_renderer->csv_upload($gradinguploadform, $csvtype);
}