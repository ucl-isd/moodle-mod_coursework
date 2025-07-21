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
 * Data provider for user cell in grading report.
 *
 * @package    mod_coursework
 * @copyright  2025 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */

namespace mod_coursework\render_helpers\grading_report\data;

use mod_coursework\grading_table_row_base;
use stdClass;

/**
 * Class student_cell_data provides data for student cell in tr template.
 *
 */
class student_cell_data extends cell_data_base {
    /**
     * Get the data for the student cell.
     *
     * @param grading_table_row_base $rowsbase
     * @return stdClass|null The data object for template rendering.
     */
    public function get_table_cell_data(grading_table_row_base $rowsbase): ?stdClass {
        $user = new stdClass();
        $user->name = $rowsbase->get_user_name(true);
        $user->picture = '';
        if ($rowsbase->can_view_username() || $rowsbase->is_published()) {
            $user->picture = $rowsbase->get_user_picture();
        }

        return $user;
    }
}
