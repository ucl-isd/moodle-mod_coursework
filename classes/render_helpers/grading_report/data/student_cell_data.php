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
use mod_coursework\models\group;
use mod_coursework\models\user;
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
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function get_table_cell_data(grading_table_row_base $rowsbase): ?stdClass {
        $submissiontype = new stdClass();
        $allocatable = $rowsbase->get_allocatable();
        $hidden = $this->should_hide_identity();

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
        $data->id = $hidden ? '' : $group->id;
        $data->name = $group->name();
        $data->picture = $hidden ? '' :
            get_group_picture_url(group::get_cached_object_from_id($group->id()), $this->coursework->get_course_id());
        $data->members = $this->get_group_members($group, $hidden);
        return $data;
    }

    /**
     * Get group members data.
     *
     * @param group $group The group object
     * @param bool $hidden Whether identity should be hidden
     * @return array
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws coding_exception
     */
    private function get_group_members(group $group, bool $hidden): array {
        $members = [];
        $cm = $this->coursework->get_course_module();
        foreach ($group->get_members($this->coursework->get_context(), $cm) as $member) {
            $members[] = $hidden
                ? (object)['name' => $this->get_candidate_or_fallback($member->id(), 'membershidden'), 'url' => '#']
                : (object)[
                    'name' => $this->get_enhanced_name_with_candidate_number($member->id(), $member->name()),
                    'url' => $member->get_user_profile_url(),
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
     * @throws \dml_exception
     * @throws coding_exception
     */
    private function get_user_data(user $user, grading_table_row_base $rowsbase, bool $hidden): stdClass {
        if ($hidden) {
            return (object)[
                'id' => '',
                'name' => $this->get_candidate_or_fallback($rowsbase->get_allocatable_id(), 'hidden'),
                'url' => '',
            ];
        }
        return (object)[
            'id' => $rowsbase->get_allocatable_id(),
            'name' => $this->get_enhanced_name_with_candidate_number(
                $user->id(),
                $rowsbase->can_see_user_name()
                    ? $rowsbase->get_allocatable()->name()
                    : get_string('hidden', 'mod_coursework')
            ),
            'url' => $user->get_user_profile_url(),
        ];
    }

    /**
     * Get candidate number or fallback string if not available.
     *
     * @param int $userid The user ID
     * @param string $fallbackstring The fallback string identifier
     * @return string
     * @throws \dml_exception
     * @throws coding_exception
     */
    private function get_candidate_or_fallback(int $userid, string $fallbackstring): string {
        if (!get_config('mod_coursework', 'use_candidate_numbers_for_hidden_name')) {
            return get_string($fallbackstring, 'mod_coursework');
        }

        $candidatenumber = $this->get_candidate_number($userid);
        return $candidatenumber ?: get_string($fallbackstring, 'mod_coursework');
    }

    /**
     * Determine if the identity should be hidden.
     *
     * @return bool
     * @throws coding_exception
     */
    private function should_hide_identity() {
        return $this->coursework->blindmarking_enabled() &&
            !has_capability('mod/coursework:viewanonymous', $this->coursework->get_context());
    }

    /**
     * Get enhanced name with candidate number if applicable.
     *
     * @param int $userid The user ID
     * @param string $realname The real name of the user
     * @return string
     * @throws \dml_exception
     */
    private function get_enhanced_name_with_candidate_number(int $userid, string $realname): string {
        if (!get_config('mod_coursework', 'use_candidate_numbers_for_hidden_name')) {
            return $realname;
        }

        $candidatenumber = $this->get_candidate_number($userid);
        return $candidatenumber ? $candidatenumber . ' (' . $realname . ')' : $realname;
    }

    private function get_candidate_number(int $userid): ?string {
        $candidateprovidermanager = candidateprovider_manager::instance();
        if (!$candidateprovidermanager->is_provider_available()) {
            return null;
        }
        return $candidateprovidermanager->get_candidate_number($this->coursework->get_course_id(), $userid) ?: null;
    }
}
