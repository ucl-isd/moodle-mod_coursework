<?php

/**
 * Updates a plagiarism flag instance and redirects to the coursework page.
 */

require_once(dirname(__FILE__) . '/../../../../config.php');

global $CFG, $USER;

$flagid = required_param('flagid', PARAM_INT);

$params = array(
    'flagid' => $flagid
);

$controller = new mod_coursework\controllers\plagiarism_flagging_controller($params);
$controller->update_plagiarism_flag();