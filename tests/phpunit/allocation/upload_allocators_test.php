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
 * Test allocating assessors by file upload.
 * @package    mod_coursework
 * @copyright  2025 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework;

use mod_coursework\models\coursework;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/csvlib.class.php');

/**
 * Class upload_allocators_test.
 * @property coursework coursework
 * @property \stdClass student
 * @property \stdClass teacher_one
 * @property \stdClass teacher_two
 * @property \stdClass course
 * @group mod_coursework
 */
final class upload_allocators_test extends \advanced_testcase {
    use test_helpers\factory_mixin;

    /**
     * Setup.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Check assessors allocated by CSV file are applied.
     */
    public function test_upload_assessor_allocations(): void {
        global $CFG, $DB;

        $CFG->coursework_allocation_identifier = 'email';
        $this->setAdminUser();
        $generator = $this->getDataGenerator();

        $this->course = $generator->create_course();

        $params = [
            'numberofmarkers' => 2,
            'allocationenabled' => 1,
        ];

        $coursework = $this->create_a_coursework($params);

        $student = $this->create_a_student();
        $teacher1 = $this->create_a_teacher();
        $teacher2 = $this->create_another_teacher();

        $content = [
            "user_email,assessor_email_1,assessor_email_2",
            "{$student->email},{$teacher1->email},{$teacher2->email}",
        ];

        $content = implode("\n", $content);
        $csvimport = new \mod_coursework\allocation\upload($coursework);
        $processingresults = $csvimport->validate_csv($content, 'UTF-8', 'comma');
        $this->assertEmpty($processingresults);
        $processingresults = $csvimport->process_csv($content, 'UTF-8', 'comma', $processingresults);
        $this->assertEmpty($processingresults);

        // The two teachers should be allocated to the student.
        $this->assertCount(2, $DB->get_records('coursework_allocation_pairs'));
    }
}
