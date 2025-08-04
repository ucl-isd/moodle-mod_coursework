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
 * Data provider for user cell in grading report.
 *
 * @package    mod_coursework
 * @copyright  2025 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */

namespace mod_coursework\render_helpers\grading_report\data;

use mod_coursework\grading_table_row_base;
use mod_coursework\models\group;
use mod_coursework\models\user;
use moodle_url;
use stdClass;

/**
 * Class student_cell_data provides data for student cell in tr template.
 *
 */
class student_cell_data extends cell_data_base {
    /**
     * Get the data for the student cell.
     *
     * @param grading_table_row_base $rowsbase
     * @return stdClass|null The data object for template rendering.
     */
    public function get_table_cell_data(grading_table_row_base $rowsbase): ?stdClass {
        $submissiontype = new stdClass();
        $allocatable = $rowsbase->get_allocatable();
        $hidden = $this->should_hide_identity($rowsbase);

        if ($allocatable instanceof group) {
            $submissiontype->group = $this->get_group_data($allocatable, $hidden);
        } else if ($allocatable instanceof user) {
            $submissiontype->user = $this->get_user_data($allocatable, $rowsbase, $hidden);
        }

        return $submissiontype;
    }

    /**
     * Get group data for template.
     *
     * @param group $group The group object
     * @param bool $hidden Whether identity should be hidden
     * @return stdClass
     */
    private function get_group_data(group $group, bool $hidden): stdClass {
        $data = new stdClass();
        $data->name = $group->name();
        $data->picture = $hidden ? '' :
            get_group_picture_url($group->get_object($group->id()), $this->coursework->get_course_id());
        $data->members = $this->get_group_members($group, $hidden);
        return $data;
    }

    /**
     * Get group members data.
     *
     * @param group $group The group object
     * @param bool $hidden Whether identity should be hidden
     * @return array
     */
    private function get_group_members(group $group, bool $hidden): array {
        $members = [];
        $cm = $this->coursework->get_course_module();
        foreach ($group->get_members($this->coursework->get_context(), $cm) as $member) {
            $members[] = (object)[
                'name' => $hidden ? get_string('membershidden', 'coursework') : $member->name() . ' ('. $member->email.')',
                'url' => $hidden ? '#' : $member->get_user_profile_url()
            ];
        }
        return $members;
    }

    /**
     * Get user data for template.
     *
     * @param user $user The user object
     * @param grading_table_row_base $rowsbase The row base object
     * @param bool $hidden Whether identity should be hidden
     * @return stdClass
     */
    private function get_user_data(user $user, grading_table_row_base $rowsbase, bool $hidden): stdClass {
        return (object)[
            'name' => $rowsbase->get_user_name(),
            'url' => $hidden ? '' : $user->get_user_profile_url(),
            'picture' => $hidden ? '' : $user->get_user_picture_url()
        ];
    }

    /**
     * Determine if the identity should be hidden.
     *
     * @param grading_table_row_base $rowsbase
     * @return bool
     * @throws \coding_exception
     */
    private function should_hide_identity(grading_table_row_base $rowsbase) {
        return $this->coursework->blindmarking_enabled() &&
            !has_capability('mod/coursework:viewanonymous', $this->coursework->get_context())
            && !$rowsbase->is_published();
    }
}
