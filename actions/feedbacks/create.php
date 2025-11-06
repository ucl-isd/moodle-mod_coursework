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
 * @copyright  2017 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Creates a feedback instance and redirects to the coursework page.
 */

require_once(dirname(__FILE__) . '/../../../../config.php');

global $USER;

$submissionid = required_param('submissionid', PARAM_INT);
$isfinalgrade = optional_param('isfinalgrade', 0, PARAM_INT);
$assessorid = optional_param('assessorid', $USER->id, PARAM_INT);
$stageidentifier = optional_param('stageidentifier', '', PARAM_ALPHANUMEXT);
$finalised = (bool)optional_param('submitbutton', 0, PARAM_TEXT);

$params = [
    'submissionid' => $submissionid,
    'isfinalgrade' => $isfinalgrade,
    'assessorid' => $assessorid,
    'stageidentifier' => $stageidentifier,
    'finalised' => $finalised,
];

$controller = new mod_coursework\controllers\feedback_controller($params);
$controller->create_feedback();
