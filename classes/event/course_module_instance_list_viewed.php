<?php

// This file is part of the Certificate module for Moodle - http://moodle.org/
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


namespace mod_coursework\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Class course_module_instance_list_viewed is responsible for logging the fact that a user has looked at the list
 * of available coursework modules.
 *
 * @package mod_coursework\event
 */
class course_module_instance_list_viewed extends \core\event\course_module_instance_list_viewed {
}