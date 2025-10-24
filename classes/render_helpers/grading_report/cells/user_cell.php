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
use core_user;
use html_table_cell;
use html_writer;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\user;
use mod_coursework\user_row;
use stdClass;

/**
 * Class user_cell
 */
class user_cell extends cell_base implements allocatable_cell {

    /**
     * @param user_row $rowobject
     * @return string
     */
    public function get_table_cell($rowobject) {
        $content = '';
        $content .= ' ' . $rowobject->get_user_name(true);
        $content .= "<br>".$rowobject->get_email();
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
            $sortbyfirstname = $this->helper_sortable_heading(get_string('firstname'),
                                                                'firstname',
                                                                $options['sorthow'],
                                                                $options['sortby'],
                                                                $tablename);
            $sortbylastname = $this->helper_sortable_heading(get_string('lastname'),
                                                               'lastname',
                                                               $options['sorthow'],
                                                               $options['sortby'],
                                                                $tablename);
            $sortbyemail = $this->helper_sortable_heading(get_string('email', 'mod_coursework'),
               'email',
               $options['sorthow'],
               $options['sortby'],
               $tablename);

        } else { // otherwise display header without sorting
            $sortbyfirstname = get_string('firstname');
            $sortbylastname = get_string('lastname');
            $sortbyemail = get_string('email', 'mod_coursework');
        }

        if ($this->fullname_format() == 'lf') {
            $sortbyfirstname = ' / ' . $sortbyfirstname;
        } else {
            $sortbylastname = ' / ' . $sortbylastname;
        }

        $sortbyfirstname = '<span class="data-table-splitter splitter-firstname sorting">'.$sortbyfirstname.'</span>';

        $sortbylastname = '<span class="data-table-splitter splitter-lastname sorting">'.$sortbylastname.'</span>';

        $sortbyemail = '<span class="data-table-splitter splitter-email sorting">'.$sortbyemail.'</span>';

        if ($this->fullname_format() == 'lf') {
            $sortbyname = $sortbylastname . $sortbyfirstname;
        } else {
            $sortbyname = $sortbyfirstname . $sortbylastname;
        }
        $sort = $sortbyname ."<br>" .$sortbyemail;
        return $sort;
    }

    /**
     * @return string
     */
    public function get_table_header_class() {
        return 'studentname';
    }

    /**
     * Tries to guess the full name format set at the site.
     *
     * @return string fl|lf
     */
    private function fullname_format() {
        $fake = new stdclass(); // Fake user.
        $fake->lastname = 'LLLL';
        $fake->firstname = 'FFFF';
        $fullname = get_string('fullnamedisplay', '', $fake);
        if (strpos($fullname, 'LLLL') < strpos($fullname, 'FFFF')) {
            return 'lf';
        } else {
            return 'fl';
        }
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'empty';
    }
}
