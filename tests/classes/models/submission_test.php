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
 * @package    mod
 * @subpackage coursework
 * @copyright  2012 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\models\feedback;
use mod_coursework\models\submission;

defined('MOODLE_INTERNAL') || die();

global $CFG;


/**
 * Class that will make sure the allocation_manager works.
 * @group mod_coursework
 */
class coursework_submission_test extends advanced_testcase {

    use mod_coursework\test_helpers\factory_mixin;

    /**
     * Makes us a blank coursework and allocation manager.
     */
    public function setUp() {

        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        /* @var mod_coursework_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $this->setAdminUser();
        $this->coursework = $generator->create_instance(array('course' => $this->course->id, 'grade' => 0));
        $this->redirectMessages();
        $this->preventResetByRollback();
    }

    /**
     * Clean up the test fixture by removing the objects.
     */
    public function tearDown() {
        global $DB;

        $DB->delete_records('coursework', array('id' => $this->coursework->id));
        unset($this->coursework);
    }

    /**
     * Test that get_coursework() will get the coursework when asked to.
     */
    public function test_get_coursework() {
        $submission = new submission();
        $submission->courseworkid = $this->coursework->id;
        $submission->save();

        $this->assertEquals($this->coursework->id, $submission->get_coursework()->id);
    }

    /**
     * Make sure that the id field is created automatically.
     */
    public function test_save_id() {
        $submission = new submission();
        $submission->courseworkid = $this->coursework->id;
        $submission->save();

        $this->assertNotEmpty($submission->id);
    }

    /**
     * Make sure we can get the courseworkid to save.
     */
    public function test_save_courseworkid() {
        $submission = new submission();
        $submission->courseworkid = $this->coursework->id;
        $submission->save();

        $retrieved = submission::find($submission->id);

        $this->assertNotEmpty($retrieved->courseworkid);
    }

    public function test_group_decorator_is_not_added() {
        /* @var mod_coursework_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $coursework = $generator->create_instance(array('course' => $this->course->id,
                                                        'grade' => 0));

        $submission = new stdClass();
        $submission->userid = 2;
        $submission = $generator->create_submission($submission, $coursework);

        $this->assertInstanceOf('\mod_coursework\models\submission',
                                submission::find($submission->id));
    }

    public function test_get_allocatable_student() {

        $student = $this->create_a_student();
        /**
         * @var submission $submission
         */
        $submission = submission::build(array('allocatableid' => $student->id, 'allocatabletype' => 'user'));
        $this->assertEquals($student, $submission->get_allocatable());
    }

    public function test_get_allocatable_group() {

        $group = $this->create_a_group();
        /**
         * @var submission $submission
         */
        $submission = submission::build(array('allocatableid' => $group->id,
                                              'allocatabletype' => 'group'));
        $this->assertEquals($group, $submission->get_allocatable());
    }

    public function test_extract_extenstion_from_filename() {
        $filename = 'thing.docx';
        $submission = new submission();
        $this->assertEquals('docx', $submission->extract_extension_from_file_name($filename));
    }

    public function test_publish_updates_grade_timemodified() {
        global $DB;

        /* @var mod_coursework_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $student = $this->create_a_student();
        /**
         * @var submission $submission
         */
        $submission_data = array('allocatableid' => $student->id,
                                              'allocatabletype' => 'user');
        $submission = $generator->create_submission($submission_data, $this->coursework);
        $this->coursework->update_attribute('numberofmarkers', 1);
        $feedback_data = new stdClass();
        $feedback_data->submissionid = $submission->id;
        $feedback_data->grade = 54;
        $feedback_data->assessorid = 4566;
        $feedback_data->stage_identifier = 'assessor_1';

        /**
         * @var feedback $feedback
         */
        $feedback = $generator->create_feedback($feedback_data);

        sleep(1);
        $submission->publish();

        $initial_time = $feedback->timemodified;

        sleep(1);
        $feedback->update_attribute('grade', 67);

        $this->assertNotEquals($initial_time, $feedback->timemodified);

        $submission->publish();

        $grade_item = $DB->get_record('grade_items', array('itemtype' => 'mod', 'itemmodule' => 'coursework', 'iteminstance' => $this->coursework->id));
        $grade = $DB->get_record('grade_grades', array('itemid' => $grade_item->id, 'userid' => $student->id));
        $grade_time_modified = $grade->timemodified;


        $this->assertNotEquals($initial_time, $grade_time_modified);

    }

    public function test_publish_updates_grade_rawgrade() {
        global $DB;

        /* @var mod_coursework_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $student = $this->create_a_student();
        /**
         * @var submission $submission
         */
        $submission_data = array('allocatableid' => $student->id,
                                 'allocatabletype' => 'user');
        $submission = $generator->create_submission($submission_data, $this->coursework);
        $this->coursework->update_attribute('numberofmarkers', 1);
        $feedback_data = new stdClass();
        $feedback_data->submissionid = $submission->id;
        $feedback_data->grade = 54;
        $feedback_data->assessorid = 4566;
        $feedback_data->stage_identifier = 'assessor_1';

        /**
         * @var feedback $feedback
         */
        $feedback = $generator->create_feedback($feedback_data);

        $submission->publish();
        $feedback->update_attribute('grade', 67);
        $submission->publish();

        $grade_item = $DB->get_record('grade_items',
                                      array('itemtype' => 'mod',
                                            'itemmodule' => 'coursework',
                                            'iteminstance' => $this->coursework->id));
        $grade = $DB->get_record('grade_grades',
                                 array('itemid' => $grade_item->id,
                                       'userid' => $student->id));

        $this->assertEquals(67, $grade->rawgrade);

    }

    public function test_publish_sets_grade_timemodified() {
        global $DB;

        /* @var mod_coursework_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $student = $this->create_a_student();
        /**
         * @var submission $submission
         */
        $submission_data = array('allocatableid' => $student->id,
                                 'allocatabletype' => 'user');
        $submission = $generator->create_submission($submission_data, $this->coursework);
        $this->coursework->update_attribute('numberofmarkers', 1);
        $feedback_data = new stdClass();
        $feedback_data->submissionid = $submission->id;
        $feedback_data->grade = 54;
        $feedback_data->assessorid = 4566;
        $feedback_data->stage_identifier = 'assessor_1';

        $feedback = $generator->create_feedback($feedback_data);

        sleep(1); // Make sure we do not just have the same timestamp everywhere.
        $submission->publish();

        $grade_item = $DB->get_record('grade_items',
                                      array('itemtype' => 'mod',
                                            'itemmodule' => 'coursework',
                                            'iteminstance' => $this->coursework->id));
        $grade = $DB->get_record('grade_grades',
                                 array('itemid' => $grade_item->id,
                                       'userid' => $student->id));
        $time_modified = $grade->timemodified;

        $this->assertEquals($feedback->timemodified, $time_modified);
    }

}
