<?php

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/coursework/tests/behat/pages/page_base.php');


/**
 * Holds the functions that know about the HTML structure of the student page.
 *
 *
 */
class mod_coursework_behat_student_submission_form extends mod_coursework_behat_page_base {

    public function click_on_the_save_submission_button() {
        $this->getPage()->find('xpath', "//input[@id='id_submitbutton']")->press();
    }

    public function click_on_the_save_and_finalise_submission_button() {
        $this->getPage()->find('css', "#id_finalisebutton")->press();
    }

    public function should_not_have_the_save_and_finalise_button() {
        $buttons = $this->getPage()->findAll('css', '#id_finalisebutton');
        assertEmpty($buttons);
    }
}