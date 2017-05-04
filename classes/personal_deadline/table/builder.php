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

namespace mod_coursework\personal_deadline\table;

/**
 * Class file for the renderable object that makes the table for assigning personal deadlines to students.
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2011 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\models\coursework;
use mod_coursework\personal_deadline\table\row\builder as row_builder;
use mod_coursework\render_helpers\grading_report\cells\allocatable_cell;
use mod_coursework\render_helpers\grading_report\cells\group_cell;
use mod_coursework\render_helpers\grading_report\cells\user_cell;
use mod_coursework\render_helpers\grading_report\cells\personal_deadline_cell;
use mod_coursework\stages\base as stage_base;

use mod_coursework;

defined('MOODLE_INTERNAL') || die();

/**
 * Represents the table that will show all students and there personal deadline date the table will allow the admin
 * to change the date or persnal deadline on mass or individually.
 */
class builder {

    /**
     * @var coursework
     */
    private $coursework;

    /**
     * @var array The sorting options etc for the table.
     */
    private $options;

    /**
     * Constructor makes a new instance.
     *
     * @param coursework $coursework
     * @param array $options
     */
    public function __construct($coursework, array $options) {
        $this->coursework = $coursework;
        $this->options = $options;
    }


    /**
     * Takes the raw data, instantiates each row as a new renderable object and returns the whole lot.
     *
     * @return array
     */
    public function get_rows() {
        $allocatables = $this->get_coursework()->get_allocatables();

        $rows = array();
        foreach ($allocatables as $allocatable) {
            $rows[] = new row_builder($this, $allocatable);
        }

        // Sort the rows.
        $sorting = new mod_coursework\grading_report($this->options, $this->coursework);
        $method_name = 'sort_by_' . $this->options['sortby'];
        if (method_exists($sorting, $method_name)) {
            usort($rows,
                array($sorting,
                    $method_name));
        }


        return $rows;

    }

    /**
     * Getter for the coursework instance
     *
     * @return coursework
     */
    public function get_coursework() {
        return $this->coursework;
    }

    /**
     * Returns an array of options for retrieving SQL to fill the table e.g. sort.
     *
     * @return array
     */
    public function get_options() {
        return $this->options;
    }

    /**
     * @return allocatable_cell
     */
    public function get_allocatable_cell() {
        $items = array(
            'coursework' => $this->coursework
        );

        if ($this->coursework->is_configured_to_have_group_submissions()) {
            return new group_cell($items);
        }
        return new user_cell($items);
    }


    /**
     * @return personal_deadline_cell
     */
    public function get_personal_deadline_cell() {
        $items = array(
            'coursework' => $this->coursework
        );

        return new personal_deadline_cell($items);
    }
}
