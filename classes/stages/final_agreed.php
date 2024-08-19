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

namespace mod_coursework\stages;
use mod_coursework\models\user;

/**
 * Class marking_stage represents a stage of the marking process. For a basic single marked coursework,
 * there will only be one. Double marking will have 3 (Two initial assessors and a final grade), and if
 * moderation is enabled, there will be one more.
 *
 * @package mod_coursework
 */
class final_agreed extends base {

    /**
     * Tells us whether the allocation table needs to deal with this one.
     *
     * @return bool
     */
    public function uses_allocation() {
        return false;
    }

    /**
     * @return string
     */
    protected function strategy_name() {
        return 'none';
    }

    /**
     * @return string
     * @throws \coding_exception
     */
    public function allocation_table_header() {
        return get_string('agreedgrade', 'mod_coursework');
    }

    /**
     * @return string
     */
    protected function assessor_capability() {
        return 'mod/coursework:addagreedgrade';
    }
}
