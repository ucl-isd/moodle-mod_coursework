<?php

namespace mod_coursework\export\csv\cells;
use mod_coursework\models\submission;
use mod_coursework\grade_judge;
use mod_coursework\ability;
use mod_coursework\models\user;
use mod_coursework\models\feedback;

/**
 * Class otherassessors_cell
 */
class otherassessors_cell extends cell_base{


    /**
     * @param submission $submission
     * @param $student
     * @param $stage_identifier
     * @return null|string
     */
    public function get_cell($submission, $student, $stage_identifier){
        global $DB, $USER;
        // find out current user stage identifier

        $stage_identifier =
    // retrieve all feedbacks without currents user feedback


        $params = array(
            'submissionid' => $submission->id,
            'assessorid' => $USER->id,
            'stageidentifier' => $stage_identifier
        );

        $sql = "SELECT * FROM {coursework_feedbacks}
                WHERE submissionid = :submissionid
                AND assessorid <> :assessorid
                AND stage_identifier <> 'final_agreed_1'";

        $feedbacks = $DB->get_records_sql($sql, $params);
        $gradedata = array();

       // $stage_identifier = ($this->coursework->get_max_markers() == 1) ? "assessor_1" : $this->get_stage_identifier_for_assessor($submission, $student);
        foreach($feedbacks as $feedback){

            $grade = $submission->get_assessor_feedback_by_stage($feedback->stage_identifier);
            if ($grade){
                $ability = new ability(user::find($USER), $this->coursework);
                if (($ability->can('show', $feedback) && !$submission->any_editable_feedback_exists()) || is_siteadmin($USER->id)) {
                    $gradedata[] = $this->get_actual_grade($grade->grade);
                    $gradedata[] = strip_tags($grade->feedbackcomment);
                } else {
                    $gradedata[] = get_string('grade_hidden_manager', 'mod_coursework');
                    $gradedata[] = '';
                }


            } else {
                $gradedata[] = '';
                $gradedata[] = '';
            }

        }

        return   $gradedata;
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage){

        $fields = array();

        for ($i = 1; $i < $this->stages ; $i++) {
            $fields['otherassessorgrade'.$i] = get_string('otherassessorgrade', 'coursework', $i);
            $fields['otherassessorfeedback'.$i] = get_string('otherassessorfeedback', 'coursework', $i);
        }
       return $fields;
    }



}