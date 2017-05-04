<?php

require_once(dirname(__FILE__) . '/../../../../config.php');

global $CFG, $USER;

$id = required_param('id', PARAM_INT);

$params = array(
    'id' => $id,
);
$controller = new mod_coursework\controllers\deadline_extensions_controller($params);
$controller->show_deadline_extension();