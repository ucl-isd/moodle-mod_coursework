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


namespace mod_coursework\auto_grader;

/**
 * Class percentage_distance_test is responsible for testing the behaviour of the percentage_distance class.
 *
 * @package mod_coursework\auto_grader
 * @group mod_coursework
 */
class percentage_distance_test extends \advanced_testcase {

    use mod_coursework\test_helpers\factory_mixin;

    public function setUp() {
        $this->setAdminUser();
        $this->resetAfterTest();
    }

    public function test_nothing_happens_when_there_is_already_an_agreed_feedback() {
        global $DB;

        $user = $this->createMock('\mod_coursework\models\user');
        $user->expects($this->any())->method('has_agreed_feedback')
            ->with($this->anything())
            ->will($this->returnValue(true));

        $object = new percentage_distance($this->get_coursework(), $user, 10);

        $object->create_auto_grade_if_rules_match();

        $this->assertEquals(0, $DB->count_records('coursework_feedbacks'));
        // reset_after_test() has not been called, so this will fail if the DB is changed.
    }

    public function test_nothing_happens_when_the_initial_feedbacks_are_not_there() {
        global $DB;

        $user = $this->createMock('\mod_coursework\models\user');
        $user->expects($this->any())->method('has_agreed_feedback')
            ->with($this->get_coursework())
            ->will($this->returnValue(false));

        $user->expects($this->any())->method('has_all_initial_feedbacks')
            ->with($this->get_coursework())
            ->will($this->returnValue(false));

        $object = new percentage_distance($this->get_coursework(), $user, 10);

        $object->create_auto_grade_if_rules_match($user);

        $this->assertEquals(0, $DB->count_records('coursework_feedbacks'));
    }

    public function test_that_a_new_record_is_created_when_all_initial_feedbacks_are_close_enough() {
        global $DB;

        $user = $this->createMock('\mod_coursework\models\user');
        $user->expects($this->any())->method('has_agreed_feedback')
            ->with($this->get_coursework())
            ->will($this->returnValue(false));

        $user->expects($this->any())->method('has_all_initial_feedbacks')
            ->with($this->get_coursework())
            ->will($this->returnValue(true));

        $feedback_one = $this->createMock('\mod_coursework\models\feedback');
        $feedback_one->expects($this->any())->method('get_grade')->will($this->returnValue(50));

        $feedback_two = $this->createMock('\mod_coursework\models\feedback');
        $feedback_two->expects($this->any())->method('get_grade')->will($this->returnValue(55));

        $user->expects($this->any())->method('get_initial_feedbacks')
            ->with($this->get_coursework())
            ->will($this->returnValue(array($feedback_one, $feedback_two)));

        $submission = $this->createMock('\mod_coursework\models\submission');
        $submission->expects($this->any())->method('id')->will($this->returnValue(234234));

        $user->expects($this->any())->method('get_submission')->will($this->returnValue($submission));

        $object = new percentage_distance($this->get_coursework(), $user, 10);
        $object->create_auto_grade_if_rules_match($user);

        $this->assertEquals(1, $DB->count_records('coursework_feedbacks'));
    }

    public function test_that_a_new_record_is_not_created_when_all_initial_feedbacks_are_far_apart() {
        global $DB;

        $user = $this->createMock('\mod_coursework\models\user');
        $user->expects($this->any())->method('has_agreed_feedback')
            ->with($this->get_coursework())
            ->will($this->returnValue(false));

        $user->expects($this->any())->method('has_all_initial_feedbacks')
            ->with($this->get_coursework())
            ->will($this->returnValue(true));

        $feedback_one = $this->createMock('\mod_coursework\models\feedback');
        $feedback_one->expects($this->any())->method('get_grade')->will($this->returnValue(50));

        $feedback_two = $this->createMock('\mod_coursework\models\feedback');
        $feedback_two->expects($this->any())->method('get_grade')->will($this->returnValue(55));

        $user->expects($this->any())->method('get_initial_feedbacks')
            ->with($this->get_coursework())
            ->will($this->returnValue(array($feedback_one,
                                            $feedback_two)));

        $submission = $this->createMock('\mod_coursework\models\submission');
        $submission->expects($this->any())->method('id')->will($this->returnValue(234234));

        $user->expects($this->any())->method('get_submission')->will($this->returnValue($submission));

        $object = new percentage_distance($this->get_coursework(), $user, 10);

        $object->create_auto_grade_if_rules_match($user);

        $created_feedback = $DB->get_record('coursework_feedbacks', array());

        $this->assertEquals($created_feedback->grade, 55); // Right grade
        $this->assertEquals($created_feedback->submissionid, 234234); // Right submission
        $this->assertEquals($created_feedback->stage_identifier, 'final_agreed_1'); // Right stage
    }

}

