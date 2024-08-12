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


$feedbackid = required_param('feedbackid', PARAM_INT);
$finalised = !!optional_param('submitbutton', 0, PARAM_TEXT);
$ajax = optional_param('ajax', 0, PARAM_INT);
$remove = !!optional_param('removefeedbackbutton', 0, PARAM_TEXT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$params = array(
    'feedbackid' => $feedbackid,
    'finalised' => $finalised,
    'remove' => $remove,
    'confirm' => $confirm,
    'ajax' => $ajax
);

if ($ajax) {
    $params['cell_type'] = required_param('cell_type', PARAM_TEXT);
}

$controller = new mod_coursework\controllers\feedback_controller($params);
$controller->update_feedback();