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
 * Unit tests for the coursework class
 *
 * @package    mod_coursework
 * @copyright  2012 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\models\feedback;
use mod_coursework\models\submission;

defined('MOODLE_INTERNAL') || die();

/**
 * Class that will make sure the allocation_manager works.
 * @group mod_coursework
 */
final class coursework_submission_test extends advanced_testcase {

    use mod_coursework\test_helpers\factory_mixin;

    /**
     * Makes us a blank coursework and allocation manager.
     */
    public function setUp(): void {

        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $this->setAdminUser();
        $this->coursework = $generator->create_instance(['course' => $this->course->id, 'grade' => 0]);
        $this->redirectMessages();
        $this->preventResetByRollback();

        // If we don't do this, we end up with the same cached objects for all tests and they may have incorrect/missing properties.
        \mod_coursework\models\coursework::$pool = null;
        \mod_coursework\models\user::$pool = null;
    }

    /**
     * Clean up the test fixture by removing the objects.
     */
    public function tearDown(): void {
        global $DB;

        $DB->delete_records('coursework', ['id' => $this->coursework->id]);
        unset($this->coursework);
    }

    /**
     * Test that get_coursework() will get the coursework when asked to.
     */
    public function test_get_coursework(): void {
        $submission = new submission();
        $submission->courseworkid = $this->coursework->id;
        $submission->save();

        $this->assertEquals($this->coursework->id, $submission->get_coursework()->id);
    }

    /**
     * Make sure that the id field is created automatically.
     */
    public function test_save_id(): void {
        $submission = new submission();
        $submission->courseworkid = $this->coursework->id;
        $submission->save();

        $this->assertNotEmpty($submission->id);
    }

    /**
     * Make sure we can get the courseworkid to save.
     */
    public function test_save_courseworkid(): void {
        $submission = new submission();
        $submission->courseworkid = $this->coursework->id;
        $submission->save();

        $retrieved = submission::find($submission->id);

        $this->assertNotEmpty($retrieved->courseworkid);
    }

    public function test_group_decorator_is_not_added(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $coursework = $generator->create_instance(['course' => $this->course->id,
                                                        'grade' => 0]);

        $submission = new stdClass();
        $submission->userid = 2;
        $submission = $generator->create_submission($submission, $coursework);

        $this->assertInstanceOf('\mod_coursework\models\submission',
                                submission::find($submission->id));
    }

    public function test_get_allocatable_student(): void {
        $student = $this->create_a_student();
        $submission = submission::build(['allocatableid' => $student->id, 'allocatabletype' => 'user']);
        $keys = array_keys((array)$student);
        $excludedkeys = ['timemodified', 'timecreated'];
        foreach ($keys as $key) {
            if (in_array($key, $excludedkeys) || str_ends_with($key, 'dataloaded')) {
                continue;
            }
            $this->assertEquals($student->$key, $submission->get_allocatable()->$key, "Field '$key' differs");
        }
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
        $feedbackdata = new stdClass();
        $feedbackdata->submissionid = $submission->id;
        $feedbackdata->grade = 54;
        $feedbackdata->assessorid = 4566;
        $feedbackdata->stage_identifier = 'assessor_1';
        $feedback = $generator->create_feedback($feedbackdata);

        sleep(1);
        $submission->publish();

        $initialtime = $feedback->timemodified;

        sleep(1);
        $feedback->update_attribute('grade', 67);

        $this->assertNotEquals($initialtime, $feedback->timemodified);

        $submission->publish();

        $gradeitem = $DB->get_record(
            'grade_items', ['itemtype' => 'mod', 'itemmodule' => 'coursework', 'iteminstance' => $this->coursework->id]
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
        $feedbackdata = new stdClass();
        $feedbackdata->submissionid = $submission->id;
        $feedbackdata->grade = 54;
        $feedbackdata->assessorid = 4566;
        $feedbackdata->stage_identifier = 'assessor_1';
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
            ['itemid' => $gradeitem->id, 'userid' => $student->id]);

        $this->assertEquals(67, $grade->rawgrade);
    }

    public function test_publish_sets_grade_timemodified(): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $student = $this->create_a_student();
        $submissiondata = ['allocatableid' => $student->id, 'allocatabletype' => 'user'];
        $submission = $generator->create_submission($submissiondata, $this->coursework);
        $this->coursework->update_attribute('numberofmarkers', 1);
        $feedbackdata = new stdClass();
        $feedbackdata->submissionid = $submission->id;
        $feedbackdata->grade = 54;
        $feedbackdata->assessorid = 4566;
        $feedbackdata->stage_identifier = 'assessor_1';

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
