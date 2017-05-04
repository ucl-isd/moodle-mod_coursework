<?php

namespace mod_coursework\export\csv\cells;
use mod_coursework\models\submission;
use mod_coursework\grade_judge;
use mod_coursework\ability;
use mod_coursework\models\user;
use mod_coursework\models\feedback;

/**
 * Class singlegrade_cell
 */
class singlegrade_cell extends cell_base{


    /**
     * @param submission $submission
     * @param $student
     * @param $stage_identifier
     * @return null|string
     */
    public function get_cell($submission, $student, $stage_identifier){

        $stage_identifier = ($this->coursework->get_max_markers() == 1) ? "assessor_1" : $this->get_stage_identifier_for_assessor($submission, $student);

        $grade = $submission->get_assessor_feedback_by_stage($stage_identifier);
        return   (!$grade)? '' : $this->get_actual_grade($grade->grade);
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage){
        return  get_string('grade', 'coursework');
    }


    public function validate_cell($value,$submissionid,$stage_identifier='') {

        global $PAGE, $DB, $USER;

        if (has_capability('mod/coursework:addinitialgrade', $PAGE->context) || has_capability('mod/coursework:editinitialgrade', $PAGE->context)
            || has_capability('mod/coursework:administergrades', $PAGE->context))   {

            if (!empty($value)) {
                $gradejudge = new grade_judge($this->coursework);
                if (!$gradejudge->grade_in_scale($value)){
                    $errormsg = get_string('valuenotincourseworkscale', 'coursework');
                    if (is_numeric($value)) {
                        // if scale is numeric get max allowed scale
                        $errormsg .= ' '. get_string('max_cw_mark', 'coursework').' '. $this->coursework->grade;
                    }
                    return $errormsg;
                }
            }

            $dbrecord = $DB->get_record('coursework_submissions', array('id'=>$submissionid));

            $submission    =  \mod_coursework\models\submission::find($dbrecord);

            //is this submission ready to be graded
            if (!$submission->ready_to_grade() && $submission->get_state() < \mod_coursework\models\submission::FULLY_GRADED) return get_string('submissionnotreadytograde','coursework');

            //if you have administer grades you can grade anything
            if (has_capability('mod/coursework:administergrades', $PAGE->context)) return true;

            //is the current user an assessor at any of this submissions grading stages or do they have administer grades
            if ($this->coursework->allocation_enabled() && !$this->coursework->is_assessor($USER) && !has_capability('mod/coursework:administergrades',$PAGE->context))
                return get_string('nopermissiontogradesubmission','coursework');

                        //has the submission been published if yes then no further grades are allowed
            if ($submission->get_state() >= submission::PUBLISHED)  return $submission->get_status_text();

            //has this submission been graded if yes then check if the current user graded it (only if allocation is not enabled).
            $feedback_params = array(
                'submissionid' => $submission->id,
                'stage_identifier' => $stage_identifier,
            );
            $feedback = feedback::find($feedback_params);

            if (!$this->coursework->allocation_enabled() && !empty($feedback))   {
                //was this user the one who last graded this submission if not then user cannot grade
                if ($feedback->assessorid != $USER->id || !has_capability('mod/coursework:editinitialgrade', $PAGE->context) && !has_capability('mod/coursework:administergrades', $PAGE->context))
                    return get_string('nopermissiontoeditgrade','coursework');

            }




            $ability = new ability(user::find($USER), $this->coursework);

            $feedback_params = array(
                'submissionid' => $submission->id,
                'stage_identifier' => $stage_identifier,
            );
            $feedback = feedback::find($feedback_params);

            //if (!$ability->can('edit',$feedback))   return get_string('nopermissiontoeditgrade','coursework');

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