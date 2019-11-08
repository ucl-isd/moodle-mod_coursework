<?php

global $CFG;


/**
 * Class mod_coursework_allocation_strategy_percentages_test
 * @property mixed other_teacher
 * @property mixed teacher
 * @group mod_coursework
 */
class mod_coursework_allocation_strategy_percentages_test extends advanced_testcase {

    use mod_coursework\test_helpers\factory_mixin;

    public function setUp() {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->create_a_course();
        $this->create_a_coursework();
        $this->coursework->update_attribute('assessorallocationstrategy', 'percentages');
        $this->coursework->update_attribute('allocationenabled', 1);
        $this->coursework->update_attribute('moderationenabled', 0);

        $this->create_a_student();
        $this->create_another_student();
        $this->create_a_teacher();
        $this->create_another_teacher();
    }

    public function test_next_assessor_from_list_chooses_when_at_100_percent_for_one_teacher_and_no_other_allocations() {

        $strategy = $this->assessor_strategy();

        $this->set_to_100_percent_for_other_teacher();

        $next_teacher = $strategy->next_assessor_from_list($this->list_of_teachers(), $this->student);
        $this->assertEquals($this->other_teacher->id, $next_teacher->id);

    }

    public function test_next_assessor_from_list_fails_when_at_100_percent_for_one_teacher_and_already_allocated() {

        $strategy = $this->assessor_strategy();

        $this->set_to_100_percent_for_other_teacher();
        $this->allocate_the_student_to_the_other_teacer();

        $next_teacher = $strategy->next_assessor_from_list($this->list_of_teachers(), $this->student);
        $this->assertFalse($next_teacher);
    }

    public function test_when_at_100_percent_for_one_teacher_and_the_other_student_is_already_allocated() {

        $strategy = $this->assessor_strategy();

        $this->set_to_100_percent_for_other_teacher();
        $this->allocate_the_student_to_the_other_teacer();

        $next_teacher = $strategy->next_assessor_from_list($this->list_of_teachers(), $this->other_student);
        $this->assertEquals($this->other_teacher->id, $next_teacher->id);
    }

    /**
     * Theory here is that we want to not allocate when the percentage is below that which would represent
     * a whole person. However, we sill need all of the allocations to be made without missing any.
     */
    public function test_weird_percentages_work() {
        $this->delete_all_allocations();

        $this->coursework->update_attribute('numberofmarkers', 1);

        $this->set_to_80_20_percentages_in_favour_of_other_teacher();
        $this->allocate_the_student_to_the_other_teacer();

        $strategy = $this->assessor_strategy();
        $next_teacher = $strategy->next_assessor_from_list($this->list_of_teachers(), $this->other_student);
        $this->assertEquals($this->other_teacher->id, $next_teacher->id);
    }

    /**
     */
    private function set_to_100_percent_for_other_teacher() {
        global $DB;

        $setting = new stdClass();
        $setting->courseworkid = $this->coursework->id;
        $setting->allocationstrategy = 'percentages';
        $setting->assessorid = $this->other_teacher->id;
        $setting->value = 100;
        $setting->purpose = 'assessor';
        $DB->insert_record('coursework_allocation_config', $setting);
    }

    /**
     */
    private function set_to_80_20_percentages_in_favour_of_other_teacher() {
        global $DB;

        $setting = new stdClass();
        $setting->courseworkid = $this->coursework->id;
        $setting->allocationstrategy = 'percentages';
        $setting->assessorid = $this->other_teacher->id;
        $setting->value = 80;
        $setting->purpose = 'assessor';
        $DB->insert_record('coursework_allocation_config', $setting);

        $setting->assessorid = $this->teacher->id;
        $setting->value = 20;
        $DB->insert_record('coursework_allocation_config', $setting);
    }

    private function allocate_the_student_to_the_other_teacer() {
        $allocation = new \mod_coursework\models\allocation();
        $allocation->assessorid = $this->other_teacher->id;
        $allocation->allocatableid = $this->student->id;
        $allocation->allocatabletype = 'user';
        $allocation->courseworkid = $this->coursework->id;
        $allocation->save();
    }

    /**
     * @return array
     */
    private function list_of_teachers() {
        return array(
            $this->teacher,
            $this->other_teacher,
        );
    }

    /**
     * @return \mod_coursework\allocation\strategy\percentages
     */
    private function assessor_strategy() {
        $stage = new \mod_coursework\stages\assessor($this->coursework, 'assessor_1');
        $strategy = new \mod_coursework\allocation\strategy\percentages($this->coursework, $stage);
        return $strategy;
    }

    private function delete_all_allocations() {
        global $DB;

        $DB->delete_records('coursework_allocation_pairs');
    }
}