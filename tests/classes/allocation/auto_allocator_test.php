<?php
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
class auto_allocator_test extends advanced_testcase {

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
        $coursework->moderationenabled = 1;
        $coursework->allocationenabled = 1;
        $coursework->assessorallocationstrategy = 'equal';
        $coursework->moderatorallocationstrategy = 'equal';
        $this->coursework = $coursework_generator->create_instance($coursework);

        $this->create_a_student();
        $this->create_a_teacher();
        $this->create_another_teacher();
    }

    public function test_process_allocations_makes_an_allocation() {
        global $DB;

        $this->set_coursework_to_single_marker();
        $this->disable_moderation();

        // Add the correct allocation thing to the coursework
        $allocator = new \mod_coursework\allocation\auto_allocator($this->coursework);

        $allocator->process_allocations();

        $params = array(
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
        );
        $this->assertEquals(1, $DB->count_records('coursework_allocation_pairs', $params));

    }

    public function test_process_allocations_does_not_delete_other_coursework_allocations() {
        $params = array(
            'courseworkid' => 555,
            'allocatableid' => 555,
            'allocatabletype' => 'user',
            'assessorid' => 555,
        );
        $other_allocation = \mod_coursework\models\allocation::build($params);
        $other_allocation->save();

        $allocator = new \mod_coursework\allocation\auto_allocator($this->coursework);
        $allocator->process_allocations();

        $this->assertTrue(\mod_coursework\models\allocation::exists($params));
    }

    public function test_process_allocations_does_not_alter_manual_allocations() {
        $params = array(
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
            'assessorid' => 555,
            'manual' => 1,
        );
        $other_allocation = \mod_coursework\models\allocation::build($params);
        $other_allocation->save();

        $allocator = new \mod_coursework\allocation\auto_allocator($this->coursework);
        $allocator->process_allocations();

        $this->assertTrue(\mod_coursework\models\allocation::exists($params));
    }

    public function test_process_allocations_alters_non_manual_allocations() {
        $params = array(
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
            'assessorid' => 555,
        );
        $other_allocation = \mod_coursework\models\allocation::build($params);
        $other_allocation->save();

        $allocator = new \mod_coursework\allocation\auto_allocator($this->coursework);
        $allocator->process_allocations();

        $this->assertFalse(\mod_coursework\models\allocation::exists($params));
    }

    public function test_process_allocations_alters_non_manual_allocations_with_submissions() {
        $params = array(
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
            'assessorid' => 555,
        );
        $other_allocation = \mod_coursework\models\allocation::build($params);
        $other_allocation->save();

        $submission = new \mod_coursework\models\submission();
        $submission->courseworkid = $this->coursework->id;
        $submission->allocatableid = $this->student->id;
        $submission->allocatabletype = 'user';
        $submission->save();

        $allocator = new \mod_coursework\allocation\auto_allocator($this->coursework);
        $allocator->process_allocations();

        $this->assertFalse(\mod_coursework\models\allocation::exists($params));
    }

    public function test_process_allocations_does_not_alter_non_manual_allocations_with_feedback() {
        $allocation_params = array(
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
            'stage_identifier' => 'assessor_1',
            'assessorid' => 555,
        );
        $other_allocation = \mod_coursework\models\allocation::build($allocation_params);
        $other_allocation->save();

        $submission = new \mod_coursework\models\submission();
        $submission->courseworkid = $this->coursework->id;
        $submission->allocatableid = $this->student->id;
        $submission->allocatabletype = 'user';
        $submission->save();

        $feedback_params = array(
            'submissionid' => $submission->id,
            'assessorid' => 555,
            'stage_identifier' => 'assessor_1',
        );
        \mod_coursework\models\feedback::create($feedback_params);

        $allocator = new \mod_coursework\allocation\auto_allocator($this->coursework);
        $allocator->process_allocations();

        $this->assertTrue(\mod_coursework\models\allocation::exists($allocation_params));
    }





    private function set_coursework_to_single_marker() {
        $this->coursework->update_attribute('numberofmarkers', 1);
    }

    private function disable_moderation() {
        $this->coursework->update_attribute('moderationenabled', 0);
    }
}