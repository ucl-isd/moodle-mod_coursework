<?php

/**
 * Creates a submission instance and redirects to the coursework page.
 */

require_once(dirname(__FILE__) . '/../../../../config.php');

global $USER;

$courseworkid = required_param('courseworkid', PARAM_INT);
$allocatableid = optional_param('allocatableid', $USER->id, PARAM_INT);
$allocatabletype = optional_param('allocatabletype', 'user', PARAM_ALPHANUMEXT);

$params = array(
    'courseworkid' => $courseworkid,
    'allocatableid' => $allocatableid,
    'allocatabletype' => $allocatabletype,
);
$controller = new mod_coursework\controllers\submissions_controller($params);
$controller->new_submission();