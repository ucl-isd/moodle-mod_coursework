<?php

/**
 */
class final_agreed_test extends advanced_testcase {

    use \mod_coursework\test_helpers\factory_mixin;

    public function setUp() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->create_a_course();
        $this->create_a_coursework();
    }

    public function test_prerequisite_stages_is_false_with_no_feedbacks() {
        $this->coursework->update_attribute('numberofmarkers', 2);
        $this->coursework->update_attribute('moderationenabled', 1);
        $this->coursework->update_attribute('moderatorallocationstrategy', 'none');

        $stage = $this->coursework->get_final_agreed_marking_stage();

        $student = $this->create_a_student();
        $this->create_a_submission_for_the_student();

        $this->assertFalse($stage->prerequisite_stages_have_feedback($student));

    }

    public function test_prerequisite_stages_is_false_with_one_assessor_feedback() {
        $this->coursework->update_attribute('numberofmarkers', 2);
        $this->coursework->update_attribute('moderationenabled', 1);
        $this->coursework->update_attribute('moderatorallocationstrategy', 'none');

        $stage = $this->coursework->get_final_agreed_marking_stage();

        $student = $this->create_a_student();
        $this->create_a_submission_for_the_student();
        $this->create_a_teacher();
        $this->create_an_assessor_feedback_for_the_submisison($this->teacher);

        $this->assertFalse($stage->prerequisite_stages_have_feedback($student));
    }

    public function test_prerequisite_stages_is_true_with_two_assessor_feedbacks() {
        $this->coursework->update_attribute('numberofmarkers', 2);

        $stage = $this->coursework->get_final_agreed_marking_stage();

        $student = $this->create_a_student();
        $this->create_a_submission_for_the_student();
        $this->create_a_teacher();
        $this->create_another_teacher();
        $this->create_an_assessor_feedback_for_the_submisison($this->teacher);
        $this->create_an_assessor_feedback_for_the_submisison($this->other_teacher);

        // Need to student to be in the sample.

        $this->assertTrue($stage->prerequisite_stages_have_feedback($student));
    }

}