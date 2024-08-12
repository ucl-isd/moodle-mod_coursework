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
