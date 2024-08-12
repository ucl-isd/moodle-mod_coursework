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

namespace mod_coursework\auto_grader;

/**
 * Class none is responsible for implementing the none object pattern for the auto grader mechanism.
 *
 * @package mod_coursework\auto_grader
 */
class none implements auto_grader {

    /**
     * @param $coursework
     * @param $allocatable
     * @param $percentage
     */
    public function __construct($coursework, $allocatable) {
    }

    public function create_auto_grade_if_rules_match() {
    }
}
