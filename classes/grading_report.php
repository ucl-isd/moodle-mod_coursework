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

namespace mod_coursework;

use mod_coursework\models\allocation;
use mod_coursework\models\course_module;
use mod_coursework\models\coursework;
use mod_coursework\models\deadline_extension;
use mod_coursework\models\feedback;
use mod_coursework\models\module;
use mod_coursework\models\personaldeadline;
use mod_coursework\models\submission;
use mod_coursework\render_helpers\grading_report\cells\cell_interface;

/**
 * Renderable component containing all the data needed to display the grading report
 */
class grading_report {
    /**
     * Gets data for all students. Use bulk queries to aid performance
     *
     * @param coursework $coursework
     * @return grading_table_row_base[] row objects
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function get_table_rows_for_page(coursework $coursework): array {

        global $USER;
        $participants = $coursework->get_allocatables();
        $allsubmissionfiles = submission::get_all_submission_files_data($coursework);

        // Make tablerow objects so we can use the methods to check permissions and set things.
        $rows = [];
        $ability = new ability($USER->id, $coursework);

        $participantsfound = 0;

        foreach ($participants as $key => $participant) {
            // To save multiple queries to DB for extensions and deadlines, add them here.
            // New grading_table_row_base.
            $row = new grading_table_row_base(
                $coursework,
                $participant,
                $allsubmissionfiles[$participant->type() . "-" . $participant->id()] ?? [],
            );

            // Now, we skip the ones who should not be visible on this page.
            $canshow = $ability->can('show', $row);
            if (!$canshow && !isset($options['unallocated'])) {
                unset($participants[$key]);
                continue;
            }
            if ($canshow && isset($options['unallocated'])) {
                unset($participants[$key]);
                continue;
            }
            $rows[$participant->id()] = $row;
            $participantsfound++;
            if (!empty($rowcount) && $participantsfound >= $rowcount) {
                break;
            }
        }
        return $rows;
    }

    /**
     * Get the submission IDs that the user can see in this grading report.
     * @param coursework $coursework
     * @return int[]
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function get_visible_row_submission_ids(coursework $coursework): array {
        $visiblegradingreportrows = self::get_table_rows_for_page($coursework);
        $visiblesubmissionids = [];
        foreach ($visiblegradingreportrows as $visiblegradingreportrow) {
            $submission = $visiblegradingreportrow->get_submission();
            if ($submission) {
                $visiblesubmissionids[] = $submission->id();
            }
        }
        return $visiblesubmissionids;
    }

    /**
     *
     * @return array rendering options
     */
    public function get_options() {
        return $this->options;
    }
}
