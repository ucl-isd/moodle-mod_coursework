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

use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Exception\ElementNotFoundException;

/**
 * Holds the functions that know about the HTML structure of the student page.
 *
 *
 */
class mod_coursework_behat_student_page extends mod_coursework_behat_page_base {

    public function should_have_two_submission_files() {

        $files = $this->getPage()->findAll('css', '.submissionfile');
        $number_of_files = count($files);

        $expected_number_of_files = 2;
        if (!$number_of_files == $expected_number_of_files) {
            $message = 'Expected 2 submission files but there were ' . $number_of_files;
            throw new ExpectationException($message, $this->getSession());
        }
    }

    /**
     * @param int $expected_number_of_files
     * @throws ExpectationException
     */
    public function should_have_number_of_feedback_files($expected_number_of_files) {

        $files = $this->getPage()->findAll('css', '.feedbackfile');
        $number_of_files = count($files);

        if (!$number_of_files == $expected_number_of_files) {
            $message = 'Expected '.$expected_number_of_files.' feedback files but there were ' . $number_of_files;
            throw new ExpectationException($message, $this->getSession());
        }
    }

    /**
     * @param $rolename
     */
    public function should_show_the_submitter_as($rolename) {
        $submission_user_cell = $this->getPage()->find('css', 'td.submission-user');
        $cell_contents = $submission_user_cell->getText();
        $student_name = fullname((object)(array)$this->getContext()->$rolename);
        if (!str_contains($cell_contents, $student_name)) {
            throw new ExpectationException(
                "Expected the submission to have been made by {$student_name}, but got {$cell_contents}",
                $this->getSession()
            );
        }
    }

    /**
     * @return string
     */
    public function get_visible_grade(): ?string {
        // final_feedback_grade
        $final_grade_cell = $this->getPage()->find('css', '#final_feedback_grade');
        return $final_grade_cell ? $final_grade_cell->getText() : null;
    }

    /**
     * @return string
     */
    public function get_visible_feedback() {
        // final_feedback_grade
        $final_grade_cell = $this->getPage()->find('css', '#final_feedback_comment');
        return $final_grade_cell->getText();
    }

    public function has_finalise_button(): bool {
        return !empty($this->getPage()->findAll('css', '.finalisesubmissionbutton'));
    }
}
