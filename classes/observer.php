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
use mod_coursework\models\coursework;
use mod_coursework\task\process_auto_allocations_task;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/coursework/lib.php');

class mod_coursework_observer {
    public static function coursework_deadline_changed(mod_coursework\event\coursework_deadline_changed $event) {

        coursework_send_deadline_changed_emails($event);
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

    /**
     * @param \core\event\base $event
     * @throws dml_exception
     */
    public static function queue_process_auto_allocations_task(\core\event\base $event) {
        $context = $event->get_context();

        if (
            $context->contextlevel == CONTEXT_MODULE
            &&
            $cm = get_coursemodule_from_id('coursework', $context->instanceid)
        ) {
            $courseworks = [coursework::get_from_id($cm->instance)];
        } else if ($context->contextlevel == CONTEXT_COURSE) {
            $courseworks = coursework::find_all(['course' => $context->instanceid]);
        } else {
            return;
        }

        $processautoallocationssync = get_config('mod_coursework', 'process_auto_allocations_sync');
        $adminid = get_admin()->id;

        foreach ($courseworks as $coursework) {
            $task = new process_auto_allocations_task();
            $task->set_userid($adminid);
            $task->set_custom_data(['courseworkid' => $coursework->id]);

            if ($processautoallocationssync) {
                $task->execute();
            } else {
                \core\task\manager::queue_adhoc_task($task, true);
            }
        }
    }
}
