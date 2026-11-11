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

use mod_coursework\auto_grader\average_grade;

/**
 * Tests for average grade calculation
 *
 * @package   mod_coursework
 * @author    Conn Warwicker <conn.warwicker@catalyst-eu.net>
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group     mod_coursework
 * @covers    \mod_coursework\auto_grader\average_grade
 */
final class average_grade_test extends \advanced_testcase {
    use \mod_coursework\test_helpers\factory_mixin;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Data provider for test_average_grade_calculation_simple_grading.
     *
     * Each case is: [rounding rule, marker 1's grade, marker 2's grade, expected agreed grade].
     * The coursework is graded out of 100 and the grade item uses the default of 2 decimal places.
     *
     * @return array
     */
    public static function provider_average_grade_calculation_simple_grading(): array {
        return [
            'Whole number average - mid' => ['mid', 6, 7, 6.5],
            'Whole number average - up' => ['up', 6, 7, 7],
            'Whole number average - down' => ['down', 6, 7, 6],
            'Half mark average - mid keeps the decimal' => ['mid', 5, 8, 6.5],
            'Half mark average - up rounds to whole number above' => ['up', 5, 8, 7],
            'Half mark average - down rounds to whole number below' => ['down', 5, 8, 6],
            'Two decimal place average - mid rounds to 2 decimal places' => ['mid', 5.5, 8.75, 7.13],
            'Two decimal place average - up rounds to whole number above' => ['up', 5.5, 8.75, 8],
            'Two decimal place average - down rounds to whole number below' => ['down', 5.5, 8.75, 7],
        ];
    }

    /**
     * Test the agreed grade is the average of the initial markers' grades, rounded according to the coursework's
     * rounding rule setting, for a simple point graded coursework (no advanced grading method).
     *
     * @dataProvider provider_average_grade_calculation_simple_grading
     * @param string $roundingrule The coursework's rounding rule setting: mid, up or down.
     * @param float $grade1 The grade given by the first marker.
     * @param float $grade2 The grade given by the second marker.
     * @param float $expectedgrade The expected automatically agreed grade.
     * @return void
     */
    public function test_average_grade_calculation_simple_grading(
        string $roundingrule,
        float $grade1,
        float $grade2,
        float $expectedgrade
    ): void {
        global $DB;

        // Double marked, simple point graded coursework (out of 100), using automatic average grade agreement.
        $coursework = $this->create_a_coursework([
            'grade' => 10,
            'numberofmarkers' => 2,
            'automaticagreementstrategy' => 'average_grade',
            'roundingrule' => $roundingrule,
        ]);
        $this->assertFalse($coursework->get_advanced_grading_active_controller());

        $submission = $this->create_a_submission_for_the_student();
        $marker1 = $this->create_a_teacher();
        $marker2 = $this->create_another_teacher();

        // Both initial markers give their grade.
        $generator = $this->get_coursework_generator();
        $generator->create_feedback([
            'submissionid' => $submission->id,
            'assessorid' => $marker1->id,
            'stageidentifier' => 'assessor_1',
            'grade' => $grade1,
        ]);
        $generator->create_feedback([
            'submissionid' => $submission->id,
            'assessorid' => $marker2->id,
            'stageidentifier' => 'assessor_2',
            'grade' => $grade2,
        ]);

        // Run the auto grader, as happens after a marker saves their feedback.
        $autograder = new average_grade($coursework, $submission->get_allocatable());
        $autograder->create_auto_grade_if_rules_match();

        // There should now be one automatic agreed feedback, with the averaged and rounded grade.
        $finalfeedbacks = $DB->get_records('coursework_feedbacks', [
            'submissionid' => $submission->id,
            'stageidentifier' => 'final_agreed_1',
        ]);
        $this->assertCount(1, $finalfeedbacks);
        $finalfeedback = reset($finalfeedbacks);
        $this->assertEquals(1, $finalfeedback->isautomatic);
        $this->assertEqualsWithDelta($expectedgrade, (float)$finalfeedback->grade, 0.00001);
    }
}
