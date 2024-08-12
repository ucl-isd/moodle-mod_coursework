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


require_once(dirname(__FILE__) . '/../../../../../config.php');

global $CFG, $USER;
$courseworkid = required_param('courseworkid', PARAM_INT);
$allocatableid = optional_param('allocatableid', $USER->id, PARAM_INT);
$allocatabletype = optional_param('allocatabletype', $USER->id, PARAM_ALPHANUMEXT);
$requesttype = optional_param('requesttype', 'new', PARAM_ALPHANUMEXT);
$id = optional_param('id', 0, PARAM_INT);
$extra_information_text = optional_param('text', '', PARAM_RAW);
$extra_information_format = optional_param('format', 1, PARAM_ALPHANUMEXT);
$extended_deadline = optional_param('extended_deadline', 0, PARAM_ALPHANUMEXT);
$pre_defined_reason = optional_param('pre_defined_reason', null, PARAM_ALPHANUMEXT);
$submissionid = optional_param('submissionid', 0, PARAM_INT);
$name = optional_param('name', 0, PARAM_ALPHANUMEXT);
$params = array(
    'courseworkid' => $courseworkid,
    'allocatableid' => $allocatableid,
    'allocatabletype' => $allocatabletype,
);
$controller = new mod_coursework\controllers\deadline_extensions_controller($params);

$params['id'] = $id;
$params['extra_information_text'] = $extra_information_text;
$params['extra_information_format'] = $extra_information_format;
$params['extended_deadline'] = strtotime($extended_deadline);
$params['pre_defined_reason'] = $pre_defined_reason;
$params['submissionid'] = $submissionid;
$params['name'] = $name;

$controller->ajax_new_mitigation($params);