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
 * @copyright  2017 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @group mod_coursework
 */
final class assessor_test extends advanced_testcase {

    use mod_coursework\test_helpers\factory_mixin;

    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->create_a_course();
        $this->create_a_coursework();
    }

    public function test_prerequisite_stages_is_ok_with_no_feedbacks(): void {
        $this->coursework->update_attribute('numberofmarkers', 2);
        $this->coursework->update_attribute('moderationenabled', 1);
        $this->coursework->update_attribute('moderatorallocationstrategy', 'none');

        $stages = $this->coursework->get_assessor_marking_stages();
        $firststage = reset($stages);

        $student = $this->create_a_student();
        $this->create_a_submission_for_the_student();

        $this->assertTrue($firststage->prerequisite_stages_have_feedback($student));

    }

    public function test_prerequisite_stages_is_ok_with_one_assessor_feedback(): void {
        $this->coursework->update_attribute('numberofmarkers', 2);
        $this->coursework->update_attribute('moderationenabled', 1);
        $this->coursework->update_attribute('moderatorallocationstrategy', 'none');

        $stages = $this->coursework->get_assessor_marking_stages();
        array_shift($stages);
        $secondstage = reset($stages);
        $this->assertEquals('assessor_2', $secondstage->identifier());

        $student = $this->create_a_student();
        $this->create_a_submission_for_the_student();
        $this->create_a_teacher();
        $this->create_an_assessor_feedback_for_the_submisison($this->teacher);

        $this->assertTrue($secondstage->prerequisite_stages_have_feedback($student));
    }

    public function test_type(): void {
        $stage = new \mod_coursework\stages\assessor($this->coursework, 'assessor_1');
        $this->assertEquals('assessor', $stage->type());
    }

}
