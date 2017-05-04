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
 * PHPUnit data generator tests
 *
 * @package    mod
 * @subpackage coursework
 * @category   phpunit
 * @copyright  2011 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/coursework/lib.php');

/**
 * PHPUnit data generator testcase
 *
 * @package    mod_assignment
 * @category   phpunit
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_coursework_generator_testcase extends advanced_testcase {

    /**
     * Sets things up for every test. We want all to clean up after themselves.
     */
    public function setUp() {
        $this->resetAfterTest(true);
    }

    /**
     * Checks that the data generator for making coursework instances in the PHPUnit DB is working.
     * Mostly pinched from the same file in the assignment module.
     */
    public function test_create_instance() {

        global $DB;

        $this->assertEquals(0, $DB->count_records('coursework'));

        $course = $this->getDataGenerator()->create_course();

        /* @var mod_coursework_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $this->assertInstanceOf('mod_coursework_generator', $generator);
        $this->assertEquals('coursework', $generator->get_modulename());

        // Calendar event creation will fail if we have no user.
        $this->setAdminUser();

        $generator->create_instance(array('course' => $course->id,
                                          'grade' => 0));
        $generator->create_instance(array('course' => $course->id,
                                          'grade' => 0));
        $coursework = $generator->create_instance(array('course' => $course->id,
                                                        'grade' => 100));
        $this->assertEquals(3, $DB->count_records('coursework'));

        $cm = get_coursemodule_from_instance('coursework', $coursework->id);
        $this->assertEquals($coursework->id, $cm->instance);
        $this->assertEquals('coursework', $cm->modname);
        $this->assertEquals($course->id, $cm->course);

        $context = context_module::instance($cm->id);
        $this->assertEquals($coursework->get_coursemodule_id(), $context->instanceid);

        // Test gradebook integration using low level DB access - DO NOT USE IN PLUGIN CODE!
        $gitem = $DB->get_record('grade_items',
                                 array('courseid' => $course->id,
                                       'itemtype' => 'mod',
                                       'itemmodule' => 'coursework',
                                       'iteminstance' => $coursework->id));
        $this->assertNotEmpty($gitem);
        $this->assertEquals(100, $gitem->grademax);
        $this->assertEquals(0, $gitem->grademin);
        $this->assertEquals(GRADE_TYPE_VALUE, $gitem->gradetype);

        // Test eventslib integration.
        // TODO doesn't seem to do anything.
        $generator->create_instance(array('course' => $course->id,
                                          'timedue' => strtotime('+1 day')));
        $this->setUser(null);
    }

    /**
     * Makes sure we can make allocations OK.
     */
    public function test_create_allocation_default_assessor() {

        global $DB;

        $data = new stdClass();
        $data->allocatableid = 5;
        $data->allocatabletype = 'user';
        $data->stage_identifier = 'assessor_1';
        $data->courseworkid = 65;

        /* @var mod_coursework_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');

        $this->setAdminUser();

        // Should fail because we have no assessorid and we have no logged ourselves in.
        $allocation = $generator->create_allocation($data);
        $allocation = $DB->get_record('coursework_allocation_pairs', array('id' => $allocation->id));

        $this->assertNotEmpty($allocation);

        $this->assertEquals(2, $allocation->assessorid);
        $this->assertEquals(5, $allocation->allocatableid);
        $this->assertEquals(65, $allocation->courseworkid);
        $this->assertEquals(0, $allocation->manual);

    }

    /**
     * Makes sure we can make feedbacks OK.
     */
    public function test_create_feedback() {

        global $DB;

        $data = new stdClass();
        $data->submissionid = 5;
        $data->assessorid = 65;

        /* @var mod_coursework_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');

        // Should fail because we have no assessorid and we have no logged ourselves in.
        $feedback = $generator->create_feedback($data);
        $feedback = $DB->get_record('coursework_feedbacks', array('id' => $feedback->id));

        $this->assertNotEmpty($feedback);

        $this->assertEquals(5, $feedback->submissionid);
        $this->assertEquals(65, $feedback->assessorid);
    }

    /**
     * Makes sure we can make fake submissions.
     */
    public function test_create_submission() {

        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser(); // Calendar complains otherwise.

        /* @var mod_coursework_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $coursework = new stdClass();
        $coursework->course = $course;
        $coursework = $generator->create_instance($coursework);

        $data = new stdClass();
        $data->courseworkid = $coursework->id;
        $data->userid = $user->id;

        // Should fail because we have no assessorid and we have no logged ourselves in.
        $submission = $generator->create_submission($data, $coursework);
        $submission = $DB->get_record('coursework_submissions', array('id' => $submission->id));

        $this->assertNotEmpty($submission);

        $this->assertEquals($coursework->id, $submission->courseworkid);
        $this->assertEquals($user->id, $submission->userid);
    }


}
