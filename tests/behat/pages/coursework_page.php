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

    public function show_hide_non_allocated_students() {
        if ($this->getPage()->hasLink('Show submissions for other students')) {
            $this->getPage()->clickLink('Show submissions for other students');
        }
    }

    public function get_coursework_name($courseworkName) {
        $coursework_heading = $this->getPage()->find('css', 'h2');
        $coursework_heading_present = strpos($coursework_heading->getText(), $courseworkName);

        return $coursework_heading_present !== false;
    }

    public function get_coursework_student_name($studentName) {
        $table_users = $this->getPage()->findAll('css', 'table.submissions');

        if (!empty($table_users)) {
            foreach ($table_users as $table_user) {
                $coursework_student_name = strpos($table_user->getText(), $studentName);

                if ($coursework_student_name !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}
