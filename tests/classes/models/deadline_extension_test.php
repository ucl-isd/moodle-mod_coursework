<?php
use mod_coursework\models\deadline_extension;

/**
 * Class mod_coursework_models_deadline_extension_test is responsible for testin
 * the deadline_extension model class.
 * @group mod_coursework
 */
class mod_coursework_models_deadline_extension_test extends advanced_testcase {

    use \mod_coursework\test_helpers\factory_mixin;

    public function setUp() {
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    public function test_create() {
        $params = array('allocatableid' => 3,
                        'allocatabletype' => 'user',
                        'courseworkid' => 4,
                        'extended_deadline' => time());
        $new_thing = deadline_extension::create($params);
        $this->assertInstanceOf('mod_coursework\models\deadline_extension', $new_thing);
    }

    public function test_user_extension_allows_submission_when_active() {
        $coursework = $this->create_a_coursework();
        $user = $this->create_a_student();
        $params = array('allocatableid' => $user->id(),
                        'allocatabletype' => 'user',
                        'courseworkid' => $coursework->id,
                        'extended_deadline' => strtotime('+ 1 week'));
        deadline_extension::create($params);
        $this->assertTrue(deadline_extension::allocatable_extension_allows_submission($user, $coursework));
    }

    public function test_user_extension_allows_submission_when_passed() {
        $coursework = $this->create_a_coursework();
        $user = $this->create_a_student();
        $params = array('allocatableid' => $user->id(),
                        'allocatabletype' => 'user',
                        'courseworkid' => $coursework->id,
                        'extended_deadline' => strtotime('- 1 week'));
        deadline_extension::create($params);
        $this->assertFalse(deadline_extension::allocatable_extension_allows_submission($user, $coursework));
    }

    public function test_get_coursework() {
        $coursework = $this->create_a_coursework();
        $params = array('allocatableid' => 3,
                        'allocatabletype' => 'user',
                        'courseworkid' => $coursework->id,
                        'extended_deadline' => strtotime('- 1 week'));
        $extension = deadline_extension::create($params);
        $this->assertEquals($extension->get_coursework(), $coursework);
    }

}