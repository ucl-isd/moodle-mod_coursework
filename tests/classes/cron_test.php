<?php
use mod_coursework\models\submission;
use mod_coursework\cron;

/**
 * Class cron_test
 * @group mod_coursework
 */
class cron_test extends advanced_testcase {

    use mod_coursework\test_helpers\factory_mixin;

    public function setUp() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->preventResetByRollback();
        $this->redirectMessages();
    }

    public function test_cron_auto_finalises_after_deadline() {
        // Given there is a student
        $this->create_a_course();
        $student = $this->create_a_student();

        // And the submission deadline has passed
        $coursework = $this->create_a_coursework();
        $coursework->update_attribute('deadline', strtotime('1 week ago'));

        // And the student has a submission
        $submission_params = array(
            'allocatableid' => $student->id,
            'allocatabletype' => 'user',
            'courseworkid' => $coursework->id,
        );
        $submission = submission::create($submission_params);

        // When the cron runs
        \mod_coursework\cron::run();

        // Then the submission should be finalised
        $submission->reload();
        $this->assertEquals(1, $submission->finalised);
    }

    public function test_cron_does_not_auto_finalise_before_deadline() {
        // Given there is a student
        $this->create_a_course();
        $student = $this->create_a_student();

        // And the submission deadline has passed
        $coursework = $this->create_a_coursework();

        // And the student has a submission
        $submission_params = array(
            'allocatableid' => $student->id,
            'allocatabletype' => 'user',
            'courseworkid' => $coursework->id,
        );
        $submission = submission::create($submission_params);

        // When the cron runs
        \mod_coursework\cron::run();

        // Then the submission should be finalised
        $submission->reload();
        $this->assertEquals(0, $submission->finalised);
    }

    public function test_admins_and_graders() {
        $this->create_a_course();
        $this->create_a_coursework();
        $teacher = $this->create_a_teacher();
        $this->enrol_as_manager($teacher);
        $cron_class = new cron();
        $this->assertEquals(array($teacher), $cron_class->get_admins_and_teachers($this->coursework->get_context()));
    }


    public function test_auto_finalising_does_not_alter_time_submitted() {
        $this->create_a_course();
        $coursework = $this->create_a_coursework();
        $this->create_a_student();
        $submission = $this->create_a_submission_for_the_student();
        $submission->update_attribute('finalised', 0);
        $coursework->update_attribute('deadline', strtotime('-1 week'));
        $submission->update_attribute('timesubmitted', 5555);

        \mod_coursework\cron::run();

        $this->assertEquals($submission->reload()->timesubmitted, 5555);
    }

    public function test_auto_releasing_does_not_alter_time_submitted() {
        $this->create_a_course();
        $coursework = $this->create_a_coursework();
        $this->create_a_student();
        $submission = $this->create_a_submission_for_the_student();
        $submission->update_attribute('finalised', 1);
        $coursework->update_attribute('deadline', strtotime('-1 week'));
        $coursework->update_attribute('individualfeedback', strtotime('-1 week'));
        $submission->update_attribute('timesubmitted', 5555);

        \mod_coursework\cron::run();

        $this->assertEquals($submission->reload()->timesubmitted, 5555);
    }

    public function test_auto_releasing_does_not_happen_before_deadline() {
        $this->create_a_course();
        $coursework = $this->create_a_coursework();
        $this->create_a_student();
        $submission = $this->create_a_submission_for_the_student();
        $submission->update_attribute('finalised', 1);
        $coursework->update_attribute('individualfeedback', strtotime('+1 week'));

        \mod_coursework\cron::run();

        $this->assertEmpty($submission->reload()->firstpublished);
    }

    public function test_auto_releasing_happens_after_deadline() {
        $this->create_a_course();
        $coursework = $this->create_a_coursework();
        $this->create_a_student();
        $submission = $this->create_a_submission_for_the_student();
        $submission->update_attribute('finalised', 1);
        $this->create_a_final_feedback_for_the_submisison();
        $coursework->update_attribute('individualfeedback', strtotime('-1 week'));

        \mod_coursework\cron::run();

        $this->assertNotEmpty($submission->reload()->firstpublished);
    }



    /**
     * Was throwing an error when the allocatable could not be found.
     */
    public function test_cron_auto_releasing_when_the_user_is_not_there() {
        $this->create_a_course();
        $coursework = $this->create_a_coursework();
        $this->create_a_student();
        $submission = $this->create_a_submission_for_the_student();
        $submission->update_attribute('finalised', 1);
        $this->create_a_final_feedback_for_the_submisison();
        $coursework->update_attribute('individualfeedback', strtotime('-1 week'));

        $submission->update_attribute('allocatableid', 34523452345234);

        \mod_coursework\cron::run();

        $this->assertEmpty($submission->reload()->firstpublished);
    }
}