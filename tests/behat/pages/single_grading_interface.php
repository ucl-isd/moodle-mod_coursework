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
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
    public function student_has_a_final_grade($studenthash): bool {
        $studentgradecell = $this->getPage()->find('css', '#submission_'. $studenthash.' .single_final_grade_cell');
        return !empty($studentgradecell->getText());
    }

    /**
     * @param allocatable $allocatable
     * @return bool is there an icon?
     */
    public function there_is_a_feedback_icon($allocatable): bool {
        $feedbackcell = $this->getPage()->find('css', $this->allocatable_row_id($allocatable).' .single_assessor_feedback_cell');
        $feedbackicon = $feedbackcell->findAll('css', '.smallicon');
        return !empty($feedbackicon);
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
        $cellcss = '.single_assessor_feedback_cell';
        $this->should_have_css($cellcss, $assessor->name());
    }

    /**
     * @param \mod_coursework\allocation\allocatable $allocatable
     * @return string
     */
    private function allocatable_row_id(\mod_coursework\allocation\allocatable $allocatable) {
        return '#allocatable_' . $this->allocatable_identifier_hash($allocatable);
    }
}
