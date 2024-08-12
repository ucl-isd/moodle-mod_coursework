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

namespace mod_coursework\render_helpers\grading_report\cells;
use html_table_cell;
use mod_coursework\grading_table_row_base;

/**
 * Class feedback_cell
 */
class status_cell extends cell_base {

    /**
     * @param grading_table_row_base $rowobject
     * @return string
     */
    public function get_table_cell($rowobject) {
        $content = '';

        $submission = $rowobject->get_submission();
        if ($submission) {
            $content = $submission->get_status_text();
        }
        return $this->get_new_cell_with_class($content);
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options = array()) {
        return get_string('tableheadstatus', 'coursework');
    }

    /**
     * @return string
     */
    public function get_table_header_class() {
        return 'tableheadstatus';
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'empty';
    }
}
