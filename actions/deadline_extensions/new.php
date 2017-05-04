<?php

require_once(dirname(__FILE__) . '/../../../../config.php');

global $CFG, $USER;

$courseworkid = required_param('courseworkid', PARAM_INT);
$allocatableid = optional_param('allocatableid', $USER->id, PARAM_INT);
$allocatabletype = optional_param('allocatabletype', $USER->id, PARAM_ALPHANUMEXT);

$params = array(
    'courseworkid' => $courseworkid,
    'allocatableid' => $allocatableid,
    'allocatabletype' => $allocatabletype,
);
$controller = new mod_coursework\controllers\deadline_extensions_controller($params);
$controller->new_deadline_extension();