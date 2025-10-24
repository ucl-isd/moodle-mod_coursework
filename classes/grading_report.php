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
use mod_coursework\models\feedback;
use mod_coursework\models\module;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use mod_coursework\render_helpers\grading_report\cells\cell_interface;
use mod_coursework\render_helpers\grading_report\sub_rows\sub_rows_interface;

/**
 * Renderable component containing all the data needed to display the grading report
 */
class grading_report {

    // Added constants to determine in what manner the report is loaded.
    const MODE_GET_ALL = 1;
    const MODE_GET_FIRST_RECORDS = 2;
    const MODE_GET_REMAIN_RECORDS = 3;

    /**
     * @var array rendering options
     */
    private $options;

    /**
     * Instance of coursework
     * @var models\coursework
     */
    private $coursework;

    /**
     * @var grading_table_row_base[] The rows to show on this page of the table.
     */
    private $tablerows;

    /**
     * @var int The total number of rows the user could see on all pages.
     */
    private $totalrows;

    /**
     * @var int The real total number of rows the user could see on all pages.
     */
    public $realtotalrows;

    /**
     * @var
     */
    private $subrows;

    /**
     * @var cell_interface[]
     */
    private $cells;

    /**
     * Grades in $data must be already rounded to the set number of decimals or must be null
     * (in which later case, the [mod_workshop, nullgrade] string shall be displayed)
     *
     * @param array $options
     * @param coursework $coursework
     */
    public function __construct(array $options, $coursework) {

        $options['courseworkid'] = $coursework->id;

        $this->options = $options;
        $this->coursework = $coursework;
        $this->fill_pool();
    }

    /**
     * Cache data for later use
     */
    protected function fill_pool() {
        global $DB;

        $courseworkid = $this->coursework->id;
        submission::fill_pool_coursework($courseworkid);
        coursework::fill_pool([$this->coursework]);
        course_module::fill_pool([$this->coursework->get_course_module()]);
        module::fill_pool($DB->get_records('modules', ['name' => 'coursework']));
        feedback::fill_pool_submissions($courseworkid, array_keys(submission::$pool[$courseworkid]['id']));
        allocation::fill_pool_coursework($courseworkid);
    }

    /**
     * Returns the associated coursework
     *
     * @return models\coursework
     */
    public function get_coursework() {
        return $this->coursework;
    }

    /**
     * @param $options
     * @return string
     */
    protected function construct_sort_function_name($options) {
        $methodname = 'sort_by_' . $options['sortby'];
        return $methodname;
    }

    /**
     * For use with usort(). Method called dynamically, so don't delete if unused. See construct_sort_function_name().
     *
     * @param $a
     * @param $b
     * @return int
     */
    private function sort_by_stringfield($a, $b) {

        $desc = $this->options['sorthow'] == 'DESC';

        $order = strnatcmp($a, $b);
        // Make it into a -1, 1 or 0.
        switch (true) {
            case $order < 0:
                $order = -1;
                break;

            case $order > 0:
                $order = 1;
                break;

            default:
                $order = 0;
        }

        return $desc ? -1 * $order : $order; // If desc, flip the order.
    }

    /**
     * For use with usort(). Method called dynamically, so don't delete if unused. See construct_sort_function_name().
     *
     * @param $afield
     * @param $bfield
     * @return int
     */
    private function sort_by_numberfield($afield, $bfield) {

        $desc = $this->options['sorthow'] == 'DESC';

        switch (true) {

            case $afield == $bfield:
                $result = 0;
                break;

            case $afield < $bfield:
                $result = -1;
                break;

            case $afield > $bfield:
                $result = 1;
                break;

            default:
                $result = 0;
        }

        // The first (ASC) sort needs to show more recent stuff.
        return $desc ? $result : -1 * $result;
    }

    /**
     * For use with usort.
     * Method called dynamically, so don't delete if unused. See construct_sort_function_name().
     *
     * @param grading_table_row_base $a
     * @param grading_table_row_base $b
     * @return int
     */
    public function sort_by_firstname($a, $b) {
        $sort = $this->sort_by_stringfield($a->get_student_firstname(), $b->get_student_firstname());
        return $sort;
    }

    /**
     * For use with usort.
     * Method called dynamically, so don't delete if unused. See construct_sort_function_name().
     *
     * @param grading_table_row_base $a
     * @param grading_table_row_base $b
     * @return int
     */
    private function sort_by_finalgrade($a, $b) {
        $sort = $this->sort_by_numberfield($a->get_final_grade(), $b->get_final_grade());
        return $sort;
    }

    /**
     * For use with usort.
     * Method called dynamically, so don't delete if unused. See construct_sort_function_name().
     *
     * @param grading_table_row_base $a
     * @param grading_table_row_base $b
     * @return int
     */
    private function sort_by_timesubmitted($a, $b) {
        $sort = $this->sort_by_numberfield($a->get_time_submitted(), $b->get_time_submitted());
        return $sort;
    }

    /**
     * For use with usort.
     * Method called dynamically, so don't delete if unused. See construct_sort_function_name().
     *
     * @param grading_table_row_base $a
     * @param grading_table_row_base $b
     * @return int
     */
    private function sort_by_hash($a, $b) {
        $sort = $this->sort_by_stringfield($a->get_filename_hash(), $b->get_filename_hash());
        return $sort;
    }

    /**
     * For use with usort.
     * Method called dynamically, so don't delete if unused. See construct_sort_function_name().
     *
     * @param grading_table_row_base $a
     * @param grading_table_row_base $b
     * @return int
     */
    public function sort_by_lastname($a, $b) {
        return $this->sort_by_stringfield($a->get_student_lastname(), $b->get_student_lastname());
    }

    /**
     * For use with usort.
     * Method called dynamically, so don't delete if unused. See construct_sort_function_name().
     *
     * @param grading_table_row_base $a
     * @param grading_table_row_base $b
     * @return int
     */
    public function sort_by_groupname($a, $b) {
        return $this->sort_by_stringfield($a->get_allocatable()->name, $b->get_allocatable()->name);
    }

    /**
     * For use with usort.
     * Method called dynamically, so don't delete if unused. See construct_sort_function_name().
     *
     * @param grading_table_row_base $a
     * @param grading_table_row_base $b
     * @return int
     */
    public function sort_by_personaldeadline($a, $b) {
        $courseworkdeadline = $this->get_coursework()->deadline;
        $sort = $this->sort_by_numberfield(
            $a->get_personal_deadline_time() ?? $courseworkdeadline,
            $b->get_personal_deadline_time() ?? $courseworkdeadline
        );
        return $sort;
    }

    /**
     * Tells us whether there are any students who need a final grade still.
     *
     * @return bool
     */
    public function all_graded() {

        foreach ($this->tablerows as $row) {
            if (!$row->has_final_agreed_grade()) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param cell_interface $cell
     */
    public function add_cell($cell) {
        $this->cells[] = $cell;
    }

    /**
     * @return cell_interface[]
     */
    public function get_cells_helpers() {
        return $this->cells;
    }

    /**
     * @return sub_rows_interface
     */
    public function get_sub_row_helper() {
        return $this->subrows;
    }

    /**
     * @param $rowsstrategy
     */
    public function add_sub_rows($rowsstrategy) {
        $this->subrows = $rowsstrategy;
    }

    /**
     * Counts the total number of students that the current user can see.
     *
     * @return int
     */
    public function get_participant_count() {

        if (!isset($this->totalrows)) {
            $this->get_table_rows_for_page();
        }
        return $this->totalrows;
    }

    /**
     * Gets data for all students. Use bulk queries to aid performance
     *
     * @return grading_table_row_base[] row objects
     */
    public function get_table_rows_for_page($rowcount = false) {

        global $USER;

        if (!isset($this->tablerows)) { // Cache so we can count the rows before we need them.
            $options = $this->options;

            $participants = $this->coursework->get_allocatables();

            // Make tablerow objects so we can use the methods to check permissions and set things.
            $rows = [];
            $rowclass = $this->coursework->has_multiple_markers()
                ? 'mod_coursework\grading_table_row_multi' : 'mod_coursework\grading_table_row_single';
            $ability = new ability($USER->id, $this->get_coursework());

            $participantsfound = 0;

            foreach ($participants as $key => $participant) {

                // handle 'Group mode' - unset groups/individuals thaat are not in the chosen group
                if (!empty($options['group']) && $options['group'] != -1) {
                    if ($this->coursework->is_configured_to_have_group_submissions()) {
                        if ($options['group'] != $participant->id) {
                            continue;
                        }
                    } else {
                        if (!$this->coursework->student_in_group($participant->id, $options['group'])) {
                            continue;
                        }
                    }
                }

                $row = new $rowclass($this->coursework, $participant);

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
            // Sort the rows.
            $methodname = 'sort_by_' . $options['sortby'];
            if (method_exists($this, $methodname)) {
                usort($rows,
                    [$this,
                        $methodname]);
            }

            // Some will have submissions and therefore data fields. Others will have those fields null.
            /* @var grading_table_row_base[] $tablerows */

            $counter = count($rows);
            $this->realtotalrows = $counter;
            $mode = empty($this->options['mode']) ? self::MODE_GET_ALL : $this->options['mode'];
            if ($mode != self::MODE_GET_ALL) {
                $perpage = $this->options['perpage'] ?? 10;
                if ($counter > $perpage) {
                    if ($mode == self::MODE_GET_FIRST_RECORDS) {
                        $rows = array_slice($rows, 0, $perpage);
                    } else if ($mode == self::MODE_GET_REMAIN_RECORDS) {
                        $rows = array_slice($rows, $perpage);
                    }
                }
            }

            $this->tablerows = $rows;
            $this->totalrows = count($rows);
        }

        return $this->tablerows;
    }

    /**
     *
     * @return array rendering options
     */
    public function get_options() {
        return $this->options;
    }

}
