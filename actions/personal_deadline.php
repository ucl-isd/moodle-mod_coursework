<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../../config.php');

global $CFG, $USER, $PAGE, $DB;



$courseworkid = required_param('courseworkid', PARAM_INT);
$allocatableid_arr = optional_param_array('allocatableid_arr', false, PARAM_RAW);
$allocatableid = optional_param('allocatableid', $USER->id, PARAM_RAW);
$allocatabletype = optional_param('allocatabletype', $USER->id, PARAM_ALPHANUMEXT);
$setpersonaldeadlinespage = optional_param('setpersonaldeadlinespage', 0, PARAM_INT);
$multipleuserdeadlines = optional_param('multipleuserdeadlines', 0, PARAM_INT);
$selectedtype = optional_param('selectedtype', 'date', PARAM_RAW);
$personal_deadline_time = optional_param('personal_deadline_time',null,PARAM_RAW);

$allocatableid = (!empty($allocatableid_arr))    ?   $allocatableid_arr  : $allocatableid  ;


$coursework_db = $DB->get_record('coursework',array('id' => $courseworkid));

$coursework = \mod_coursework\models\coursework::find($coursework_db);

require_login($coursework->get_course(),false, $coursework->get_course_module());

$params = array(
    'courseworkid' => $courseworkid,
    'allocatableid' => $allocatableid,
    'allocatabletype' => $allocatabletype,
    'setpersonaldeadlinespage' => $setpersonaldeadlinespage,
    'multipleuserdeadlines' =>  $multipleuserdeadlines
);

if ($selectedtype != 'unfinalise') {
    $controller = new mod_coursework\controllers\personal_deadlines_controller($params);

    if (!empty($personal_deadline_time)) {
        $result = $controller->insert_update($personal_deadline_time);
        echo json_encode($result);
    } else {
        $controller->new_personal_deadline();
    }
} else {

    if (!has_capability('mod/coursework:revertfinalised', $PAGE->context)) {
        $message = 'You do not have permission to revert submissions';
        redirect($url, $message);
    }

    $controller = new mod_coursework\controllers\submissions_controller($params);
    $controller->unfinalise_submission();
}
