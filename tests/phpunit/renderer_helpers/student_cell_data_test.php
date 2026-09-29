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
 * @copyright  2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework;

use mod_coursework\render_helpers\grading_report\data\student_cell_data;

/**
 * Tests for rendering user and group details.
 * @group mod_coursework
 */
final class student_cell_data_test extends \advanced_testcase {
    use test_helpers\factory_mixin;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $teacher = $this->create_a_teacher();

        $this->setUser($teacher->get_raw_record());
    }

    public function test_show_user_details(): void {
        global $DB;

        $student = $this->create_a_student();
        $this->coursework = $this->create_a_coursework();
        $row = new grading_table_row_base($this->coursework, $this->student, null, null, []);
        $celldata = new student_cell_data($this->coursework);
        $data = $celldata->get_table_cell_data($row);

        // Teacher can see user details when blind marking disabled.
        $this->assertEquals($student->id, $data->user->id);
        $this->assertEquals(fullname($student->get_raw_record()), $data->user->name);
        $this->assertStringEndsWith($student->id, $data->user->url);

        // Enable blind marking.
        $this->coursework->blindmarking = true;
        $this->coursework->save();

        // Teacher cannot see user details when blind marking disabled.
        $celldata = new student_cell_data($this->coursework);
        $data = $celldata->get_table_cell_data($row);
        $this->assertEmpty($data->user->id);
        $this->assertEquals('Hidden', $data->user->name);
        $this->assertEmpty($data->user->url);

        // Assign viewanonymous capabilidy to the teacher role.
        $role = $DB->get_record('role', ['shortname' => 'teacher']);
        assign_capability('mod/coursework:viewanonymous', CAP_ALLOW, $role->id, $this->coursework->get_context(), true);
        $celldata = new student_cell_data($this->coursework);
        $data = $celldata->get_table_cell_data($row);

        // With viewanonymous capability, teacher can see user details.
        $this->assertEquals($student->id, $data->user->id);
        $this->assertEquals(fullname($student->get_raw_record()), $data->user->name);
        $this->assertStringEndsWith($student->id, $data->user->url);
    }

    public function test_show_user_details_candidatenumber(): void {
        global $DB;

        $student = $this->create_a_student();
        $this->coursework = $this->create_a_coursework();
        set_config('use_candidate_numbers_for_hidden_name', true, 'mod_coursework');
        set_config('candidate_provider', 'idnumber', 'mod_coursework');
        $row = new grading_table_row_base($this->coursework, $this->student, null, null, []);
        $celldata = new student_cell_data($this->coursework);
        $data = $celldata->get_table_cell_data($row);

        // Teacher can see user details when blind marking disabled.
        $this->assertEquals($student->id, $data->user->id);
        $this->assertEquals(fullname($student->get_raw_record()), $data->user->name);
        $this->assertStringEndsWith($student->id, $data->user->url);

        // Enable blind marking.
        $this->coursework->blindmarking = true;
        $this->coursework->save();
        $celldata = new student_cell_data($this->coursework);

        // Teacher cannot see user details when blind marking disabled.
        $data = $celldata->get_table_cell_data($row);
        $this->assertEmpty($data->user->id);
        $this->assertEquals('Hidden', $data->user->name);
        $this->assertEmpty($data->user->url);

        // Teacher sees a candidate number when blind marking enabled.
        $user = $student->get_raw_record();
        $user->idnumber = 'useridnumber';
        user_update_user($user, false);
        $data = $celldata->get_table_cell_data($row);
        $this->assertEmpty($data->user->id);
        $this->assertEquals('useridnumber', $data->user->name);
        $this->assertEmpty($data->user->url);

        // Assign viewanonymous capabilidy to the teacher role.
        $role = $DB->get_record('role', ['shortname' => 'teacher']);
        assign_capability('mod/coursework:viewanonymous', CAP_ALLOW, $role->id, $this->coursework->get_context(), true);
        $celldata = new student_cell_data($this->coursework);
        $data = $celldata->get_table_cell_data($row);

        // With viewanonymous capability, teacher can see user details.
        $this->assertEquals($student->id, $data->user->id);
        $this->assertEquals('useridnumber (Student Lastname2)', $data->user->name);
        $this->assertStringEndsWith($student->id, $data->user->url);
    }

    public function test_show_user_details_group(): void {
        $student = $this->create_a_student();
        $this->create_a_group();
        $this->add_student_to_the_group();
        $this->coursework = $this->create_a_coursework(['usegroups' => true]);
        $row = new grading_table_row_base($this->coursework, $this->group, null, null, []);
        $celldata = new student_cell_data($this->coursework);
        $data = $celldata->get_table_cell_data($row);

        // Teacher can see group details and user details when blind marking disabled.
        $this->assertEquals($this->group->id, $data->group->id);
        $this->assertEquals($this->group->name, $data->group->name);
        $this->assertEquals($student->id, $data->group->members[0]->id);
        $this->assertEquals(fullname($student->get_raw_record()), $data->group->members[0]->name);
        $this->assertStringEndsWith($student->id, $data->group->members[0]->url);

        // Enable blind marking.
        $this->coursework->blindmarking = true;
        $this->coursework->save();

        // Teacher can see group details but not user details when blind marking enabled.
        $celldata = new student_cell_data($this->coursework);
        $data = $celldata->get_table_cell_data($row);
        $this->assertEmpty($data->group->id);
        $this->assertEquals($this->group->name, $data->group->name);
        $this->assertEmpty($data->group->members[0]->id);
        $this->assertEquals('Hidden', $data->group->members[0]->name);
        $this->assertEmpty($data->group->members[0]->url);
    }
}
