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

use cache;
use mod_coursework\models\coursework;
use mod_coursework\models\deadline_extension;
use mod_coursework\models\feedback;
use mod_coursework\models\personaldeadline;
use mod_coursework\models\plagiarism_flag;
use mod_coursework\models\submission;
use mod_coursework\stages\assessor;
use mod_coursework\stages\final_agreed;

/**
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class ability_test is responsible for testing the ability class to make sure the mechanisms work.
 * @group mod_coursework
 */
final class cache_test extends \advanced_testcase
{
    use test_helpers\factory_mixin;

    const NON_EXISTENT_ID = 999999111111;

    /**
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function setUp(): void {
        parent::setUp();
        $this->setAdminUser();
        $this->resetAfterTest();
        $this->coursework = $this->create_a_coursework(
            ['name' => 'CW cache test', 'numberofmarkers' => 2, 'plagiarismflagenabled' => 1]
        );
        $this->student = $this->create_a_student();
        $this->teacher = $this->create_a_teacher();
        $this->otherteacher = $this->create_another_teacher();
    }

    /**
     * Test cache for coursework class.
     * @return void
     * @throws \core\exception\coding_exception
     * @throws \core\exception\invalid_parameter_exception
     * @throws \dml_exception
     */
    public function test_coursework_cache(): void {
        $this->assertNull(coursework::get_from_id(self::NON_EXISTENT_ID));

        $cwtest = coursework::get_from_id($this->coursework->id);
        $this->assertNotNull($cwtest);

        $this->assertEquals($this->coursework->id, $cwtest->id);
        $this->assertEquals($this->coursework->name, $cwtest->name);

        $cache = cache::make('mod_coursework', coursework::CACHE_AREA_IDS);
        $cachedcw = $cache->get($this->coursework->id);
        $this->assertEquals($this->coursework->id, $cachedcw->id);
        $this->assertEquals($this->coursework->name, $cachedcw->name);

        $cwtest->update_attribute('name', 'CW cache test name amended');
        $cwtest2 = coursework::get_from_id($this->coursework->id);
        $this->assertEquals($this->coursework->id, $cwtest2->id);
        $this->assertEquals('CW cache test name amended', $cwtest2->name);

        $cwtest->clear_cache();
        $this->assertFalse($cache->get($this->coursework->id));

        $cwtest2->destroy();
        $this->assertNull(coursework::get_from_id($cwtest2->id));
    }

    /**
     * Test cache for submission class
     * @return void
     * @throws \core\exception\coding_exception
     * @throws \core\exception\invalid_parameter_exception
     * @throws \dml_exception
     */
    public function test_submission_cache(): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');

        $this->assertNull(submission::get_from_id(self::NON_EXISTENT_ID));

        $submission = new \stdClass();
        $submission->userid = $this->student->id;
        $submission = $generator->create_submission($submission, $this->coursework);

        // Set timecreated to special value so we can check later.
        $timecreated = 12345;
        $DB->set_field('coursework_submissions', 'timecreated', $timecreated, ['id' => $submission->id]);
        $submission->timecreated = $timecreated;

        $submissiontest = submission::get_from_id($submission->id);
        $this->assertNotNull($submissiontest);

        $this->assertEquals($submission->id, $submissiontest->id);
        $this->assertEquals($timecreated, $submissiontest->timecreated);

        $cache = cache::make('mod_coursework', submission::CACHE_AREA_IDS);
        $cachedsubmission = $cache->get($submission->id);
        $this->assertEquals($submission->id, $cachedsubmission->id);
        $this->assertEquals($submission->timecreated, $cachedsubmission->timecreated);

        $submission->update_attribute('timecreated', '54321');
        $submission2 = submission::get_from_id($submission->id);
        $this->assertEquals($submission->id, $submission2->id);
        $this->assertEquals('54321', $submission2->timecreated);

        $submissiontest->clear_cache();
        $this->assertFalse($cache->get($submissiontest->id));

        $submission2->destroy();
        $this->assertNull(coursework::get_from_id($submission2->id));
    }

    /**
     * Test cache for feedback class.
     * @return void
     * @throws \core\exception\coding_exception
     * @throws \core\exception\invalid_parameter_exception
     * @throws \dml_exception
     */
    public function test_feedback_cache(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');

        $submission = new \stdClass();
        $submission->userid = $this->student->id;
        $submission = $generator->create_submission($submission, $this->coursework);

        // Assessor one feedback.
        $feedbackdata = new \stdClass();
        $feedbackdata->submissionid = $submission->id;
        $feedbackdata->grade = 54;
        $feedbackdata->assessorid = $this->teacher->id;
        $feedbackdata->stageidentifier = assessor::STAGE_ASSESSOR_1;
        $feedbackdata->feedbackcomment = 'Assessor 1 feedback';
        $feedbackone = $generator->create_feedback($feedbackdata);

        // Assessor two feedback.
        $feedbackdata = new \stdClass();
        $feedbackdata->submissionid = $submission->id;
        $feedbackdata->grade = 59;
        $feedbackdata->assessorid = $this->otherteacher->id;
        $feedbackdata->stageidentifier = assessor::STAGE_ASSESSOR_2;
        $feedbackdata->feedbackcomment = 'Assessor 2 feedback';
        $feedbacktwo = $generator->create_feedback($feedbackdata);

        // Agreed feedback.
        $feedbackdata = new \stdClass();
        $feedbackdata->submissionid = $submission->id;
        $feedbackdata->grade = 60;
        $feedbackdata->assessorid = $this->otherteacher->id;
        $feedbackdata->stageidentifier = final_agreed::STAGE_FINAL_AGREED_1;
        $feedbackdata->feedbackcomment = 'Final agreed feedback';
        $feedbackagreed = $generator->create_feedback($feedbackdata);

        $this->assertEmpty(feedback::get_all_for_submission(self::NON_EXISTENT_ID));

        $feedbacks = feedback::get_all_for_submission($submission->id);
        $this->assertEquals(3, count($feedbacks));
        $this->assertEquals($feedbacks[$feedbackone->id], $feedbackone);
        $this->assertEquals($feedbacks[$feedbacktwo->id], $feedbacktwo);
        $this->assertEquals($feedbacks[$feedbackagreed->id], $feedbackagreed);

        $cache = cache::make('mod_coursework', feedback::CACHE_AREA_IDS);
        $feedbackonetest = $cache->get($feedbackone->id);
        $this->assertEquals('Assessor 1 feedback', $feedbackonetest->feedbackcomment);
        feedback::get_from_id($feedbackone->id)->clear_cache();
        $this->assertFalse($cache->get($feedbackone->id));

        $feedbackone->destroy();
        $this->assertEquals(2, count(feedback::get_all_for_submission($submission->id)));
        $feedbacktwo->destroy();
        $this->assertEquals(1, count(feedback::get_all_for_submission($submission->id)));
        $feedbackagreed->destroy();
        $this->assertEquals(0, count(feedback::get_all_for_submission($submission->id)));
    }

    /**
     * Test cache for plagiarism flag cache.
     * @return void
     * @throws \core\exception\coding_exception
     * @throws \core\exception\invalid_parameter_exception
     * @throws \dml_exception
     */
    public function test_plagiarism_flag_cache(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');

        $submission = new \stdClass();
        $submission->userid = $this->student->id;
        $submission = $generator->create_submission($submission, $this->coursework);

        $flag = plagiarism_flag::build(
            [
                'courseworkid' => $this->coursework->id,
                'submissionid' => $submission->id,
                'status' => 0,
                'comment' => 'AI generated',
                'createdby' => $this->teacher->id,
            ]
        );
        $flag->save();

        $flagtest = plagiarism_flag::get_for_submission($submission->id);
        $this->assertEquals($flag, $flagtest);
        $flagtest->update_attribute('status', '1');
        $this->assertEquals('1', plagiarism_flag::get_from_id($flag->id)->status);

        $this->assertNull(plagiarism_flag::get_from_id(self::NON_EXISTENT_ID));
        $this->assertNull(
            plagiarism_flag::get_for_submission(self::NON_EXISTENT_ID)
        );
    }

    /**
     * Test cache for personal deadline class.
     * @return void
     * @throws \core\exception\coding_exception
     * @throws \core\exception\invalid_parameter_exception
     * @throws \dml_exception
     */
    public function test_personal_deadline_cache(): void {
        $newdeadline = strtotime('+1 week', time());
        $deadline = personaldeadline::build(
            [
                'courseworkid' => $this->coursework->id,
                'allocatableid' => $this->student->id,
                'allocatabletype' => 'user',
                'personaldeadline' => $newdeadline,
                'createdbyid' => $this->teacher->id,
            ]
        );
        $deadline->save();

        $deadlinetest = personaldeadline::get_for_allocatable(
            $this->coursework->id,
            $this->student->id,
            'user'
        );
        $this->assertEquals($deadline, $deadlinetest);
        $deadlinetest->update_attribute('personaldeadline', $newdeadline + 1000);
        $this->assertEquals($newdeadline + 1000, personaldeadline::get_from_id($deadline->id)->personaldeadline);

        $deadlinetest->destroy();
        $this->assertNull(coursework::get_from_id($deadlinetest->id));

        $this->assertNull(personaldeadline::get_from_id(self::NON_EXISTENT_ID));
        $this->assertNull(
            personaldeadline::get_for_allocatable(
                $this->coursework->id,
                self::NON_EXISTENT_ID,
                'user'
            )
        );
    }

    /**
     * Test for deadline extension class.
     * @return void
     * @throws \core\exception\coding_exception
     * @throws \core\exception\invalid_parameter_exception
     * @throws \dml_exception
     */
    public function test_deadline_extension_cache(): void {
        $newextension = strtotime('+1 week', time());
        $extension = deadline_extension::build(
            [
                'courseworkid' => $this->coursework->id,
                'allocatableid' => $this->student->id,
                'allocatabletype' => 'user',
                'extended_deadline' => $newextension,
                'createdbyid' => $this->teacher->id,
            ]
        );
        $extension->save();

        $extensiontest = deadline_extension::get_for_allocatable(
            $this->coursework->id,
            $this->student->id,
            'user'
        );
        $this->assertEquals($extension, $extensiontest);
        $extensiontest->update_attribute('extended_deadline', $newextension + 1000);
        $this->assertEquals($newextension + 1000, deadline_extension::get_from_id($extension->id)->extended_deadline);

        $extensiontest->destroy();
        $this->assertNull(coursework::get_from_id($extensiontest->id));

        $this->assertNull(deadline_extension::get_from_id(self::NON_EXISTENT_ID));
        $this->assertNull(
            deadline_extension::get_for_allocatable(
                $this->coursework->id,
                self::NON_EXISTENT_ID,
                'user'
            )
        );
    }

    /**
     * Test for allocation class.
     * @return void
     */
    public function test_allocation_cache(): void {
        throw new \Exception("Not yet implemented");
    }

    /**
     * Test for assessment_set_membership class.
     * @return void
     */
    public function test_assessment_set_membership_cache(): void {
        throw new \Exception("Not yet implemented");
    }
}
