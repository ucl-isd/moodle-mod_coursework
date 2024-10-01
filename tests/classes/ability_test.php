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
 * @copyright  2017 University of London Computer Centre {@link http://ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\ability;

/**
 * Class ability_test is responsible for testing the ability class to make sure the mechanisms work.
 * @group mod_coursework
 */
final class ability_test extends advanced_testcase {

    use mod_coursework\test_helpers\factory_mixin;

    public function setUp(): void {
        $this->setAdminUser();
        $this->resetAfterTest();
    }

    public function test_allow_saves_rules(): void {
        $ability = new ability($this->create_a_teacher(), $this->create_a_coursework());
        $this->assertTrue($ability->can('show', $this->get_coursework()));
    }

    public function test_ridiculous_things_are_banned_by_default_if_not_mentioned(): void {
        $ability = new ability($this->create_a_teacher(), $this->create_a_coursework());
        $this->assertFalse($ability->can('set_fire_to', $this->get_coursework()));
    }

}
