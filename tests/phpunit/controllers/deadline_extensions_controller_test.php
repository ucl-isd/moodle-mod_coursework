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

namespace mod_coursework;

use mod_coursework\controllers\deadline_extensions_controller;

/**
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class deadline_extensions_controller_test is responsible for testing the deadline_extensions controller
 * class.
 * @group mod_coursework
 */
final class deadline_extensions_controller_test extends \advanced_testcase {
    use test_helpers\factory_mixin;

    public function test_model_name(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $coursework = $this->create_a_coursework();
        $controller = new deadline_extensions_controller([
            'courseid' => $course->id,
            'courseworkid' => $coursework->id(),
        ]);
        $this->assertEquals('deadline_extension', $controller->model_name());
    }
}
