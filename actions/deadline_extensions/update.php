<?php

require_once(dirname(__FILE__) . '/../../../../config.php');

global $CFG, $PAGE, $USER;

$id = required_param('id', PARAM_INT);

$params = array(
    'id' => $id,
);
$url = '/mod/coursework/actions/deadline_extensions/create.php';
$link = new \moodle_url($url, $params);
$PAGE->set_url($link);

$controller = new mod_coursework\controllers\deadline_extensions_controller($params);
$controller->update_deadline_extension();