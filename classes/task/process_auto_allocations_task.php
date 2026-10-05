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

namespace mod_coursework\task;

use cache;
use core\task\adhoc_task;
use core\task\scheduled_task;
use mod_coursework\allocation\auto_allocator;
use mod_coursework\models\coursework;

/**
 * A scheduled task for the coursework module cron.
 *
 * @package    mod_coursework
 * @copyright  2014 ULCC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class process_auto_allocations_task extends adhoc_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_name() {
        return get_string('enroltask', 'mod_coursework');
    }

    /**
     * Run coursework cron.
     */
    public function execute() {
        if ($coursework = coursework::get_from_id($this->get_custom_data()->courseworkid)) {
            $allocator = new auto_allocator($coursework);
            $allocator->process_allocations();
        }

        return true;
    }
}
