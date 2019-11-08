<?php

namespace mod_coursework\export\csv\cells;
use mod_coursework\models\submission;
use mod_coursework\ability;
use mod_coursework\models\user;
use mod_coursework\models\feedback;
use mod_coursework\grade_judge;

/**
 * Class feedbackcomments_cell
 */
class feedbackcomments_cell extends cell_base {


    /**
     * @param submission $submission
     * @param $student
     * @param $stage_identifier
     * @return string
     */
    public function get_cell($submission, $student, $stage_identifier){

        $stage_identifier = ($this->coursework->get_max_markers() == 1) ? "assessor_1" : $this->get_stage_identifier_for_assessor($submission, $student);
        $grade = $submission->get_assessor_feedback_by_stage($stage_identifier);
        return   (!$grade)? '' : strip_tags($grade->feedbackcomment);
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage){
        return  get_string('feedbackcomment', 'coursework');
    }


    public function validate_cell($value, $submissionid, $stage_identifier='',$uploadedgradecells = array()) {

        global $PAGE, $DB, $USER;

        if (has_capability('mod/coursework:addinitialgrade', $PAGE->context) || has_capability('mod/coursework:editinitialgrade', $PAGE->context)
            || has_capability('mod/coursework:administergrades', $PAGE->context))   {

            $dbrecord = $DB->get_record('coursework_submissions', array('id'=>$submissionid));

            $submission    =  \mod_coursework\models\submission::find($dbrecord);

            //is this submission ready to be graded
            if (!$submission->ready_to_grade()) return get_string('submissionnotreadytograde','coursework');

            //if you have administer grades you can grade anything
            if (has_capability('mod/coursework:administergrades', $PAGE->context)) return true;

            //is the current user an assessor at any of this submissions grading stages or do they have administer grades
            if (!$this->coursework->is_assessor($USER) && !has_capability('mod/coursework:administergrades',$PAGE->context))
                return get_string('nopermissiontogradesubmission','coursework');

            $ability = new ability(user::find($USER), $this->coursework);

            $feedback_params = array(
                'submissionid' => $submission->id,
                'stage_identifier' => $stage_identifier,
            );
            $feedback = feedback::find($feedback_params);

            //does a feedback exist for this stage
            if (empty($feedback)) {

                $feedback_params = array(
                    'submissionid' => $submissionid,
                    'assessorid' => $USER->id,
                    'stage_identifier' => $stage_identifier,
                );
                $new_feedback = feedback::build($feedback_params);


                //this is a new feedback check it against the new ability checks
                if (!$ability->can('new',$new_feedback))   return get_string('nopermissiontogradesubmission','coursework');
            } else {
                //this is a new feedback check it against the edit ability checks
                if (!$ability->can('edit',$feedback))   return get_string('nopermissiontoeditgrade','coursework');
            }
        } else {
            return get_string('nopermissiontoimportgrade', 'coursework');
        }

        return true;
    }

}