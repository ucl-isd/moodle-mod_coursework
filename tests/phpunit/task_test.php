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
 * Unit tests for mod/coursework/classes/task/process_auto_allocations_task.
 *
 * @package    mod_coursework
 * @copyright  2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Leon Stringer <leon.stringer@ucl.ac.uk>
 */

namespace mod_coursework;

use core\task\manager;
use mod_coursework\task\process_auto_allocations_task;

/**
 * Unit tests for mod/coursework/lib.php.
 *
 * @copyright  2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class task_test extends \advanced_testcase {
    /**
     * Add a single record for the enrol task to process.
     */
    public function test_process_auto_allocations_tasks(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        $this->assertEmpty(manager::get_adhoc_tasks(process_auto_allocations_task::class));

        $this->getDataGenerator()->create_module('coursework', ['course' => $course->id]);
        $this->assertCount(1, manager::get_adhoc_tasks(process_auto_allocations_task::class));

        $DB->delete_records('task_adhoc');

        $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->assertCount(1, manager::get_adhoc_tasks(process_auto_allocations_task::class));

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->assertCount(1, manager::get_adhoc_tasks(process_auto_allocations_task::class));

        $task = \core\task\manager::get_next_adhoc_task(time());
        $task->execute();
        manager::adhoc_task_complete($task);
        $this->assertEmpty(manager::get_adhoc_tasks(process_auto_allocations_task::class));

        $enrol = enrol_get_plugin('manual');
        $manualenrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        $enrol->unenrol_user($manualenrol, $teacher->id);
        $this->assertCount(1, manager::get_adhoc_tasks(process_auto_allocations_task::class));
    }
}
