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
 * @copyright  2011 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../../config.php');

global $CFG, $USER;

$submissionid = required_param('submissionid', PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$feedbackid = optional_param('feedbackid', 0, PARAM_INT);
$assessorid = optional_param('assessorid', $USER->id, PARAM_INT);
$stageidentifier = optional_param('stage_identifier', 'uh-oh',  PARAM_RAW);
$ajax = optional_param('ajax', 0,  PARAM_INT);

$params = [
    'submissionid' => $submissionid,
    'cmid' => $cmid,
    'feedbackid' => $feedbackid,
    'assessorid' => $assessorid,
    'stage_identifier' => $stageidentifier,
    'ajax' => $ajax,
];
$controller = new mod_coursework\controllers\feedback_controller($params);
$controller->new_feedback();
