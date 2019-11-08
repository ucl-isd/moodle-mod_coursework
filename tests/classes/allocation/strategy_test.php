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

defined('MOODLE_INTERNAL') || die();

global $CFG;


/**
 * Unit tests for the base allocation strategy class.
 * @group mod_coursework
 */
class coursework_allocation_strategy_test extends advanced_testcase {

    /**
     * @var \mod_coursework\models\coursework
     */
    private $coursework;

    /**
     * Makes us a blank coursework and allocation manager.
     */
    public function setUp() {

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        /* @var mod_coursework_generator $courseworkgenerator */
        $courseworkgenerator = $generator->get_plugin_generator('mod_coursework');
        $this->setAdminUser();
        $this->coursework = $courseworkgenerator->create_instance(array('course' => $course->id, 'grade' => 0));
    }

    /**
     * See whether this works to create all allocations
     */
    public function test_allocate_all_ungraded() {

        $generator = $this->getDataGenerator();
        /* @var mod_coursework_generator $courseworkgenerator */
        $courseworkgenerator = $generator->get_plugin_generator('mod_coursework');

        // Make some students.
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        $user3 = $generator->create_user();

        // Make some teachers.
        $teacher1 = $generator->create_user();
        $teacher2 = $generator->create_user();
        $teacher3 = $generator->create_user();

        // Make a submission, feedback and allocation (manual) for one of them.

        // Check that there is one allocation.

        // Allocate all ungraded.

        // Check that there are now three allocations.

        // Check that the original one is still OK.

    }


}
