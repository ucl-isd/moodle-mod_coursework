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
 * Class to handle interaction with core calendar.
 * @package    mod_coursework
 * @copyright  2024 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework;

/**
 * Class to handle interaction with core calendar.
 *
 * @package mod_coursework
 */
class calendar {
    /**
     * Get an object to create a calendar event for coursework.
     * @param object $coursework
     * @param string $eventtype
     * @param int|null $deadline
     * @return \stdClass
     */
    public static function coursework_event(object $coursework, string $eventtype, ?int $deadline): \stdClass {

        $event = new \stdClass();
        $event->type = CALENDAR_EVENT_TYPE_ACTION;

        // Description field needs some formatting in same way a mod_assign does it.
        // Convert the links to pluginfile. It is a bit hacky but at this stage the files
        // might not have been saved in the module area yet.
        if ($draftid = file_get_submitted_draft_itemid('introeditor')) {
            $intro = file_rewrite_urls_to_pluginfile($coursework->intro, $draftid);
        } else {
            $intro = $coursework->intro;
        }

        $cm = get_coursemodule_from_instance('coursework', $coursework->id);

        // We need to remove the links to files as the calendar is not ready
        // to support module events with file areas.
        $intro = strip_pluginfile_content($intro);
        if ($cm->showdescription) {
            $event->description = array(
                'text' => $intro,
                'format' => $coursework->introformat
            );
        } else {
            $event->description = array(
                'text' => '',
                'format' => $coursework->introformat
            );
        }

        $event->courseid = $coursework->course;
        $event->name = $coursework->name;
        $event->groupid = 0;
        $event->userid = 0;
        $event->modulename = 'coursework';
        $event->instance = $coursework->id;
        $event->eventtype = $eventtype;
        $event->timestart = $deadline;
        $event->timeduration = 0;
        $event->timesort = $deadline;
        $event->visible = instance_is_visible('coursework', $coursework);

        return $event;
    }


    /**
     * @param object $coursework
     * @param string $eventtype if null then will remove all
     * @return void
     * @throws \dml_exception
     */
    public static function remove_event($coursework, $eventtype = '') {
        global $DB;

        $params = array('modulename' => 'coursework', 'instance' => $coursework->id);

        if ($eventtype) {
            $params['eventtype'] = $eventtype;
        }

        $events = $DB->get_records('event', $params);
        foreach ($events as $eventid) {
            $event = \calendar_event::load($eventid->id);
            $event->delete(); // delete events from mdl_event table
        }
    }
}
