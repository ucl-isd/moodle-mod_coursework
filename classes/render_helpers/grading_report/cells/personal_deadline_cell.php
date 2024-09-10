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
use mod_coursework\ability;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\personal_deadline;
use mod_coursework\models\user;
use pix_icon;

/**
 * Class personal_deadline_cell
 */
class personal_deadline_cell extends cell_base {

    /**
     * @param grading_table_row_base $row_object
     * @throws coding_exception
     * @return string
     */
    public function get_table_cell($rowobject) {
        global $OUTPUT, $USER;

        $coursework = $rowobject->get_coursework();
        $deadline = $coursework->get_deadline();
        $content = '<div class="show_personal_dealine">';

        $newpersonaldeadlineparams = [
            'allocatableid' => $rowobject->get_allocatable()->id(),
            'allocatabletype' => $rowobject->get_allocatable()->type(),
            'courseworkid' => $rowobject->get_coursework()->id,
        ];

        $personaldeadline = personal_deadline::find_or_build($newpersonaldeadlineparams);
        if ($personaldeadline->personal_deadline) {
            $deadline = $personaldeadline->personal_deadline;
        }
        $date = userdate($deadline, '%a, %d %b %Y, %H:%M');
        $content .= '<div class="content_personal_deadline">'.$date.'</div>';
        $ability = new ability(user::find($USER, false), $rowobject->get_coursework());
        $class = 'edit_personal_deadline';
        if (!$ability->can('edit', $personaldeadline)) {
            $class .= ' display-none';
        }

        $link = $this->get_router()->get_path('edit personal deadline', $newpersonaldeadlineparams);
        // $link = '/';
        $icon = new pix_icon('edit', 'Edit personal deadline', 'coursework');
        $newpersonaldeadlineparams['multipleuserdeadlines'] = 0;

        $content .= $OUTPUT->action_icon($link,
            $icon,
            null,
            ['class' => $class, 'data-get' => json_encode($newpersonaldeadlineparams), 'data-time' => date('d-m-Y H:i', $deadline) ]);
        $content .= '</div><div class="show_edit_personal_dealine display-none"> </div>';

        return $this->get_new_cell_with_order_data(['display' => $content, '@data-order' => $deadline]);
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options  = []) {

        $tablename = (!empty($options['tablename'])) ? $options['tablename'] : '';

        return $this->helper_sortable_heading(get_string('tableheadpersonaldeadline', 'coursework'),
            'personaldeadline',
            $options['sorthow'],
            $options['sortby'],
            $tablename);
    }

    /**
     * @return string
     */
    public function get_table_header_class() {
        return 'tableheadpersonaldeadline';
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'empty';
    }
}
