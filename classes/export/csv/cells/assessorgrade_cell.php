<?php

namespace mod_coursework\export\csv\cells;
use mod_coursework\models\submission;
use mod_coursework\ability;
use mod_coursework\models\user;
use mod_coursework\models\feedback;
use mod_coursework\grade_judge;
use mod_coursework\export;
/**
 * Class assessorgrade_cell
 */
class assessorgrade_cell extends cell_base{


    /**
     * @param submission$submission
     * @param $student
     * @param $stage_identifier
     * @return string
     */

    public function get_cell($submission, $student, $stage_identifier){

        global $USER;

        $grade = $submission->get_assessor_feedback_by_stage($stage_identifier);
        if ($grade){
            // check if user can see initial grades before all of them are completed
            $ability = new ability(user::find($USER), $this->coursework);

            $feedback_params = array(
                'submissionid' => $submission->id,
                'stage_identifier' => $stage_identifier,
            );
            $feedback = feedback::find($feedback_params);

            if (($submission->get_agreed_grade() || $ability->can('show', $feedback)) || !$submission->any_editable_feedback_exists() || is_siteadmin($USER->id)) {
                $grade = $this->get_actual_grade($grade->grade);
            } else {
                $grade = get_string('grade_hidden_manager', 'mod_coursework');

            }
        } else {
            $grade= '';
        }

        return $grade;
    }

    /**
     * @param $stage
     * @return string
     * @throws \coding_exception
     */
    public function get_header($stage){
        return  get_string('assessorgradecsv', 'coursework', $stage);
    }

    public function validate_cell($value,$submissionid,$stage_identifier='')
    {
        global $DB, $PAGE, $USER;

        $agreedgradecap = array('mod/coursework:addagreedgrade', 'mod/coursework:editagreedgrade');
        $initialgradecap = array('mod/coursework:addinitialgrade', 'mod/coursework:editinitialgrade');
        $subdbrecord = $DB->get_record('coursework_submissions', array('id' => $submissionid));
        $submission = \mod_coursework\models\submission::find($subdbrecord);

        if (has_any_capability($agreedgradecap, $PAGE->context) && has_any_capability($initialgradecap, $PAGE->context)
            || has_capability('mod/coursework:administergrades', $PAGE->context)
        ) {


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


            //is the submission in question ready to grade?
            if (!$submission->ready_to_grade()) return get_string('submissionnotreadytograde', 'coursework');


            //has the submission been published if yes then no further grades are allowed
            if ($submission->get_state() >= submission::PUBLISHED) return $submission->get_status_text();

            //if you have administer grades you can grade anything
            if (has_capability('mod/coursework:administergrades', $PAGE->context)) return true;

            //has this submission been graded if yes then check if the current user graded it (only if allocation is not enabled).
            $feedback_params = array(
                'submissionid' => $submission->id,
                'stage_identifier' => $stage_identifier,
            );
            $feedback = feedback::find($feedback_params);

            $ability = new ability(user::find($USER), $this->coursework);

            //does a feedback exist for this stage
            if (!empty($feedback)) {
                //this is a new feedback check it against the new ability checks
                if (!has_capability('mod/coursework:administergrades', $PAGE->context) && !$ability->can('new', $feedback)) return get_string('nopermissiontoeditgrade', 'coursework');

            } else {

                //this is a new feedback check it against the edit ability checks
                if (!has_capability('mod/coursework:administergrades', $PAGE->context) && !$ability->can('edit', $feedback)) return get_string('nopermissiontoeditgrade', 'coursework');

            }


            if (!$this->coursework->allocation_enabled() && !empty($feedback)) {
                //was this user the one who last graded this submission if not then user cannot grade
                if ($feedback->assessorid != $USER->id || !has_capability('mod/coursework:editinitialgrade', $PAGE->context))
                    return get_string('nopermissiontogradesubmission', 'coursework');

            }

            if ($this->coursework->allocation_enabled()) {
                //check that the user is allocated to the author of the submission
                $allocation_params = array(
                    'courseworkid' => $this->coursework->id,
                    'allocatableid' => $submission->allocatableid,
                    'allocatabletype' => $submission->allocatabletype,
                    'stage_identifier' => $stage_identifier
                );

                if (!has_capability('mod/coursework:administergrades', $PAGE->context)
                    && !$DB->get_record('coursework_allocation_pairs', $allocation_params)
                ) return get_string('nopermissiontogradesubmission', 'coursework');
            }


            //check for coursework without allocations - with/without samplings
            if (has_capability('mod/coursework:addinitialgrade', $PAGE->context) && !has_capability('mod/coursework:editinitialgrade', $PAGE->context)
                && $this->coursework->get_max_markers() > 1 && !$this->coursework->allocation_enabled()
            ) {

                // check how many feedbacks for this submission
                $feedbacks = $DB->count_records('coursework_feedbacks', array('submissionid' => $submissionid));

                if ($this->coursework->sampling_enabled()) {
                    // check how many sample assessors + add 1 that is always in sample
                    $in_sample = $submission->get_submissions_in_sample();
                    $assessors = ($in_sample) ? sizeof($in_sample) + 1 : 1;
                } else {
                    //check how many assessors for this coursework
                    $assessors = $this->coursework->get_max_markers();
                }
                if ($assessors == $feedbacks) return get_string('gradealreadyexists', 'coursework');
            }


        } else if (has_any_capability($agreedgradecap, $PAGE->context)) {

          //if you have the add agreed or edit agreed grades capabilities then you may have the grades on your export sheet
          //we will return true as we will ignore them
          return true;

        } else {
            return get_string('nopermissiontoimportgrade', 'coursework');
        }
    }

}