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
 * @copyright  2025 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework;

use mod_coursework\auto_grader\average_grade_no_straddle;


/**
 * Class average_grade_no_straddle_test is responsible for testing the behaviour of the average_grade_no_straddle class.
 *
 * @package mod_coursework\auto_grader
 * @group mod_coursework
 */
final class average_grade_no_straddle_test extends \advanced_testcase {
    use \mod_coursework\test_helpers\factory_mixin;

    public function setUp(): void {
        parent::setUp();
        $this->setAdminUser();
        $coursework = $this->get_coursework();
        $coursework->update_attribute('automaticagreementstrategy', 'average_grade_no_straddle');
        // Grades within 5 percent of eachother.
        $coursework->update_attribute('automaticagreementrange', 5);
        set_config('autogradeclassboundaries', average_grade_no_straddle::get_example_setting(), 'coursework');
        $this->resetAfterTest();
    }

    /**
     * Test that setting content is as expected.
     * @covers \mod_coursework\average_grade_no_straddle::get_config_setting()
     * @return void
     * @throws \core\exception\invalid_parameter_exception
     */
    public function test_get_setting(): void {
        $setting = average_grade_no_straddle::get_config_setting('autogradeclassboundaries');
        $this->assertEquals([[70, 100], [60, 69.99], [50, 59.99], [40, 49.99], [1, 39.99], [0, 0.99]], $setting);
    }

    /**
     * Data provider for test_create_auto_grade.
     *
     * @return array
     */
    public static function provider_test_create_auto_grade(): array {
        return [
            'Grades close enough and in same class - auto grade is 52.5' => [50, 55, 52.5],
            'Grades close enough and in same class - auto grade is 63.5' => [64, 67, 65.5],
            'Grades close enough and in same class - auto grade is 73' => [71, 75, 73],
            'Grades in same class, but too far apart - no auto grade' => [50, 58, null],
            'Grades too far apart and in different classes - no auto grade' => [50, 75, null],
            'Grade 101 falls outside all ranges - no auto grade' => [101, 99, null, true],
            'Grade 59.999 rounded down to 59.99 for band classification purposes - auto grade is 59.07' => [
                59.999, 58.15, round((59.99 + 58.15) / 2, average_grade_no_straddle::MAX_DECIMAL_PLACES),
            ],
        ];
    }

    /**
     * Test that auto grade is created and correct, or not created if not appropriate.
     * @param float $gradeone
     * @param float $gradetwo
     * @param float|null $expectedautograde
     * @param bool $expectoutofrange are we expecting an out of range grade?
     * @return void
     * @covers \mod_coursework\auto_grader\average_grade_no_straddle::create_auto_grade_if_rules_match
     * @dataProvider provider_test_create_auto_grade
     */
    public function test_create_auto_grade(
        float $gradeone,
        float $gradetwo,
        ?float $expectedautograde,
        bool $expectoutofrange = false
    ): void {
        global $DB;
        $user = $this->createMock('\mod_coursework\models\user');
        $user->expects($this->any())->method('has_agreed_feedback')
            ->with($this->get_coursework())
            ->will($this->returnValue(false));

        $user->expects($this->any())->method('has_all_initial_feedbacks')
            ->with($this->get_coursework())
            ->will($this->returnValue(true));

        $feedbackone = $this->createMock('\mod_coursework\models\feedback');
        $feedbackone->expects($this->any())->method('get_grade')->will($this->returnValue($gradeone));

        $feedbacktwo = $this->createMock('\mod_coursework\models\feedback');
        $feedbacktwo->expects($this->any())->method('get_grade')->will($this->returnValue($gradetwo));

        $user->expects($this->any())->method('get_initial_feedbacks')
            ->with($this->get_coursework())
            ->will($this->returnValue([$feedbackone, $feedbacktwo]));

        $submission = $this->createMock('\mod_coursework\models\submission');

        $user->expects($this->any())->method('get_submission')->will($this->returnValue($submission));

        $autograder = new average_grade_no_straddle($this->get_coursework(), $user);
        $autograder->create_auto_grade_if_rules_match();
        if ($expectoutofrange) {
            $this->assertDebuggingCalled(
                "Cannot determine whether to assign agreed average grade for coursework " . $this->coursework->id
                . ". Grade '$gradeone'falls outside known class boundaries"
            );
        }

        $records = $DB->get_records(
            'coursework_feedbacks',
            // As it's auto graded, lasteditedbyuser should be zero (no user involved).
            ['stageidentifier' => 'final_agreed_1', 'lasteditedbyuser' => 0],
            'ID DESC',
            '*',
            0,
            1
        );
        if ($expectedautograde === null) {
            // Not expecting an auto graded feedback at all.
            $this->assertEquals(0, count($records));
        } else {
            $this->assertEquals($expectedautograde, reset($records)->grade ?? false);
            $this->assertEquals(1, count($records));
        }
    }

    /**
     * Test get range for percentage grades.
     * @covers \mod_coursework\auto_grader\average_grade_no_straddle::get_grade_range_index
     * @return void
     */
    public function test_get_range(): void {
        $setting = average_grade_no_straddle::get_config_setting('autogradeclassboundaries');

        // Value 101 is out of all ranges, so index should be null when using example ranges.
        $this->assertNull(average_grade_no_straddle::get_grade_range_index(101, $setting));

        // Value 90 is in range index 0 when using example ranges.
        $this->assertEquals(0, average_grade_no_straddle::get_grade_range_index(90, $setting));

        // Value 60 is in range index 1 when using example ranges.
        $this->assertEquals(1, average_grade_no_straddle::get_grade_range_index(60, $setting));

        // Value 55 is in range index 2 when using example ranges.
        $this->assertEquals(2, average_grade_no_straddle::get_grade_range_index(55, $setting));

        // Value 59.991 is in range index 2 when using example ranges (as it's rounded down to 59.99).
        $this->assertEquals(2, average_grade_no_straddle::get_grade_range_index(59.991, $setting));

        // Value 42 is in range index 3 when using example ranges.
        $this->assertEquals(3, average_grade_no_straddle::get_grade_range_index(42, $setting));

        // Value 49.999 is in range index 3 when using example ranges (as it's rounded down to 49.99).
        $this->assertEquals(3, average_grade_no_straddle::get_grade_range_index(49.999, $setting));
    }
}
