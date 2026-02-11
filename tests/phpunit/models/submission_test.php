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
 * Unit tests for the coursework class
 *
 * @package    mod_coursework
 * @copyright  2012 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\models\coursework;
use mod_coursework\models\submission;

/**
 * Class that will make sure the allocation_manager works.
 * @group mod_coursework
 */
final class submission_test extends \advanced_testcase {
    use test_helpers\factory_mixin;

    /**
     * Makes us a blank coursework and allocation manager.
     */
    public function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();
        $this->coursework = $this->create_a_coursework(['grade' => 0]);
        $this->redirectMessages();
        $this->preventResetByRollback();
    }

    /**
     * Clean up the test fixture by removing the objects.
     */
    public function tearDown(): void {
        $coursework = coursework::get_from_id($this->coursework->id);
        $coursework->destroy();
        unset($this->coursework);
        parent::tearDown();
    }

    /**
     * Test that get_coursework() will get the coursework when asked to.
     */
    public function test_get_coursework(): void {
        $submission = new submission();
        $submission->courseworkid = $this->coursework->id;
        $student = $this->create_a_student();
        $submission->allocatableid = $student->id;
        $submission->allocatabletype = 'user';
        $submission->save();

        $this->assertEquals($this->coursework->id, $submission->get_coursework()->id);
    }

    /**
     * Make sure that the id field is created automatically.
     */
    public function test_save_id(): void {
        $submission = new submission();
        $submission->courseworkid = $this->coursework->id;
        $student = $this->create_a_student();
        $submission->allocatableid = $student->id;
        $submission->allocatabletype = 'user';
        $submission->save();

        $this->assertNotEmpty($submission->id);
    }

    /**
     * Make sure we can get the courseworkid to save.
     */
    public function test_save_courseworkid(): void {
        $submission = new submission();
        $submission->courseworkid = $this->coursework->id;
        $student = $this->create_a_student();
        $submission->allocatableid = $student->id;
        $submission->allocatabletype = 'user';

        $this->assertFalse($submission->persisted());
        $submission->save();
        $this->assertTrue($submission->persisted());

        $retrieved = submission::get_from_id($submission->id);
        $this->assertequals($submission->id(), $retrieved->id());

        $this->assertEquals($this->coursework->id, $retrieved->courseworkid);
    }

    public function test_group_decorator_is_not_added(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $coursework = $this->create_a_coursework(['grade' => 0]);

        $submission = new \stdClass();
        $submission->userid = 2;
        $submission = $generator->create_submission($submission, $coursework);

        $this->assertInstanceOf(
            '\mod_coursework\models\submission',
            submission::get_from_id($submission->id)
        );
    }

    public function test_get_allocatable_student(): void {
        $student = $this->create_a_student();
        $submission = submission::build(['allocatableid' => $student->id, 'allocatabletype' => 'user']);
        $this->assertEquals($student, $submission->get_allocatable());
    }

    public function test_get_allocatable_group(): void {
        $group = $this->create_a_group();
        $submission = submission::build(['allocatableid' => $group->id, 'allocatabletype' => 'group']);
        $this->assertEquals($group, $submission->get_allocatable());
    }

    public function test_extract_extenstion_from_filename(): void {
        $filename = 'thing.docx';
        $submission = new submission();
        $this->assertEquals('docx', $submission->extract_extension_from_file_name($filename));
    }

    public function test_publish_updates_grade_timemodified(): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $student = $this->create_a_student();
        $submissiondata = ['allocatableid' => $student->id, 'allocatabletype' => 'user'];
        $submission = $generator->create_submission($submissiondata, $this->coursework);
        $this->coursework->update_attribute('numberofmarkers', 1);
        $feedbackdata = new \stdClass();
        $feedbackdata->submissionid = $submission->id;
        $feedbackdata->grade = 54;
        $feedbackdata->assessorid = 4566;
        $feedbackdata->stageidentifier = 'assessor_1';
        $feedback = $generator->create_feedback($feedbackdata);

        sleep(1);
        $submission->publish();

        $initialtime = $feedback->timemodified;

        sleep(1);
        $feedback->update_attribute('grade', 67);

        $this->assertNotEquals($initialtime, $feedback->timemodified);

        $submission->publish();

        $gradeitem = $DB->get_record(
            'grade_items',
            ['itemtype' => 'mod', 'itemmodule' => 'coursework', 'iteminstance' => $this->coursework->id]
        );
        $grade = $DB->get_record('grade_grades', ['itemid' => $gradeitem->id, 'userid' => $student->id]);
        $gradetimemodified = $grade->timemodified;

        $this->assertNotEquals($initialtime, $gradetimemodified);
    }

    public function test_publish_updates_grade_rawgrade(): void {
        global $DB;

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $student = $this->create_a_student();
        $submissiondata = ['allocatableid' => $student->id, 'allocatabletype' => 'user'];
        $submission = $generator->create_submission($submissiondata, $this->coursework);
        $this->coursework->update_attribute('numberofmarkers', 1);
        $feedbackdata = new \stdClass();
        $feedbackdata->submissionid = $submission->id;
        $feedbackdata->grade = 54;
        $feedbackdata->assessorid = 4566;
        $feedbackdata->stageidentifier = 'assessor_1';
        $feedback = $generator->create_feedback($feedbackdata);

        $submission->publish();
        $feedback->update_attribute('grade', 67);
        $submission->publish();

        $gradeitem = $DB->get_record(
            'grade_items',
            ['itemtype' => 'mod', 'itemmodule' => 'coursework', 'iteminstance' => $this->coursework->id]
        );
        $grade = $DB->get_record(
            'grade_grades',
            ['itemid' => $gradeitem->id, 'userid' => $student->id]
        );

        $this->assertEquals(67, $grade->rawgrade);
    }

    public function test_publish_sets_grade_timemodified(): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $student = $this->create_a_student();
        $submissiondata = ['allocatableid' => $student->id, 'allocatabletype' => 'user'];
        $submission = $generator->create_submission($submissiondata, $this->coursework);
        $this->coursework->update_attribute('numberofmarkers', 1);
        $feedbackdata = new \stdClass();
        $feedbackdata->submissionid = $submission->id;
        $feedbackdata->grade = 54;
        $feedbackdata->assessorid = 4566;
        $feedbackdata->stageidentifier = 'assessor_1';

        $feedback = $generator->create_feedback($feedbackdata);

        sleep(1); // Make sure we do not just have the same timestamp everywhere.
        $submission->publish();

        $gradeitem = $DB->get_record(
            'grade_items',
            ['itemtype' => 'mod', 'itemmodule' => 'coursework', 'iteminstance' => $this->coursework->id]
        );
        $grade = $DB->get_record('grade_grades', ['itemid' => $gradeitem->id, 'userid' => $student->id]);
        $timemodified = $grade->timemodified;
        $this->assertEquals($feedback->timemodified, $timemodified);
    }
}
