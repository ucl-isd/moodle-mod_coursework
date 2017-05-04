<?php

use mod_coursework\allocation\allocatable;
use mod_coursework\models\coursework;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/coursework/tests/behat/pages/page_base.php');

/**
 * Holds the functions that know about the HTML structure of the multiple grading page.
 *
 */
class mod_coursework_behat_single_grading_interface extends mod_coursework_behat_page_base {

    /**
     * @param $student_hash
     */
    public function student_should_have_a_final_grade($student_hash) {
        $student_grade_cell = $this->getPage()->find('css', '#submission_'. $student_hash.' .single_final_grade_cell');
        $message = "Should be a grade in the student row final grade cell, but there's not";
        assertNotEmpty($student_grade_cell->getText(), $message);
    }

    /**
     * @param allocatable $allocatable
     */
    public function there_should_not_be_a_feedback_icon($allocatable) {
        $feedback_cell = $this->getPage()->find('css', $this->allocatable_row_id($allocatable).' .single_assessor_feedback_cell');
        $feedback_icon = $feedback_cell->findAll('css', '.smallicon');
        assertEquals(0, count($feedback_icon));
    }

    /**
     * @param allocatable $allocatable
     */
    public function click_new_final_feedback_button($allocatable) {
        $identifier = '#new_final_feedback_' . $this->allocatable_identifier_hash($allocatable);
        $this->click_that_thing($identifier);
    }

    /**
     * @param allocatable $allocatable
     * @throws Behat\Mink\Exception\ElementException
     */
    public function click_edit_feedback_button($allocatable) {
        $identifier = '#edit_final_feedback_' . $this->allocatable_identifier_hash($allocatable);
        $this->getPage()->find('css', $identifier)->click();
    }

    /**
     * @param allocatable $allocatable
     * @throws Behat\Mink\Exception\ElementException
     */
    public function should_have_new_moderator_feedback_button($allocatable) {
        $identifier = $this->new_moderator_feedback_button_id($allocatable);
        $this->should_have_css($identifier);
    }

    /**
     * @param allocatable $allocatable
     * @throws Behat\Mink\Exception\ElementException
     */
    public function should_not_have_new_moderator_feedback_button($allocatable) {
        $identifier = $this->new_moderator_feedback_button_id($allocatable);
        $this->should_not_have_css($identifier);
    }

    /**
     * @param $allocatable
     * @return string
     */
    private function new_moderator_feedback_button_id($allocatable) {
        $identifier = '#new_moderator_feedback_' . $this->allocatable_identifier_hash($allocatable);
        return $identifier;
    }

    /**
     * @param allocatable $student
     */
    public function should_not_have_user_name_in_user_cell($student) {
        $css = '.user_cell';
        $this->should_not_have_css($css, $student->name());
    }

    /**
     * @param allocatable $student
     */
    public function should_have_user_name_in_user_cell($student) {
        $css = '.user_cell';
        $this->should_have_css($css, $student->name());
    }

    /**
     * @param allocatable $student
     */
    public function should_not_have_user_name_in_group_cell($student) {
        $css = '.group_cell';
        $this->should_not_have_css($css, $student->name());
    }

    /**
     * @param allocatable $student
     */
    public function should_have_user_name_in_group_cell($student) {
        $css = '.group_cell';
        $this->should_have_css($css, $student->name());
    }

    /**
     * @param mod_coursework\models\user $assessor
     */
    public function should_have_assessor_name_in_assessor_feedback_cell($assessor) {
        $cell_css = '.single_assessor_feedback_cell';
        $this->should_have_css($cell_css, $assessor->name());
    }

    /**
     * @param \mod_coursework\allocation\allocatable $allocatable
     * @return string
     */
    private function allocatable_row_id(\mod_coursework\allocation\allocatable $allocatable) {
        return '#allocatable_' . $this->allocatable_identifier_hash($allocatable);
    }
}