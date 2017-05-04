<?php


use mod_coursework\grade_judge;

class grade_judge_test extends advanced_testcase {


    use \mod_coursework\test_helpers\factory_mixin;

    public function setUp() {
        $this->setAdminUser();
        $this->resetAfterTest();
    }


    public function test_get_feedbck_that_is_promoted_to_gradebook_returns_initial_feedback() {
        $coursework = $this->create_a_coursework();
        $grade_judge = new grade_judge($coursework);

        $coursework->update_attribute('samplingenabled', 1);

        $submission = $this->create_a_submission_for_the_student();
        $assessor = $this->create_a_teacher();
        $feedback = $this->create_an_assessor_feedback_for_the_submisison($assessor);

        $this->assertEquals($feedback->id, $grade_judge->get_feedback_that_is_promoted_to_gradebook($submission)->id);
    }


    public function test_sampling_disabled_one_marker() {
        $coursework = $this->create_a_coursework();
        $grade_judge = new grade_judge($coursework);

        $coursework->update_attribute('samplingenabled', 0);
        $coursework->update_attribute('numberofmarkers', 1);

        $submission = $this->create_a_submission_for_the_student();
        $assessor = $this->create_a_teacher();
        $feedback = $this->create_an_assessor_feedback_for_the_submisison($assessor);

        $this->assertEquals($feedback->id, $grade_judge->get_feedback_that_is_promoted_to_gradebook($submission)->id);
    }
}