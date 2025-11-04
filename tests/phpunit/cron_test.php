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

use mod_coursework\models\submission;

/**
 * Class cron_test
 * @group mod_coursework
 */
final class cron_test extends \advanced_testcase {

    use test_helpers\factory_mixin;

    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->preventResetByRollback();
        $this->redirectMessages();
        // If we don't do this, we end up with the same cached objects for all tests and they may have incorrect/missing properties.
        \mod_coursework\models\coursework::$pool = null;
    }

    public function test_cron_auto_finalises_after_deadline(): void {
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
        $this->assertEquals(submission::FINALISED_STATUS_FINALISED, $submission->finalisedstatus);
    }

    public function test_cron_does_not_auto_finalise_before_deadline(): void {
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
        $this->assertEquals(submission::FINALISED_STATUS_NOT_FINALISED, $submission->finalisedstatus);
    }

    public function test_admins_and_graders(): void {
        $this->create_a_course();
        $this->create_a_coursework();
        $teacher = $this->create_a_teacher();
        $this->enrol_as_manager($teacher);
        $cronclass = new cron();
        $this->assertEquals([$teacher], $cronclass->get_admins_and_teachers($this->coursework->get_context()));
    }

    public function test_auto_finalising_does_not_alter_time_submitted(): void {
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
}
