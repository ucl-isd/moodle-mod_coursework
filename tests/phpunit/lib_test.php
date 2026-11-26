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
 * Unit tests for mod/coursework/lib.php.
 *
 * @package    mod_coursework
 * @copyright  2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Leon Stringer <leon.stringer@ucl.ac.uk>
 */

namespace mod_coursework;

/**
 * Unit tests for mod/coursework/lib.php.
 *
 * @copyright  2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends \advanced_testcase {
    /**
     * Test calendar event initialgradingdue.
     */
    public function test_coursework_core_calendar_provide_event_action_initialgradingdue(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $clock = $this->mock_clock_with_frozen();
        $now = $clock->time();
        $course = $this->getDataGenerator()->create_course();
        $coursework = $this->getDataGenerator()->create_module('coursework', [
            'course' => $course->id,
            'deadline' => $now + DAYSECS,
            'markingdeadlineenabled' => 1,
            'initialmarkingdeadline' => $now + WEEKSECS,
        ]);
        $event = $this->create_action_event($course->id, $coursework->id, 'initialgradingdue');

        $factory = new \core_calendar\action_factory();
        $actionevent = mod_coursework_core_calendar_provide_event_action($event, $factory);
    }

    /**
     * Test calendar event agreedgradingdue.
     */
    public function test_coursework_core_calendar_provide_event_action_agreedgradingdue(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $clock = $this->mock_clock_with_frozen();
        $now = $clock->time();
        $course = $this->getDataGenerator()->create_course();
        $coursework = $this->getDataGenerator()->create_module('coursework', ['course' => $course->id,
            'numberofmarkers' => 2,
            'deadline' => $now + DAYSECS,
            'markingdeadlineenabled' => 1,
            'initialmarkingdeadline' => $now + WEEKSECS,
            'agreedgrademarkingdeadline' => $now + (2 * DAYSECS),
        ]);
        $event = $this->create_action_event($course->id, $coursework->id, 'agreedgradingdue');

        $factory = new \core_calendar\action_factory();
        $actionevent = mod_coursework_core_calendar_provide_event_action($event, $factory);
    }

    /**
     * Test that saving the activity settings does not change the "Feedback for all students" release date.
     *
     * @covers ::coursework_update_instance
     */
    public function test_coursework_update_instance_keeps_general_feedback_release_date(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/coursework/lib.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $releasedate = time() + (3 * WEEKSECS);
        $coursework = $this->getDataGenerator()->create_module('coursework', [
            'course' => $course->id,
            'deadline' => time() + WEEKSECS,
            'generalfeedback' => $releasedate,
        ]);

        $data = $DB->get_record('coursework', ['id' => $coursework->id]);
        $data->instance = $coursework->id;
        $data->coursemodule = $coursework->cmid;
        $data->name = 'Renamed coursework';
        unset($data->generalfeedback);

        $sink = $this->redirectEvents();
        coursework_update_instance($data);
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof event\coursework_deadline_changed);
        $sink->close();

        $record = $DB->get_record('coursework', ['id' => $coursework->id]);
        $this->assertEquals('Renamed coursework', $record->name);
        $this->assertEquals($releasedate, $record->generalfeedback);
        $this->assertEmpty($events);
    }

    /**
     * Creates an action event.
     *
     * @param int $courseid The course id.
     * @param int $instanceid The Coursework id.
     * @param string $eventtype The event type.
     * @return bool|calendar_event
     */
    private function create_action_event($courseid, $instanceid, $eventtype) {
        $clock = $this->mock_clock_with_frozen();
        $event = new \stdClass();
        $event->name = 'Calendar event';
        $event->modulename = 'coursework';
        $event->courseid = $courseid;
        $event->instance = $instanceid;
        $event->type = CALENDAR_EVENT_TYPE_ACTION;
        $event->eventtype = $eventtype;
        $event->timestart = $clock->time();

        return \calendar_event::create($event);
    }
}
