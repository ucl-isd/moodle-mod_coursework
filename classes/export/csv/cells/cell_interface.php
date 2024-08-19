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

namespace mod_coursework\export\csv\cells;

/**
 * Interface cell_interface makes sure that all of the grading report cells are the same.
 */
interface cell_interface {

    /**
     * @param $submission
     * @param $student
     * @param $stage_identifier
     * @return mixed
     */
    public function get_cell($submission, $student, $stage_identifier);

    /**
     * @param $stage
     * @return mixed
     */
    public function get_header($stage);

    /**
     * @param $value
     * @param $submissions
     * @param $stage_dentifier
     * @return mixed
     */
    public function validate_cell($value, $submissions, $stage_dentifier='');

}
