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
