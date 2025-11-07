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

defined('MOODLE_INTERNAL') || die();

/**
 * Event handlers. Mostly for dealing with auto allocation of markers.
 *
 * @package    mod_coursework
 * @copyright  2012 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$observers = [

    [
        'eventname' => '\core\event\role_assigned',
        'callback' => 'mod_coursework_observer::autoallocate_when_user_added',
    ],
    [
        'eventname' => '\core\event\role_unassigned',
        'callback' => 'mod_coursework_observer::autoallocate_when_user_removed',
    ],
    [
        'eventname' => '\mod_coursework\event\coursework_deadline_changed',
        'callback' => 'mod_coursework_observer::coursework_deadline_changed',
        'schedule' => 'cron',
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => 'mod_coursework_observer::process_allocation_after_update',
    ],
    [
        'eventname' => '\core\event\course_module_created',
        'callback' => 'mod_coursework_observer::process_allocation_after_creation',
    ],
    [
        'eventname' => '\core\event\group_member_added',
        'callback' => 'mod_coursework_observer::process_allocations_when_group_member_added',
    ],
    [
        'eventname' => '\core\event\group_member_removed',
        'callback' => 'mod_coursework_observer::process_allocations_when_group_member_removed',
    ],
    [
        'eventname' => 'core\event\role_assigned',
        'callback' => 'mod_coursework_observer::add_teacher_to_dropdown_when_enrolled',
    ],
    [
        'eventname' => 'core\event\role_unassigned',
        'callback' => 'mod_coursework_observer::remove_teacher_from_dropdown_when_unenrolled',
    ],
];

