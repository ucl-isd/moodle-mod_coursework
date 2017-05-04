<?php

namespace mod_coursework\export\csv\cells;
use mod_coursework\models\submission;

/**
 * Class stages_cell
 */
class stages_cell extends cell_base {

    /**
     * @param submission $submission
     * @param $student
     * @param $stage_identifier
     * @return array
     */
    public function get_cell($submission, $student, $stage_identifier){
        global $DB;

        $timecreated = 0;
        $timemodified = 0;

        $feedback = $DB->get_record('coursework_feedbacks', array('submissionid' => $submission->id, 'assessorid' => 0));
        if ($feedback) {
            $timecreated = $feedback->timecreated;
            $timemodified = $feedback->timemodified;
        }



        $gradedata = array();
        // go through each stage and get a grade, if grade not present then put  a placeholder
        for ($i = 1; $i <= $this->stages; $i++){
            $stage_identifier = 'assessor_'.$i;
            $grade = $submission->get_assessor_feedback_by_stage($stage_identifier);

            if ($this->coursework->allocation_enabled()) {
                $allocation = $submission->get_assessor_allocation_by_stage($stage_identifier);
                if ($allocation) {
                    $gradedata[] = $this->get_assessor_name($allocation->assessorid);
                    $gradedata[] = $this->get_assessor_username($allocation->assessorid);
                } else if ($i != 1 && $this->coursework->sampling_enabled() && !$submission->sampled_feedback_exists()){
                    $gradedata[] = get_string('notincludedinsample', 'mod_coursework');
                    $gradedata[] = get_string('notincludedinsample', 'mod_coursework');
                } else {
                    $gradedata[] = get_string('assessornotallocated', 'mod_coursework');
                    $gradedata[] = get_string('assessornotallocated', 'mod_coursework');
                }
            }
            if ($grade){
                $gradedata[] = $this->get_actual_grade($grade->grade);
                $gradedata[] = $this->get_assessor_name($grade->assessorid);
                $gradedata[] = $this->get_assessor_username($grade->assessorid);
                $gradedata[] = userdate($grade->timemodified, $this->dateformat);
            } else {
                $gradedata[] = '';
                $gradedata[] = '';
                $gradedata[] = '';
                $gradedata[] = '';
            }
        }

        if ($this->stages >= 2) { // if there are two or more stages for a submission, we will have agreed grade
            $gradedata[] = $submission->get_agreed_grade() == false ? '' : $this->get_actual_grade($submission->get_agreed_grade()->grade);

            if ((!$this->coursework->sampling_enabled() || $submission->sampled_feedback_exists()) && $feedback && (($feedback->assessorid == 0 && $timecreated == $timemodified) || $feedback->lasteditedbyuser == 0 )) {
                $gradedata[] = get_string('automaticagreement', 'coursework');
                $gradedata[] = get_string('automaticagreement', 'coursework');
            } else {
                $gradedata[] = $submission->get_agreed_grade() == false ? '' : $this->get_assessor_name($submission->get_agreed_grade()->lasteditedbyuser);
                $gradedata[] = $submission->get_agreed_grade() == false ? '' : $this->get_assessor_username($submission->get_agreed_grade()->lasteditedbyuser);
            }


            $gradedata[] = $submission->get_agreed_grade() == false ? '' : userdate($submission->get_agreed_grade()->timemodified, $this->dateformat);
        }

        return $gradedata;
    }

    /**
     * @param $stage
     * @return array
     * @throws \coding_exception
     */
    public function get_header($stage){

        $fields = array();

        for ($i = 1; $i <= $this->stages; $i++) {
            if ($this->coursework->allocation_enabled()) {
                $fields['allocatedassessor' . $i . 'name'] = 'Allocated assessor ' . $i . ' name';
                $fields['allocated' . $i . 'userame'] = 'Allocated assessor ' . $i . ' username';
            }
            $fields['assessor' . $i] = 'Assessor ' . $i . ' grade';
            $fields['assessor' . $i . 'name'] = 'Assessor ' . $i . ' name';
            $fields['assessor' . $i . 'username'] = 'Assessor ' . $i . ' username';
            $fields['assessor' . $i . 'markingtime'] = 'Assessor ' . $i . ' marked on';
        }
        if ($this->stages >= 2) { // if there are two or more stages for a submission, we will have agreed grade
            $fields['agreedgrade'] = get_string('agreedgrade', 'coursework');
            $fields['agreedgradeby'] = get_string('agreedgradeby', 'coursework');
            $fields['agreedgradebyusername'] = get_string('agreedgradebyusername', 'coursework');
            $fields['agreedgradeon'] = get_string('agreedgradeon', 'coursework');
        }

        return $fields;
    }






}