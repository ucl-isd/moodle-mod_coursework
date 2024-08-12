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


/**
 * Creates a feedback instance and redirects to the coursework page.
 */

require_once(dirname(__FILE__) . '/../../../../config.php');

global $CFG, $USER;


$submissionid = required_param('submissionid', PARAM_INT);
$isfinalgrade = optional_param('isfinalgrade', 0, PARAM_INT);
$assessorid = optional_param('assessorid', $USER->id, PARAM_INT);
$stage_identifier = optional_param('stage_identifier', '', PARAM_ALPHANUMEXT);
$finalised = !!optional_param('submitbutton', 0, PARAM_TEXT);
$ajax = optional_param('ajax', 0, PARAM_INT);

$params = array(
    'submissionid' => $submissionid,
    'isfinalgrade' => $isfinalgrade,
    'assessorid' => $assessorid,
    'stage_identifier' => $stage_identifier,
    'finalised' => $finalised,
    'ajax' => $ajax,
);

if ($ajax) {
    $params['cell_type'] = required_param('cell_type', PARAM_TEXT);
}

$controller = new mod_coursework\controllers\feedback_controller($params);
$controller->create_feedback();