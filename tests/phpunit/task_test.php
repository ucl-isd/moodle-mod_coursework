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
 * Unit tests for mod/coursework/classes/task/enrol_task and unenrol_task.
 *
 * @package    mod_coursework
 * @copyright  2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Leon Stringer <leon.stringer@ucl.ac.uk>
 */

namespace mod_coursework;

use mod_coursework\task\enrol_task;
use mod_coursework\task\mail_task;
use mod_coursework\task\reminders_task;
use mod_coursework\task\unenrol_task;

/**
 * Unit tests for mod/coursework/lib.php.
 *
 * @copyright  2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class task_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // This is required because the email redirection and capture fails under postgres due to transactions.
        $this->preventResetByRollback();
    }

    /**
     * Add a single record for the enrol task to process.
     */
    public function test_enrol_tasks(): void {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $coursework = $this->getDataGenerator()->create_module('coursework', ['course' => $course->id]);

        // Enrol teacher and check there is a processenrol record.
        $teacher  = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->assertCount(1, $DB->get_records('coursework', ['processenrol' => 1]));

        // Run task and check there are no processenrol records.
        $task = new enrol_task();
        $task->execute();
        $this->assertCount(0, $DB->get_records('coursework', ['processenrol' => 1]));
    }

    /**
     * Add a single record for the unenrol task to process.
     */
    public function test_unenrol_tasks(): void {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $teacher  = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $coursework = $this->getDataGenerator()->create_module('coursework', ['course' => $course->id]);

        // Unenrol teacher and check there is a processunenrol record.
        $enrol = enrol_get_plugin('manual');
        $manualenrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        $enrol->unenrol_user($manualenrol, $teacher->id);
        $this->assertCount(1, $DB->get_records('coursework', ['processunenrol' => 1]));

        // Run task and check there are no processunenrol records.
        $task = new unenrol_task();
        $task->execute();
        $this->assertCount(0, $DB->get_records('coursework', ['processunenrol' => 1]));
    }

    public function test_reminders_task_sends_initial_marking_reminders_for_markers_with_unmarked_submissions(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $coursework = $this->getDataGenerator()->create_module('coursework', [
            'course' => $course->id,
            'allocationenabled' => 0,
            'initialmarkingdeadline' => time() + 12 * HOURSECS,
        ]);

        // Create and enrol 3 teachers and 4 students.
        $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student3 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student4 = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create a submission for 2 of the students.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $generator->create_submission([
            'courseworkid' => $coursework->id,
            'allocatableid' => $student->id,
            'allocatabletype' => 'user',
            'finalisedstatus' => 1,
        ], $coursework);
        $generator->create_submission([
            'courseworkid' => $coursework->id,
            'allocatableid' => $student4->id,
            'allocatabletype' => 'user',
            'finalisedstatus' => 1,
        ], $coursework);

        $sink = $this->redirectEmails();
        $task = new reminders_task();
        $task->execute();
        $this->runAdhocTasks(mail_task::class);

        // Confirm that 3 emails were sent (for the 3 teachers).
        $messages = $sink->get_messages();
        $this->assertCount(3, $messages);

        $subjects = array_map(fn($message) => $message->subject, $messages);
        $this->assertContains('Outstanding Moodle Marking: Action Required', $subjects);

        // Confirm that each teacher received the email about 2 submissions needing marking today.
        foreach ($messages as $message) {
            $body = $this->normalise_body($message->body);
            $this->assertStringContainsString('Initial marking due today', $body);
            $this->assertStringContainsString('2 submissions awaiting marking and agreement', $body);
            $this->assertStringNotContainsString('Agreed marking due', $body);
        }
    }

    public function test_reminders_task_ignores_courseworks_outside_the_reminder_window(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $courseworkinwindow = $this->getDataGenerator()->create_module('coursework', [
            'course' => $course->id,
            'allocationenabled' => 0,
            'initialmarkingdeadline' => time() + 12 * HOURSECS,
        ]);
        $courseworkoutsidewindow = $this->getDataGenerator()->create_module('coursework', [
            'course' => $course->id,
            'allocationenabled' => 0,
            'initialmarkingdeadline' => time() + 7 * DAYSECS,
        ]);

        // Add 2 teachers and 1 student.
        $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Create submissions for both activities.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $generator->create_submission([
            'courseworkid' => $courseworkinwindow->id,
            'allocatableid' => $student->id,
            'allocatabletype' => 'user',
            'finalisedstatus' => 1,
        ], $courseworkinwindow);
        $generator->create_submission([
            'courseworkid' => $courseworkoutsidewindow->id,
            'allocatableid' => $student->id,
            'allocatabletype' => 'user',
            'finalisedstatus' => 1,
        ], $courseworkoutsidewindow);

        $sink = $this->redirectEmails();
        $task = new reminders_task();
        $task->execute();
        $this->runAdhocTasks(mail_task::class);

        // Confirm that only 2 messages are received (1 per teacher) and they only mention the in-window coursework.
        $messages = $sink->get_messages();
        $this->assertCount(2, $messages);
        foreach ($messages as $message) {
            $body = $this->normalise_body($message->body);
            $this->assertStringContainsString($courseworkinwindow->name, $body);
            $this->assertStringNotContainsString($courseworkoutsidewindow->name, $body);
        }
    }

    public function test_reminders_task_only_sends_to_allocated_markers_when_allocation_is_enabled(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $coursework = $this->getDataGenerator()->create_module('coursework', [
            'course' => $course->id,
            'allocationenabled' => 1,
            'initialmarkingdeadline' => time() + 12 * HOURSECS,
        ]);

        $markerassigned = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $markerunassigned = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $markerwithfeedback = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');

        $submission = $generator->create_submission([
            'courseworkid' => $coursework->id,
            'allocatableid' => $student->id,
            'allocatabletype' => 'user',
            'finalisedstatus' => 1,
        ], $coursework);

        $generator->create_allocation([
            'courseworkid' => $coursework->id,
            'allocatableid' => $student->id,
            'allocatabletype' => 'user',
            'assessorid' => $markerassigned->id,
            'stageidentifier' => 'assessor_1',
        ]);
        $generator->create_allocation([
            'courseworkid' => $coursework->id,
            'allocatableid' => $student->id,
            'allocatabletype' => 'user',
            'assessorid' => $markerwithfeedback->id,
            'stageidentifier' => 'assessor_2',
        ]);

        // Create mark given by just one of the teachers.
        $generator->create_feedback([
            'submissionid' => $submission->id,
            'assessorid' => $markerwithfeedback->id,
            'stageidentifier' => 'assessor_2',
            'grade' => 50,
            'finalised' => 1,
        ]);

        $sink = $this->redirectEmails();
        $task = new reminders_task();
        $task->execute();
        $this->runAdhocTasks(mail_task::class);

        // Confirm that only 1 of the teachers got an email and that it was the one without any feedback given.
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($markerassigned->email, $messages[0]->to);
    }

    public function test_reminders_task_sends_agreed_marking_reminders_for_markers_with_unmarked_agreed_submissions(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $coursework = $this->getDataGenerator()->create_module('coursework', [
            'course' => $course->id,
            'allocationenabled' => 0,
            'numberofmarkers' => 2,
            'agreedgrademarkingdeadline' => time() + 12 * HOURSECS,
        ]);

        $marker1 = $this->getDataGenerator()->create_and_enrol($course, 'manager');
        $marker2 = $this->getDataGenerator()->create_and_enrol($course, 'manager');
        $marker3 = $this->getDataGenerator()->create_and_enrol($course, 'manager');

        $students = [
            $this->getDataGenerator()->create_and_enrol($course, 'student'),
            $this->getDataGenerator()->create_and_enrol($course, 'student'),
            $this->getDataGenerator()->create_and_enrol($course, 'student'),
            $this->getDataGenerator()->create_and_enrol($course, 'student'),
        ];

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');

        foreach ($students as $key => $student) {
            if ($key < 3) {
                $submission = $generator->create_submission([
                    'courseworkid' => $coursework->id,
                    'allocatableid' => $student->id,
                    'allocatabletype' => 'user',
                    'finalisedstatus' => 1,
                ], $coursework);

                $generator->create_feedback([
                    'submissionid' => $submission->id,
                    'assessorid' => $marker1->id,
                    'stageidentifier' => 'assessor_1',
                    'grade' => 50,
                    'finalised' => 1,
                ]);
                $generator->create_feedback([
                    'submissionid' => $submission->id,
                    'assessorid' => $marker2->id,
                    'stageidentifier' => 'assessor_2',
                    'grade' => 55,
                    'finalised' => 1,
                ]);
            }
        }

        $sink = $this->redirectEmails();
        $task = new reminders_task();
        $task->execute();
        $this->runAdhocTasks(mail_task::class);

        $messages = $sink->get_messages();

        // One summary email per marker.
        $this->assertCount(3, $messages);
        foreach ($messages as $message) {
            $body = $this->normalise_body($message->body);
            $this->assertStringContainsString('Agreed marking due today', $body);
            $this->assertStringContainsString('3 submissions awaiting agreed final marking', $body);
            $this->assertStringNotContainsString('Agreed marking due within the next', $body);
        }
    }

    public function test_reminders_task_ignores_agreed_marking_courseworks_outside_the_reminder_window(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $courseworkinwindow = $this->getDataGenerator()->create_module('coursework', [
            'course' => $course->id,
            'allocationenabled' => 0,
            'numberofmarkers' => 2,
            'agreedgrademarkingdeadline' => time() + 12 * HOURSECS,
        ]);
        $courseworkoutsidewindow = $this->getDataGenerator()->create_module('coursework', [
            'course' => $course->id,
            'allocationenabled' => 0,
            'numberofmarkers' => 2,
            'agreedgrademarkingdeadline' => time() + 7 * DAYSECS,
        ]);

        $marker = $this->getDataGenerator()->create_and_enrol($course, 'manager');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');

        $inwindowsubmission = $generator->create_submission([
            'courseworkid' => $courseworkinwindow->id,
            'allocatableid' => $student->id,
            'allocatabletype' => 'user',
            'finalisedstatus' => 1,
        ], $courseworkinwindow);

        $generator->create_feedback([
            'submissionid' => $inwindowsubmission->id,
            'assessorid' => $marker->id,
            'stageidentifier' => 'assessor_1',
            'grade' => 50,
            'finalised' => 1,
        ]);

        $outsidewindowsubmission = $generator->create_submission([
            'courseworkid' => $courseworkoutsidewindow->id,
            'allocatableid' => $student->id,
            'allocatabletype' => 'user',
            'finalisedstatus' => 1,
        ], $courseworkoutsidewindow);

        $generator->create_feedback([
            'submissionid' => $outsidewindowsubmission->id,
            'assessorid' => $marker->id,
            'stageidentifier' => 'assessor_1',
            'grade' => 60,
            'finalised' => 1,
        ]);

        $sink = $this->redirectEmails();
        $task = new reminders_task();
        $task->execute();
        $this->runAdhocTasks(mail_task::class);

        $messages = $sink->get_messages();

        $this->assertCount(1, $messages);
        $body = $this->normalise_body($messages[0]->body);
        $this->assertStringContainsString('1 submissions awaiting agreed final marking', $body);
        $this->assertStringContainsString($courseworkinwindow->name, $body);
        $this->assertStringNotContainsString($courseworkoutsidewindow->name, $body);
    }

    public function test_reminders_task_only_sends_to_allocated_markers_for_agreed_marking_when_allocation_is_enabled(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $coursework = $this->getDataGenerator()->create_module('coursework', [
            'course' => $course->id,
            'allocationenabled' => 1,
            'numberofmarkers' => 2,
            'agreedgrademarkingdeadline' => time() + 12 * HOURSECS,
        ]);

        $markerassigned = $this->getDataGenerator()->create_and_enrol($course, 'manager');
        $markerunassigned = $this->getDataGenerator()->create_and_enrol($course, 'manager');
        $markerwithfeedback = $this->getDataGenerator()->create_and_enrol($course, 'manager');

        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $submission = $generator->create_submission([
            'courseworkid' => $coursework->id,
            'allocatableid' => $student->id,
            'allocatabletype' => 'user',
            'finalisedstatus' => 1,
        ], $coursework);

        $generator->create_allocation([
            'courseworkid' => $coursework->id,
            'allocatableid' => $student->id,
            'allocatabletype' => 'user',
            'assessorid' => $markerassigned->id,
            'stageidentifier' => 'assessor_1',
        ]);
        $generator->create_allocation([
            'courseworkid' => $coursework->id,
            'allocatableid' => $student->id,
            'allocatabletype' => 'user',
            'assessorid' => $markerwithfeedback->id,
            'stageidentifier' => 'assessor_2',
        ]);

        $generator->create_feedback([
            'submissionid' => $submission->id,
            'assessorid' => $markerassigned->id,
            'stageidentifier' => 'assessor_1',
            'grade' => 50,
            'finalised' => 1,
        ]);
        $generator->create_feedback([
            'submissionid' => $submission->id,
            'assessorid' => $markerwithfeedback->id,
            'stageidentifier' => 'assessor_2',
            'grade' => 55,
            'finalised' => 1,
        ]);

        $sink = $this->redirectEmails();
        $task = new reminders_task();
        $task->execute();
        $this->runAdhocTasks(mail_task::class);

        $messages = $sink->get_messages();

        $this->assertCount(2, $messages);
        $this->assertEqualsCanonicalizing(
            [$markerassigned->email, $markerwithfeedback->email],
            array_map(fn($message) => $message->to, $messages)
        );
    }

    public function test_reminders_task_sends_agreed_marking_reminders_for_markers_with_unmarked_agreed_submissions_with_threshold(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $coursework = $this->getDataGenerator()->create_module('coursework', [
            'course' => $course->id,
            'allocationenabled' => 0,
            'numberofmarkers' => 2,
            'agreedgrademarkingdeadline' => time() + (DAYSECS * 3),
        ]);

        $marker1 = $this->getDataGenerator()->create_and_enrol($course, 'manager');
        $marker2 = $this->getDataGenerator()->create_and_enrol($course, 'manager');

        $students = [
            $this->getDataGenerator()->create_and_enrol($course, 'student'),
            $this->getDataGenerator()->create_and_enrol($course, 'student'),
        ];

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');

        foreach ($students as $student) {
            $submission = $generator->create_submission([
                'courseworkid' => $coursework->id,
                'allocatableid' => $student->id,
                'allocatabletype' => 'user',
                'finalisedstatus' => 1,
            ], $coursework);

            $generator->create_feedback([
                'submissionid' => $submission->id,
                'assessorid' => $marker1->id,
                'stageidentifier' => 'assessor_1',
                'grade' => 50,
                'finalised' => 1,
            ]);
            $generator->create_feedback([
                'submissionid' => $submission->id,
                'assessorid' => $marker2->id,
                'stageidentifier' => 'assessor_2',
                'grade' => 55,
                'finalised' => 1,
            ]);
        }

        $sink = $this->redirectEmails();
        $task = new reminders_task();
        $task->execute();
        $this->runAdhocTasks(mail_task::class);

        $messages = $sink->get_messages();
        $this->assertCount(2, $messages);
        foreach ($messages as $message) {
            $body = $this->normalise_body($message->body);
            $this->assertStringContainsString('Agreed marking due within the next 5 days', $body);
            $this->assertStringContainsString('2 submissions awaiting agreed final marking', $body);
            $this->assertStringNotContainsString('Agreed marking due today', $body);
        }
    }

    public function test_reminders_task_sends_single_summary_email_covering_all_reminder_types(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $initialtoday = $this->getDataGenerator()->create_module('coursework', [
            'course' => $course->id,
            'name' => 'Initial today coursework',
            'allocationenabled' => 0,
            'initialmarkingdeadline' => time() + 12 * HOURSECS,
        ]);
        $initialupcoming = $this->getDataGenerator()->create_module('coursework', [
            'course' => $course->id,
            'name' => 'Initial upcoming coursework',
            'allocationenabled' => 0,
            'initialmarkingdeadline' => time() + 3 * DAYSECS,
        ]);
        $agreedtoday = $this->getDataGenerator()->create_module('coursework', [
            'course' => $course->id,
            'name' => 'Agreed today coursework',
            'allocationenabled' => 0,
            'numberofmarkers' => 2,
            'agreedgrademarkingdeadline' => time() + 12 * HOURSECS,
        ]);
        $agreedupcoming = $this->getDataGenerator()->create_module('coursework', [
            'course' => $course->id,
            'name' => 'Agreed upcoming coursework',
            'allocationenabled' => 0,
            'numberofmarkers' => 2,
            'agreedgrademarkingdeadline' => time() + 4 * DAYSECS,
        ]);

        // Managers have both the initial and agreed marking capabilities.
        $marker = $this->getDataGenerator()->create_and_enrol($course, 'manager');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        foreach ([$initialtoday, $initialupcoming, $agreedtoday, $agreedupcoming] as $coursework) {
            $generator->create_submission([
                'courseworkid' => $coursework->id,
                'allocatableid' => $student->id,
                'allocatabletype' => 'user',
                'finalisedstatus' => 1,
            ], $coursework);
        }

        $sink = $this->redirectEmails();
        $task = new reminders_task();
        $task->execute();
        $this->runAdhocTasks(mail_task::class);

        // Only one email, containing all four sections.
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($marker->email, $messages[0]->to);
        $this->assertEquals('Outstanding Moodle Marking: Action Required', $messages[0]->subject);

        $body = $this->normalise_body($messages[0]->body);
        $sections = [
            'Initial marking due today' => $initialtoday->name,
            'Initial marking due within the next 5 days' => $initialupcoming->name,
            'Agreed marking due today' => $agreedtoday->name,
            'Agreed marking due within the next 5 days' => $agreedupcoming->name,
        ];
        // Each section heading should appear in order, followed by its coursework.
        $offset = 0;
        foreach ($sections as $heading => $name) {
            $headingpos = strpos($body, $heading, $offset);
            $this->assertNotFalse($headingpos, "Missing or out of order section '{$heading}'");
            $namepos = strpos($body, $name, $headingpos);
            $this->assertNotFalse($namepos, "Missing coursework '{$name}' under '{$heading}'");
            $offset = $namepos;
        }
    }

    /**
     * Collapse an email body down to a single line, so line wrapping and quoted-printable encoding
     * don't break string assertions.
     * @param string $body
     * @return string
     */
    private function normalise_body(string $body): string {
        $body = quoted_printable_decode($body);
        return preg_replace('/\s+/', ' ', $body);
    }
}
