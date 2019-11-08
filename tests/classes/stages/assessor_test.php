<?php

/**
 * @group mod_coursework
 */
class assessor_test extends advanced_testcase {

    use \mod_coursework\test_helpers\factory_mixin;

    public function setUp() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->create_a_course();
        $this->create_a_coursework();
    }

    public function test_prerequisite_stages_is_ok_with_no_feedbacks() {
        $this->coursework->update_attribute('numberofmarkers', 2);
        $this->coursework->update_attribute('moderationenabled', 1);
        $this->coursework->update_attribute('moderatorallocationstrategy', 'none');

        $stages = $this->coursework->get_assessor_marking_stages();
        $first_stage = reset($stages);

        $student = $this->create_a_student();
        $this->create_a_submission_for_the_student();

        $this->assertTrue($first_stage->prerequisite_stages_have_feedback($student));

    }

    public function test_prerequisite_stages_is_ok_with_one_assessor_feedback() {
        $this->coursework->update_attribute('numberofmarkers', 2);
        $this->coursework->update_attribute('moderationenabled', 1);
        $this->coursework->update_attribute('moderatorallocationstrategy', 'none');

        $stages = $this->coursework->get_assessor_marking_stages();
        array_shift($stages);
        $second_stage = reset($stages);
        $this->assertEquals('assessor_2', $second_stage->identifier());

        $student = $this->create_a_student();
        $this->create_a_submission_for_the_student();
        $this->create_a_teacher();
        $this->create_an_assessor_feedback_for_the_submisison($this->teacher);

        $this->assertTrue($second_stage->prerequisite_stages_have_feedback($student));
    }

    public function test_type() {
        $stage = new \mod_coursework\stages\assessor($this->coursework, 'assessor_1');
        $this->assertEquals('assessor', $stage->type());
    }

}