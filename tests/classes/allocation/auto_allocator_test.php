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
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\models\coursework;

/**
 * Class test_auto_allocator
 * @property coursework coursework
 * @property stdClass student
 * @property stdClass teacher_one
 * @property stdClass teacher_two
 * @property stdClass course
 * @group mod_coursework
 */
final class auto_allocator_test extends advanced_testcase {

    use mod_coursework\test_helpers\factory_mixin;

    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        /**
         * @var mod_coursework_generator $coursework_generator
         */
        $courseworkgenerator = $generator->get_plugin_generator('mod_coursework');

        $this->course = $generator->create_course();

        $coursework = new stdClass();
        $coursework->course = $this->course;
        $coursework->moderationenabled = 1;
        $coursework->allocationenabled = 1;
        $coursework->assessorallocationstrategy = 'equal';
        $coursework->moderatorallocationstrategy = 'equal';
        $this->coursework = $courseworkgenerator->create_instance($coursework);

        $this->create_a_student();
        $this->create_a_teacher();
        $this->create_another_teacher();
    }

    public function test_process_allocations_makes_an_allocation(): void {
        global $DB;

        $this->set_coursework_to_single_marker();
        $this->disable_moderation();

        // Add the correct allocation thing to the coursework
        $allocator = new \mod_coursework\allocation\auto_allocator($this->coursework);

        $allocator->process_allocations();

        $params = [
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
        ];
        $this->assertEquals(1, $DB->count_records('coursework_allocation_pairs', $params));

    }

    public function test_process_allocations_does_not_delete_other_coursework_allocations(): void {
        $params = [
            'courseworkid' => 555,
            'allocatableid' => 555,
            'allocatabletype' => 'user',
            'assessorid' => 555,
        ];
        $otherallocation = \mod_coursework\models\allocation::build($params);
        $otherallocation->save();

        $allocator = new \mod_coursework\allocation\auto_allocator($this->coursework);
        $allocator->process_allocations();

        $this->assertTrue(\mod_coursework\models\allocation::exists($params));
    }

    public function test_process_allocations_does_not_alter_manual_allocations(): void {
        $params = [
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
            'assessorid' => 555,
            'manual' => 1,
        ];
        $otherallocation = \mod_coursework\models\allocation::build($params);
        $otherallocation->save();

        $allocator = new \mod_coursework\allocation\auto_allocator($this->coursework);
        $allocator->process_allocations();

        $this->assertTrue(\mod_coursework\models\allocation::exists($params));
    }

    public function test_process_allocations_alters_non_manual_allocations(): void {
        $params = [
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
            'assessorid' => 555,
        ];
        $otherallocation = \mod_coursework\models\allocation::build($params);
        $otherallocation->save();

        $allocator = new \mod_coursework\allocation\auto_allocator($this->coursework);
        $allocator->process_allocations();

        $this->assertFalse(\mod_coursework\models\allocation::exists($params));
    }

    public function test_process_allocations_alters_non_manual_allocations_with_submissions(): void {
        $params = [
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
            'assessorid' => 555,
        ];
        $otherallocation = \mod_coursework\models\allocation::build($params);
        $otherallocation->save();

        $submission = new \mod_coursework\models\submission();
        $submission->courseworkid = $this->coursework->id;
        $submission->allocatableid = $this->student->id;
        $submission->allocatabletype = 'user';
        $submission->save();

        $allocator = new \mod_coursework\allocation\auto_allocator($this->coursework);
        $allocator->process_allocations();

        $this->assertFalse(\mod_coursework\models\allocation::exists($params));
    }

    public function test_process_allocations_does_not_alter_non_manual_allocations_with_feedback(): void {
        $allocationparams = [
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
            'stage_identifier' => 'assessor_1',
            'assessorid' => 555,
        ];
        $otherallocation = \mod_coursework\models\allocation::build($allocationparams);
        $otherallocation->save();

        $submission = new \mod_coursework\models\submission();
        $submission->courseworkid = $this->coursework->id;
        $submission->allocatableid = $this->student->id;
        $submission->allocatabletype = 'user';
        $submission->save();

        $feedbackparams = [
            'submissionid' => $submission->id,
            'assessorid' => 555,
            'stage_identifier' => 'assessor_1',
        ];
        \mod_coursework\models\feedback::create($feedbackparams);

        $allocator = new \mod_coursework\allocation\auto_allocator($this->coursework);
        $allocator->process_allocations();

        $this->assertTrue(\mod_coursework\models\allocation::exists($allocationparams));
    }

    private function set_coursework_to_single_marker() {
        $this->coursework->update_attribute('numberofmarkers', 1);
    }

    private function disable_moderation() {
        $this->coursework->update_attribute('moderationenabled', 0);
    }
}
