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

namespace mod_coursework\allocation;

/**
 * Rendererable object to make the widget to choose allocation strategy.
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2012 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\models\coursework;

defined('MOODLE_INTERNAL') || die();


/**
 * Just a placeholder for the renderer really.
 */
class widget {

    /**
     * @var coursework
     */
    private $coursework;

    /**
     * Constructor
     *
     * @param coursework $coursework
     */
    public function __construct($coursework) {
        $this->coursework = $coursework;
    }

    /**
     * @return coursework
     */
    public function get_coursework() {
        return $this->coursework;
    }

    /**
     * Gets the name of the strategy used by this coursework to auto allocate students to assessors.
     *
     * @return string
     */
    public function get_assessor_allocation_strategy() {
        return $this->coursework->assessorallocationstrategy;

    }

}
