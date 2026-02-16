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
 * @package mod_coursework
 * @author Andrew Hancox <andrewdchancox@googlemail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright 2025 UCL
 */

namespace mod_coursework;

use mod_coursework\models\coursework;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . "/phpunit/classes/restore_date_testcase.php");

final class restore_date_test extends \restore_date_testcase {
    /**
     * Sets things up for every test. We want all to clean up after themselves.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_smoketest_backup_restore(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $coursework = $generator->create_instance(['course' => $course->id,
            'grade' => 100]);

        $data = new \stdClass();
        $data->allocatableid = $user->id;
        $data->allocatabletype = 'user';
        $data->stageidentifier = 'assessor_1';
        $data->courseworkid = $coursework->id;
        $allocation = $generator->create_allocation($data);

        $data = new \stdClass();
        $data->courseworkid = $coursework->id;
        $data->userid = $user->id;
        $submission = $generator->create_submission($data, coursework::get_from_id($coursework->id));

        $data = new \stdClass();
        $data->submissionid = $submission->id;
        $data->assessorid = 65;
        $feedback = $generator->create_feedback($data);

        // Do backup and restore.
        $newcourseid = $this->backup_and_restore($course);

        $newcoursework = $DB->get_record('coursework', ['course' => $newcourseid]);

        $submissions = $DB->get_records('coursework_submissions', ['courseworkid' => $newcoursework->id]);
        $this->assertCount(1, $submissions);
        $this->assertEquals(2, $DB->count_records('coursework_submissions'));
        $submission = reset($submissions);

        $feedbacks = $DB->get_records('coursework_feedbacks', ['submissionid' => $submission->id]);
        $this->assertCount(1, $feedbacks);
        $this->assertEquals(2, $DB->count_records('coursework_feedbacks'));
    }
}
