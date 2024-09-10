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

/**
 * Class coursework_user_test
 * @group mod_coursework
 */
class coursework_user_test extends advanced_testcase {

    use mod_coursework\test_helpers\factory_mixin;

    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    public function test_find(): void {
        $user = $this->getDataGenerator()->create_user();

        $this->assertNotEmpty($user->firstname);
        $this->assertEquals($user->firstname, \mod_coursework\models\user::find($user->id)->firstname);
    }

    // has_agreed_feedback()

    public function test_has_final_agreed_grade_returns_true_when_present(): void {
        $this->create_a_final_feedback_for_the_submisison();

        $this->assertTrue($this->get_student()->has_agreed_feedback($this->get_coursework()));
    }

    public function test_has_final_agreed_grade_returns_false_when_not_present(): void {
        $this->create_a_student();
        $this->assertFalse($this->get_student()->has_agreed_feedback($this->get_coursework()));
    }

    public function test_has_final_agreed_grade_returns_false_when_present_for_different_coursework(): void {
        $this->create_a_student();
        $this->create_a_final_feedback_for_the_submisison();
        $coursework = $this->getMockBuilder('\mod_coursework\coursework')->setMethods(array('id'))->getMock();
        $coursework->expects($this->any())
            ->method('id')
            ->will($this->returnValue(234234));
        $this->assertFalse($this->get_student()->has_agreed_feedback($coursework));
    }

    public function test_has_final_agreed_grade_returns_false_when_initial_feedback_is_present(): void {
        $this->create_a_student();
        $teacher = $this->create_a_teacher();
        $this->create_an_assessor_feedback_for_the_submisison($teacher);
        $this->assertFalse($this->get_student()->has_agreed_feedback($this->get_coursework()));
    }

    // has_all_initial_feedbacks()

    public function test_has_all_initial_feedbacks_returns_false_when_only_some_are_present(): void {
        $this->create_a_student();
        $this->create_a_coursework();
        $this->coursework->update_attribute('numberofmarkers', 2);
        $teacher = $this->create_a_teacher();
        $this->create_an_assessor_feedback_for_the_submisison($teacher);
        $this->assertFalse($this->get_student()->has_all_initial_feedbacks($this->get_coursework()));
    }

    public function test_has_all_initial_feedbacks_returns_false_when_only_final_grade_is_present(): void {
        $this->create_a_student();
        $this->create_a_coursework();
        $this->coursework->update_attribute('numberofmarkers', 1);
        $teacher = $this->create_a_teacher();
        $this->create_a_final_feedback_for_the_submisison($teacher);
        $this->assertFalse($this->get_student()->has_all_initial_feedbacks($this->get_coursework()));
    }

    public function test_has_all_initial_feedbacks_returns_false_when_all_are_present(): void {
        $this->create_a_student();
        $this->create_a_coursework();
        $this->coursework->update_attribute('numberofmarkers', 1);
        $teacher = $this->create_a_teacher();
        $this->create_an_assessor_feedback_for_the_submisison($teacher);
        $this->assertTrue($this->get_student()->has_all_initial_feedbacks($this->get_coursework()));
    }

    // get_initial_feedbacks()


}
