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

use mod_coursework\models\deadline_extension;

/**
 * Class mod_coursework_models_deadline_extension_test is responsible for testin
 * the deadline_extension model class.
 * @group mod_coursework
 */
final class mod_coursework_models_deadline_extension_test extends advanced_testcase {

    use mod_coursework\test_helpers\factory_mixin;

    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    public function test_create(): void {
        $params = ['allocatableid' => 3,
                        'allocatabletype' => 'user',
                        'courseworkid' => 4,
                        'extended_deadline' => time()];
        $newthing = deadline_extension::create($params);
        $this->assertInstanceOf('mod_coursework\models\deadline_extension', $newthing);
    }

    public function test_user_extension_allows_submission_when_active(): void {
        $coursework = $this->create_a_coursework();
        $user = $this->create_a_student();
        $params = ['allocatableid' => $user->id(),
                        'allocatabletype' => 'user',
                        'courseworkid' => $coursework->id,
                        'extended_deadline' => strtotime('+ 1 week')];
        deadline_extension::create($params);
        $this->assertTrue(deadline_extension::allocatable_extension_allows_submission($user, $coursework));
    }

    public function test_user_extension_allows_submission_when_passed(): void {
        $coursework = $this->create_a_coursework();
        $user = $this->create_a_student();
        $params = ['allocatableid' => $user->id(),
                        'allocatabletype' => 'user',
                        'courseworkid' => $coursework->id,
                        'extended_deadline' => strtotime('- 1 week')];
        deadline_extension::create($params);
        $this->assertFalse(deadline_extension::allocatable_extension_allows_submission($user, $coursework));
    }

    public function test_get_coursework(): void {
        $coursework = $this->create_a_coursework();
        $params = ['allocatableid' => 3,
                        'allocatabletype' => 'user',
                        'courseworkid' => $coursework->id,
                        'extended_deadline' => strtotime('- 1 week')];
        $extension = deadline_extension::create($params);
        $this->assertEquals($extension->get_coursework(), $coursework);
    }

}
