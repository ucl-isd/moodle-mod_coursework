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
 * Test allocating assessors by file upload.
 * @package    mod_coursework
 * @copyright  2025 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework;

use core\context\system as context_system;
use core\exception\moodle_exception;
use mod_coursework\allocation\marker_role;
use mod_coursework\allocation\upload;
use mod_coursework\models\coursework;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/csvlib.class.php');

/**
 * Class upload_allocators_test.
 * @property coursework coursework
 * @property \stdClass student
 * @property \stdClass teacher_one
 * @property \stdClass teacher_two
 * @property \stdClass course
 * @group mod_coursework
 */
final class upload_allocators_test extends \advanced_testcase {
    use test_helpers\factory_mixin;

    /**
     * Setup.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Check assessors allocated by CSV file are applied.
     */
    public function test_upload_assessor_allocations(): void {
        global $CFG, $DB;

        set_config('allocation_identifier', 'email', 'mod_coursework');
        $this->setAdminUser();
        $generator = $this->getDataGenerator();

        $this->course = $generator->create_course();

        $params = [
            'numberofmarkers' => 2,
            'allocationenabled' => 1,
        ];

        $coursework = $this->create_a_coursework($params);

        $student = $this->create_a_student();
        $teacher1 = $this->create_a_teacher();
        $teacher2 = $this->create_another_teacher();

        $content = [
            "user_email,assessor_email_1,assessor_email_2",
            "{$student->email},{$teacher1->email},{$teacher2->email}",
        ];

        $content = implode("\n", $content);
        $csvimport = new \mod_coursework\allocation\upload($coursework);
        $processingresults = $csvimport->validate_csv($content, 'UTF-8', 'comma');
        $this->assertEmpty($processingresults);
        $processingresults = $csvimport->process_csv($content, 'UTF-8', 'comma', $processingresults);
        $this->assertEmpty($processingresults);

        // The two teachers should be allocated to the student.
        $this->assertCount(2, $DB->get_records('coursework_allocation_pairs'));
    }

    /**
     * Set up a 2-marker coursework where the teacher role cannot mark and a marker role is configured.
     *
     * @return int the marker role id.
     */
    private function setup_marker_role_scenario(): int {
        global $DB;

        set_config('allocation_identifier', 'email', 'mod_coursework');
        $this->setAdminUser();
        $this->create_a_coursework(['numberofmarkers' => 2, 'allocationenabled' => 1]);

        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        $editingteacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        unassign_capability('mod/coursework:addinitialgrade', $teacherroleid);

        $markerroleid = create_role('Coursework marker', 'courseworkmarker', '');
        set_role_contextlevels($markerroleid, [CONTEXT_COURSE, CONTEXT_MODULE]);
        assign_capability('mod/coursework:addinitialgrade', CAP_ALLOW, $markerroleid, context_system::instance());

        set_config('markerrole', $markerroleid, 'mod_coursework');
        set_config('teachingroles', "{$editingteacherroleid},{$teacherroleid}", 'mod_coursework');
        accesslib_clear_all_caches_for_unit_testing();

        return $markerroleid;
    }

    /**
     * Only markers with a teaching role but no marking capability are flagged.
     *
     * @covers \mod_coursework\allocation\marker_role::get_users_requiring_role
     */
    public function test_get_users_requiring_marker_role(): void {
        $this->resetAfterTest();
        $this->setup_marker_role_scenario();

        $student = $this->create_a_student();
        $teacher = $this->create_a_teacher();
        $marker = $this->create_another_teacher();
        role_assign(get_config('mod_coursework', 'markerrole'), $marker->id, $this->coursework->get_context()->id);
        $othernonteacher = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $content = implode("\n", [
            "user_email,assessor_email_1,assessor_email_2",
            "{$student->email},{$teacher->email},{$marker->email}",
            "{$othernonteacher->email},{$teacher->email},{$othernonteacher->email}",
        ]);

        $users = (new marker_role($this->coursework))->get_users_requiring_role($content, 'UTF-8', 'comma');
        $this->assertEquals([$teacher->id], array_keys($users));

        set_config('markerrole', 0, 'mod_coursework');
        $users = (new marker_role($this->coursework))->get_users_requiring_role($content, 'UTF-8', 'comma');
        $this->assertEmpty($users);
    }

    /**
     * Once the marker role is granted the flagged teacher can be allocated.
     *
     * @covers \mod_coursework\allocation\marker_role::assign
     * @covers \mod_coursework\allocation\upload::validate_and_process
     */
    public function test_assign_marker_role_then_allocate(): void {
        global $DB;

        $this->resetAfterTest();
        $markerroleid = $this->setup_marker_role_scenario();

        $student = $this->create_a_student();
        $teacher = $this->create_a_teacher();
        $content = implode("\n", [
            "user_email,assessor_email_1,assessor_email_2",
            "{$student->email},{$teacher->email},",
        ]);
        $csvimport = new upload($this->coursework);
        $context = $this->coursework->get_context();
        $markerrole = new marker_role($this->coursework);

        $this->assertNotEmpty($csvimport->validate_csv($content, 'UTF-8', 'comma'));

        $markerrole->assign(array_keys($markerrole->get_users_requiring_role($content, 'UTF-8', 'comma')));

        $this->assertTrue(user_has_role_assignment($teacher->id, $markerroleid, $context->id));
        $this->assertFalse($csvimport->validate_and_process($content, 'UTF-8', 'comma'));
        $this->assertTrue($DB->record_exists('coursework_allocation_pairs', [
            'courseworkid' => $this->coursework->id,
            'assessorid' => $teacher->id,
            'allocatableid' => $student->id,
        ]));
    }

    /**
     * Users who are not allowed to assign the marker role cannot grant it.
     *
     * @covers \mod_coursework\allocation\marker_role::can_assign
     */
    public function test_assign_marker_role_requires_permission(): void {
        $this->resetAfterTest();
        $this->setup_marker_role_scenario();

        $teacher = $this->create_a_teacher();
        $this->setUser($teacher->id);
        $markerrole = new marker_role($this->coursework);

        $this->assertFalse($markerrole->can_assign());
        $this->expectException(moodle_exception::class);
        $markerrole->assign([$teacher->id]);
    }
}
