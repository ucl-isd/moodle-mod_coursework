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

/**
 * @package    mod_coursework
 * @copyright  2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\models\coursework;

/**
 * Test manual allocation of markers.
 */
final class manual_test extends \advanced_testcase {
    use test_helpers\factory_mixin;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();

        $this->course = $generator->create_course();
    }

    /**
     * For a coursework with double marking and allocated markers, which has a
     * manager allocated as a marker but has left feedback for *both* stages
     * (which is allowed for managers), check there are no errors unenrolling
     * that manager.
     *
     * @covers mod_coursework\models\submission->has_specific_assessor_feedback()
     */
    public function test_allocated_double_marking_unenrol(): void {
        $params = [
            'numberofmakers' => 2,
            'allocationenabled' => 1,
            'assessorallocationstrategy' => 'none', // Manual.
        ];
        $this->coursework = $this->create_a_coursework($params);

        $student = $this->create_a_student();
        $teacher1 = $this->create_a_teacher();
        $teacher2 = $this->create_another_teacher();
        $this->enrol_as_manager($teacher1);

        // Allocate teacher1 as first marker and teacher2 as second marker.
        $stage = $this->coursework->get_stage('assessor_1');
        $stage->make_manual_allocation($student, $teacher1);
        $stage = $this->coursework->get_stage('assessor_2');
        $stage->make_manual_allocation($student, $teacher2);

        // Teacher1 is a manager so can leave feedback for both stages.
        $this->create_an_assessor_feedback_for_the_submission($teacher1);
        $this->create_an_assessor_feedback_for_the_submission($teacher1);

        role_unassign_all(['userid' => $teacher1->id, 'contextid' => \context_course::instance($this->course->id)->id]);

        // Check no errors occurred above.
        $this->assertTrue(true);
    }
}
