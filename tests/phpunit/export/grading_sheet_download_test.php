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
 * Unit tests for the grading sheet download
 *
 * @package    mod_coursework
 * @copyright  2015 University of London Computer Centre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace phpunit\export;

use mod_coursework\export\coding_exception;
use stdClass;

/**
 * Unit test for downloading grading sheet.
 *
 * @property mixed feedback_data
 * @property mixed csv
 * @group mod_coursework
 */
final class grading_sheet_download_test extends \advanced_testcase {

    use \mod_coursework\test_helpers\factory_mixin;

    public function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();

        $this->setAdminUser();

        $this->student = $this->create_a_student();
        $this->otherstudent = $this->create_another_student();
        $this->teacher = $this->create_a_teacher();
        $this->otherteacher = $this->create_another_teacher();

        // If we don't do this, we end up with the same cached objects for all tests and they may have incorrect/missing properties.
        \mod_coursework\models\coursework::$pool = null;
        \mod_coursework\models\submission::$pool = null;
        \mod_coursework\models\feedback::$pool = null;
    }

    /**
     * One stage only, no allocation, one student, coursework submitted but not graded
     * @throws coding_exception
     */
    public function test_one_stage_no_allocations(): void {

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $params = [
             'grade' => 100,
             'numberofmarkers' => 1,
             'deadline' => time() + 86400,
        ];
        $coursework = $this->create_a_coursework($params);
        $submission = new stdClass();
        $submission->userid = $this->student->id;
        $submission->allocatableid = $this->student->id;
        $submission = $generator->create_submission($submission, $coursework);

        $student = $this->student;

        // Headers and data for csv.
        $csvcells = ['submissionid', 'submissionfileid', 'name', 'username', 'submissiontime', 'singlegrade', 'feedbackcomments'];

        $timestamp = date('d_m_y @ H-i');
        $filename = get_string('gradingsheetfor', 'coursework'). $coursework->name .' '.$timestamp;
        $gradingsheet = new \mod_coursework\export\grading_sheet($coursework, $csvcells, $filename);
        $actualsubmission = $gradingsheet->add_csv_data($submission);

        $studentname = $student->lastname .' '.$student->firstname;

        // Build an array.
        $expectedsubmission = [
            '0' => $submission->id,
            '1' => $coursework->get_username_hash($student->id),
            '2' => $studentname,
            '3' => $student->username,
            '4' => 'On time',
            '5' => '',
            '6' => '',
        ];

        $this->assertEquals($expectedsubmission, $actualsubmission[0]);
    }

    /**
     * Two stages with allocation, two students, both submissions made
     * student1 graded by assessor2, student2 graded by assessor1 and assessor2
     * @throws coding_exception
     */
    public function test_two_stages_with_allocations(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $params = [
            'grade' => 100,
            'numberofmarkers' => 2,
            'allocationenabled' => 1,
            'deadline' => time() + 86400,
        ];
        $coursework = $this->create_a_coursework($params);

        // 2 assessors
        $assessor1 = $this->teacher;
        $assessor2 = $this->otherteacher;
        // 2students
        $student1 = $this->student;
        $student2 = $this->otherstudent;

        // Submissions.
        $submission1 = new stdClass();
        $submission1->userid = $student1->id;
        $submission1->allocatableid = $student1->id;
        $submission1 = $generator->create_submission($submission1, $coursework);

        $submission2 = new stdClass();
        $submission2->userid = $student2->id;
        $submission2->allocatableid = $student2->id;
        $submission2 = $generator->create_submission($submission2, $coursework);

        // Assessor2 feedback for student1.
        $feedbackdata1 = new stdClass();
        $feedbackdata1->submissionid = $submission1->id;
        $feedbackdata1->grade = 54;
        $feedbackdata1->feedbackcomment = 'abc';
        $feedbackdata1->assessorid = $assessor2->id;
        $feedbackdata1->stage_identifier = 'assessor_2';
        $feedback1 = $generator->create_feedback($feedbackdata1);

        // Assessor1 feedback for student2.
        $feedbackdata2 = new stdClass();
        $feedbackdata2->submissionid = $submission2->id;
        $feedbackdata2->grade = 60;
        $feedbackdata2->feedbackcomment = 'abc';
        $feedbackdata2->assessorid = $assessor1->id;
        $feedbackdata2->stage_identifier = 'assessor_1';
        $feedback2 = $generator->create_feedback($feedbackdata2);

        // Assessor2 feedback for student2.
        $feedbackdata3 = new stdClass();
        $feedbackdata3->submissionid = $submission2->id;
        $feedbackdata3->grade = 65;
        $feedbackdata3->feedbackcomment = 'abc';
        $feedbackdata3->assessorid = $assessor2->id;
        $feedbackdata3->stage_identifier = 'assessor_2';
        $feedback3 = $generator->create_feedback($feedbackdata3);

        // Agreed grade feedback.
        $feedbackdata4 = new stdClass();
        $feedbackdata4->submissionid = $submission2->id;
        $feedbackdata4->grade = 62;
        $feedbackdata4->feedbackcomment = '<p>abc’s feedback</p>';
        $feedbackdata4->assessorid = $assessor2->id;
        $feedbackdata4->stage_identifier = 'final_agreed_1';
        $feedback4 = $generator->create_feedback($feedbackdata4);

        // Headers and data for csv.
        $csvcells = ['submissionid', 'submissionfileid', 'name', 'username', 'submissiontime',
                           'assessor1', 'assessorgrade1', 'assessorfeedback1', 'assessor2', 'assessorgrade2', 'assessorfeedback2',
                           'agreedgrade', 'agreedfeedback'];

        $timestamp = date('d_m_y @ H-i');
        $filename = get_string('gradingsheetfor', 'coursework'). $coursework->name .' '.$timestamp;
        $gradingsheet = new \mod_coursework\export\grading_sheet($coursework, $csvcells, $filename);
        $actualsubmission1 = $gradingsheet->add_csv_data($submission1);
        $actualsubmission2 = $gradingsheet->add_csv_data($submission2);
        $actualsubmission = array_merge($actualsubmission1, $actualsubmission2);

        $studentname1 = $student1->lastname .' '.$student1->firstname;
        $studentname2 = $student2->lastname .' '.$student2->firstname;

        $assessor1name = $assessor1->lastname .' '. $assessor1->firstname;
        $assessor2name = $assessor2->lastname .' '. $assessor2->firstname;

        // Build an array.
        $expectedsubmission = [
            [
                $submission1->id,
                $coursework->get_username_hash($student1->id),
                $studentname1,
                $student1->username,
                'On time',
                $assessor1name,
                '',
                '',
                $assessor2name,
                $feedbackdata1->grade,
                $feedbackdata1->feedbackcomment,
                '',
                '',
            ],
            [
                $submission2->id,
                $coursework->get_username_hash($student2->id),
                $studentname2,
                $student2->username,
                'On time',
                $assessor2name,
                $feedbackdata2->grade,
                $feedbackdata2->feedbackcomment,
                $assessor1name,
                $feedbackdata3->grade,
                $feedbackdata3->feedbackcomment,
                $feedbackdata4->grade,
                'abc\'s feedback',
            ],
        ];

        $this->assertEquals($expectedsubmission, $actualsubmission);
    }
}
