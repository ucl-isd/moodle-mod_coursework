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
class mod_coursework_behat_coursework_page extends mod_coursework_behat_page_base {

    /**
     * @return bool
     */
    public function individual_feedback_date_present() {
        $table = $this->getPage()->find('css', 'table.deadlines');
        $table_header_present = strpos($table->getText(), 'utomatically release individual feedback') !== false;
        return $table_header_present;
    }

    /**
     * @return bool
     */
    public function general_feedback_date_present() {
        $table = $this->getPage()->find('css', 'table.deadlines');
        $table_header_present = strpos($table->getText(), 'General feedback deadline');
        return $table_header_present !== false;
    }

    public function confirm() {
        if ($this->has_that_thing('input', 'Yes')) {
            $this->click_that_thing('input', 'Yes');
        } else if ($this->has_that_thing('button', 'Yes')) {
            $this->click_that_thing('button', 'Yes');
        }
    }


    public function show_hide_non_allocated_students()  {
        if ($this->getPage()->hasLink('Show submissions for other students')) {
            $this->getPage()->clickLink('Show submissions for other students');
        }
    }


}