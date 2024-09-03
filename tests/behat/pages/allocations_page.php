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
use mod_coursework\models\user;
use \Behat\Mink\Exception\ElementNotFoundException;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/coursework/tests/behat/pages/page_base.php');

/**
 * Holds the functions that know about the HTML structure of the student page.
 *
 *
 */
class mod_coursework_behat_allocations_page extends mod_coursework_behat_page_base {

    public function save_everything() {
        $this->getPage()->pressButton('save_manual_allocations_1');

        if ($this->getPage()->hasLink('Continue')) {
            $this->getPage()->clickLink('Continue');
        }
    }

    /**
     * @param \mod_coursework\models\user $user
     * @param string $stageidentifier e.g. 'assessor_1'
     * @throws Behat\Mink\Exception\ElementNotFoundException
     */
    public function user_allocated_assessor($user, $stageidentifier): string {
        $cell_span = $this->getPage()->find('css', '#user_'.$user->id.' .'.$stageidentifier.' .existing-assessor');
        return $cell_span ? $cell_span->getText() : '';
    }

    /**
     * @param allocatable $allocatable
     * @param user $assessor
     * @param string $stage_identifier
     */
    public function manually_allocate($allocatable, $assessor, $stage_identifier) {

        // Identify the allocation dropdown.
        $dropdown_id = $allocatable->type().'_' . $allocatable->id . '_'.$stage_identifier;
        $node = $this->getContext()->find_field($dropdown_id);

        // We delegate to behat_form_field class, it will
        // guess the type properly as it is a select tag.
        $field = behat_field_manager::get_form_field($node, $this->getSession());
        $field->set_value($assessor->id());

        $this->pin_allocation($allocatable, $stage_identifier);
    }

    /**
     * @param allocatable $student
     * @param string $stage_identifier
     */
    public function select_for_sample($student, $stage_identifier) {
        $elementid = $this->sampling_checkbox_id($student, $stage_identifier);
        $node = $this->getPage()->find('css', $elementid);
        $node->check();
    }

    /**
     * @param allocatable $allocatable
     * @param string $stage_identifier
     */
    private function pin_allocation($allocatable, $stage_identifier) {
        $name = "//input[@name='allocatables[".$allocatable->id()."][".$stage_identifier."][pinned]']";
        $nodes = $this->getPage()->findAll('xpath', $name);

        // We delegate to behat_form_field class, it will
        // guess the type properly as it is a select tag.
        if ($nodes) {
            $field = behat_field_manager::get_form_field(reset($nodes), $this->getSession());
            $field->set_value(true);
        }
    }

    public function show_assessor_allocation_settings() {
        $this->getPage()->find('css', '#assessor_allocation_settings_header')->click();
        $this->getSession()->wait(1000);
    }

    /**
     * @param allocatable $allocatable
     */
    public function should_not_have_moderator_allocated($allocatable) {
        $locator = '#'.$allocatable->type().'_'.$allocatable->id().' .moderator_1 .existing-assessor';
        $this->should_not_have_css($locator);
    }

    /**
     * @param allocatable $allocatable
     * @param allocatable $assessor
     */
    public function should_have_moderator_allocated($allocatable, $assessor) {
        $locator = '#' . $allocatable->type() . '_' . $allocatable->id() . ' .moderator_1 .existing-assessor';
        $this->should_have_css($locator, $assessor->name());
    }

    /**
     * @param allocatable $student
     * @param $stage_identifier
     * @throws \Behat\Mink\Exception\ElementException
     */
    public function deselect_for_sample($student, $stage_identifier) {
        $elementid = $this->sampling_checkbox_id($student, $stage_identifier);
        $node = $this->getPage()->find('css', $elementid);
        $node->uncheck();
    }

    /**
     * @param allocatable $student
     * @param $stage_identifier
     * @return string
     */
    public function sampling_checkbox_id($student, $stage_identifier) {
        $elementid = '#' . $student->type() . '_' . $student->id . '_' . $stage_identifier . '_samplecheckbox';
        return $elementid;
    }

    public function student_should_have_allocation($student, $teacher, $string) {

    }

    /**
     * @param $stage
     * @throws ElementNotFoundException
     */
    public function enable_atomatic_sampling_for($stage) {
        $elementid = '#assessor_'.$stage.'_samplingstrategy';
        $node = $this->getPage()->find('css', $elementid);

        $node->selectOption('Automatic');
    }

    /**
     * @param $stage
     * @throws \Behat\Mink\Exception\ElementException
     */
    public function enable_total_rule_for_stage($stage) {
        $elementid = '#assessor_'.$stage.'_sampletotal_checkbox';
        $node = $this->getPage()->find('css', $elementid);

        $node->check();
    }

    /**
     * @param $stage
     * @throws \Behat\Mink\Exception\ElementException
     */
    public function add_grade_range_rule_for_stage($stage) {
        $elementid = 'assessor_'.$stage.'_addgradderule';

        $this->getPage()->clickLink($elementid);
    }

    /**
     * @param $stage
     * @param $ruleno
     * @throws \Behat\Mink\Exception\ElementException
     */
    public function enable_grade_range_rule_for_stage($stage, $ruleno) {
        $elementid = '#assessor_'.$stage.'_samplerules_'.$ruleno;
        $node = $this->getPage()->find('css', $elementid);

        $node->check();
    }

    /**
     * @param $stage
     * @param $ruleno
     * @param $type
     * @throws ElementNotFoundException
     */
    public function select_type_of_grade_range_rule_for_stage($stage, $ruleno, $type) {
        $elementid = '#assessor_'.$stage.'_sampletype_'.$ruleno;
        $node = $this->getPage()->find('css', $elementid);

        $node->selectOption($type);

    }

    /**
     * @param $range
     * @param $stage
     * @param $ruleno
     * @param $value
     * @throws ElementNotFoundException
     */
    public function select_range_for_grade_range_rule_for_stage($range, $stage, $ruleno, $value) {
        $elementid = '#assessor_'.$stage.'_sample'.$range.'_'.$ruleno;
        $node = $this->getPage()->find('css', $elementid);

        $node->selectOption($value);
    }

    /**
     * @param $percentage
     * @param $stage
     * @throws ElementNotFoundException
     */
    public function select_total_percentage_for_stage($percentage, $stage) {

        // Increment stage as the this will match the id of the element;
        $stage++;

        $elementid = '#assessor_'.$stage.'_sampletotal';
        $node = $this->getPage()->find('css', $elementid);

        $node->selectOption($percentage);
    }

    /**
     * @param $coursework
     * @param $user
     * @param $otheruser
     * @param $stagenumber
     * @return bool
     */
    public function automatically_included_in_sample($coursework, $user, $otheruser, $stagenumber): bool {
        global $DB;

        $othersql = (!empty($otheruser)) ? "OR allocatableid = $otheruser->id" : '';

        $sql = "SELECT *
                     FROM {coursework_sample_set_mbrs}
                     WHERE courseworkid = :courseworkid
                     AND stage_identifier = :stage
                     AND (allocatableid = :user $othersql)";

        $stage = "assessor_".$stagenumber;

        $params = [
            'courseworkid' => $coursework->id,
            'user' => $user->id,
            'stage' => $stage,
        ];

        return $DB->record_exists_sql($sql, $params);
    }

    public function save_sampling_strategy() {
        $this->getPage()->pressButton('save_manual_sampling');
    }
}
