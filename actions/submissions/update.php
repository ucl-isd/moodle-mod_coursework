<?php

/**
 * Creates a submission instance and redirects to the coursework page.
 */

require_once(dirname(__FILE__) . '/../../../../config.php');

$submissionid = required_param('submissionid', PARAM_INT);
$finalised = !!optional_param('finalisebutton', 0, PARAM_TEXT);

$params = array(
    'submissionid' => $submissionid,
    'finalised' => $finalised,
);
$controller = new mod_coursework\controllers\submissions_controller($params);
$controller->update_submission();