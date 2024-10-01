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
 * @copyright  2017 University of London Computer Centre {@link http://ulcc.ac.uk}
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
        $numberoffiles = count($files);

        $expectednumberoffiles = 2;
        if (!$numberoffiles == $expectednumberoffiles) {
            $message = 'Expected 2 submission files but there were ' . $numberoffiles;
            throw new ExpectationException($message, $this->getSession());
        }
    }

    /**
     * @param int $expectednumberoffiles
     * @throws ExpectationException
     */
    public function should_have_number_of_feedback_files($expectednumberoffiles) {

        $files = $this->getPage()->findAll('css', '.feedbackfile');
        $numberoffiles = count($files);

        if (!$numberoffiles == $expectednumberoffiles) {
            $message = 'Expected '.$expectednumberoffiles.' feedback files but there were ' . $numberoffiles;
            throw new ExpectationException($message, $this->getSession());
        }
    }

    /**
     * @param $rolename
     */
    public function should_show_the_submitter_as($rolename) {
        $submissionusercell = $this->getPage()->find('css', 'td.submission-user');
        $cellcontents = $submissionusercell->getText();
        $studentname = fullname((object)(array)$this->getContext()->$rolename);
        if (!str_contains($cellcontents, $studentname)) {
            throw new ExpectationException(
                "Expected the submission to have been made by {$studentname}, but got {$cellcontents}",
                $this->getSession()
            );
        }
    }

    /**
     * @return string|null
     */
    public function get_visible_grade(): ?string {
        // final_feedback_grade
        $finalgradecell = $this->getPage()->find('css', '#final_feedback_grade');
        return $finalgradecell ? $finalgradecell->getText() : null;
    }

    /**
     * @return string
     */
    public function get_visible_feedback() {
        // final_feedback_grade
        $finalgradecell = $this->getPage()->find('css', '#final_feedback_comment');
        return $finalgradecell->getText();
    }

    public function has_finalise_button(): bool {
        return !empty($this->getPage()->findAll('css', '.finalisesubmissionbutton'));
    }
}
