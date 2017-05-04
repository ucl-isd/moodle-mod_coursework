<?php

use mod_coursework\allocation\allocatable;
use mod_coursework\models\coursework;
use mod_coursework\models\deadline_extension;
use mod_coursework\models\feedback;
use mod_coursework\models\group;
use mod_coursework\models\submission;
use mod_coursework\models\user;

use Behat\Mink\Exception\ElementNotFoundException;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/coursework/tests/behat/pages/single_grading_interface.php');

/**
 * Holds the functions that know about the HTML structure of the multiple grading page.
 *
 */
class mod_coursework_behat_multiple_grading_interface extends mod_coursework_behat_single_grading_interface {

    /**
     * @param string $student_hash
     */
    public function there_should_not_be_a_feedback_icon($student_hash) {
        $this->getContext()->show_me_the_page();
        $feedback_icon = $this->getPage()->findAll('css', '.cfeedbackcomment .smallicon');
        assertEquals(0, count($feedback_icon));
    }

    /**
     * @param user $allocatable
     */
    public function student_should_have_a_final_grade($allocatable) {
        $student_grade_cell =
            $this->getPage()->find('css', $this->allocatable_row_id($allocatable) . ' .multiple_agreed_grade_cell');
        $message = "Should be a grade in the student row final grade cell, but there's not";
        assertNotEmpty($student_grade_cell->getText(), $message);
    }

    /**
     * @param group $allocatable
     */
    public function group_should_have_a_final_multiple_grade($allocatable) {
        $group_row_id = $this->allocatable_row_id($allocatable);
        $locator = $group_row_id . ' .multiple_agreed_grade_cell';
        $grade_cell = $this->getPage()->find('css', $locator);
        $message = "Should be a grade in the student row final grade cell, but there's not (css: {$locator})";
        $actual_text = $grade_cell ? $grade_cell->getText() : '';
        assertNotEmpty($actual_text, $message);
    }

    /**
     * @param allocatable $allocatable
     */
    public function click_new_final_feedback_button($allocatable) {
        $identifier = '#new_final_feedback_'. $this->allocatable_identifier_hash($allocatable);
        $nodeElement = $this->getPage()->find('css', $identifier);
        if ($nodeElement) {
            $nodeElement->click();
        }
    }

    /**
     * @param allocatable $allocatable
     */
    public function click_edit_final_feedback_button($allocatable) {
        $identifier = '#edit_final_feedback_' . $this->allocatable_identifier_hash($allocatable);
        $nodeElement = $this->getPage()->find('css', $identifier);
        assertNotEmpty($nodeElement, 'Edit feedback button not present');
        $nodeElement->click();
    }

    /**
     * @param allocatable $allocatable
     * @return string
     */
    private function allocatable_row_id($allocatable) {
        return '#allocatable_' . $this->allocatable_identifier_hash($allocatable);
    }


    /**
     * @param allocatable $allocatable
     * @return string
     */
    private function assessor_feedback_table_id($allocatable) {
        return '#assessorfeedbacktable_' . $this->allocatable_identifier_hash($allocatable);
    }



    /**
     * @param allocatable $allocatable
     * @throws Behat\Mink\Exception\ElementException
     */
    public function click_new_moderator_feedback_button($allocatable) {
        $identifier = $this->allocatable_row_id($allocatable).' .moderation_cell .new_feedback';
        $this->getPage()->find('css', $identifier)->click();
    }

    /**
     * @param allocatable $student
     * @param $grade
     */
    public function should_have_moderator_grade_for($student, $grade) {
        $identifier = $this->allocatable_row_id($student) . ' .moderation_cell';
        $text = $this->getPage()->find('css', $identifier)->getText();
        assertContains($grade, $text);
    }

    /**
     * @param allocatable $student
     * @throws Behat\Mink\Exception\ElementException
     */
    public function click_edit_moderator_feedback_button($student) {
        $identifier = $this->allocatable_row_id($student) . ' .moderation_cell .edit_feedback';
        $this->getPage()->find('css', $identifier)->click();
    }

    /**
     * @param allocatable $allocatable
     * @param int $assessor_number
     * @param int $expected_grade
     */
    public function assessor_grade_should_be_present($allocatable, $assessor_number, $expected_grade) {
        $locator = $this->assessor_feedback_table_id($allocatable) . ' .assessor_'.$assessor_number.' '. $this->assessor_grade_cell_class();
        $grade_container = $this->getPage()->find('css', $locator);
        $text = $grade_container ? $grade_container->getText() : '';
        assertContains((string)$expected_grade, $text);
    }

    /**
     * @param allocatable $allocatable
     * @param int $assessor_number
     * @param int $expected_grade
     */
    public function assessor_grade_should_not_be_present($allocatable, $assessor_number, $expected_grade) {
        $locator =
            $this->assessor_feedback_table_id($allocatable) . ' .assessor_' . $assessor_number . ' ' . $this->assessor_grade_cell_class();
        $cell = $this->getPage()->findAll('css', $locator);
        if (!empty($cell)) {
            $cell = reset($cell);
            assertNotContains($expected_grade, $cell->getText());
        }
    }

    /**
     * @return string
     */
    protected function assessor_grade_cell_class() {
        return '.assessor_feedback_grade';
    }

    /**
     * @param int $assessor_number
     * @param allocatable $allocatable
     */
    public function click_assessor_new_feedback_button($assessor_number, $allocatable) {
        $locator =
            $this->assessor_feedback_table_id($allocatable) . ' .assessor_' . $assessor_number . ' ' . $this->assessor_grade_cell_class().' .new_feedback';
        $this->click_that_thing($locator);
    }

    public function press_publish_button() {
        $this->getPage()->pressButton('id_publishbutton');
    }

    public function confirm_publish_action() {



        if ($this->getPage()->hasButton('Continue')) {
            $this->getPage()->pressButton('Continue');
        } else {
echo "failed";
        }


        if ($this->getPage()->hasLink('Continue')) {
            $this->getPage()->clickLink('Continue');
        } else {

        }
    }

    /**
     * @param feedback $feedback
     * @throws \Behat\Mink\Exception\ElementNotFoundException
     */
    public function click_feedback_show_icon($feedback) {
        $link_id = "show_feedback_" . $feedback->id;
        $this->getPage()->clickLink($link_id);

        if ($this->getPage()->hasLink('Continue')) {
            $this->getPage()->clickLink('Continue');
        }
    }

    /**
     * @param feedback $feedback
     */
    public function should_not_have_show_feedback_icon($feedback) {
        $link_id = "show_feedback_" . $feedback->id;
        $this->should_not_have_css($link_id);
    }

    /**
     * @param feedback $feedback
     */
    public function should_not_have_grade_in_assessor_table($feedback) {
        $assessor = $feedback->assessor();
        $row_class = '.feedback-' . $assessor->id() . '-' . $feedback->get_allocatable()
                ->id() . '.' . $feedback->get_stage()->identifier().' .assessor_feedback_grade';

        $this->should_not_have_css($row_class, $feedback->grade);
    }

    /**
     * @param feedback $feedback
     */
    public function should_have_grade_in_assessor_table($feedback) {
        $assessor = $feedback->assessor();
        $row_class = '.feedback-' . $assessor->id() . '-' . $feedback->get_allocatable()
                ->id() . '.' . $feedback->get_stage()->identifier() . ' .assessor_feedback_grade';

        $this->should_have_css($row_class, $feedback->grade);
    }

    /**
     * @param feedback $feedback
     */
    public function should_not_have_edit_link_for_feedback($feedback) {
        $identifier = '#edit_feedback_'.$feedback->id;
        $this->should_not_have_css($identifier);
    }

    /**
     * @param feedback $feedback
     */
    public function should_have_edit_link_for_feedback($feedback) {
        $identifier = '#edit_feedback_'.$feedback->id;
        $this->should_have_css($identifier);
    }

    /**
 * @param $allocatable
 */
    public function should_not_have_add_button_for_final_feedback($studentid) {
        $identifier = '#new_final_feedback_'.$studentid;
        $this->should_not_have_css($identifier);
    }

    /**
     * @param $allocatable
     */
    public function should_have_add_button_for_final_feedback($studentid) {
        $identifier = '#new_final_feedback_'.$studentid;
        $this->should_have_css($identifier);
    }



    /**
     * @param feedback $feedback
     */
    public function should_not_have_edit_link_for_final_feedback($allocatable) {
        $identifier = '#edit_final_feedback_' . $this->allocatable_identifier_hash($allocatable);
        $this->should_not_have_css($identifier);
    }


    /**
     * @param submission $submission
     */
    public function should_not_have_new_feedback_button($submission)
    {
        $elementid = $this->new_feedback_button_css($submission);
        $this->should_not_have_css($elementid);
    }

    /**
     * @param submission $submission
     */
    public function should_have_new_feedback_button($submission) {
        $elementid = $this->new_feedback_button_css($submission);
        echo $elementid;
        $this->should_have_css($elementid);
    }

    /**
     * @param submission $submission
     * @return string
     */
    protected function new_feedback_button_css($submission) {
        $elementid = '#assessorfeedbacktable_' . $submission->get_coursework()
                ->get_allocatable_identifier_hash($submission->get_allocatable()). ' .new_feedback';
        return $elementid;
    }

    /**
     * @param submission $submission
     * @return string
     */
    public function get_provisional_grade_field($submission) {
       $elementid = '#allocatable_' . $submission->get_coursework()
                ->get_allocatable_identifier_hash($submission->get_allocatable()). ' .assessor_feedback_grade';
       $grade_field =  $this->getPage()->find('css', $elementid);
       return $grade_field ? $grade_field->getValue() : false;
    }

    /**
     * @param submission $submission
     * @return string
     */
    public function get_grade_field($submission){
        $elementid = '#assessorfeedbacktable_' . $submission->get_coursework()
                 ->get_allocatable_identifier_hash($submission->get_allocatable()). ' .grade_for_gradebook_cell';
        $grade_field =  $this->getPage()->find('css', $elementid);
        return $grade_field ? $grade_field->getValue() : false;
    }

    /**
     * @param user $student
     * @throws \Behat\Mink\Exception\ElementException
     */
    public function click_new_extension_button_for($student) {
        $element_selector = $this->allocatable_row_id($student).' .new_deadline_extension';
        $this->getPage()->find('css', $element_selector)->click();
    }

    /**
     * @param user $student
     * @throws \Behat\Mink\Exception\ElementException
     */
    public function click_edit_extension_button_for($student) {
        $element_selector = $this->allocatable_row_id($student) . ' .edit_deadline_extension';
        $this->getPage()->find('css', $element_selector)->click();
    }

    /**
     * @param allocatable $student
     * @param int $deadline_extension
     */
    public function should_show_extension_for_allocatable($student, $deadline_extension) {
        $element_selector = $this->allocatable_row_id($student).' .time_submitted_cell';
        $this->should_have_css($element_selector, userdate($deadline_extension, '%a, %d %b %Y, %H:%M' ));
    }

    /**
     * @param allocatable $student
     */
    public function should_show_extension_reason_for_allocatable($student) {
        $element_selector = $this->allocatable_row_id($student) . ' .time_submitted_cell';
        $reasons = coursework::extension_reasons();
        $this->should_have_css($element_selector, $reasons[1]);
    }


    /**
     * @param allocatable $allocatable
     * @throws \Behat\Mink\Exception\ElementException
     */
    public function click_new_submission_button_for($allocatable) {
        $element_selector = $this->allocatable_row_id($allocatable) . ' .new_submission';
        $this->getPage()->find('css', $element_selector)->click();
    }

    /**
     * @param allocatable $allocatable
     * @throws \Behat\Mink\Exception\ElementException
     */
    public function click_edit_submission_button_for($allocatable) {
        $element_selector = $this->allocatable_row_id($allocatable) . ' .edit_submission';
        $this->getPage()->find('css', $element_selector)->click();
    }
}