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
 * Observers
 *
 * @package    mod_coursework
 * @copyright  2016 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\event\group_member_added;
use core\event\group_member_removed;
use core\event\role_assigned;
use core\event\role_unassigned;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/coursework/lib.php');

class mod_coursework_observer {
    public static function autoallocate_when_user_added(core\event\role_assigned $event) {

        coursework_role_assigned_event_handler($event);
    }

    public static function autoallocate_when_user_removed(core\event\role_unassigned $event) {

        coursework_role_unassigned_event_handler($event);
    }

    public static function coursework_deadline_changed(mod_coursework\event\coursework_deadline_changed $event) {

        coursework_send_deadline_changed_emails($event);
    }

    public static function process_allocation_after_update(core\event\course_module_updated $event) {

        coursework_mod_updated($event);
    }

    public static function process_allocation_after_creation(core\event\course_module_created $event) {

        coursework_mod_updated($event);
    }

    /**
     * @param group_member_added $event
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function process_allocations_when_group_member_added(group_member_added $event) {
        course_group_member_added($event);
    }

    /**
     * @param group_member_removed $event
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function process_allocations_when_group_member_removed(group_member_removed $event) {
        course_group_member_removed($event);
    }

    /**
     * @param role_assigned $event
     */
    public static function add_teacher_to_dropdown_when_enrolled(core\event\role_assigned $event) {
        teacher_allocation_cache_purge($event);
    }

    /**
     * @param role_unassigned $event
     * @throws dml_exception
     */
    public static function remove_teacher_from_dropdown_when_unenrolled(core\event\role_unassigned $event) {
        teacher_removed_allocated_not_graded($event);
        teacher_allocation_cache_purge($event);
    }
}
