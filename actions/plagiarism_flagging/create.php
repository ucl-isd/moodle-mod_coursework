<?php

/**
 * Creates a plagiarism flagging instance and redirects to the coursework page.
 */

require_once(dirname(__FILE__) . '/../../../../config.php');

global $CFG, $USER;

$submissionid = required_param('submissionid', PARAM_INT);

$params = array(
    'submissionid' => $submissionid
);

$controller = new mod_coursework\controllers\plagiarism_flagging_controller($params);
$controller->create_plagiarism_flag();