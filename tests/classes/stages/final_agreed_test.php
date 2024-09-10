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

/**
 * @group mod_coursework
 */
final class final_agreed_test extends advanced_testcase {

    use mod_coursework\test_helpers\factory_mixin;

    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->create_a_course();
        $this->create_a_coursework();
    }

    public function test_prerequisite_stages_is_false_with_no_feedbacks(): void {
        $this->coursework->update_attribute('numberofmarkers', 2);
        $this->coursework->update_attribute('moderationenabled', 1);
        $this->coursework->update_attribute('moderatorallocationstrategy', 'none');

        $stage = $this->coursework->get_final_agreed_marking_stage();

        $student = $this->create_a_student();
        $this->create_a_submission_for_the_student();

        $this->assertFalse($stage->prerequisite_stages_have_feedback($student));

    }

    public function test_prerequisite_stages_is_false_with_one_assessor_feedback(): void {
        $this->coursework->update_attribute('numberofmarkers', 2);
        $this->coursework->update_attribute('moderationenabled', 1);
        $this->coursework->update_attribute('moderatorallocationstrategy', 'none');

        $stage = $this->coursework->get_final_agreed_marking_stage();

        $student = $this->create_a_student();
        $this->create_a_submission_for_the_student();
        $this->create_a_teacher();
        $this->create_an_assessor_feedback_for_the_submisison($this->teacher);

        $this->assertFalse($stage->prerequisite_stages_have_feedback($student));
    }

    public function test_prerequisite_stages_is_true_with_two_assessor_feedbacks(): void {
        $this->coursework->update_attribute('numberofmarkers', 2);

        $stage = $this->coursework->get_final_agreed_marking_stage();

        $student = $this->create_a_student();
        $this->create_a_submission_for_the_student();
        $this->create_a_teacher();
        $this->create_another_teacher();
        $this->create_an_assessor_feedback_for_the_submisison($this->teacher);
        $this->create_an_assessor_feedback_for_the_submisison($this->other_teacher);

        // Need to student to be in the sample.

        $this->assertTrue($stage->prerequisite_stages_have_feedback($student));
    }

}
