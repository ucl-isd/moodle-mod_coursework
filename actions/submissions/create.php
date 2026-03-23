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
 * Creates a submission instance and redirects to the coursework page.
 */

use mod_coursework\exceptions\access_denied;
use mod_coursework\models\coursework;

require_once(dirname(__FILE__) . '/../../../../config.php');

$controller = new mod_coursework\controllers\submissions_controller([
    'courseworkid' => required_param('courseworkid', PARAM_INT),
    'finalised' => optional_param('finalisebutton', 0, PARAM_TEXT),
    'allocatableid' => required_param('allocatableid', PARAM_INT),
    'allocatabletype' => required_param('allocatabletype', PARAM_TEXT),
    'submissionid' => optional_param('submissionid', null, PARAM_INT),
]);
require_login($controller->get_course(), false, $controller->get_coursemodule());
$controller->create_submission();
