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

namespace mod_coursework;

use mod_coursework\models\submission;
use mod_coursework\models\user;

/**
 * Unit tests for the ability class.
 *
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class ability_test is responsible for testing the ability class to make sure the mechanisms work.
 * @group mod_coursework
 */
final class ability_test extends \advanced_testcase {
    use test_helpers\factory_mixin;

    public function setUp(): void {
        parent::setUp();
        $this->setAdminUser();
        $this->resetAfterTest();
    }

    public function test_allow_saves_rules(): void {
        $ability = new ability($this->create_a_teacher()->id(), $this->create_a_coursework());
        $this->assertTrue($ability->can('show', $this->get_coursework()));
    }

    public function test_ridiculous_things_are_banned_by_default_if_not_mentioned(): void {
        $ability = new ability($this->create_a_teacher()->id(), $this->create_a_coursework());
        $this->assertFalse($ability->can('set_fire_to', $this->get_coursework()));
    }

    /**
     * A manager can release the mark of a submission which is ready to publish.
     *
     * @covers \mod_coursework\ability::can
     */
    public function test_manager_can_release_submission_ready_to_publish(): void {
        $this->resetAfterTest();
        $submission = $this->create_a_marked_submission();

        $ability = new ability($this->create_a_manager()->id(), $this->get_coursework());
        $this->assertTrue($ability->can('release', $submission));
    }

    /**
     * A teacher without the publish capability cannot release the mark of a submission.
     *
     * @covers \mod_coursework\ability::can
     */
    public function test_teacher_cannot_release_submission(): void {
        $this->resetAfterTest();
        $submission = $this->create_a_marked_submission();

        $ability = new ability($this->create_another_teacher()->id(), $this->get_coursework());
        $this->assertFalse($ability->can('release', $submission));
    }

    /**
     * A manager cannot release the mark of a submission which has not been marked yet.
     *
     * @covers \mod_coursework\ability::can
     */
    public function test_manager_cannot_release_submission_not_marked(): void {
        $this->resetAfterTest();
        $this->create_a_coursework(['numberofmarkers' => 1]);
        $this->create_a_submission_for_the_student();
        $this->get_submission()->update_attribute('finalisedstatus', submission::FINALISED_STATUS_FINALISED);
        $submission = submission::get_from_id($this->get_submission()->id);

        $ability = new ability($this->create_a_manager()->id(), $this->get_coursework());
        $this->assertFalse($ability->can('release', $submission));
    }

    /**
     * A manager cannot release the mark of a submission which has already been released.
     *
     * @covers \mod_coursework\ability::can
     */
    public function test_manager_cannot_release_submission_already_published(): void {
        $this->resetAfterTest();
        $submission = $this->create_a_marked_submission();
        $submission->publish();
        $submission = submission::get_from_id($submission->id);

        $ability = new ability($this->create_a_manager()->id(), $this->get_coursework());
        $this->assertFalse($ability->can('release', $submission));
    }

    /**
     * Creates a single marker coursework with a finalised submission marked and finalised by a teacher.
     *
     * @return submission
     */
    private function create_a_marked_submission(): submission {
        $this->create_a_coursework(['numberofmarkers' => 1]);
        $this->create_a_submission_for_the_student();
        $this->get_submission()->update_attribute('finalisedstatus', submission::FINALISED_STATUS_FINALISED);
        $this->create_an_assessor_feedback_for_the_submission($this->create_a_teacher());

        return submission::get_from_id($this->get_submission()->id);
    }

    /**
     * Creates a user enrolled as a manager on the course.
     *
     * @return user
     */
    private function create_a_manager(): user {
        $manager = user::get_from_id($this->getDataGenerator()->create_user()->id);
        $this->enrol_as_manager($manager);

        return $manager;
    }
}
