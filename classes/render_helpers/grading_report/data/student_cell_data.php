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

use coding_exception;
use mod_coursework\candidateprovider_manager;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\coursework;
use mod_coursework\models\group;
use mod_coursework\models\user;
use stdClass;

/**
 * Class student_cell_data provides data for student cell in tr template.
 *
 */
class student_cell_data extends cell_data_base {
    private bool $hidestudentidentities;
    private bool $viewanonymouscap;

    public function __construct(coursework $coursework) {
        parent::__construct($coursework);

        $this->hidestudentidentities = $this->coursework->hide_student_identities();
        $this->viewanonymouscap = has_capability('mod/coursework:viewanonymous', $this->coursework->get_context());
    }

    /**
     * Get the data for the student cell.
     *
     * @param grading_table_row_base $rowsbase
     * @return stdClass|null The data object for template rendering.
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function get_table_cell_data(grading_table_row_base $rowsbase): ?stdClass {
        $allocatable = $rowsbase->get_allocatable();

        if ($allocatable instanceof group) {
            return (object)['group' => $this->get_group_data($allocatable)];
        } else {
            return (object)['user' => $this->get_user_data($allocatable)];
        }
    }

    /**
     * Get group data for template.
     *
     * @param group $group The group object
     * @param bool $hidden Whether identity should be hidden
     * @return stdClass
     */
    private function get_group_data(group $group): stdClass {
        $cm = $this->coursework->get_course_module();

        $data = new stdClass();
        $data->id = $this->hidestudentidentities ? '' : $group->id;
        $data->name = $group->name();
        $data->picture = $this->hidestudentidentities ? '' : get_group_picture_url($group, $this->coursework->get_course_id());
        $data->members = [];

        foreach ($group->get_members($this->coursework->get_context(), $cm) as $member) {
            $data->members[] = $this->get_user_data($member);
        }

        return $data;
    }

    /**
     * Get user data for template.
     *
     * @param user $user The user object
     * @param grading_table_row_base $rowsbase The row base object
     * @param bool $hidden Whether identity should be hidden
     * @return stdClass
     * @throws \dml_exception
     * @throws coding_exception
     */
    private function get_user_data(user $user): stdClass {
        return (object)[
            'id' => $this->hidestudentidentities ? '' : $user->id,
            'name' => $this->get_user_display_name($user),
            'url' => $this->hidestudentidentities ? '' : $user->get_user_profile_url(),
        ];
    }

    private function get_user_display_name(user $user): string {
        $realname = $user->name();

        if (!$this->coursework->blindmarking_enabled()) {
            return $realname;
        }

        if (!get_config('mod_coursework', 'use_candidate_numbers_for_hidden_name')) {
            return $this->viewanonymouscap ? $realname : get_string('hidden', 'mod_coursework');
        }

        $candidatenumber = candidateprovider_manager::instance()->get_candidate_number($this->coursework->get_course_id(), $user->id);

        if ($this->viewanonymouscap) {
            return empty($candidatenumber) ? $realname : $candidatenumber . ' (' . $realname . ')';
        }

        return empty($candidatenumber) ? get_string('hidden', 'mod_coursework') : $candidatenumber;
    }
}
