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

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/mod/coursework/framework/test/classes/user_table.php');

/**
 * Class framework_table_base_test
 */
class framework_table_base_test extends advanced_testcase {

    public function SetUp() {
        $this->resetAfterTest();
    }

    public function test_magic_getter() {
        $generator = testing_util::get_data_generator();

        $user = $generator->create_user();

        $class = new framework_user_table($user->id);
        $this->assertEquals($user->username, $class->username);
    }

    public function test_get_table_name() {
        $this->assertEquals('user', framework_user_table::get_table_name());
    }

    public function test_find_when_true_with_id() {
        $generator = testing_util::get_data_generator();

        $params = array(
            'username' => 'freddo'
        );
        $user = $generator->create_user($params);

        $this->assertEquals($user->id, framework_user_table::find($user->id)->id);
    }

    public function test_find_when_true_with_other_param() {
        $generator = testing_util::get_data_generator();

        $params = array(
            'username' => 'freddo'
        );
        $user = $generator->create_user($params);

        $this->assertEquals($user->id, framework_user_table::find($params)->id);
    }

    public function test_find_when_true_with_entire_db_object() {
        $generator = testing_util::get_data_generator();

        $params = array(
            'username' => 'freddo'
        );
        $user = $generator->create_user($params);

        $this->assertEquals($user->id, framework_user_table::find($user)->id);
    }

    public function test_find_when_false() {
        $params = array(
            'username' => 'freddo'
        );
        $this->assertFalse(framework_user_table::find($params));
    }

    public function test_find_when_false_and_zero_supplied() {
        $this->assertFalse(framework_user_table::find(0));
    }

    public function test_exists_when_true() {
        $generator = testing_util::get_data_generator();

        $params = array(
            'username' => 'freddo'
        );
        $generator->create_user($params);

        $this->assertTrue(framework_user_table::exists($params));
    }

    public function test_exists_when_false() {
        $params = array(
            'username' => 'freddo'
        );
        $this->assertFalse(framework_user_table::exists($params));
    }

    public function test_find_when_given_a_db_record() {
        $generator = testing_util::get_data_generator();

        $params = array(
            'username' => 'freddo'
        );
        $user = $generator->create_user($params);
        $this->assertEquals($user->id, framework_user_table::find($user)->id);
    }

    public function test_find_all_returns_records() {
        $generator = testing_util::get_data_generator();

        $generator->create_user();
        $generator->create_user();
        // Admin user and guest user are there too

        $this->assertEquals(4, count(framework_user_table::find_all()));
    }

    public function test_find_all_returns_specific_records() {
        $generator = testing_util::get_data_generator();

        $generator->create_user(array('firstname' => 'Dave'));
        $generator->create_user(array('firstname' => 'Dave'));
        // Admin user and guest user are there too

        $this->assertEquals(2, count(framework_user_table::find_all(array('firstname' => 'Dave'))));
    }
}
