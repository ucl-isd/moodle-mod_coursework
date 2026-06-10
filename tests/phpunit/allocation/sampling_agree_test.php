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
 * CTP-6337 - Tests for agree/release behaviour when sampling is enabled and some submissions are in the sample
 *
 * Looks like CTP-4904 introduced an issue with the ability to agree marks when sampling.
 *
 * When a coursework is set up with 3 markers and sampling enabled:
 *  - Submission 1: both markers have given feedback (e.g. 72 - 72), submission not added to sample.
 *  - Submission 2: both markers have given feedback (e.g. 72 - 99), submission manually added to sample for a 3rd mark.
 *
 * Expected behaviour:
 *  - Submission 1 can be agreed with 2 marks - no extra marker shown
 *  - Submission 2 can be agreed once marker 3 gives feedback
 *
 * @package    mod_coursework
 * @copyright  2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework;

use mod_coursework\models\assessment_set_membership;
use mod_coursework\models\submission;
use mod_coursework\render_helpers\grading_report\data\marking_cell_data;

/**
 * CTP-6337 - Tests for agree/release behaviour when sampling is enabled.
 * @group mod_coursework
 */
final class sampling_agree_test extends \advanced_testcase {
    use test_helpers\factory_mixin;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();

        $this->coursework = $this->create_a_coursework([
            'numberofmarkers'   => 3,
            'samplingenabled'   => 1,
            'allocationenabled' => 0,
            'deadline'          => time() + DAYSECS,
            'grade'             => 100,
        ]);

        $this->create_a_teacher();
        $this->create_another_teacher();
    }

    /**
     * CTP-6337: Sampling is enabled but a submission is not in the sample, two marks are enough.
     *
     * @covers \mod_coursework\stages\base::prerequisite_stages_have_feedback
     */
    public function test_prerequisite_stages_have_feedback_with_two_marks_not_in_sample(): void {
        $this->create_a_student();
        $this->create_finalised_submission_with_two_marks($this->student, 72, 72);

        // Do not create sample.
        $finalstage = $this->coursework->get_final_agreed_marking_stage();

        $this->assertTrue($finalstage->prerequisite_stages_have_feedback($this->student));
    }

    /**
     * CTP-6337: Two markers have given feedback, get_final_feedback_data should return "Agree marking" button data.
     *
     * @covers \mod_coursework\render_helpers\grading_report\data\marking_cell_data::get_final_feedback_data
     */
    public function test_marking_cell_data_get_final_feedback_data_with_two_marks_not_in_sample(): void {
        $this->create_a_student();
        $this->create_finalised_submission_with_two_marks($this->student, 72, 72);

        // Do not create sample.
        $row = new grading_table_row_base($this->coursework, $this->student, null, null, []);
        $celldata = new marking_cell_data($this->coursework);

        $result = $celldata->get_final_feedback_data($row);

        $this->assertNotNull($result);
        $this->assertNotEmpty($result->addfinalfeedback);
    }

    /**
     * CTP-6337: Table cell data for a submission not sampled only has two markers.
     *
     * @covers \mod_coursework\render_helpers\grading_report\data\marking_cell_data::get_table_cell_data
     */
    public function test_marking_cell_data_with_two_marks_not_in_sample(): void {
        $this->create_a_student();
        $this->create_finalised_submission_with_two_marks($this->student, 72, 72);

        // Do not create sample.
        $row = new grading_table_row_base($this->coursework, $this->student, null, null, []);
        $celldata = new marking_cell_data($this->coursework);
        $result = $celldata->get_table_cell_data($row);

        $this->assertCount(2, $result->markers);
    }

    /**
     * CTP-6337: Two markers. Student not in sample, one mark given. Agree button must appear.
     *
     * @covers \mod_coursework\render_helpers\grading_report\data\marking_cell_data::get_final_feedback_data
     */
    public function test_marking_cell_data_final_feedback_with_two_marks_not_in_sample(): void {
        $this->create_coursework_with_two_markers_and_sampling();
        $this->create_a_student();
        $this->create_finalised_submission_with_one_mark($this->student, 44);

        $row = new grading_table_row_base($this->coursework, $this->student, null, null, []);
        $celldata = new marking_cell_data($this->coursework);

        $result = $celldata->get_final_feedback_data($row);

        $this->assertNotNull($result);
        $this->assertNotEmpty($result->addfinalfeedback);
    }

    /**
     * CTP-6337: Two markers. Student in sample for assessor_2, both marks given. Agree button must appear.
     *
     * @covers \mod_coursework\render_helpers\grading_report\data\marking_cell_data::get_final_feedback_data
     */
    public function test_marking_cell_data_final_feedback_with_two_marks_in_sample(): void {
        $this->create_coursework_with_two_markers_and_sampling();
        $this->create_a_student();
        $this->create_sample($this->student, 2);
        $submission = $this->create_finalised_submission_with_one_mark($this->student, 1);
        $this->get_coursework_generator()->create_feedback((object)[
            'submissionid'    => $submission->id,
            'assessorid'      => $this->otherteacher->id,
            'stageidentifier' => 'assessor_2',
            'grade'           => 0,
            'isfinalgrade'    => 0,
            'ismoderation'    => 0,
            'finalised'       => 1,
        ]);

        $row = new grading_table_row_base($this->coursework, $this->student, null, null, []);
        $celldata = new marking_cell_data($this->coursework);

        $result = $celldata->get_final_feedback_data($row);

        $this->assertNotNull($result);
        $this->assertNotEmpty($result->addfinalfeedback);
    }

    /**
     * CTP-6337: Two markers. Student not in sample shows one marker.
     *
     * @covers \mod_coursework\render_helpers\grading_report\data\marking_cell_data::get_table_cell_data
     */
    public function test_marking_cell_data_num_markers_with_one_mark_not_in_sample(): void {
        $this->create_coursework_with_two_markers_and_sampling();
        $this->create_a_student();
        $this->create_finalised_submission_with_one_mark($this->student, 44);

        $row = new grading_table_row_base($this->coursework, $this->student, null, null, []);
        $celldata = new marking_cell_data($this->coursework);
        $result = $celldata->get_table_cell_data($row);

        $this->assertCount(1, $result->markers);
    }

    /**
     * CTP-6337: Two markers. Student in sample with one mark must not show agree button.
     *
     * @covers \mod_coursework\render_helpers\grading_report\data\marking_cell_data::get_final_feedback_data
     */
    public function test_marking_cell_data_final_feedback_with_one_mark_in_sample(): void {
        $this->create_coursework_with_two_markers_and_sampling();
        $this->create_a_student();
        $this->create_sample($this->student, 2);
        $this->create_finalised_submission_with_one_mark($this->student, 44);

        $row = new grading_table_row_base($this->coursework, $this->student, null, null, []);
        $celldata = new marking_cell_data($this->coursework);

        $result = $celldata->get_final_feedback_data($row);

        $this->assertNull($result);
    }

    /**
     * Create a finalised submission for the given student and add two finalised marker feedbacks
     */
    private function create_finalised_submission_with_two_marks(models\user $student, int $grade1, int $grade2): void {

        $submission = $this->get_coursework_generator()->create_submission(
            (object) [
                'courseworkid' => $this->coursework->id,
                'allocatableid' => $student->id,
                'allocatabletype' => 'user',
                'finalisedstatus' => submission::FINALISED_STATUS_FINALISED,
            ],
            $this->coursework
        );

        $generator = $this->get_coursework_generator();
        $generator->create_feedback((object)[
            'submissionid'     => $submission->id,
            'assessorid'       => $this->teacher->id,
            'stageidentifier'  => 'assessor_1',
            'grade'            => $grade1,
            'isfinalgrade'     => 0,
            'ismoderation'     => 0,
            'finalised'        => 1,
        ]);
        $generator->create_feedback((object)[
            'submissionid'     => $submission->id,
            'assessorid'       => $this->otherteacher->id,
            'stageidentifier'  => 'assessor_2',
            'grade'            => $grade2,
            'isfinalgrade'     => 0,
            'ismoderation'     => 0,
            'finalised'        => 1,
        ]);
    }

    /**
     * Create 2 markers with sampling, allocation and blind marking, future dated.
     */
    private function create_coursework_with_two_markers_and_sampling(): void {
        $this->create_a_coursework([
            'numberofmarkers'   => 2,
            'samplingenabled'   => 1,
            'allocationenabled' => 1,
            'blindmarking'      => 1,
            'deadline'          => time() + DAYSECS,
            'grade'             => 100,
        ]);
    }

    /**
     * Create a finalised submission for student with a finalised assessor_1 feedback.
     */
    private function create_finalised_submission_with_one_mark(models\user $student, int $grade): object {
        $submission = $this->get_coursework_generator()->create_submission(
            (object) [
                'courseworkid'    => $this->coursework->id,
                'allocatableid'   => $student->id,
                'allocatabletype' => 'user',
                'finalisedstatus' => submission::FINALISED_STATUS_FINALISED,
            ],
            $this->coursework
        );
        $this->get_coursework_generator()->create_feedback((object)[
            'submissionid'    => $submission->id,
            'assessorid'      => $this->teacher->id,
            'stageidentifier' => 'assessor_1',
            'grade'           => $grade,
            'isfinalgrade'    => 0,
            'ismoderation'    => 0,
            'finalised'       => 1,
        ]);
        return $submission;
    }

    /**
     * Creates a coursework_sample_set_mbrs record for the student in this coursework.
     */
    private function create_sample(models\user $student, int $stage): void {
        assessment_set_membership::create([
            'courseworkid'    => $this->coursework->id,
            'allocatableid'   => $student->id,
            'allocatabletype' => 'user',
            'stageidentifier' => 'assessor_' . $stage,
            'selectiontype'   => 'manual',
        ]);
    }
}
