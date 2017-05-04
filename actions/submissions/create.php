<?php

/**
 * Creates a submission instance and redirects to the coursework page.
 */

require_once(dirname(__FILE__) . '/../../../../config.php');

global $USER;

$courseworkid = required_param('courseworkid', PARAM_INT);
$allocatableid = required_param('allocatableid', PARAM_INT);
$allocatabletype = required_param('allocatabletype', PARAM_ALPHANUMEXT);
$submissionid = optional_param('submissionid', 0, PARAM_INT);
$finalised = !!optional_param('finalisebutton', 0, PARAM_TEXT);


if (!in_array($allocatabletype, array('user', 'group'))) {
    throw new \mod_coursework\exceptions\access_denied(\mod_coursework\models\coursework::find($courseworkid),
                                                       'Bad alloctable type');
}

$params = array(
    'courseworkid' => $courseworkid,
    'finalised' => $finalised,
    'allocatableid' => $allocatableid,
    'allocatabletype' => $allocatabletype,
);
if ($submissionid) {
    $params['submissionid'] = $submissionid;
}
$controller = new mod_coursework\controllers\submissions_controller($params);
$controller->create_submission();