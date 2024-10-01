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
use stdClass;

/**
 * Class idnumber_cell
 */
class idnumber_cell extends cell_base implements allocatable_cell {

    /**
     * @param user_row $rowobject
     * @return string
     */
    public function get_table_cell($rowobject) {
        global $OUTPUT, $PAGE;

        $content = '';
        $content .= $rowobject->get_idnumber();

        return $this->get_new_cell_with_class($content);
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options  = []) {

        $viewanonymous = has_capability('mod/coursework:viewanonymous', $this->coursework->get_context());

        // Adding this line so that the sortable heading function will make a sortable link unique to the table
        // If tablename is set
        $tablename = (!empty($options['tablename'])) ? $options['tablename'] : '';

        // allow to sort users only if CW is not set to blind marking or a user has capability to view anonymous
        if ($viewanonymous || !$this->coursework->blindmarking) {
            $sortheader = $this->helper_sortable_heading(get_string('idnumber'),
                'idnumber',
                $options['sorthow'],
                $options['sortby'],
                $tablename);

        } else { // otherwise display header without sorting
            $sortheader = get_string('idnumber');
        }

        return $sortheader;
    }

    /**
     * @return string
     */
    public function get_table_header_class() {
        return 'tableheadidnumber';
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'empty';
    }
}
