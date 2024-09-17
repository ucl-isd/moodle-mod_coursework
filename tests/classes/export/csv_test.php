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
 * Unit tests for the csv class
 *
 * @package    mod_coursework
 * @copyright  2012 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\export\csv;
use mod_coursework\models\submission;
use mod_coursework\models\deadline_extension;

defined('MOODLE_INTERNAL') || die();

/**
 * @property mixed feedback_data
 * @property mixed csv
 * @group mod_coursework
 */
final class csv_test extends advanced_testcase {

    use mod_coursework\test_helpers\factory_mixin;

    public function setUp(): void {
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();
        $this->student = $this->create_a_student();
        $this->teacher = $this->create_a_teacher();
        $this->otherteacher = $this->create_another_teacher();

        // If we don't do this, we end up with the same cached objects for all tests and they may have incorrect/missing properties. has numberofmarkers = 1.
        // In that case we fail the test - the agreed mark for test 2 is not picked up as we only appear to have one marker.
        \mod_coursework\models\coursework::$pool = null;
    }

    /**
     * One stage only, extension enabled
     * @throws coding_exception
     */
    public function test_one_stage(): void {
        $dateformat = '%a, %d %b %Y, %H:%M';
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');

        $coursework = $generator->create_instance(
            [
                'course' => $this->course->id,
                'grade' => 100,
                'numberofmarkers' => 1,
                'deadline' => time() + 86400,
                'extensionsenabled' => 1,
            ]
        );
        $submission = new stdClass();
        $submission->userid = $this->student->id;
        $submission->allocatableid = $this->student->id;
        $submission = $generator->create_submission($submission, $coursework);

        $student = $this->student;
        $assessor = $this->teacher;

        $feedbackdata = new stdClass();
        $feedbackdata->submissionid = $submission->id;
        $feedbackdata->grade = 54;
        $feedbackdata->assessorid = $assessor->id;
        $feedbackdata->stage_identifier = 'assessor_1';
        $feedback = $generator->create_feedback($feedbackdata);

        $extendiondeadline = time();
        $params = [
            'allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
            'courseworkid' => $coursework->id,
            'pre_defined_reason' => 1,
            'createdbyid' => 4,
            'extra_information_text' => '<p>extra information</p>',
            'extra_information_format' => 1,
            'extended_deadline' => $extendiondeadline,
        ];

        $extension = deadline_extension::create($params);

        $extensionreasons = $coursework->extension_reasons();

        if (empty($extensionreasons)) {

            set_config('coursework_extension_reasons_list', "coursework extension \n sick leave");
            $extensionreasons = $coursework->extension_reasons();

        }

        // Headers and data for csv.
        $csvcells = ['name', 'username', 'submissiondate', 'submissiontime', 'submissionfileid'];

        if ($coursework->extensions_enabled()) {
            $csvcells[] = 'extensiondeadline';
            $csvcells[] = 'extensionreason';
            $csvcells[] = 'extensionextrainfo';
        }
        $csvcells[] = 'stages';
        $csvcells[] = 'finalgrade';

        $timestamp = date('d_m_y @ H-i');
        $filename = get_string('finalgradesfor', 'coursework'). $coursework->name .' '.$timestamp;
        $csv = new \mod_coursework\export\csv($coursework, $csvcells, $filename);
        $csvgrades = $csv->add_cells_to_array($submission, $student, $csvcells);

        // Build an array.
        $studentname = $student->lastname .' '.$student->firstname;
        $assessorname = $assessor->lastname .' '. $assessor->firstname;
        $assessorusername = $assessor->username;

        $oneassessorgrades = [
            '0' => $studentname,
            '1' => $student->username,
            '2' => userdate(time(), $dateformat),
            '3' => 'On time',
            '4' => $coursework->get_username_hash($submission->allocatableid),
            '5' => userdate($extension->extended_deadline, $dateformat),
            '6' => $extensionreasons[1],
            '7' => 'extra information',
            '8' => $feedback->grade,
            '9' => $assessorname,
            '10' => $assessorusername,
            '11' => userdate(time(), $dateformat),
            '12' => $feedback->grade,
        ];

        $this->assertEquals($oneassessorgrades, $csvgrades);
    }

    /**
     * Two stages with final agreed grade, extension not enabled
     */
    public function test_two_stages(): void {
        $timenow = time();
        $dateformat = '%a, %d %b %Y, %H:%M';
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');

        $coursework = $generator->create_instance(
            [
                'course' => $this->course->id,
                'grade' => 100,
                'numberofmarkers' => 2,
                'deadline' => strtotime('-1 day', $timenow), // This means that the submissions will be late.
            ]
        );

        $submission = new stdClass();
        $submission->userid = $this->student->id;
        $submission = $generator->create_submission($submission, $coursework);

        $student = $this->student;
        $assessor1 = $this->teacher;
        $assessor2 = $this->otherteacher;

        // Assessor one feedback.
        $feedbackdata1 = new stdClass();
        $feedbackdata1->submissionid = $submission->id;
        $feedbackdata1->grade = 54;
        $feedbackdata1->assessorid = $assessor1->id;
        $feedbackdata1->stage_identifier = 'assessor_1';
        $feedback1 = $generator->create_feedback($feedbackdata1);

        // Assessor two feedback.
        $feedbackdata2 = new stdClass();
        $feedbackdata2->submissionid = $submission->id;
        $feedbackdata2->grade = 60;
        $feedbackdata2->assessorid = $assessor2->id;
        $feedbackdata2->stage_identifier = 'assessor_2';
        $feedback2 = $generator->create_feedback($feedbackdata2);

        // Agreed grade feedback.
        $feedbackdata3 = new stdClass();
        $feedbackdata3->submissionid = $submission->id;
        $feedbackdata3->grade = 58;
        $feedbackdata3->assessorid = $assessor1->id;
        $feedbackdata3->stage_identifier = 'final_agreed_1';
        $feedbackdata3->lasteditedbyuser = $assessor1->id;
        $feedback3 = $generator->create_feedback($feedbackdata3);

        // Headers and data for csv.
        $csvcells = ['name', 'username', 'submissiondate', 'submissiontime',
            'submissionfileid'];

        if ($coursework->extensions_enabled()) {
            $csvcells[] = 'extensiondeadline';
            $csvcells[] = 'extensionreason';
            $csvcells[] = 'extensionextrainfo';
        }
        $csvcells[] = 'stages';
        $csvcells[] = 'finalgrade';

        $timestamp = date('d_m_y @ H-i');
        $filename = get_string('finalgradesfor', 'coursework'). $coursework->name .' '.$timestamp;
        $csv = new \mod_coursework\export\csv($coursework, $csvcells, $filename);
        $csvgrades = $csv->add_cells_to_array($submission, $student, $csvcells);

        // Build an array.
        $studentname = $student->lastname . ' ' . $student->firstname;
        $assessorname1 = $assessor1->lastname . ' ' . $assessor1->firstname;
        $assessorname2 = $assessor2->lastname . ' ' . $assessor2->firstname;

        $assessorusername1 = $assessor1->username;
        $assessorusername2 = $assessor2->username;

        $twoassessorsgrades = [
            '0' => $studentname,
            '1' => $student->username,
            '2' => userdate($timenow, $dateformat),
            '3' => 'Late',
            '4' => $coursework->get_username_hash($submission->allocatableid),
            '5' => (float)$feedback1->grade,
            '6' => $assessorname1,
            '7' => $assessorusername1,
            '8' => userdate($timenow, $dateformat),
            '9' => (float)$feedback2->grade,
            '10' => $assessorname2,
            '11' => $assessorusername2,
            '12' => userdate($timenow, $dateformat),
            '13' => (float)$feedback3->grade,
            '14' => $assessorname1,
            '15' => $assessorusername1,
            '16' => userdate($timenow, $dateformat),
            '17' => (float)$feedback3->grade,
        ];
        $this->assertEqualsCanonicalizing($twoassessorsgrades, $csvgrades);

    }

    /**
     * Sampling enabled, student not in sample, extension not enabled
     */
    public function test_student_not_in_sample(): void {

        $dateformat = '%a, %d %b %Y, %H:%M';
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');

        $coursework = $generator->create_instance(
            [
                'course' => $this->course->id,
                'grade' => 100,
                'numberofmarkers' => 2,
                'samplingenabled' => 1,
                'deadline' => time() + 86400,
            ]
        );
        $submission = new stdClass();
        $submission->userid = $this->student->id;
        $submission = $generator->create_submission($submission, $coursework);

        $student = $this->student;
        $assessor1 = $this->teacher;

        // Assessor one feedback.
        $feedbackdata = new stdClass();
        $feedbackdata->submissionid = $submission->id;
        $feedbackdata->grade = 54;
        $feedbackdata->assessorid = $assessor1->id;
        $feedbackdata->stage_identifier = 'assessor_1';
        $feedback = $generator->create_feedback($feedbackdata);

        // Headers and data for csv.
        $csvcells = ['name', 'username', 'submissiondate', 'submissiontime',
            'submissionfileid'];

        if ($coursework->extensions_enabled()) {
            $csvcells[] = 'extensiondeadline';
            $csvcells[] = 'extensionreason';
            $csvcells[] = 'extensionextrainfo';
        }
        $csvcells[] = 'stages';
        $csvcells[] = 'finalgrade';

        $timestamp = date('d_m_y @ H-i');
        $filename = get_string('finalgradesfor', 'coursework'). $coursework->name .' '.$timestamp;
        $csv = new \mod_coursework\export\csv($coursework, $csvcells, $filename);
        $csvgrades = $csv->add_cells_to_array($submission, $student, $csvcells);

        // Build an array.
        $studentname = $student->lastname .' '.$student->firstname;
        $assessorname1 = $assessor1->lastname .' '. $assessor1->firstname;

        $assessorusername1 = $assessor1->username;

        $grades = [
            '0' => $studentname,
            '1' => $student->username,
            '2' => userdate(time(), $dateformat),
            '3' => 'On time',
            '4' => $coursework->get_username_hash($submission->allocatableid),
            '5' => $feedback->grade,
            '6' => $assessorname1,
            '7' => $assessorusername1,
            '8' => userdate(time(), $dateformat),
            '9' => '',
            '10' => '',
            '11' => '',
            '12' => '',
            '13' => '',
            '14' => '',
            '15' => '',
            '16' => '',
            '17' => $feedback->grade,
        ];

        $this->assertEquals($grades, $csvgrades);
    }

    /**
     * Two students but only one is double marked and should have agreed grade, extension not enabled
     */
    public function test_two_students_one_in_sample(): void {
        global $DB;
        $dateformat = '%a, %d %b %Y, %H:%M';
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');

        $coursework = $generator->create_instance(
            [
                'course' => $this->course->id,
                'grade' => 100,
                'numberofmarkers' => 2,
                'samplingenabled' => 1,
                'deadline' => time() + 86400,
            ]
        );
        $student1 = $this->student;
        $assessor1 = $this->teacher;
        $assessor2 = $this->otherteacher;
        $submission1 = new stdClass();
        $submission1->userid = $student1->id;
        $submission1->allocatableid = $student1->id;
        $submission1 = $generator->create_submission($submission1, $coursework);

        $student2 = $this->create_a_student();
        $submission2 = new stdClass();
        $submission2->userid = $student2->id;
        $submission2->allocatableid = $student2->id;
        $submission2 = $generator->create_submission($submission2, $coursework);

        // Student 2 manual sampling enabled.
        $setmembersdata = new stdClass();
        $setmembersdata->courseworkid = $coursework->id;
        $setmembersdata->allocatableid = $submission2->allocatableid;
        $setmembersdata->allocatabletype = 'user';
        $setmembersdata->stage_identifier = 'assessor_2';

        $DB->insert_record('coursework_sample_set_mbrs', $setmembersdata);

        // Assessor one feedback for student 1.
        $feedbackdata1 = new stdClass();
        $feedbackdata1->submissionid = $submission1->id;
        $feedbackdata1->grade = 54;
        $feedbackdata1->assessorid = $assessor1->id;
        $feedbackdata1->stage_identifier = 'assessor_1';
        $feedback1 = $generator->create_feedback($feedbackdata1);

        // Assessor one feedback for student 2.
        $feedbackdata2 = new stdClass();
        $feedbackdata2->submissionid = $submission2->id;
        $feedbackdata2->grade = 60;
        $feedbackdata2->assessorid = $assessor1->id;
        $feedbackdata2->stage_identifier = 'assessor_1';
        $feedback2 = $generator->create_feedback($feedbackdata2);

        // Assessor two feedback for student 2.
        $feedbackdata3 = new stdClass();
        $feedbackdata3->submissionid = $submission2->id;
        $feedbackdata3->grade = 50;
        $feedbackdata3->assessorid = $assessor2->id;
        $feedbackdata3->stage_identifier = 'assessor_2';
        $feedback3 = $generator->create_feedback($feedbackdata3);

        // Agreed grade feedback.
        $feedbackdata4 = new stdClass();
        $feedbackdata4->submissionid = $submission2->id;
        $feedbackdata4->grade = 58;
        $feedbackdata4->assessorid = $assessor2->id;
        $feedbackdata4->stage_identifier = 'final_agreed_1';
        $feedbackdata4->lasteditedbyuser = $assessor2->id;
        $feedback4 = $generator->create_feedback($feedbackdata4);

        // Headers and data for csv.
        $csvcells = ['name', 'username', 'submissiondate', 'submissiontime',
            'submissionfileid'];

        if ($coursework->extensions_enabled()) {
            $csvcells[] = 'extensiondeadline';
            $csvcells[] = 'extensionreason';
            $csvcells[] = 'extensionextrainfo';
        }
        $csvcells[] = 'stages';
        $csvcells[] = 'finalgrade';

        $timestamp = date('d_m_y @ H-i');
        $filename = get_string('finalgradesfor', 'coursework'). $coursework->name .' '.$timestamp;
        $csv = new \mod_coursework\export\csv($coursework, $csvcells, $filename);
        $array1 = $csv->add_cells_to_array($submission1, $student1, $csvcells);
        $array2 = $csv->add_cells_to_array($submission2, $student2, $csvcells);

        $csvgrades = array_merge($array1, $array2);

        // Build an array.
        $studentname1 = $student1->lastname .' '.$student1->firstname;
        $studentname2 = $student2->lastname .' '.$student2->firstname;
        $assessorname1 = $assessor1->lastname .' '. $assessor1->firstname;
        $assessorname2 = $assessor2->lastname .' '. $assessor2->firstname;

        $assessorusername1 = $assessor1->username;
        $assessorusername2 = $assessor2->username;

        $assessorsgrades = [
            '0' => $studentname1,
            '1' => $student1->username,
            '2' => userdate(time(), $dateformat),
            '3' => 'On time',
            '4' => $coursework->get_username_hash($submission1->allocatableid),
            '5' => $feedback1->grade,
            '6' => $assessorname1,
            '7' => $assessorusername1,
            '8' => userdate(time(), $dateformat),
            '9' => '',
            '10' => '',
            '11' => '',
            '12' => '',
            '13' => '',
            '14' => '',
            '15' => '',
            '16' => '',
            '17' => $feedback1->grade,
            '18' => $studentname2,
            '19' => $student2->username,
            '20' => userdate(time(), $dateformat),
            '21' => 'On time',
            '22' => $coursework->get_username_hash($submission2->allocatableid),
            '23' => $feedback2->grade,
            '24' => $assessorname1,
            '25' => $assessorusername1,
            '26' => userdate(time(), $dateformat),
            '27' => $feedback3->grade,
            '28' => $assessorname2,
            '29' => $assessorusername2,
            '30' => userdate(time(), $dateformat),
            '31' => $feedback4->grade,
            '32' => $assessorname2,
            '33' => $assessorusername2,
            '34' => userdate(time(), $dateformat),
            '35' => $feedback4->grade,
        ];
        $this->assertEquals($assessorsgrades, $csvgrades);
    }
}

