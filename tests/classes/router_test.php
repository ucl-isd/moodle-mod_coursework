<?php
use mod_coursework\models\submission;
use mod_coursework\router;

/**
 * Class router_test
 */
class router_test extends advanced_testcase {

    /**
     * @var router
     */
    protected $router;

    /**
     * @var stdClass
     */
    protected $course;

    /**
     * @var string
     */
    protected $moodle_location = 'http://www.example.com/moodle';

    public function setUp() {
        $this->router = router::instance();
        $this->setAdminUser();
        $this->resetAfterTest();
    }

    public function test_new_submission_path() {

        $submission = submission::build(array('allocatableid' => 4, 'allocatabletype' => 'user', 'courseworkid' => 5));
        $path = $this->router->get_path('new submission', array('submission' => $submission));
        $this->assertEquals($this->moodle_location.'/mod/coursework/actions/submissions/new.php?allocatableid=4&amp;allocatabletype=user&amp;courseworkid=5', $path);
    }

    /**
     * @return mod_coursework_generator
     * @throws coding_exception
     */
    protected function get_generator() {
        return $this->getDataGenerator()->get_plugin_generator('mod_coursework');
    }

    /**
     * @return \mod_coursework\models\coursework
     * @throws coding_exception
     */
    protected function get_coursework() {
        $coursework = new stdClass();
        $coursework->course = $this->get_course();
        return $this->get_generator()->create_instance($coursework);
    }

    /**
     * @return stdClass
     */
    private function get_course() {
        $this->course =  $this->getDataGenerator()->create_course();
        return $this->course;
    }
}