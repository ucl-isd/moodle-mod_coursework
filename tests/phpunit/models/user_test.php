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

namespace mod_coursework;

use mod_coursework\models\user;
use stdClass;
use testing_util;

/**
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class coursework_user_test
 * @group mod_coursework
 */
final class user_test extends \advanced_testcase {
    use test_helpers\factory_mixin;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    public function test_find(): void {
        $user = $this->getDataGenerator()->create_user();

        $this->assertNotEmpty($user->firstname);
        $this->assertEquals($user->firstname, \mod_coursework\models\user::find($user->id)->firstname);
    }

    public function test_has_final_agreed_grade_returns_true_when_present(): void {
        $this->create_a_final_feedback_for_the_submission();

        $this->assertTrue($this->get_student()->has_agreed_feedback($this->get_coursework()));
    }

    public function test_has_final_agreed_grade_returns_false_when_not_present(): void {
        $this->create_a_student();
        $this->assertFalse($this->get_student()->has_agreed_feedback($this->get_coursework()));
    }

    public function test_has_final_agreed_grade_returns_false_when_present_for_different_coursework(): void {
        $this->create_a_student();
        $this->create_a_final_feedback_for_the_submission();
        $coursework = $this->getMockBuilder('\mod_coursework\models\coursework')->getMock();
        $coursework->expects($this->any())
            ->method('id')
            ->will($this->returnValue(234234));
        $this->assertFalse($this->get_student()->has_agreed_feedback($coursework));
    }

    public function test_has_final_agreed_grade_returns_false_when_initial_feedback_is_present(): void {
        $this->create_a_student();
        $teacher = $this->create_a_teacher();
        $this->create_an_assessor_feedback_for_the_submission($teacher);
        $this->assertFalse($this->get_student()->has_agreed_feedback($this->get_coursework()));
    }

    public function test_has_all_initial_feedbacks_returns_false_when_only_some_are_present(): void {
        $this->create_a_student();
        $this->create_a_coursework();
        $this->coursework->update_attribute('numberofmarkers', 2);
        $teacher = $this->create_a_teacher();
        $this->create_an_assessor_feedback_for_the_submission($teacher);
        $this->assertFalse($this->get_student()->has_all_initial_feedbacks($this->get_coursework()));
    }

    public function test_has_all_initial_feedbacks_returns_false_when_only_final_grade_is_present(): void {
        $this->create_a_student();
        $this->create_a_coursework();
        $this->coursework->update_attribute('numberofmarkers', 1);
        $teacher = $this->create_a_teacher();
        $this->create_a_final_feedback_for_the_submission($teacher);
        $this->assertFalse($this->get_student()->has_all_initial_feedbacks($this->get_coursework()));
    }

    public function test_has_all_initial_feedbacks_returns_false_when_all_are_present(): void {
        $this->create_a_student();
        $this->create_a_coursework();
        $this->coursework->update_attribute('numberofmarkers', 1);
        $teacher = $this->create_a_teacher();
        $this->create_an_assessor_feedback_for_the_submission($teacher);
        $this->assertTrue($this->get_student()->has_all_initial_feedbacks($this->get_coursework()));
    }

    /**
     * Test that user::find() and user->persisted() working as expected.
     * @covers \mod_coursework\models\user::find()
     * @covers \mod_coursework\framework\table_base::find()
     * @covers \mod_coursework\framework\table_base::persisted()
     * @throws \core\exception\invalid_parameter_exception
     */
    public function test_persisted(): void {
        global $DB;
        $generator = testing_util::get_data_generator();

        $user = new stdClass();
        $user->firstname = 'Rare name';
        $user->Lastname = 'Smith';
        $dbstudent = $generator->create_user($user);
        $this->assertNotFalse($dbstudent);

        // Find the user coursework object without providing their ID, using an array.
        $courseworkuser = user::find((array)$user);
        $this->assertNotFalse($courseworkuser);
        $this->assertTrue($courseworkuser->persisted());
        $this->assertEquals($dbstudent->id, $courseworkuser->id());

        // Find the user coursework object without providing their ID, using an object.
        $courseworkuser2 = user::find($user);
        $this->assertNotFalse($courseworkuser2);
        $this->assertTrue($courseworkuser2->persisted());
        $this->assertEquals($dbstudent->id, $courseworkuser2->id());

        // Find the user coursework object providing their ID.
        $courseworkuser3 = user::find($dbstudent);
        $this->assertNotFalse($courseworkuser3);
        $this->assertTrue($courseworkuser3->persisted());
        $this->assertEquals($dbstudent->id, $courseworkuser3->id());

        // Find the user coursework object providing a fresh DB record ID, preventing reload.
        $dbrecord = $DB->get_record('user', ['id' => $dbstudent->id], '*', MUST_EXIST);
        $courseworkuser4 = user::find($dbrecord, false);
        $this->assertNotFalse($courseworkuser4);
        $this->assertTrue($courseworkuser4->persisted());
        $this->assertEquals($dbrecord->id, $courseworkuser4->id());
    }
}
