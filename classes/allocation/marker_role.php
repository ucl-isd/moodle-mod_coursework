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

namespace mod_coursework\allocation;

use core\context;
use core\exception\moodle_exception;
use csv_import_reader;
use mod_coursework\models\coursework;
use stdClass;

/**
 * Helper for granting the configured marker role to teaching staff during allocation upload.
 *
 * @package    mod_coursework
 * @copyright  2026 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Vlad Kidanov <vlad.kidanov@catalyst-eu.net>
 */
class marker_role {
    /** @var context the coursework module context */
    private readonly context $context;

    /**
     * Constructor.
     *
     * @param coursework $coursework
     */
    public function __construct(
        /** @var coursework the coursework allocations are being uploaded to */
        private readonly coursework $coursework,
    ) {
        $this->context = $coursework->get_context();
    }

    /**
     * Options for the marker role admin setting: roles assignable at activity level.
     *
     * @return array The role id => role name, with 0 meaning "not configured".
     */
    public static function get_role_options(): array {
        $options = [0 => get_string('none')];
        $moduleroleids = get_roles_for_contextlevels(CONTEXT_MODULE);
        $roles = role_fix_names(get_all_roles(), null, ROLENAME_ORIGINAL);
        foreach ($roles as $role) {
            if (in_array($role->id, $moduleroleids)) {
                $options[$role->id] = $role->localname;
            }
        }
        return $options;
    }

    /**
     * The configured marker role, if any.
     *
     * @return stdClass|null
     */
    public function get_role(): ?stdClass {
        global $DB;

        $roleid = (int)get_config('mod_coursework', 'markerrole');
        if (!$roleid) {
            return null;
        }
        $role = $DB->get_record('role', ['id' => $roleid]);
        return $role ?: null;
    }

    /**
     * Localised name of the configured marker role.
     *
     * @return string
     */
    public function get_role_name(): string {
        $role = $this->get_role();
        return $role ? role_get_name($role, $this->context) : '';
    }

    /**
     * Whether the current user may grant the configured marker role in this coursework.
     *
     * @return bool
     */
    public function can_assign(): bool {
        $role = $this->get_role();
        if (!$role) {
            return false;
        }
        $assignable = get_assignable_roles($this->context, ROLENAME_ORIGINAL);
        return array_key_exists($role->id, $assignable);
    }

    /**
     * Whether a user is enrolled and holds one of the configured teaching roles in this coursework or its course.
     *
     * @param int $userid
     * @return bool
     */
    public function has_teaching_role(int $userid): bool {
        $teachingroleids = array_filter(explode(',', (string)get_config('mod_coursework', 'teachingroles')));
        if (empty($teachingroleids) || !is_enrolled($this->context, $userid)) {
            return false;
        }
        foreach (get_user_roles($this->context, $userid) as $assignment) {
            if (in_array($assignment->roleid, $teachingroleids)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Find markers named in an allocations csv who cannot mark this coursework but hold a teaching role in it.
     *
     * These users can be allocated once they have been granted the configured marker role.
     *
     * @param string $content
     * @param string $encoding
     * @param string $delimiter
     * @return stdClass[] user records keyed by id.
     */
    public function get_users_requiring_role(string $content, string $encoding, string $delimiter): array {
        global $DB;

        if (!$this->get_role()) {
            return [];
        }

        $identifier = get_config('mod_coursework', 'allocation_identifier');
        $markerids = array_keys(get_enrolled_users($this->context, 'mod/coursework:addinitialgrade', 0, 'u.id'));
        $stages = $this->coursework->get_max_markers();

        $iid = csv_import_reader::get_new_iid('courseworkallocationsdata');
        $csvreader = new csv_import_reader($iid, 'courseworkallocationsdata');
        $csvreader->load_csv_content($content, $encoding, $delimiter);
        $csvreader->init();

        $users = [];
        while ($line = $csvreader->next()) {
            foreach (array_filter(array_slice($line, 1, $stages)) as $value) {
                $user = $DB->get_record('user', [$identifier => $value, 'deleted' => 0], '*', IGNORE_MULTIPLE);
                $needsrole = $user
                    && !isset($users[$user->id])
                    && !in_array($user->id, $markerids)
                    && $this->has_teaching_role($user->id);
                if ($needsrole) {
                    $users[$user->id] = $user;
                }
            }
        }
        $csvreader->close();
        $csvreader->cleanup();

        return $users;
    }

    /**
     * Grant the configured marker role to the given users in this coursework.
     *
     * @param int[] $userids
     * @return void
     * @throws moodle_exception if the current user is not allowed to assign the role.
     */
    public function assign(array $userids): void {
        if (!$this->can_assign()) {
            throw new moodle_exception('nopermissiontoassignmarkerrole', 'mod_coursework');
        }
        $roleid = $this->get_role()->id;
        foreach ($userids as $userid) {
            role_assign($roleid, $userid, $this->context->id);
        }
    }
}
