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

namespace mod_coursework\auto_grader;

use mod_coursework\admin_setting_autogradeboundaries;
use mod_coursework\allocation\allocatable;
use mod_coursework\models\coursework;

/**
 * Class average_grade_no_straddle is responsible for calculating and applying the automatically agreed average grade
 * if the initial assessor grades are within a certain percentage of one another, and do not straddle class boundaries.
 *
 * @package mod_coursework\auto_grader
 */
class average_grade_no_straddle extends average_grade {
    /** Maximum number of decimal places allowed in a grade. */
    const MAX_DECIMAL_PLACES = 2;

    /**
     * @var coursework
     */
    private $coursework;

    /**
     * @var int
     */
    private $percentage;

    /**
     * Constructor.
     * @param coursework $coursework
     * @param allocatable $allocatable
     */
    public function __construct($coursework, $allocatable) {
        $this->coursework = $coursework;
        $this->percentage = (int)$this->coursework->automaticagreementrange;
        parent::__construct($coursework, $allocatable);
    }

    /**
     * This will test whether there is a grade already present, test whether the rules for this class match the
     * state of the initial assessor grades and make an automatic grade if they do.
     *
     */
    public function create_auto_grade_if_rules_match() {
        if (!$this->grades_are_close_enough()) {
            return;
        }

        $gradeclassesadminsetting = self::get_config_setting('autogradeclassboundaries');
        if (!$gradeclassesadminsetting) {
            return;
        }

        $grades = $this->grades_as_percentages();
        if (empty($grades)) {
            // No grades found, so we are not applying this rule.
            return;
        }

        $gradeclassesseen = [];
        foreach ($grades as $gradepercentage) {
            $index = self::get_grade_range_index($gradepercentage, $gradeclassesadminsetting);
            if ($index === null) {
                // This is not expected to happen, so emit debugging and do not create an auto grade in this case.
                debugging(
                    "Cannot determine whether to assign agreed average grade for coursework " . $this->coursework->id
                    . ". Grade '$gradepercentage'falls outside known class boundaries"
                );
                return;
            }
            if (!in_array($index, $gradeclassesseen)) {
                $gradeclassesseen[] = $index;
            }
        }
        if (count($gradeclassesseen) > 1) {
            // We have seen more than one grade class, so the grades straddle class boundaries - no auto grade.
            return;
        }

        // We also want to do some other checks, e.g. that advanced grading is not being used, before applying auto grade.
        // Parent method will do all that.
        parent::create_auto_grade_if_rules_match();
    }

    /**
     * Are grades close enough (within % setting in cm settings) to allow auto agreement?
     * @return bool
     */
    private function grades_are_close_enough(): bool {
        $grades = $this->grades_as_percentages();
        if (empty($grades)) {
            return false;
        }
        $maxgrade = round(max($grades), self::MAX_DECIMAL_PLACES);
        $mingrade = round(min($grades), self::MAX_DECIMAL_PLACES);
        return $maxgrade - $mingrade <= $this->percentage;
    }

    /**
     * In which of the grade class boundary ranges does this grade fall?
     * @param float $gradepercentage
     * @param array $gradeclassesadminsetting
     * @return ?int the index of the range or null if none of them.
     */
    public static function get_grade_range_index(float $gradepercentage, array $gradeclassesadminsetting): ?int {
        foreach ($gradeclassesadminsetting as $index => $gradeclassboundaries) {
            $boundarybottom = $gradeclassboundaries[0];
            $boundarytop = $gradeclassboundaries[1];
            $roundeddowngradepercentage = floor($gradepercentage * 100) / 100;
            // For grade band classification purposes, we round $gradepercentage down to 2 decimal places.
            // E.g. a percentage grade of 59.999% would be rounded down to 59.99% to determine which band it was in.
            if ($roundeddowngradepercentage >= $boundarybottom && $roundeddowngradepercentage <= $boundarytop) {
                // Grade is within this class.
                return $index;
            }
        }
        return null;
    }


    /**
     * Parse the value from the stored admin setting string.
     * Has been validated on entry.
     * @see admin_setting_autogradeboundaries::validate()
     * @param string $settingname
     * @return ?array
     */
    public static function get_config_setting(string $settingname): ?array {
        $setting = get_config('coursework', $settingname);
        if (!$setting) {
            return null;
        }
        $result = [];
        $lines = explode("\n", $setting);
        foreach ($lines as $line) {
            $parts = explode('|', $line);
            $result[] = [clean_param($parts[0], PARAM_LOCALISEDFLOAT), clean_param($parts[1], PARAM_LOCALISEDFLOAT)];
        }
        return $result;
    }

    /**
     * Get an example (default) setting text.
     * These are the grade class boundaries, in descending order.
     * @see admin_setting_autogradeboundaries
     * @return string
     */
    public static function get_example_setting(): string {
        return "70.00|100.00"
            . "\n60.00|69.99"
            . "\n50.00|59.99"
            . "\n40.00|49.99"
            . "\n1.00|39.99"
            . "\n0.00|0.99";
    }
}
