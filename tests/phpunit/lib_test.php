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

use mod_coursework\models\personaldeadline;
use stdClass;

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

        $now = time();
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

        $now = time();
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
     * Test coursework_get_coursemodule_info with positive deadline.
     */
    public function test_coursework_get_coursemodule_info_with_deadline(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $now = time();
        $course = $this->getDataGenerator()->create_course();
        $coursework = $this->getDataGenerator()->create_module('coursework', [
           'course' => $course->id,
           'deadline' => $now + DAYSECS,
        ]);

        $cm = get_coursemodule_from_instance('coursework', $coursework->id);
        $result = coursework_get_coursemodule_info($cm);

        $this->assertNotFalse($result);
        $this->assertEquals($coursework->name, $result->name);
        $this->assertTrue(isset($result->customdata['duedate']));
        $this->assertEquals($now + DAYSECS, $result->customdata['duedate']);
    }

    /**
     * Test coursework_get_coursemodule_info with personal deadline.
     */
    public function test_coursework_get_coursemodule_info_with_personal_deadline(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $now = time();
        $course = $this->getDataGenerator()->create_course();
        $coursework = $this->getDataGenerator()->create_module('coursework', [
            'course' => $course->id,
            'deadline' => $now + DAYSECS,
        ]);

        // Set personal deadlines enabled.
        $DB->set_field('coursework', 'personaldeadlineenabled', 1, ['id' => $coursework->id]);

        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id);

        $data = new stdClass();
        $data->allocatabletype = 'user';
        $data->allocatableid = $user->id;
        $data->courseworkid = $coursework->id;
        $data->personaldeadline = $now + WEEKSECS;
        $data->createdbyid = 1;
        personaldeadline::build($data)->save();

        $this->setUser($user);

        $cm = get_coursemodule_from_instance('coursework', $coursework->id);
        $result = coursework_get_coursemodule_info($cm);

        $this->assertNotFalse($result);
        $this->assertEquals($coursework->name, $result->name);
        $this->assertTrue(isset($result->customdata['duedate']));
        $this->assertEquals($now + WEEKSECS, $result->customdata['duedate']);

        $this->setUser($user2);

        $cm = get_coursemodule_from_instance('coursework', $coursework->id);
        $result = coursework_get_coursemodule_info($cm);
        $this->assertNotFalse($result);
        $this->assertEquals($coursework->name, $result->name);
        $this->assertTrue(isset($result->customdata['duedate']));
        $this->assertEquals($now + DAYSECS, $result->customdata['duedate']);
    }

    /**
     * Test coursework_get_coursemodule_info with empty/zero deadline.
     */
    public function test_coursework_get_coursemodule_info_with_empty_deadline(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $coursework = $this->getDataGenerator()->create_module('coursework', [
           'course' => $course->id,
           'deadline' => 0,
        ]);

        $cm = get_coursemodule_from_instance('coursework', $coursework->id);
        $result = coursework_get_coursemodule_info($cm);

        $this->assertNotFalse($result);
        $this->assertEquals($coursework->name, $result->name);
        $this->assertFalse(isset($result->customdata['duedate']));
    }

    /**
     * Test coursework_get_coursemodule_info with description shown.
     */
    public function test_coursework_get_coursemodule_info_with_description(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $now = time();
        $course = $this->getDataGenerator()->create_course();
        $coursework = $this->getDataGenerator()->create_module('coursework', [
           'course' => $course->id,
           'deadline' => $now + DAYSECS,
           'intro' => 'Test coursework intro',
           'introformat' => FORMAT_HTML,
        ]);

        $cm = get_coursemodule_from_instance('coursework', $coursework->id);
        $cm->showdescription = 1;

        $result = coursework_get_coursemodule_info($cm);

        $this->assertNotFalse($result);
        $this->assertEquals($coursework->name, $result->name);
        $this->assertNotEmpty($result->content);
        $this->assertTrue(isset($result->customdata['duedate']));
    }

    /**
     * Test coursework_get_coursemodule_info with description hidden.
     */
    public function test_coursework_get_coursemodule_info_without_description(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $now = time();
        $course = $this->getDataGenerator()->create_course();
        $coursework = $this->getDataGenerator()->create_module('coursework', [
           'course' => $course->id,
           'deadline' => $now + DAYSECS,
           'intro' => 'Test coursework intro',
           'introformat' => FORMAT_HTML,
        ]);

        $cm = get_coursemodule_from_instance('coursework', $coursework->id);
        $cm->showdescription = 0;

        $result = coursework_get_coursemodule_info($cm);

        $this->assertNotFalse($result);
        $this->assertEquals($coursework->name, $result->name);
        $this->assertFalse(isset($result->content) && !empty($result->content));
    }

    /**
     * Test coursework_get_coursemodule_info with invalid instance.
     */
    public function test_coursework_get_coursemodule_info_invalid_instance(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cm = new \stdClass();
        $cm->instance = 99999;

        $result = coursework_get_coursemodule_info($cm);

        $this->assertFalse($result);
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
        $event = new \stdClass();
        $event->name = 'Calendar event';
        $event->modulename = 'coursework';
        $event->courseid = $courseid;
        $event->instance = $instanceid;
        $event->type = CALENDAR_EVENT_TYPE_ACTION;
        $event->eventtype = $eventtype;
        $event->timestart = time();

        return \calendar_event::create($event);
    }
}
