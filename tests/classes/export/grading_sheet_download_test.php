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
 * @package    mod/coursework
 * @copyright  2015 University of London Computer Centre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * @property mixed feedback_data
 * @property mixed csv
 */
class grading_sheet_download_test extends advanced_testcase {


    use mod_coursework\test_helpers\factory_mixin;

    public function setUp() {

        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();

        $this->setAdminUser();

        $this->student = $this->create_a_student();
        $this->other_student = $this->create_another_student();
        $this->teacher = $this->create_a_teacher();
        $this->other_teacher = $this->create_another_teacher();

    }

    /**
     * One stage only, no allocation, one student, coursework submitted but not graded
     * @throws coding_exception
     */
    public function test_one_stage_no_allocations(){

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');

        /* @var mod_coursework_generator $generator */
        $this->coursework = $generator->create_instance(array('course' => $this->course->id,
                                                             'grade' => 100,
                                                             'numberofmarkers' => 1,
                                                             'deadline'=>time()+86400));
        $this->submission = new stdClass();
        $this->submission->userid = $this->student->id;
        $this->submission->allocatableid = $this->student->id;
        $this->submission = $generator->create_submission($this->submission, $this->coursework);

        $student = $this->student;
        $submission = $this->submission;

        // headers and data for csv
        $csv_cells = array('submissionid','submissionfileid','name','username','submissiontime','singlegrade','feedbackcomments');

        $timestamp = date('d_m_y @ H-i');
        $filename = get_string('gradingsheetfor', 'coursework'). $this->coursework->name .' '.$timestamp;
        $grading_sheet = new \mod_coursework\export\grading_sheet($this->coursework, $csv_cells, $filename);
        $actual_submission = $grading_sheet->add_cells_to_array($submission,$student,$csv_cells);

        $studentname = $student->lastname .' '.$student->firstname;

        // build an array
        $expected_submission = array('0' => $submission->id,
                                     '1' => $this->coursework->get_username_hash($student->id),
                                     '2' => $studentname,
                                     '3' => $student->username,
                                     '4' => 'On time',
                                     '5' => '',
                                     '6' => '');

        $this->assertEquals($expected_submission, $actual_submission);
    }

    /**
     * Two stages with allocation, two students, both submissions made
     * student1 graded by assessor2, student2 graded by assessor1 and assessor2
     * @throws coding_exception
     */
    public function test_two_stages_with_allocations(){
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');


        /* @var mod_coursework_generator $generator */
        $this->coursework = $generator->create_instance(array('course' => $this->course->id,
                                                              'grade' => 100,
                                                              'numberofmarkers' => 2,
                                                              'allocationenabled' => 1,
                                                              'deadline'=>time()+86400));

        // 2 assessors
        $assessor1 = $this->teacher;
        $assessor2 = $this->other_teacher;
        // 2students
        $student1 = $this->student;
        $student2 = $this->other_student;

        // submissions
        $submission1 = new stdClass();
        $submission1->userid = $student1->id;
        $submission1->allocatableid = $student1->id;
        $submission1 = $generator->create_submission($submission1, $this->coursework);

        $submission2 = new stdClass();
        $submission2->userid = $student2->id;
        $submission2->allocatableid = $student2->id;
        $submission2 = $generator->create_submission($submission2, $this->coursework);



        // Assessor2 feedback for student1
        $feedback_data1 = new stdClass();
        $feedback_data1->submissionid = $submission1->id;
        $feedback_data1->grade = 54;
        $feedback_data1->feedbackcomment = 'abc';
        $feedback_data1->assessorid = $assessor2->id;
        $feedback_data1->stage_identifier = 'assessor_2';
        $feedback1 = $generator->create_feedback($feedback_data1);

        // Assessor1 feedback for studen2
        $feedback_data2 = new stdClass();
        $feedback_data2->submissionid = $submission2->id;
        $feedback_data2->grade = 60;
        $feedback_data2->feedbackcomment = 'abc';
        $feedback_data2->assessorid = $assessor1->id;
        $feedback_data2->stage_identifier = 'assessor_1';
        $feedback2 = $generator->create_feedback($feedback_data2);

        // Assessor2 feedback for studen2
        $feedback_data3 = new stdClass();
        $feedback_data3->submissionid = $submission2->id;
        $feedback_data3->grade = 65;
        $feedback_data3->feedbackcomment = 'abc';
        $feedback_data3->assessorid = $assessor2->id;
        $feedback_data3->stage_identifier = 'assessor_2';
        $feedback3 = $generator->create_feedback($feedback_data3);


        // Agreed grade feedback
        $feedback_data4 = new stdClass();
        $feedback_data4->submissionid = $submission2->id;
        $feedback_data4->grade = 62;
        $feedback_data4->feedbackcomment = 'abc';
        $feedback_data4->assessorid = $assessor2->id;
        $feedback_data4->stage_identifier = 'final_agreed_1';
        $feedback4 = $generator->create_feedback($feedback_data4);


        // headers and data for csv
        $csv_cells = array('submissionid','submissionfileid','name','username','submissiontime',
                           'assessor1','assessorgrade1','assessorfeedback1','assessor2','assessorgrade2','assessorfeedback2',
                           'agreedgrade','agreedfeedback');

        $timestamp = date('d_m_y @ H-i');
        $filename = get_string('gradingsheetfor', 'coursework'). $this->coursework->name .' '.$timestamp;
        $grading_sheet = new \mod_coursework\export\grading_sheet($this->coursework, $csv_cells, $filename);
        $actual_submission1 = $grading_sheet->add_cells_to_array($submission1,$student1,$csv_cells);
        $actual_submission2 = $grading_sheet->add_cells_to_array($submission2,$student2,$csv_cells);
        $actual_submission = array_merge($actual_submission1, $actual_submission2);

        $studentname1 = $student1->lastname .' '.$student1->firstname;
        $studentname2 = $student2->lastname .' '.$student2->firstname;

        $assessor1name = $assessor1->lastname .' '. $assessor1->firstname;
        $assessor2name = $assessor2->lastname .' '. $assessor2->firstname;

        // build an array
        $expected_submission = array('0' => $submission1->id,
                                     '1' => $this->coursework->get_username_hash($student1->id),
                                     '2' => $studentname1,
                                     '3' => $student1->username,
                                     '4' => 'On time',
                                     '5' => $assessor1name,
                                     '6' => '',
                                     '7' => '',
                                     '8' => $assessor2name,
                                     '9' => $feedback_data1->grade,
                                     '10' => $feedback_data1->feedbackcomment,
                                     '11' => '',
                                     '12' => '',
                                     '13' => $submission2->id,
                                     '14' => $this->coursework->get_username_hash($student2->id),
                                     '15' => $studentname2,
                                     '16' => $student2->username,
                                     '17' => 'On time',
                                     '18' => $assessor2name,
                                     '19' => $feedback_data2->grade,
                                     '20' => $feedback_data2->feedbackcomment,
                                     '21' => $assessor1name,
                                     '22' => $feedback_data3->grade,
                                     '23' => $feedback_data3->feedbackcomment,
                                     '24' => $feedback_data4->grade,
                                     '25' => $feedback_data4->feedbackcomment );

        $this->assertEquals($expected_submission, $actual_submission);

    }
}


