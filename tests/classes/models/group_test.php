<?php
use mod_coursework\models\group;

/**
 * Class coursework_user_test
 */
class coursework_group_test extends advanced_testcase {

    public function setUp() {
        $this->resetAfterTest();
    }

    public function test_find() {

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        $group = new stdClass();
        $group->courseid = $course->id;
        $group = $generator->create_group($group);

        $this->assertNotEmpty($group->name);
        $this->assertEquals($group->name, group::find($group->id)->name);
    }
    
}