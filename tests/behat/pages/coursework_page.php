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
 * @copyright  2017 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
        $things = $this->getPage()->findAll('css', 'h3');

        foreach ($things as $thing) {
            if ($thing->getText() == 'Auto-release feedback') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function general_feedback_date_present() {
        /*
         * Matches:
         * <div class="d-flex">
         *     <h4 class="h5">General feedback</h4>
         * </div>
         *
         * <p>
         *     <b>Due</b>
         *     ...
         * </p>
         */
        $xpath = "//div/h4[text()='General feedback']/../../p[starts-with(normalize-space(string()),'Due')]";

        if ($this->getPage()->find('xpath', $xpath)) {
            return true;
        }

        return false;
    }

    public function confirm() {
        if ($this->has_that_thing("input[type='submit']", 'Yes')) {
            $this->click_that_thing('input', 'Yes');
        } else if ($this->has_that_thing('button', 'Yes')) {
            $this->click_that_thing('button', 'Yes');
        }
    }

    public function show_hide_non_allocated_students() {
        if ($this->getPage()->hasLink('Show submissions for other students')) {
            $this->getPage()->clickLink('Show submissions for other students');
        }
    }

    public function get_coursework_name($courseworkname) {
        $courseworkheading = $this->getPage()->find('css', '#page-header');
        $courseworkheadingpresent = strpos($courseworkheading->getText(), $courseworkname);

        return $courseworkheadingpresent !== false;
    }

    public function get_coursework_student_name($studentname) {
        $tableusers = $this->getPage()->findAll('css', 'table.submissions');

        if (!empty($tableusers)) {
            foreach ($tableusers as $tableuser) {
                $courseworkstudentname = strpos($tableuser->getText(), $studentname);

                if ($courseworkstudentname !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}
