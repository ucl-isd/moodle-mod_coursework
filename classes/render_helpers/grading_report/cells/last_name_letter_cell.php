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

namespace mod_coursework\render_helpers\grading_report\cells;
use mod_coursework\models\user;
use mod_coursework\user_row;

/**
 * Class user_cell
 */
class last_name_letter_cell extends cell_base implements allocatable_cell {

    /**
     * @param user_row $rowobject
     * @return string
     */
    public function get_table_cell($rowobject) {
        $content = '';

        /**
         * @var user $user
         */
        $user = $rowobject->get_allocatable();

        mb_internal_encoding('utf-8');
        $content .= ' ' . mb_substr($user->lastname, 0, 1);

        return $this->get_new_cell_with_class($content);
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options  = []) {
        return "Last Name Letter";
    }

    /**
     * @return string
     */
    public function get_table_header_class() {
        return 'lastname_letter_cell';
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'empty';
    }
}
