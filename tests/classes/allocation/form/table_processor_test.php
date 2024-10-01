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
 * @copyright  2017 University of London Computer Centre {@link http://ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\allocation\table\processor;
use mod_coursework\models\coursework;


/**
 * This class takes the manual data about the teachers who should be allocated to various
 * students and saves it. We want to keep this separate from the processing of the auto allocations
 * so that we can have one enabled and not the other.
 *
 * It works by
 *
 * @property coursework coursework
 * @property stdClass student
 * @property stdClass course
 * @property stdClass teacher
 * @property stdClass other_teacher
 * @group mod_coursework
 */
final class table_processor_test extends advanced_testcase {

    use mod_coursework\test_helpers\factory_mixin;

    public function setUp(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $courseworkgenerator = $generator->get_plugin_generator('mod_coursework');

        $this->course = $generator->create_course();
        $coursework = new stdClass();
        $coursework->course = $this->course;
        $coursework->numberofmarkers = 2;
        $this->coursework = $courseworkgenerator->create_instance($coursework);

        $this->create_a_student();
        $this->create_a_teacher();
        $this->create_another_teacher();

        // Delete all auto allocations caused by enrol hooks.
        $DB->delete_records('coursework_allocation_pairs');
    }

    public function test_process_rows_makes_a_new_assessor_allocation(): void {

        global $DB;

        $testrows = [
            $this->student->id => [
                'assessor_1' => [
                    'assessor_id' => $this->teacher->id,
                ],
                'assessor_2' => [
                    'assessor_id' => $this->otherteacher->id,
                ],
            ],
        ];

        $processor = new processor($this->coursework);
        $processor->process_data($testrows);

        $params = [
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
            'manual' => 1,
        ];
        $allocations = $DB->get_records('coursework_allocation_pairs', $params);
        $this->assertEquals(2, count($allocations));

    }

    public function test_process_rows_sets_the_stage_identifiers_for_new_assessor_allocation(): void {

        global $DB;

        $testrows = [
            $this->student->id => [
                'assessor_1' => [
                    'assessor_id' => $this->teacher->id,
                ],
                'assessor_2' => [
                    'assessor_id' => $this->otherteacher->id,
                ],
            ],
        ];

        $processor = new processor($this->coursework);
        $processor->process_data($testrows);

        $params = [
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
            'manual' => 1,
            'assessorid' => $this->teacher->id,
            'stage_identifier' => 'assessor_1',
        ];
        $firstallocation = $DB->get_record('coursework_allocation_pairs', $params);
        $params['assessorid'] = $this->otherteacher->id;
        $params['stage_identifier'] = 'assessor_2';
        $secondallocation = $DB->get_record('coursework_allocation_pairs', $params);

        $this->assertEquals('assessor_1', $firstallocation->stage_identifier);
        $this->assertEquals('assessor_2', $secondallocation->stage_identifier);
    }

    public function test_process_rows_alters_an_existing_allocation(): void {

        global $DB;
        $this->coursework->update_attribute('numberofmarkers', 1);

        // Make a non manual allocation for teacher.
        $allocation = \mod_coursework\models\allocation::build([
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
            'assessorid' => $this->teacher->id,
            'stage_identifier' => 'assessor_1',
        ]);
        $allocation->save();
        $this->assertEquals($allocation->assessorid, $this->teacher->id);

        $testrows = [
            $this->student->id => [
                'assessor_1' => [
                    'allocation_id' => $allocation->id,
                    'assessor_id' => $this->otherteacher->id,
                ],
            ],
        ];
        $processor = new processor($this->coursework);
        $processor->process_data($testrows);

        $allocation->reload();
        $this->assertEquals($allocation->assessorid, $this->otherteacher->id);

        $params = [
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
            'stage_identifier' => 'assessor_1',
        ];
        $records = $DB->get_records('coursework_allocation_pairs', $params);
        $this->assertEquals(
            1, count($records),
            'Too many allocations ' . json_encode($records)
        );
        $this->assertEquals($this->otherteacher->id, reset($records)->assessorid, 'Wrong teacher id');
    }

    public function test_that_missing_columns_dont_mess_it_up(): void {
        $processor = new processor($this->coursework);
        $processor->process_data([$this->student->id => []]);
    }

    public function test_that_missing_rows_dont_mess_it_up(): void {
        $processor = new processor($this->coursework);
        $processor->process_data();
    }
}
