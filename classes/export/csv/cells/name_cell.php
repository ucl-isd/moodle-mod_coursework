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

namespace mod_coursework\export\csv\cells;
use mod_coursework\models\submission;

/**
 * Class name_cell
 */
class name_cell extends cell_base {

    /**
     * @param submission $submission
     * @param $student
     * @param $stageidentifier
     * @return string
     * @throws \coding_exception
     */
    public function get_cell($submission, $student, $stageidentifier) {

        if ($this->can_view_hidden() || $submission->is_published()) {
            $name = $student->lastname . ' ' . $student->firstname;
        } else {
            $name = get_string('hidden', 'coursework');
        }

        return  $name;
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage) {
        return  get_string('studentname', 'coursework');
    }
}
