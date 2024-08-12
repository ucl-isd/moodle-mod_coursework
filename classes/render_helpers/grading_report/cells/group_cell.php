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
use mod_coursework\grading_table_row_base;
use mod_coursework\models\group;
use mod_coursework\models\user;

/**
 * Class group_cell
 */
class group_cell extends cell_base implements allocatable_cell {

    /**
     * @param grading_table_row_base $row_object
     * @throws coding_exception
     * @return string
     */
    public function get_table_cell($row_object) {
        $content = '';
        /**
         * @var group $group
         */
        $group = $row_object->get_allocatable();
        $content .= '<span class="group">'.$group->name().'</span>';
        $content .= '<br>';
        $content .= '<div class="group_style">';
        $content .= '<select>';


        if ($this->coursework->blindmarking_enabled() && !has_capability('mod/coursework:viewanonymous', $this->coursework->get_context()) && !$row_object->is_published()){
            $content .= '<option class="expand_members" selected="selected">'.get_string('membershidden','coursework').'</option>';
        } else{
            $content .= '<option class="expand_members" selected="selected">'.get_string('viewmembers','coursework').'</option>';
        }

        $cm = $this->coursework->get_course_module();
        foreach ($group->get_members($this->coursework->get_context(), $cm) as $group_member) {

            $content .= $this->add_group_member_name($group_member, $row_object);
        }
        $content .= '</select>';
        $content .= '</div>';
        $content .= '</ul class="group-members">';

        return $this->get_new_cell_with_class($content);
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options = array()) {

        //adding this line so that the sortable heading function will make a sortable link unique to the table
        //if tablename is set
        $tablename = (isset($options['tablename']))  ? $options['tablename']  : ''  ;

        return $this->helper_sortable_heading(get_string('tableheadgroups', 'coursework'),
                                              'groupname',
                                              $options['sorthow'],
                                              $options['sortby'],
                                              $tablename);
    }

    /**
     * @return string
     */
    public function get_table_header_class(){
        return 'tableheadgroups';
    }

    /**
     * @param grading_table_row_base $row_object
     * @param user $group_member
     * @return string
     */
    protected function add_group_member_name($group_member, $row_object) {
        $text = '<option>';
        if ($this->coursework->blindmarking_enabled() && !has_capability('mod/coursework:viewanonymous', $this->coursework->get_context()) && !$row_object->is_published()) {
            $text .= 'Hidden';
        } else {
            $text .= $group_member->profile_link(false) . ' ('. $group_member->email.')';
        }
        $text .= '</option>';
        return $text;
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'empty';
    }
}
