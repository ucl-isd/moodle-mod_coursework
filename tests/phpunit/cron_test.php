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

/**
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\exception\moodle_exception;
use mod_coursework\models\submission;

/**
 * Class cron_test
 * @group mod_coursework
 */
final class cron_test extends \advanced_testcase {
    use test_helpers\factory_mixin;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->preventResetByRollback();
    }

    public function test_cron_auto_finalises_after_deadline(): void {
        $this->redirectMessages();

        // Given there is a student.
        $this->create_a_course();
        $student = $this->create_a_student();

        // And the submission deadline has passed.
        $coursework = $this->create_a_coursework();
        $coursework->update_attribute('deadline', strtotime('1 week ago'));

        // And the student has a submission.
        $submissionparams = [
            'allocatableid' => $student->id,
            'allocatabletype' => 'user',
            'courseworkid' => $coursework->id,
        ];
        $submission = submission::create($submissionparams);

        // When the cron runs.
        \mod_coursework\cron::run();

        // Then the submission should be finalised.
        $submission->reload();
        $this->assertTrue($submission->is_finalised());
    }

    public function test_cron_does_not_auto_finalise_before_deadline(): void {
        $this->redirectMessages();

        // Given there is a student.
        $this->create_a_course();
        $student = $this->create_a_student();

        // And the submission deadline has passed.
        $coursework = $this->create_a_coursework();

        // And the student has a submission.
        $submissionparams = [
            'allocatableid' => $student->id,
            'allocatabletype' => 'user',
            'courseworkid' => $coursework->id,
        ];
        $submission = submission::create($submissionparams);

        // When the cron runs.
        \mod_coursework\cron::run();

        // Then the submission should be finalised.
        $submission->reload();
        $this->assertFalse($submission->is_finalised());
    }

    public function test_admins_and_graders(): void {
        $this->redirectMessages();
        $this->create_a_course();
        $this->create_a_coursework();
        $teacher = $this->create_a_teacher();
        $this->enrol_as_manager($teacher);
        $cronclass = new cron();
        $this->assertEquals([$teacher], $cronclass->get_admins_and_teachers($this->coursework->get_context()));
    }

    public function test_auto_finalising_does_not_alter_time_submitted(): void {
        $this->redirectMessages();
        $this->create_a_course();
        $coursework = $this->create_a_coursework();
        $this->create_a_student();
        $submission = $this->create_a_submission_for_the_student();
        $submission->update_attribute('finalisedstatus', submission::FINALISED_STATUS_NOT_FINALISED);
        $coursework->update_attribute('deadline', strtotime('-1 week'));
        $submission->update_attribute('timesubmitted', 5555);

        \mod_coursework\cron::run();

        $this->assertEquals(5555, $submission->reload()->timesubmitted);
    }

    public function test_auto_releasing_does_not_alter_time_submitted(): void {
        $this->redirectMessages();
        $this->create_a_course();
        $coursework = $this->create_a_coursework();
        $this->create_a_student();
        $submission = $this->create_a_submission_for_the_student();
        $submission->update_attribute('finalisedstatus', submission::FINALISED_STATUS_FINALISED);
        $coursework->update_attribute('deadline', strtotime('-1 week'));
        $coursework->update_attribute('individualfeedback', strtotime('-1 week'));
        $submission->update_attribute('timesubmitted', 5555);

        \mod_coursework\cron::run();

        $this->assertEquals(5555, $submission->reload()->timesubmitted);
    }

    public function test_auto_releasing_does_not_happen_before_deadline(): void {
        $this->redirectMessages();
        $this->create_a_course();
        $coursework = $this->create_a_coursework();
        $this->create_a_student();
        $submission = $this->create_a_submission_for_the_student();
        $submission->update_attribute('finalisedstatus', submission::FINALISED_STATUS_FINALISED);
        $coursework->update_attribute('individualfeedback', strtotime('+1 week'));

        \mod_coursework\cron::run();

        $this->assertEmpty($submission->reload()->firstpublished);
    }

    public function test_auto_releasing_happens_after_deadline(): void {
        $this->redirectMessages();
        $this->create_a_course();
        $coursework = $this->create_a_coursework();
        $this->create_a_student();
        $submission = $this->create_a_submission_for_the_student();
        $submission->update_attribute('finalisedstatus', submission::FINALISED_STATUS_FINALISED);
        $this->create_a_final_feedback_for_the_submission();
        $coursework->update_attribute('individualfeedback', strtotime('-1 week'));

        \mod_coursework\cron::run();
        $submission = $submission->reload();
        $this->assertNotEmpty($submission->firstpublished);
    }

    /**
     * Test submission receipt notifications from adhoc tasks.
     */
    public function test_send_submission_receipt_notifications_to_students(): void {
        $this->create_a_course();
        $this->create_a_student();
        $coursework = $this->create_a_coursework();
        // Set the deadline so it's already passed.
        $coursework->update_attribute('deadline', strtotime('-1 days'));
        // Create a non-finalised submission.
        $submission = $this->create_a_submission_for_the_student();
        $submission->update_attribute('finalisedstatus', submission::FINALISED_STATUS_NOT_FINALISED);
        // Now run the cron and redirect emails.
        $sink = $this->redirectEmails();
        \mod_coursework\cron::run();
        $this->runAdhocTasks(\mod_coursework\task\mail_task::class);
        $messages = $sink->get_messages();
        $this->assertEquals(1, count($messages));
        $message = reset($messages);
        $this->assertStringContainsString(
            "Submission Receipt",
            $message->subject
        );
    }

    /**
     * Test sending feedback notifications from adhoc tasks.
     */
    public function test_send_feedback_notifications_to_students(): void {
        $this->create_a_course();
        $this->create_a_student();
        $this->create_a_coursework();
        $submission = $this->create_a_submission_for_the_student();
        $this->create_a_final_feedback_for_the_submission();
        $submission->publish();
        // Now run the cron and redirect emails.
        $sink = $this->redirectEmails();
        \mod_coursework\cron::run();
        $this->runAdhocTasks(\mod_coursework\task\mail_task::class);
        $messages = $sink->get_messages();
        $this->assertEquals(1, count($messages));
        $message = reset($messages);
        $this->assertStringContainsString(
            "Coursework feedback released",
            $message->subject
        );
    }

    /**
     * Test sending deadline reminder notifications from adhoc tasks.
     */
    public function test_send_deadline_reminder_notifications_to_students(): void {
        $this->create_a_course();
        $this->create_a_student();
        $coursework = $this->create_a_coursework();
        $coursework->update_attribute('deadline', strtotime('+1 hours'));
        // Now run the cron and redirect emails.
        $sink = $this->redirectEmails();
        \mod_coursework\cron::run();
        $this->runAdhocTasks(\mod_coursework\task\mail_task::class);
        $messages = $sink->get_messages();
        $this->assertEquals(1, count($messages));
        $message = reset($messages);
        $this->assertStringContainsString(
            "Reminder: your assignment for {$coursework->name} is due",
            $message->subject
        );
    }

    /**
     * Test sending submission notifications from adhoc tasks.
     */
    public function test_send_submission_notifications_to_students(): void {
        $this->create_a_course();
        $student = $this->create_a_student();
        $manager = $this->create_a_teacher();
        $this->enrol_as_manager($manager);

        $coursework = $this->create_a_coursework([
            'deadline' => 0,
        ]);
        $coursework->update_attribute('submissionnotification', $manager->id);

        $draftitemid = file_get_unused_draft_itemid();
        $fs = get_file_storage();
        $usercontext = \context_user::instance($student->id);
        $fs->create_file_from_string([
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'submission.txt',
        ], 'Test submission content');

        $this->setUser($student->get_raw_record());
        \mod_coursework\forms\student_submission_form::mock_submit([
            'courseworkid' => $coursework->id,
            'userid' => $student->id,
            'submissionid' => 0,
            'allocatableid' => $student->id,
            'allocatabletype' => 'user',
            'submission_manager' => $draftitemid,
            'finalisebutton' => 1,
        ], []);

        $controller = new \mod_coursework\controllers\submissions_controller([
            'courseworkid' => $coursework->id,
            'finalised' => 1,
            'allocatableid' => $student->id,
            'allocatabletype' => 'user',
        ]);

        $exception = false;

        try {
            $controller->create_submission();
            $this->fail('Expected the submission redirect exception.');
        } catch (moodle_exception $e) {
            // The redirect is expected after the submission is created.
            $exception = true;
        }

        $sink = $this->redirectEmails();
        $this->runAdhocTasks(\mod_coursework\task\mail_task::class);
        $messages = $sink->get_messages();
        $this->assertCount(2, $messages);
        $subjects = array_map(static fn($message) => $message->subject, $messages);
        $this->assertNotEmpty(array_filter($subjects, static fn($subject) => str_contains($subject, 'Submission Receipt')));
        $this->assertNotEmpty(array_filter($subjects, static fn($subject) => str_contains($subject, 'A submission has been made in')));
    }
}
