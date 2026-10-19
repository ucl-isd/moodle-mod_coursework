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
 * Unit tests for mod/coursework/classes/task/process_auto_allocations_task.
 *
 * @package    mod_coursework
 * @copyright  2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Leon Stringer <leon.stringer@ucl.ac.uk>
 */

namespace mod_coursework;

use core\task\manager;
use mod_coursework\task\process_auto_allocations_task;
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
    public function test_process_auto_allocations_tasks(): void {
        global $DB;

        $this->setAdminUser();
        set_config('process_auto_allocations_sync', false, 'mod_coursework');

        $course = $this->getDataGenerator()->create_course();

        $this->assertEmpty(manager::get_adhoc_tasks(process_auto_allocations_task::class));

        $this->getDataGenerator()->create_module('coursework', ['course' => $course->id]);
        $this->assertCount(1, manager::get_adhoc_tasks(process_auto_allocations_task::class));

        $DB->delete_records('task_adhoc');

        $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->assertCount(1, manager::get_adhoc_tasks(process_auto_allocations_task::class));

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->assertCount(1, manager::get_adhoc_tasks(process_auto_allocations_task::class));

        $this->runAdhocTasks('\mod_coursework\task\process_auto_allocations_task');
        $this->assertEmpty(manager::get_adhoc_tasks(process_auto_allocations_task::class));

        $enrol = enrol_get_plugin('manual');
        $manualenrol = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        $enrol->unenrol_user($manualenrol, $teacher->id);
        $this->assertCount(1, manager::get_adhoc_tasks(process_auto_allocations_task::class));
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

        // Confirm that each teacher received the email about 2 submissions needing marking.
        foreach ($messages as $message) {
            $this->assertStringContainsString('2 submissions awaiting marking and agreement', $message->body);
        }
    }

    public function test_reminders_task_ignores_courseworks_outside_the_24_hour_window(): void {
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
            'initialmarkingdeadline' => time() + 3 * DAYSECS,
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

        // Confirm that only 2 messages are received, not 4.
        $messages = $sink->get_messages();
        $this->assertCount(2, $messages);
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

        // The reason this is 6 and not 3, is that the reminders task has 2 methods for agreed marking. One for
        // due "today" and one for "within 5 days", so these will appear in both.
        $this->assertCount(6, $messages);
        foreach ($messages as $num => $message) {
            // The first 3 will be for the "within 5 days", the next 3 will be for "Today".
            if ($num > 2) {
                $this->assertStringContainsString('due for agreed marking Today', $message->body);
                $this->assertStringContainsString('3 submissions awaiting agreed final marking', $message->body);
            }
        }
    }

    public function test_reminders_task_ignores_agreed_marking_courseworks_outside_the_24_hour_window(): void {
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

        // Same reason here why 2 not 1.
        $this->assertCount(2, $messages);
        $this->assertStringContainsString('1 submissions awaiting agreed final marking', $messages[0]->body);
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

        // Same reason as in previous test for this being 4 not 2.
        $this->assertCount(4, $messages);
        $this->assertEquals($markerassigned->email, $messages[0]->to);
        $this->assertEquals($markerwithfeedback->email, $messages[1]->to);
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
            $this->assertStringContainsString('due for agreed marking within the next 5 days', $message->body);
            $this->assertStringContainsString('2 submissions awaiting agreed final marking', $message->body);
        }
    }
}
