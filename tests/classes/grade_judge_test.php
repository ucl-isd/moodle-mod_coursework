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

use mod_coursework\grade_judge;

/**
 * Class grade_judge_test
 * @group mod_coursework
 */
final class grade_judge_test extends advanced_testcase {

    use mod_coursework\test_helpers\factory_mixin;

    public function setUp(): void {
        $this->setAdminUser();
        $this->resetAfterTest();
    }

    public function test_get_feedbck_that_is_promoted_to_gradebook_returns_initial_feedback(): void {
        $coursework = $this->create_a_coursework();
        $gradejudge = new grade_judge($coursework);

        $coursework->update_attribute('samplingenabled', 1);

        $submission = $this->create_a_submission_for_the_student();
        $assessor = $this->create_a_teacher();
        $feedback = $this->create_an_assessor_feedback_for_the_submisison($assessor);

        $this->assertEquals($feedback->id, $gradejudge->get_feedback_that_is_promoted_to_gradebook($submission)->id);
    }

    public function test_sampling_disabled_one_marker(): void {
        $coursework = $this->create_a_coursework();
        $gradejudge = new grade_judge($coursework);

        $coursework->update_attribute('samplingenabled', 0);
        $coursework->update_attribute('numberofmarkers', 1);

        $submission = $this->create_a_submission_for_the_student();
        $assessor = $this->create_a_teacher();
        $feedback = $this->create_an_assessor_feedback_for_the_submisison($assessor);

        $this->assertEquals($feedback->id, $gradejudge->get_feedback_that_is_promoted_to_gradebook($submission)->id);
    }
}
