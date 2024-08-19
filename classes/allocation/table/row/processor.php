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

namespace mod_coursework\allocation\table\row;
use mod_coursework\allocation\allocatable;
use mod_coursework\models\coursework;

/**
 * Class row represents one row in the allocation table. It really just acts as a factory for the calls and the
 * allocatable.
 *
 * @package mod_coursework\allocation\table
 */
class processor {

    /**
     * @var coursework
     */
    private $coursework;

    /**
     * User or group
     *
     * @var allocatable
     */
    private $allocatable;

    /**
     * @param $coursework
     * @param allocatable $allocatable
     */
    public function __construct($coursework, $allocatable) {
        $this->coursework = $coursework;
        $this->allocatable = $allocatable;
    }

    /**
     * Processes all the data in order to save it.
     * @param array $data
     */
    public function process($data) {
        $stages = $this->coursework->marking_stages();

        foreach ($stages as $stage) {
            if ($data) {
                $stage->process_allocation_form_row_data($this->allocatable, $data);
            }
        }
    }

}
