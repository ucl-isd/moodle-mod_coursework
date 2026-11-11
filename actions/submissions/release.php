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
 * Releases the mark for a single submission and redirects to the coursework page.
 *
 * @package    mod_coursework
 * @author     Vlad Kidanov <vlad.kidanov@catalyst-eu.net>
 * @copyright  2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../../../config.php');

$controller = new mod_coursework\controllers\submissions_controller([
    'submissionid' => required_param('submissionid', PARAM_INT),
]);
require_login($controller->get_course(), false, $controller->get_coursemodule());
require_sesskey();
$controller->release_submission();
