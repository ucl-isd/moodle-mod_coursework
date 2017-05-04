<?php
use mod_coursework\allocation\table\processor;
use mod_coursework\models\coursework;

global $CFG;


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
 */
class table_processor_test extends advanced_testcase {

    use mod_coursework\test_helpers\factory_mixin;

    public function setUp() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        /**
         * @var mod_coursework_generator $coursework_generator
         */
        $coursework_generator = $generator->get_plugin_generator('mod_coursework');

        $this->course = $generator->create_course();
        $coursework = new stdClass();
        $coursework->course = $this->course;
        $coursework->numberofmarkers = 2;
        $this->coursework = $coursework_generator->create_instance($coursework);

        $this->create_a_student();
        $this->create_a_teacher();
        $this->create_another_teacher();
        $this->delete_all_auto_allocations_caused_by_enrol_hooks();
    }


    public function test_process_rows_makes_a_new_assessor_allocation() {

        global $DB;

        $test_rows = array(
            $this->student->id => array(
                'assessor_1' => array(
                    'assessor_id' => $this->teacher->id,
                ),
                'assessor_2' => array(
                    'assessor_id' => $this->other_teacher->id,
                ),
            ),
        );

        $processor = new processor($this->coursework);
        $processor->process_data($test_rows);

        $params = array(
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
            'manual' => 1,
        );
        $allocations = $DB->get_records('coursework_allocation_pairs', $params);
        $this->assertEquals(2, count($allocations));

    }

    public function test_process_rows_sets_the_stage_identifiers_for_new_assessor_allocation() {

        global $DB;

        $test_rows = array(
            $this->student->id => array(
                'assessor_1' => array(
                    'assessor_id' => $this->teacher->id,
                ),
                'assessor_2' => array(
                    'assessor_id' => $this->other_teacher->id,
                ),
            ),
        );

        $processor = new processor($this->coursework);
        $processor->process_data($test_rows);

        $params = array(
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
            'manual' => 1,
            'assessorid' => $this->teacher->id,
            'stage_identifier' => 'assessor_1',
        );
        $first_allocation = $DB->get_record('coursework_allocation_pairs', $params);
        $params['assessorid'] = $this->other_teacher->id;
        $params['stage_identifier'] = 'assessor_2';
        $second_allocation = $DB->get_record('coursework_allocation_pairs', $params);

        $this->assertEquals('assessor_1', $first_allocation->stage_identifier);
        $this->assertEquals('assessor_2', $second_allocation->stage_identifier);
    }

    public function test_process_rows_alters_an_existing_allocation() {

        global $DB;

        $this->set_coursework_to_single_marker();
        $allocation = $this->make_a_non_manual_allocation_for_teacher();

        $test_rows = array(
            $this->student->id => array(
                'assessor_1' => array(
                    'allocation_id' => $allocation->id,
                    'assessor_id' => $this->other_teacher->id
                )
            )
        );

        $processor = new processor($this->coursework);
        $processor->process_data($test_rows);

        $params = array(
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
            'stage_identifier' => 'assessor_1',
        );
        $records = $DB->get_records('coursework_allocation_pairs', $params);
        $this->assertEquals(1, $DB->count_records('coursework_allocation_pairs'), 'Too many allocations.');

        $this->assertEquals($this->other_teacher->id, reset($records)->assessorid, 'Wrong teacher id');

    }

    public function test_that_missing_columns_dont_mess_it_up() {
        $processor = new processor($this->coursework);
        $processor->process_data(array($this->student->id => array()));
    }

    public function test_that_missing_rows_dont_mess_it_up() {
        $processor = new processor($this->coursework);
        $processor->process_data();
    }

    private function set_coursework_to_single_marker() {
        $this->coursework->update_attribute('numberofmarkers', 1);
    }

    /**
     */
    private function make_a_non_manual_allocation_for_teacher() {
        global $DB;

        $allocation = new stdClass();
        $allocation->assessorid = $this->teacher->id;
        $allocation->courseworkid = $this->coursework->id;
        $allocation->allocatableid = $this->student->id;
        $allocation->allocatabletype = 'user';
        $allocation->stage_identifier = 'assessor_1';

        $allocation->id = $DB->insert_record('coursework_allocation_pairs', $allocation);

        return $allocation;
    }

    /**
     */
    private function make_a_non_manual_moderator_allocation_for_teacher() {
        global $DB;

        $allocation = new stdClass();
        $allocation->allocatableid = $this->student->id;
        $allocation->allocatabletype = 'user';
        $allocation->assessorid = $this->teacher->id;
        $allocation->courseworkid = $this->coursework->id;
        $allocation->stage_identifier = 'moderator_1';
        $allocation->moderator = 1;

        $allocation->id = $DB->insert_record('coursework_allocation_pairs', $allocation);

        return $allocation;
    }

    private function delete_all_auto_allocations_caused_by_enrol_hooks() {
        global $DB;

        $DB->delete_records('coursework_allocation_pairs');
    }
}