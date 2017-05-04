<?php

require_once(dirname(__FILE__) . '/../../../../config.php');

global $CFG, $PAGE, $USER;

$courseworkid = required_param('courseworkid', PARAM_INT);
$allocatableid = optional_param('allocatableid', $USER->id, PARAM_INT);
$allocatabletype = optional_param('allocatabletype', $USER->id, PARAM_ALPHANUMEXT);

$params = array(
    'courseworkid' => $courseworkid,
    'allocatableid' => $allocatableid,
    'allocatabletype' => $allocatabletype,
);
$url = '/mod/coursework/actions/deadline_extensions/create.php';
$link = new \moodle_url($url, $params);
$PAGE->set_url($link);

$controller = new mod_coursework\controllers\deadline_extensions_controller($params);
$controller->create_deadline_extension();