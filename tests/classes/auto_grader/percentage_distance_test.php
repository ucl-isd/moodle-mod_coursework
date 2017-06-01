<?php

namespace mod_coursework\auto_grader;

/**
 * Class percentage_distance_test is responsible for testing the behaviour of the percentage_distance class.
 *
 * @package mod_coursework\auto_grader
 */
class percentage_distance_test extends \advanced_testcase {

    use \mod_coursework\test_helpers\factory_mixin;

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

