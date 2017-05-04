<?php

require_once(dirname(__FILE__) . '/../../../config.php');

global $CFG, $USER;

$courseworkid = required_param('courseworkid', PARAM_INT);
$allocatableid_arr = optional_param_array('allocatableid_arr', false, PARAM_RAW);
$allocatableid = optional_param('allocatableid', $USER->id, PARAM_RAW);
$allocatabletype = optional_param('allocatabletype', $USER->id, PARAM_ALPHANUMEXT);
$setpersonaldeadlinespage    =   optional_param('setpersonaldeadlinespage', 0, PARAM_INT);
$multipleuserdeadlines  =   optional_param('multipleuserdeadlines', 0, PARAM_INT);


$allocatableid  =   (!empty($allocatableid_arr))    ?   $allocatableid_arr  : $allocatableid  ;

$params = array(
    'courseworkid' => $courseworkid,
    'allocatableid' => $allocatableid,
    'allocatabletype' => $allocatabletype,
    'setpersonaldeadlinespage'   => $setpersonaldeadlinespage,
    'multipleuserdeadlines'  =>  $multipleuserdeadlines
);
$controller = new mod_coursework\controllers\personal_deadlines_controller($params);
$controller->new_personal_deadline();
