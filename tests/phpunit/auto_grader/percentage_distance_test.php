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
 * @copyright  2017 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework;

use mod_coursework\auto_grader\percentage_distance;

/**
 * Class percentage_distance_test is responsible for testing the behaviour of the percentage_distance class.
 *
 * @package mod_coursework\auto_grader
 * @group mod_coursework
 */
final class percentage_distance_test extends \advanced_testcase {
    use \mod_coursework\test_helpers\factory_mixin;

    public function setUp(): void {
        parent::setUp();
        $this->setAdminUser();
        $this->resetAfterTest();
    }

    public function test_nothing_happens_when_there_is_already_an_agreed_feedback(): void {
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

    public function test_nothing_happens_when_the_initial_feedbacks_are_not_there(): void {
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

    /**
     * Test there is no error after student submission with:
     *   - Number of times each submission should initially be marked: 2
     *   - Sampling enabled: Yes
     *   - Automatic agreement of marks: Percentage distance
     */
    public function test_no_error_with_auto_agreement_and_sampling(): void {
        $coursework = $this->get_coursework();
        $coursework->update_attribute('samplingenabled', 1);
        $user = $this->get_student();
        $this->create_a_submission_for_the_student();
        $object = new percentage_distance($this->get_coursework(), $user, 10);

        $object->create_auto_grade_if_rules_match($user);

        $this->assertTrue(true); // Check there are no errors above.
    }

    public function test_that_a_new_record_is_created_when_all_initial_feedbacks_are_close_enough(): void {
        global $DB;

        $user = $this->createMock('\mod_coursework\models\user');
        $user->expects($this->any())->method('has_agreed_feedback')
            ->with($this->get_coursework())
            ->will($this->returnValue(false));

        $user->expects($this->any())->method('has_all_initial_feedbacks')
            ->with($this->get_coursework())
            ->will($this->returnValue(true));

        $submission = $this->createMock('\mod_coursework\models\submission');
        $user->expects($this->any())->method('get_submission')
            ->with($this->anything())
            ->will($this->returnValue($submission));

        $feedbackone = $this->createMock('\mod_coursework\models\feedback');
        $feedbackone->expects($this->any())->method('get_grade')->will($this->returnValue(50));
        $feedbackone->expects($this->any())->method('get_submission')->will($this->returnValue($submission));

        $feedbacktwo = $this->createMock('\mod_coursework\models\feedback');
        $feedbacktwo->expects($this->any())->method('get_grade')->will($this->returnValue(55));
        $feedbacktwo->expects($this->any())->method('get_submission')->will($this->returnValue($submission));

        $user->expects($this->any())->method('get_initial_feedbacks')
            ->with($this->get_coursework())
            ->will($this->returnValue([$feedbackone, $feedbacktwo]));

        $user->expects($this->any())->method('get_submission')
            ->with($this->anything())
            ->will($this->returnValue($submission));

        $object = new percentage_distance($this->get_coursework(), $user);
        // Constructor percentage_distance no longer accepts percentage param since commit c1132f6, so set 10.
        $object->set_percentage(10);
        $object->create_auto_grade_if_rules_match();

        $this->assertEquals(1, $DB->count_records('coursework_feedbacks'));
    }

    public function test_that_a_new_record_is_not_created_when_all_initial_feedbacks_are_far_apart(): void {
        global $DB;
        $user = $this->createMock('\mod_coursework\models\user');
        $user->expects($this->any())->method('has_agreed_feedback')
            ->with($this->get_coursework())
            ->will($this->returnValue(false));

        $user->expects($this->any())->method('has_all_initial_feedbacks')
            ->with($this->get_coursework())
            ->will($this->returnValue(true));

        $submission = $this->createMock('\mod_coursework\models\submission');

        $feedbackone = $this->createMock('\mod_coursework\models\feedback');
        $feedbackone->expects($this->any())->method('get_grade')->will($this->returnValue(50));
        $feedbackone->expects($this->any())->method('get_submission')->will($this->returnValue($submission));

        $feedbacktwo = $this->createMock('\mod_coursework\models\feedback');
        $feedbacktwo->expects($this->any())->method('get_grade')->will($this->returnValue(55));
        $feedbacktwo->expects($this->any())->method('get_submission')->will($this->returnValue($submission));

        $user->expects($this->any())->method('get_initial_feedbacks')
            ->with($this->get_coursework())
            ->will($this->returnValue([$feedbackone, $feedbacktwo]));

        $user->expects($this->any())->method('get_submission')
            ->with($this->anything())
            ->will($this->returnValue($submission));

        $object = new percentage_distance($this->get_coursework(), $user);
        // Constructor percentage_distance no longer accepts percentage param since commit c1132f6, so set 10.
        $object->set_percentage(10);
        $object->create_auto_grade_if_rules_match();

        $createdfeedbacks = $DB->get_records('coursework_feedbacks', [], 'id DESC', '*', 0, 1);
        $createdfeedback = reset($createdfeedbacks);
        $this->assertEquals($createdfeedback->grade ?? null, 55); // Right grade.
        $this->assertEquals($createdfeedback->stageidentifier ?? null, 'final_agreed_1'); // Right stage.
    }
}
