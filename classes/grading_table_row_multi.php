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

namespace mod_coursework;

/**
 * Single row of the grading table for when there are multiple markers.
 */
class grading_table_row_multi extends grading_table_row_base {
    /**
     * Returns a new table object using the attached submission as a constructor.
     *
     * @return assessor_feedback_table
     */
    public function get_assessor_feedback_table() {
        return new assessor_feedback_table($this->coursework, $this->get_allocatable(), $this->get_submission());
    }
}
