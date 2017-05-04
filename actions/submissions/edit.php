<?php

/**
 * Creates a submission instance and redirects to the coursework page.
 */

require_once(dirname(__FILE__) . '/../../../../config.php');

$submissionid = required_param('submissionid', PARAM_INT);

$params = array(
    'submissionid' => $submissionid,
);
$controller = new mod_coursework\controllers\submissions_controller($params);
$controller->edit_submission();