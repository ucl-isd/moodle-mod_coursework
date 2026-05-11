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
 * Unit tests for mod/coursework/classes/task/enrol_task and unenrol_task.
 *
 * @package    mod_coursework
 * @copyright  2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Leon Stringer <leon.stringer@ucl.ac.uk>
 */

namespace mod_coursework;

use mod_coursework\task\enrol_task;
use mod_coursework\task\unenrol_task;

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
    public function test_enrol_tasks(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $coursework = $this->getDataGenerator()->create_module('coursework', ['course' => $course->id]);

        // Enrol teacher and check there is a processenrol record.
        $teacher  = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->assertCount(1, $DB->get_records('coursework', ['processenrol' => 1]));

        // Run task and check there are no processenrol records.
        $task = new enrol_task();
        $task->execute();
        $this->assertCount(0, $DB->get_records('coursework', ['processenrol' => 1]));
    }

    /**
     * Add a single record for the unenrol task to process.
     */
    public function test_unenrol_tasks(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $teacher  = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $coursework = $this->getDataGenerator()->create_module('coursework', ['course' => $course->id]);

        // Unenrol teacher and check there is a processunenrol record.
        $enrol = enrol_get_plugin('manual');
        $manualenrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        $enrol->unenrol_user($manualenrol, $teacher->id);
        $this->assertCount(1, $DB->get_records('coursework', ['processunenrol' => 1]));

        // Run task and check there are no processunenrol records.
        $task = new unenrol_task();
        $task->execute();
        $this->assertCount(0, $DB->get_records('coursework', ['processunenrol' => 1]));
    }
}
