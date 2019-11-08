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
 * @package    mod
 * @subpackage csv
 * @copyright  2012 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


use mod_coursework\export\csv;
use mod_coursework\models\submission;
use mod_coursework\models\deadline_extension;

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * @property mixed feedback_data
 * @property mixed csv
 * @group mod_coursework
 */
class csv_test extends advanced_testcase {


    use mod_coursework\test_helpers\factory_mixin;




    public function setUp() {

        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();

       // $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');
        $this->setAdminUser();

        $this->student = $this->create_a_student();
        $this->teacher = $this->create_a_teacher();
        $this->other_teacher = $this->create_another_teacher();

    }

    /**
     * One stage only, extension enabled
     * @throws coding_exception
     */
    public function test_one_stage(){

        $dateformat = '%a, %d %b %Y, %H:%M';
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');

        /* @var mod_coursework_generator $generator */
        $this->coursework = $generator->create_instance(array('course' => $this->course->id,
                                                              'grade' => 100,
                                                              'numberofmarkers' => 1,
                                                              'deadline'=>time()+86400,
                                                                'extensionsenabled'=>1));
        $this->submission = new stdClass();
        $this->submission->userid = $this->student->id;
        $this->submission->allocatableid = $this->student->id;
        $this->submission = $generator->create_submission($this->submission, $this->coursework);


        $student = $this->student;
        $assessor = $this->teacher;
        $submission = $this->submission;
        $feedback_data = new stdClass();
        $feedback_data->submissionid = $submission->id;
        $feedback_data->grade = 54;
        $feedback_data->assessorid = $assessor->id;
        $feedback_data->stage_identifier = 'assessor_1';
        $feedback = $generator->create_feedback($feedback_data);

        $extendion_deadline  =   time();
        $params = array('allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
            'courseworkid' => $this->coursework->id,
            'pre_defined_reason' => 1,
            'createdbyid' => 4,
            'extra_information_text' => '<p>extra information</p>',
            'extra_information_format' => 1,
            'extended_deadline' => $extendion_deadline);

        $extension = deadline_extension::create($params);

        $extension_reasons  =   $this->coursework->extension_reasons();

        if (empty($extension_reasons)) {

            set_config('coursework_extension_reasons_list',"coursework extension \n sick leave");
            $extension_reasons  =   $this->coursework->extension_reasons();

        }


        // headers and data for csv
        $csv_cells = array('name','username','submissiondate','submissiontime',
            'submissionfileid');

        if ($this->coursework->extensions_enabled()){
            $csv_cells[] = 'extensiondeadline';
            $csv_cells[] = 'extensionreason';
            $csv_cells[] = 'extensionextrainfo';
        }
        $csv_cells[] = 'stages';
        $csv_cells[] = 'finalgrade';


        $timestamp = date('d_m_y @ H-i');
        $filename = get_string('finalgradesfor', 'coursework'). $this->coursework->name .' '.$timestamp;
        $csv = new \mod_coursework\export\csv($this->coursework, $csv_cells, $filename);
        $csv_grades = $csv->add_cells_to_array($submission,$student,$csv_cells);

       // build an array
        $studentname = $student->lastname .' '.$student->firstname;
        $assessorname = $assessor->lastname .' '. $assessor->firstname;
        $assessorusername =  $assessor->username;


        $one_assessor_grades = array('0' => $studentname,
                                     '1' => $student->username,
                                     '2' => userdate(time(),$dateformat),
                                     '3' => 'On time',
                                     '4' => $this->coursework->get_username_hash($submission->allocatableid),
                                     '5' => userdate($extension->extended_deadline, $dateformat),
                                     '6' => $extension_reasons[1],
                                     '7' => 'extra information',
                                     '8' => $feedback->grade,
                                     '9' => $assessorname,
                                     '10' => $assessorusername,
                                     '11' => userdate(time(),$dateformat),
                                     '12' => $feedback->grade);

        $this->assertEquals($one_assessor_grades, $csv_grades);
    }


    /**
     * Two stages with final agreed grade, extension not enabled
     */
    public function test_two_stages(){

        $dateformat = '%a, %d %b %Y, %H:%M';
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');

        /* @var mod_coursework_generator $generator */
        $this->coursework = $generator->create_instance(array('course' => $this->course->id,
                                                              'grade' => 100,
                                                              'numberofmarkers' => 2,
                                                              'deadline'=>time()-86400));
        $this->submission = new stdClass();
        $this->submission->userid = $this->student->id;
        $this->submission = $generator->create_submission($this->submission, $this->coursework);


        $student = $this->student;
        $assessor1 = $this->teacher;
        $assessor2 = $this->other_teacher;
        $submission = $this->submission;

        // Assessor one feedback
        $feedback_data1 = new stdClass();
        $feedback_data1->submissionid = $submission->id;
        $feedback_data1->grade = 54;
        $feedback_data1->assessorid = $assessor1->id;
        $feedback_data1->stage_identifier = 'assessor_1';
        $feedback1 = $generator->create_feedback($feedback_data1);

        // Assessor two feedback
        $feedback_data2 = new stdClass();
        $feedback_data2->submissionid = $submission->id;
        $feedback_data2->grade = 60;
        $feedback_data2->assessorid = $assessor2->id;
        $feedback_data2->stage_identifier = 'assessor_2';
        $feedback2 = $generator->create_feedback($feedback_data2);

        // Agreed grade feedback
        $feedback_data3 = new stdClass();
        $feedback_data3->submissionid = $submission->id;
        $feedback_data3->grade = 58;
        $feedback_data3->assessorid = $assessor1->id;
        $feedback_data3->stage_identifier = 'final_agreed_1';
        $feedback_data3->lasteditedbyuser = $assessor1->id;
        $feedback3 = $generator->create_feedback($feedback_data3);

        // headers and data for csv
        $csv_cells = array('name','username','submissiondate','submissiontime',
            'submissionfileid');

        if ($this->coursework->extensions_enabled()){
            $csv_cells[] = 'extensiondeadline';
            $csv_cells[] = 'extensionreason';
            $csv_cells[] = 'extensionextrainfo';
        }
        $csv_cells[] = 'stages';
        $csv_cells[] = 'finalgrade';


        $timestamp = date('d_m_y @ H-i');
        $filename = get_string('finalgradesfor', 'coursework'). $this->coursework->name .' '.$timestamp;
        $csv = new \mod_coursework\export\csv($this->coursework, $csv_cells, $filename);
        $csv_grades = $csv->add_cells_to_array($submission,$student,$csv_cells);

        // build an array
        $studentname = $student->lastname .' '.$student->firstname;
        $assessorname1 = $assessor1->lastname .' '. $assessor1->firstname;
        $assessorname2 = $assessor2->lastname .' '. $assessor2->firstname;

        $assessorusername1 = $assessor1->username;
        $assessorusername2 = $assessor2->username;

        $two_assessors_grades = array('0' => $studentname,
                                      '1' => $student->username,
                                      '2' => userdate(time(),$dateformat),
                                      '3' => 'Late',
                                 '4' => $this->coursework->get_username_hash($submission->allocatableid),
                                      '5' => $feedback1->grade,
                                      '6' => $assessorname1,
                                 '7' => $assessorusername1,
                                      '8' => userdate(time(),$dateformat),
                                      '9' => $feedback2->grade,
                                      '10' => $assessorname2,
                                  '11' => $assessorusername2,
                                      '12' => userdate(time(),$dateformat),
                                      '13' => $feedback3->grade,
                                      '14' => $assessorname1,
                                 '15' => $assessorusername1,
                                      '16' => userdate(time(),$dateformat),
                                      '17' => $feedback3->grade);

        $this->assertEquals($two_assessors_grades, $csv_grades);
    }


    /**
     * Sampling enabled, student not in sample, extension not enabled
     */
    public function test_student_not_in_sample(){

        $dateformat = '%a, %d %b %Y, %H:%M';
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');

        /* @var mod_coursework_generator $generator */
        $this->coursework = $generator->create_instance(array('course' => $this->course->id,
                                                              'grade' => 100,
                                                              'numberofmarkers' => 2,
                                                              'samplingenabled' => 1,
                                                              'deadline'=>time()+86400));
        $this->submission = new stdClass();
        $this->submission->userid = $this->student->id;
        $this->submission = $generator->create_submission($this->submission, $this->coursework);

        $student = $this->student;
        $assessor1 = $this->teacher;
        $submission = $this->submission;

        // Assessor one feedback
        $feedback_data = new stdClass();
        $feedback_data->submissionid = $submission->id;
        $feedback_data->grade = 54;
        $feedback_data->assessorid = $assessor1->id;
        $feedback_data->stage_identifier = 'assessor_1';
        $feedback = $generator->create_feedback($feedback_data);

        // headers and data for csv
        $csv_cells = array('name','username','submissiondate','submissiontime',
            'submissionfileid');

        if ($this->coursework->extensions_enabled()){
            $csv_cells[] = 'extensiondeadline';
            $csv_cells[] = 'extensionreason';
            $csv_cells[] = 'extensionextrainfo';
        }
        $csv_cells[] = 'stages';
        $csv_cells[] = 'finalgrade';


        $timestamp = date('d_m_y @ H-i');
        $filename = get_string('finalgradesfor', 'coursework'). $this->coursework->name .' '.$timestamp;
        $csv = new \mod_coursework\export\csv($this->coursework, $csv_cells, $filename);
        $csv_grades = $csv->add_cells_to_array($submission,$student,$csv_cells);

        // build an array
        $studentname = $student->lastname .' '.$student->firstname;
        $assessorname1 = $assessor1->lastname .' '. $assessor1->firstname;

        $assessorusername1 = $assessor1->username;


        $grades = array('0' => $studentname,
                        '1' => $student->username,
                        '2' => userdate(time(),$dateformat),
                        '3' => 'On time',
                        '4' => $this->coursework->get_username_hash($submission->allocatableid),
                        '5' => $feedback->grade,
                        '6' => $assessorname1,
                        '7' => $assessorusername1,
                        '8' => userdate(time(),$dateformat),
                        '9' => '',
                        '10' => '',
                        '11' => '',
                        '12' => '',
                        '13' => '',
                        '14' => '',
                        '15' => '',
                        '16' => '',
                        '17' => $feedback->grade);

        $this->assertEquals($grades, $csv_grades);
    }

    /**
     * Two students but only one is double marked and should have agreed grade, extension not enabled
     */
    public function test_two_students_one_in_sample(){
        global $DB;
        $dateformat = '%a, %d %b %Y, %H:%M';
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');

        /* @var mod_coursework_generator $generator */
        $this->coursework = $generator->create_instance(array('course' => $this->course->id,
                                                              'grade' => 100,
                                                              'numberofmarkers' => 2,
                                                              'samplingenabled' => 1,
                                                              'deadline'=>time()+86400));
        $student1 = $this->student;
        $assessor1 = $this->teacher;
        $assessor2 = $this->other_teacher;
        $submission1 = new stdClass();
        $submission1->userid = $student1->id;
        $submission1->allocatableid = $student1->id;
        $submission1 = $generator->create_submission($submission1, $this->coursework);


        $student2 = $this->create_a_student();
        $submission2 = new stdClass();
        $submission2->userid = $student2->id;
        $submission2->allocatableid = $student2->id;
        $submission2 = $generator->create_submission($submission2, $this->coursework);


        // student 2 manual sampling enabled
        $set_members_data = new stdClass();
        $set_members_data->courseworkid = $this->coursework->id;
        $set_members_data->allocatableid = $submission2->allocatableid;
        $set_members_data->allocatabletype = 'user';
        $set_members_data->stage_identifier = 'assessor_2';

        $DB->insert_record('coursework_sample_set_mbrs', $set_members_data);



        // Assessor one feedback for student 1
        $feedback_data1 = new stdClass();
        $feedback_data1->submissionid = $submission1->id;
        $feedback_data1->grade = 54;
        $feedback_data1->assessorid = $assessor1->id;
        $feedback_data1->stage_identifier = 'assessor_1';
        $feedback1 = $generator->create_feedback($feedback_data1);

        // Assessor one feedback for student 2
        $feedback_data2 = new stdClass();
        $feedback_data2->submissionid = $submission2->id;
        $feedback_data2->grade = 60;
        $feedback_data2->assessorid = $assessor1->id;
        $feedback_data2->stage_identifier = 'assessor_1';
        $feedback2 = $generator->create_feedback($feedback_data2);

        // Assessor two feedback for student 2
        $feedback_data3 = new stdClass();
        $feedback_data3->submissionid = $submission2->id;
        $feedback_data3->grade = 50;
        $feedback_data3->assessorid = $assessor2->id;
        $feedback_data3->stage_identifier = 'assessor_2';
        $feedback3 = $generator->create_feedback($feedback_data3);

        // Agreed grade feedback
        $feedback_data4 = new stdClass();
        $feedback_data4->submissionid = $submission2->id;
        $feedback_data4->grade = 58;
        $feedback_data4->assessorid = $assessor2->id;
        $feedback_data4->stage_identifier = 'final_agreed_1';
        $feedback_data4->lasteditedbyuser = $assessor2->id;
        $feedback4 = $generator->create_feedback($feedback_data4);

        // headers and data for csv
        $csv_cells = array('name','username','submissiondate','submissiontime',
            'submissionfileid');

        if ($this->coursework->extensions_enabled()){
            $csv_cells[] = 'extensiondeadline';
            $csv_cells[] = 'extensionreason';
            $csv_cells[] = 'extensionextrainfo';
        }
        $csv_cells[] = 'stages';
        $csv_cells[] = 'finalgrade';


        $timestamp = date('d_m_y @ H-i');
        $filename = get_string('finalgradesfor', 'coursework'). $this->coursework->name .' '.$timestamp;
        $csv = new \mod_coursework\export\csv($this->coursework, $csv_cells, $filename);
        $array1 = $csv->add_cells_to_array($submission1,$student1,$csv_cells);
        $array2 = $csv->add_cells_to_array($submission2,$student2,$csv_cells);

        $csv_grades = array_merge($array1, $array2);

        // build an array
        $studentname1 = $student1->lastname .' '.$student1->firstname;
        $studentname2 = $student2->lastname .' '.$student2->firstname;
        $assessorname1 = $assessor1->lastname .' '. $assessor1->firstname;
        $assessorname2 = $assessor2->lastname .' '. $assessor2->firstname;

        $assessorusername1    =   $assessor1->username;
        $assessorusername2    =   $assessor2->username;

        $assessors_grades = array('0' => $studentname1,
                                  '1' => $student1->username,
                                  '2' => userdate(time(),$dateformat),
                                  '3' => 'On time',
                                  '4' => $this->coursework->get_username_hash($submission1->allocatableid),
                                  '5' => $feedback1->grade,
                                  '6' => $assessorname1,
                                  '7' => $assessorusername1,
                                  '8' => userdate(time(),$dateformat),
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
                                  '20' => userdate(time(),$dateformat),
                                  '21' => 'On time',
                                  '22' => $this->coursework->get_username_hash($submission2->allocatableid),
                                  '23' => $feedback2->grade,
                                  '24' => $assessorname1,
                                  '25' => $assessorusername1,
                                  '26' => userdate(time(),$dateformat),
                                  '27' => $feedback3->grade,
                                  '28' => $assessorname2,
                                  '29' => $assessorusername2,
                                  '30' => userdate(time(),$dateformat),
                                  '31' => $feedback4->grade,
                                  '32' => $assessorname2,
                                 '33' => $assessorusername2,
                                  '34' => userdate(time(),$dateformat),
                                  '35'=> $feedback4->grade);

        $this->assertEquals($assessors_grades, $csv_grades);
    }
}


