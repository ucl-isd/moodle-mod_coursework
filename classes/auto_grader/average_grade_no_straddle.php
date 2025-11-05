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

use mod_coursework\allocation\allocatable;
use mod_coursework\models\coursework;
use core\exception\coding_exception;

/**
 * Class average_grade_no_straddle is responsible for calculating and applying the automatically agreed average grade
 * if the initial assessor grades are within a certain percentage of one another, and do not straddle class boundaries.
 *
 * @package mod_coursework\auto_grader
 */
class average_grade_no_straddle extends average_grade {

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

        $templateid = $this->get_coursework()->gradeboundarytemplateid;
        if (!$templateid) {
            return;
        }

        $gradeclasses = self::get_grade_class_boundaries((int)($templateid));
        if (empty($gradeclasses)) {
            return;
        }

        if (self::grades_straddle_class_boundaries($gradeclasses, $this->grades_as_percentages())) {
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
        $maxgrade = max($grades);
        $mingrade = min($grades);
        return ($maxgrade - $mingrade) <= $this->percentage;
    }

    /**
     * Do the grades awarded for this assessment straddle class boundaries?
     * @param array $gradeclasses the grade classes and boundaries.
     * @param array $grades the grade we are checking
     * @return bool
     */
    public static function grades_straddle_class_boundaries(array $gradeclasses, array $grades): bool {
        if (empty($gradeclasses) || empty($grades)) {
            // No class boundaries are set or no grades, so we are not applying this rule.
            return false;
        }

        $gradeclassesseen = [];
        foreach ($grades as $grade) {
            foreach ($gradeclasses as $index => $gradeclass) {
                if ($grade >= $gradeclass['bottom'] && $grade <= $gradeclass['top']) {
                    // Grade is within this class.
                    if (!in_array($index, $gradeclassesseen)) {
                        $gradeclassesseen[] = $index;
                    }
                    if (count($gradeclassesseen) > 1) {
                        // We have seen more than one grade class so the grades straddle class boundaries.
                        return true;
                    }
                }
            }
        }
        return false;
    }


    /**
     * Get the grade class boundaries that apply to this template.
     * @param int $templateid
     * @return array
     * @throws coding_exception
     */
    public static function get_grade_class_boundaries(int $templateid): array {
        global $DB;
        if ($templateid === 0) {
            // In this case, supply an initial set of hard coded default boundaries.
            // Site admin is expected to set correct values via admin UI.
            // So these will only be used for initial form population.
            return [
                ['bottom' => 70.00, 'top' => 100.00],
                ['bottom' => 60.00, 'top' => 69.99],
                ['bottom' => 50.00, 'top' => 59.99],
                ['bottom' => 40.00, 'top' => 49.99],
                ['bottom' => 1.00, 'top' => 39.99],
                ['bottom' => 0.00, 'top' => 0.99],
            ];
        }
        $records = $DB->get_records('coursework_class_boundaries', ['templateid' => $templateid], 'top DESC');
        if (empty($records)) {
            throw new coding_exception("No grade class boundaries found for template $templateid");
        }
        return array_map(
            function ($record) {
                return ['bottom' => $record->bottom, 'top' => $record->top];
            },
            $records
        );
    }


    /**
     * Save a set of bands for a given coursework to the database.
     * @param int $templateid template ID we are saving or zero if this is a new template.
     * @param array $bands
     * @return void
     */
    public static function save_grade_class_boundaries(int $templateid, string $title, array $bands) {
        global $DB;
        $DB->delete_records('coursework_class_boundaries', ['templateid' => $templateid]);
        usort(
            $bands,
            function($a, $b) {
                return ($a['bottom'] > $b['bottom']) ? -1 : 1;
            }
        );
        foreach ($bands as $band) {
            if (!$band['bottom'] && !$band['top']) {
                // Looks like we are intentionally leaving these fields blank.
                continue;
            }
            $DB->insert_record(
                'coursework_class_boundaries',
                ['templateid' => $templateid, 'bottom' => $band['bottom'], 'top' => $band['top']]
            );
        }
        $DB->set_field(
            'coursework_class_boundary_templates', 'name', $title, ['id' => $templateid]
        );
    }
}
