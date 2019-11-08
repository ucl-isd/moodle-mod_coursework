<?php

use mod_coursework\allocation\allocatable;
use mod_coursework\models\user;

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
     * @param \mod_coursework\models\user $assessor
     * @param string $stage_identifier e.g. 'assessor_1'
     * @throws Behat\Mink\Exception\ElementNotFoundException
     */
    public function user_should_be_alocated_to_assessor($user, $assessor, $stage_identifier) {
        $cell_span = $this->getPage()->find('css', '#user_'.$user->id.' .'.$stage_identifier.' .existing-assessor');
        if (!$cell_span) {
            throw new \Behat\Mink\Exception\ElementNotFoundException($this->getSession(), 'Current allocated assessor ');
        }
        assertEquals($assessor->name(), $cell_span->getText(), 'Expected the allocated teacher name to be '.$assessor->name().' but got '.$cell_span->getText().' instead.');
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
    public function select_for_sample($student, $stage_identifier){
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
    public function deselect_for_sample($student, $stage_identifier)
    {
        $elementid = $this->sampling_checkbox_id($student, $stage_identifier);
        $node = $this->getPage()->find('css', $elementid);
        $node->uncheck();
    }

    /**
     * @param allocatable $student
     * @param $stage_identifier
     * @return string
     */
    public function sampling_checkbox_id($student, $stage_identifier)
    {
        $elementid = '#' . $student->type() . '_' . $student->id . '_' . $stage_identifier . '_samplecheckbox';
        return $elementid;
    }

    public function student_should_have_allocation($student, $teacher, $string) {

    }

    /**
     * @param $stage
     * @throws \Behat\Mink\Exception\ElementNotFoundException
     */
    public function enable_atomatic_sampling_for($stage)  {
        $elementid  =   '#assessor_'.$stage.'_samplingstrategy';
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
     * @throws \Behat\Mink\Exception\ElementNotFoundException
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
     * @throws \Behat\Mink\Exception\ElementNotFoundException
     */
    public function select_range_for_grade_range_rule_for_stage($range, $stage, $ruleno, $value) {
        $elementid = '#assessor_'.$stage.'_sample'.$range.'_'.$ruleno;
        $node = $this->getPage()->find('css', $elementid);

        $node->selectOption($value);
    }

    /**
     * @param $percentage
     * @param $stage
     * @throws \Behat\Mink\Exception\ElementNotFoundException
     */
    public function select_total_percentage_for_stage($percentage,$stage)   {

        //increment stage as the this will match the id of the element;
        $stage++;

        $elementid  =   '#assessor_'.$stage.'_sampletotal';
        $node = $this->getPage()->find('css', $elementid);

        $node->selectOption($percentage);
    }

    /**
     * @param $coursework
     * @param $user
     * @param $stage_number
     */
    public function automatically_included_in_sample($coursework,$user,$other_user,$stage_number,$negate)  {
        global $DB;

        $other_sql = (!empty($other_user))?  "OR allocatableid = $other_user->id" : '';

        $sql    =   "SELECT     *
                     FROM       {coursework_sample_set_mbrs}
                     WHERE      courseworkid = :courseworkid
                     AND        stage_identifier = :stage
                     AND        (allocatableid = :user $other_sql)";


        $stage  =   "assessor_".$stage_number;

        $params     =   array('courseworkid'=>$coursework->id,
            'user'=>$user->id,
            'stage' => $stage);

        if (empty($negate)){
             assertTrue($DB->record_exists_sql($sql,$params));
        } else {
            assertFalse($DB->record_exists_sql($sql, $params));
        }
    }

    /**
     * @param $other
     * @param $role_name
     */

    public function thereIsAnotherTeacher($other, $role_name) {

        $other = ($other == 'another');

        $role_name = str_replace(' ', '', $role_name);

        $role_name_to_save = $other ? 'other_' . $role_name : $role_name;

        $this->$role_name_to_save = $this->create_user($role_name, $role_name_to_save);
    }


    public function save_sampling_strategy()     {

        $this->getPage()->pressButton('save_manual_sampling');

    }



}