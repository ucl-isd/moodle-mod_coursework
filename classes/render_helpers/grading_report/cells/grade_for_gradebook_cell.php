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

namespace mod_coursework\render_helpers\grading_report\cells;
use coding_exception;
use html_table_cell;
use mod_coursework\ability;
use mod_coursework\grade_judge;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\user;

/**
 * Class feedback_cell
 */
class grade_for_gradebook_cell extends cell_base {

    /**
     * @param grading_table_row_base $row_object
     * @return string
     */
    public function get_table_cell($rowobject) {
        global $USER;

        $content = '';
        $ability = new ability(user::find($USER), $rowobject->get_coursework());
        $judge = new grade_judge($this->coursework);
        if ($ability->can('show', $judge->get_feedback_that_is_promoted_to_gradebook($rowobject->get_submission())) && !$rowobject->get_submission()->editable_final_feedback_exist()) {
            $grade = $judge->get_grade_capped_by_submission_time($rowobject->get_submission());
            $content .= $judge->grade_to_display($grade);
        }
        return $this->get_new_cell_with_class($content);
    }

    /**
     * @param array $options
     * @throws coding_exception
     * @return string
     */
    public function get_table_header($options  = []) {
        return get_string('provisionalgrade', 'mod_coursework');
    }

    /**
     * @return string
     */
    public function get_table_header_class() {
        return 'provisionalgrade';
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'grades';
    }

    /**
     * @return string
     */
    public function get_table_header_help_icon() {
        global $OUTPUT;
        return ($OUTPUT->help_icon('provisionalgrade', 'mod_coursework'));
    }

}
