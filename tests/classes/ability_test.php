<?php


use mod_coursework\ability;

/**
 * Class ability_test is responsible for testing the ability class to make sure the mechanisms work.
 * @group mod_coursework
 */
class ability_test extends advanced_testcase {

    use \mod_coursework\test_helpers\factory_mixin;

    public function setUp() {
        $this->setAdminUser();
        $this->resetAfterTest();
    }

    public function test_allow_saves_rules() {
        $ability = new ability($this->create_a_teacher(), $this->create_a_coursework());
        $this->assertTrue($ability->can('show', $this->get_coursework()));
    }

    public function test_ridiculous_things_are_banned_by_default_if_not_mentioned() {
        $ability = new ability($this->create_a_teacher(), $this->create_a_coursework());
        $this->assertFalse($ability->can('set_fire_to', $this->get_coursework()));
    }

}