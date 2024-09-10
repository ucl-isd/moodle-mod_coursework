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

use mod_coursework\models\submission;
use mod_coursework\router;

/**
 * Class router_test
 * @group mod_coursework
 */
final class router_test extends advanced_testcase {

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
    protected $moodlelocation = 'https://www.example.com/moodle';

    public function setUp(): void {
        $this->router = router::instance();
        $this->setAdminUser();
        $this->resetAfterTest();
    }

    public function test_new_submission_path(): void {

        $submission = submission::build(['allocatableid' => 4, 'allocatabletype' => 'user', 'courseworkid' => 5]);

        $path = $this->router->get_path('new submission', ['submission' => $submission]);
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
        $this->course = $this->getDataGenerator()->create_course();
        return $this->course;
    }
}
