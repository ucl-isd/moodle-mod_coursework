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

use mod_coursework\models\coursework;
use mod_coursework\stages\assessor;

defined('MOODLE_INTERNAL') || die();

global $CFG;


/**
 * Class that will make sure the allocation_manager works.
 *
 * @mixin mod_coursework\test_helpers\factory_mixin
 * @property mixed group
 * @property mixed grouping
 * @property mixed student
 * @property mixed other_student
 * @group mod_coursework
 */
class coursework_test extends advanced_testcase {

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
     * Checks whether the code to validate the class name works
     *
     */
    public function test_set_assessor_allocation_strategy() {

        global $DB;

        $goodstrategy = 'equal';
        $badstrategy = 'badone';

        $this->coursework->set_assessor_allocation_strategy($goodstrategy);
        $dbstrategy = $DB->get_field('coursework', 'assessorallocationstrategy', array('id' => $this->coursework->id));
        $this->assertEquals($goodstrategy, $dbstrategy);

        $this->assertFalse($this->coursework->set_assessor_allocation_strategy($badstrategy));

    }

    /**
     * Makes sure we can get the allocation manager and a default if it's duff.
     */
    public function test_get_allocation_manager() {

        $allocationmanager = $this->coursework->get_allocation_manager();
        $this->assertInstanceOf('\mod_coursework\allocation\manager', $allocationmanager);

        // Now make a new coursework with a duff class name.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        /* @var mod_coursework_generator $generator */
        $this->coursework = $generator->create_instance(array('course' => $this->course->id, 'grade' => 0));
        $this->coursework->assessorallocationstrategy = 'duffclass';
        $this->coursework->save();

        $allocationmanager = $this->coursework->get_allocation_manager();
        $this->assertInstanceOf('\mod_coursework\allocation\manager', $allocationmanager);
    }

    public function test_group_decorator_is_added() {
        /* @var mod_coursework_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $coursework = $generator->create_instance(array('course' => $this->course->id,
                                                        'grade' => 0,
                                                        'use_groups' => true));
        $this->assertInstanceOf('\mod_coursework\decorators\coursework_groups_decorator', coursework::find($coursework->id));
    }

    public function test_group_decorator_is_not_added() {
        /* @var mod_coursework_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $coursework = $generator->create_instance(array('course' => $this->course->id,
                                                        'grade' => 0));
        $this->assertInstanceOf('\mod_coursework\models\coursework',
                                coursework::find($coursework->id));
    }

    public function test_get_user_group_no_grouping() {

        $generator = $this->get_coursework_generator();

        $this->create_a_student();
        $this->create_a_group();
        $this->add_student_to_the_group();

        $coursework = $generator->create_instance(array('course' => $this->course->id,
                                                        'grade' => 0,
                                                        'use_groups' => true));

        $this->assertEquals($this->group->id, $coursework->get_student_group($this->student)->id);

    }

    public function test_get_user_group_with_grouping() {

        $generator = $this->get_coursework_generator();

        $this->create_a_student();
        $this->create_a_group();
        $this->create_a_grouping_and_add_the_group_to_it();
        $this->add_student_to_the_group();

        $coursework = $generator->create_instance(array('course' => $this->course->id,
                                                        'grade' => 0,
                                                        'use_groups' => true,
                                                        'grouping_id' => $this->grouping->id));

        $this->assertEquals($this->group->id, $coursework->get_student_group($this->student)->id);
    }

    public function test_get_user_group_with_wrong_grouping() {

        /**
         * @var mod_coursework_generator
         */
        $generator = $this->get_coursework_generator();

        $this->create_a_student();
        $this->create_a_group();
        $this->create_a_grouping_and_add_the_group_to_it();
        $this->add_student_to_the_group();

        $coursework = $generator->create_instance(array('course' => $this->course->id,
                                                        'grade' => 0,
                                                        'use_groups' => true,
                                                        'grouping_id' => 543));

        $this->assertEquals(false, $coursework->get_student_group($this->student));
    }

    public function test_marking_stages_does_single_marker() {
        $coursework = $this->create_a_coursework();
        $coursework->update_attribute('moderationenabled', 0);
        $coursework->update_attribute('numberofmarkers', 1);
        $this->assertEquals(1, count($coursework->marking_stages()));
        $stages = $coursework->marking_stages();
        $first_stage = reset($stages);
        $stage_identifier = $first_stage->identifier();
        $this->assertEquals('assessor_1', $stage_identifier);
    }

    public function test_marking_stages_does_double_marker() {
        $coursework = $this->create_a_coursework();
        $coursework->update_attribute('moderationenabled', 0);
        $coursework->update_attribute('numberofmarkers', 2);
        $actual = $coursework->marking_stages();
        $this->assertEquals(array('assessor_1', 'assessor_2', 'final_agreed_1'), array_keys($actual));
    }

    public function test_get_stage() {
        $coursework = $this->create_a_coursework();
        $stage = $coursework->get_stage('assessor_1');
        $this->assertEquals(new \mod_coursework\stages\assessor($coursework, 'assessor_1'), $stage);
    }

    public function test_initial_assessors_sends_each_teacher_once() {
        $coursework = $this->create_a_coursework();
        $student = $this->create_a_student();
        $coursework->update_attribute('numberofmarkers', 2);
        $this->create_a_teacher();
        $this->create_another_teacher();

        $this->assertEquals(2, count($coursework->initial_assessors($student)));
    }

    public function test_file_types_spaces() {
        $coursework = $this->create_a_coursework();
        $coursework->update_attribute('filetypes', 'doc docx');
        $this->assertEquals(array('.doc',
                                  '.docx'),
                            $coursework->get_file_options()['accepted_types']);
    }

    public function test_file_types_commas() {
        $coursework = $this->create_a_coursework();
        $coursework->update_attribute('filetypes', 'doc, docx');
        $this->assertEquals(array('.doc', '.docx'), $coursework->get_file_options()['accepted_types']);
    }

    public function test_file_types_commas_dots() {
        $coursework = $this->create_a_coursework();
        $coursework->update_attribute('filetypes', '.doc, .docx');
        $this->assertEquals(array('.doc',
                                  '.docx'),
                            $coursework->get_file_options()['accepted_types']);
    }

    public function test_file_types_commas_dots_stars() {
        $coursework = $this->create_a_coursework();
        $coursework->update_attribute('filetypes', '*.doc, *.docx');
        $this->assertEquals(array('.doc',
                                  '.docx'),
                            $coursework->get_file_options()['accepted_types']);
    }

    public function test_groupings_appear_in_allocatabeles() {

        $this->create_a_course();

        $this->create_a_student();
        $group = $this->create_a_group();
        $this->add_student_to_the_group();

        $grouping = $this->create_a_grouping();

        groups_assign_grouping($grouping->id, $group->id);

        $coursework = $this->create_a_coursework();
        $coursework->update_attribute('grouping_id', $grouping->id);
        $coursework->update_attribute('use_groups', 1);

        $allocatables = $coursework->get_allocatables();
        // print_r($allocatables);die;
        $allocatable_ids = array_keys($allocatables);
        $this->assertContains($group->id, $allocatable_ids, "Actual array keys: ".implode(', ', $allocatable_ids));

    }

    public function test_individual_feedback_deadline_has_passed() {
        $coursework = $this->create_a_coursework();
        $coursework->update_attribute('individualfeedback', strtotime('1 week ago'));
        $this->assertTrue($coursework->individual_feedback_deadline_has_passed());
    }

    public function test_individual_feedback_deadline_has_not_passed() {
        $coursework = $this->create_a_coursework();
        $coursework->update_attribute('individualfeedback', strtotime('+1 week'));
        $this->assertFalse($coursework->individual_feedback_deadline_has_passed());
    }

    public function test_individual_feedback_deadline_has_passed_when_not_set() {
        $coursework = $this->create_a_coursework();
        $coursework->update_attribute('individualfeedback', 0);
        $this->assertTrue($coursework->individual_feedback_deadline_has_passed());
    }
    
    public function test_finalise_all_leaves_other_submissions_alone() {
        $coursework = $this->get_coursework();
        $submission = $this->create_a_submission_for_the_student();
        $submission->update_attribute('courseworkid', 54443434);
        $coursework->update_attribute('deadline', strtotime('1 week ago'));
        $coursework->finalise_all();
        $this->assertEquals(0, $submission->reload()->finalised);
    }

    public function test_finalise_all_works() {
        $coursework = $this->get_coursework();
        $submission = $this->create_a_submission_for_the_student();
        $coursework->update_attribute('deadline', strtotime('1 week ago'));
        $coursework->finalise_all();
        $this->assertEquals(1, $submission->reload()->finalised);
    }

    public function test_deadline_has_passed_when_it_has() {
        $coursework = $this->get_coursework();
        $coursework->update_attribute('deadline', strtotime('1 week ago'));
        $this->assertTrue($coursework->deadline_has_passed());
    }

    public function test_deadline_has_passed_when_it_has_not() {
        $coursework = $this->get_coursework();
        $coursework->update_attribute('deadline', strtotime('+1 week'));
        $this->assertFalse($coursework->deadline_has_passed());
    }

}
