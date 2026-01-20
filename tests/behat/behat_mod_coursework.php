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

/**
 * Step definitions for the Coursework module Behat tests.
 */

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\{ElementNotFoundException, ExpectationException};
use mod_coursework\models\allocation;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\models\group;
use mod_coursework\models\submission;
use mod_coursework\router;
use mod_coursework\stages\base as stage_base;
use mod_coursework\auto_grader\average_grade_no_straddle;

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

$files = glob(dirname(__FILE__) . '/steps/*.php');
foreach ($files as $filename) {
    require_once($filename);
}

/**
 * Class behat_mod_coursework
 * @property mixed teacher
 * @property mixed otherteacher
 * @property submission submission
 * @property stdClass course
 * @property mixed form
 * @property coursework coursework
 * @property mixed student
 * @property feedback feedback
 * @property mixed manager
 * @property mixed allocation
 * @property mixed finalfeedback
 * @property mixed otherstudent
 * @property mixed group
 */
class behat_mod_coursework extends behat_base {
    /**
     * @var int numbers prepended to 'user' in order to create different roles
     * without username/email collisions.
     */
    protected $usersuffix = 0;

    public $coursework;

    public $course;

    public $editingteacher;

    public $teacher;

    public $manager;

    public $student;

    public $extensiondeadline;

    public $group;

    public $feedback;

    public $finalfeedback;

    public $submission;

    public $othersubmission;

    public $otherteacher;

    public $otherstudent;

    /**
     * Factory that makes an instance of the page class, passing in the session context, then caches it
     * and returns it when required.
     *
     * @param string $pagename
     * @throws coding_exception
     * @return mod_coursework_behat_page_base
     */
    protected function get_page($pagename) {
        global $CFG;

        $pagename = str_replace(' ', '_', $pagename);

        $filepath = $CFG->dirroot . '/mod/coursework/tests/behat/pages/' . $pagename . '.php';

        if (file_exists($filepath)) {
            require_once($filepath);
            $classname = 'mod_coursework_behat_' . $pagename;
            return new $classname($this);
        }

        throw new coding_exception('Asked for a behat page class which does not exist: ' . $pagename);
    }

    /**
     * Centralises the match between the names of paths in the module and the urls they correspond to.
     *
     * @param string $path
     * @param bool $escape
     * @throws coding_exception
     * @throws moodle_exception
     * @return string the url
     */
    protected function locate_path($path, $escape = true) {

        switch ($path) {
            case 'course':
                return parent::locate_path('/course/view.php?id=' . $this->course->id);
                break;

            case 'edit coursework':
                return parent::locate_path('/mod/edit.php');
                break;

            case 'coursework settings':
                return parent::locate_path('/course/modedit.php?update=' . $this->get_coursework()->get_course_module()->id);
                break;

            case 'coursework':
                return parent::locate_path('/mod/coursework/view.php?id=' . $this->get_coursework()->get_course_module()->id);
                break;

            case 'allocations':
                return parent::locate_path('/mod/coursework/actions/allocate.php?id=' . $this->get_coursework()->get_course_module()->id);

            case 'assessor grading':
                return parent::locate_path('/mod/coursework/actions/feedback/new.php?submissionid=' . $this->submission->id . '&assessorid=' . $this->teacher->id);

            case 'new feedback':
                return $this->get_router()->get_path(
                    'new feedback',
                    ['submission' => $this->submission,
                                                           'assessor' => $this->teacher,
                                                           'stage' => $this->get_first_assesor_stage()],
                    false,
                    $escape
                );
            case 'create feedback':
                return $this->get_router()->get_path(
                    'create feedback',
                    ['coursework' => $this->coursework],
                    false,
                    $escape
                );

            case 'new submission':
                $submission = submission::build([
                                                    'courseworkid' => $this->coursework->id,
                                                    'allocatableid' => $this->student->id,
                                                    'allocatabletype' => 'user',
                                                ]);
                return $this->get_router()->get_path(
                    'new submission',
                    ['submission' => $submission],
                    false,
                    $escape
                );

            case 'create submission':
                return $this->get_router()->get_path(
                    'create submission',
                    ['coursework' => $this->coursework],
                    false,
                    $escape
                );

            case 'edit submission':
                return $this->get_router()->get_path(
                    'edit submission',
                    ['submission' => $this->submission],
                    false,
                    $escape
                );

            case 'update submission':
                return $this->get_router()->get_path(
                    'update submission',
                    ['submission' => $this->submission],
                    false,
                    $escape
                );

            case 'edit feedback':
                if (empty($this->feedback)) {
                    $this->feedback = feedback::last();
                }
                return $this->get_router()->get_path('edit feedback', ['feedback' => $this->feedback], false, $escape);

            case 'gradebook':
                return parent::locate_path('/grade/report/user/index.php?id=' . $this->course->id);
                break;

            case 'login':
                return parent::locate_path('/login/index.php');
                break;

            default:
                return parent::locate_path($path);
        }
    }

    /**
     * @Given /^I should( not)? see the file on the page$/
     *
     * @param bool $negate
     * @throws ExpectationException
     */
    public function i_should_see_the_file_on_the_page($negate = false) {
        $filecount = count($this->getsession()->getpage()->findAll('css', '.submissionfile'));
        if (!$negate && !$filecount) {
            throw new ExpectationException('No files found', $this->getsession());
        } else if ($negate && $filecount) {
            throw new ExpectationException('Files found, but there should be none', $this->getsession());
        }
    }

    /**
     * @Then /^I should see (\d+) file(?:s)? on the page$/
     *
     * @param $numberoffiles
     * @throws ExpectationException
     */
    public function i_should_see_files_on_the_page($numberoffiles) {
        $filecount = count($this->getsession()->getpage()->findAll('css', '.submissionfile'));

        if ($numberoffiles != $filecount) {
            throw new ExpectationException($filecount . ' files found, but there should be ' . $numberoffiles, $this->getsession());
        }
    }

    /**
     * @When /^the cron runs$/
     */
    public function the_cron_runs() {
        coursework_cron();
    }

    /**
     * @Then /^I (should|should not) see (the|another) student's name on the page$/
     * @param string $shouldornot
     * @param string $studentrole
     * @throws ExpectationException
     * @throws coding_exception
     */
    public function i_should_see_the_students_name_on_the_page(string $shouldornot, string $studentrole) {
        $page = $this->get_page('coursework page');
        $student = ($studentrole == "another") ? $this->otherstudent : $this->student;
        // The var $student is a user object but we must pass stdClass to fullname() to avoid  core error.
        $studentname = fullname((object)(array)$student);
        $studentfound = $page->get_coursework_student_name($studentname);
        $should = ($shouldornot == 'should');
        if (!$should && $studentfound) {
            throw new ExpectationException(
                "Student '$studentname' found but should not be",
                $this->getsession()
            );
        } else if ($should && !$studentfound) {
            throw new ExpectationException(
                "Student '$studentname' not found but should be",
                $this->getsession()
            );
        }
    }

    /**
     * Returns the last created coursework.
     *
     * @return false|coursework
     */
    private function get_coursework() {
        if (empty($this->coursework)) {
            $this->coursework = coursework::last();
        }

        return $this->coursework;
    }

    /**
     * @return stage_base
     */
    private function get_first_assesor_stage() {
        $stages = $this->coursework->get_assessor_marking_stages();
        return reset($stages);
    }

    /**
     * Returns an xpath string to find a tag that has a class and contains some text.
     *
     * @param string $tagname div td
     * @param string $class
     * @param string $text
     * @param bool $exacttext
     * @throws coding_exception
     * @return string
     */
    private function xpath_tag_class_contains_text($tagname = '', $class = '', $text = '', $exacttext = false) {

        if (!$class && !$text) {
            throw new coding_exception('Must supply one of class or text');
        }

        $xpath = '//';
        $xpath .= $tagname;

        if ($class) {
            $xpath .= "[contains(concat(' ', @class, ' '), ' {$class} ')]";
        }

        if ($text) {
            $xpath .= "[contains(., '{$text}')]";
        }

        return $xpath;
    }

    /**
     * @return router
     */
    protected function get_router() {

        return router::instance();
    }

    /**
     * In case we just created a feedback with a form submission, we want to get hold of it.
     * @return mixed
     */
    protected function get_feedback() {
        if (empty($this->feedback)) {
            $this->feedback = feedback::last();
        }

        return $this->feedback;
    }

    /**
     * @Then /^I should see the student allocated to the other teacher for the first assessor$/
     */
    public function i_should_see_the_student_allocated_to_the_other_teacher() {
        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        $allocatedassessor = $page->user_allocated_assessor($this->student, 'assessor_1');
        if ($allocatedassessor != $this->otherteacher->name()) {
            $message = "Expected the allocated teacher name to be '{$this->otherteacher->name()}'"
                . " but got '$allocatedassessor' instead.";
            throw new ExpectationException($message, $this->getsession());
        }
    }

    /**
     * @Then /^I should see the student allocated to the teacher for the first assessor$/
     */
    public function i_should_see_the_student_allocated_to_the_teacher() {
        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        $allocatedassessor = $page->user_allocated_assessor($this->student, 'assessor_1');
        if ($allocatedassessor != $this->teacher->name()) {
            $message = 'Expected the allocated teacher name to be ' . $this->teacher->name()
                . ' but got ' . $allocatedassessor . ' instead.';
            throw new ExpectationException($message, $this->getsession());
        }
    }

    /**
     * @Then /^there should be no allocations in the db$/
     */
    public function there_should_be_no_allocations_in_the_db() {
        $params = [
            'courseworkid' => $this->coursework->id,
        ];
        $count = \mod_coursework\models\allocation::count($params);
        if ($count !== 0) {
            throw new ExpectationException(
                "Found '$count' allocations in the database for coursework ID '{$this->coursework->id}'",
                $this->getsession()
            );
        };
    }

    /**
     * @Then /^I should not see the finalise button$/
     */
    public function i_should_not_see_the_finalise_button() {
        /**
         * @var mod_coursework_behat_student_page $page
         */
        $page = $this->get_page('student page');
        if ($page->has_finalise_button()) {
            throw new ExpectationException('Should not have finalise button', $this->getsession());
        }
    }

    /**
     * @Given /^I save the submission$/
     */
    public function i_save_the_submission() {
        /**
         * @var mod_coursework_behat_student_submission_form $page
         */
        $page = $this->get_page('student submission form');
        $page->click_on_the_save_submission_button();
    }

    /**
     * @Given /^I save and finalise the submission$/
     */
    public function i_save_and_finalise_the_submission() {
        /**
         * @var mod_coursework_behat_student_submission_form $page
         */
        $page = $this->get_page('student submission form');
        $page->click_on_the_save_and_finalise_submission_button();
    }

    /**
     * @Then /^I should( not)? see the save and finalise button$/
     */
    public function i_should_see_the_save_and_finalise_button($negate = false) {
        /**
         * @var mod_coursework_behat_student_submission_form $page
         */
        $page = $this->get_page('student submission form');
        if ($negate && $page->has_the_save_and_finalise_button()) {
            throw new ExpectationException("Should not have save and finalise button", $this->getsession());
        } else if (!$negate && !$page->has_the_save_and_finalise_button()) {
            throw new ExpectationException("Should have save and finalise button", $this->getsession());
        }
    }

    /**
     * @Given /^the submission deadline has passed$/
     */
    public function the_submission_deadline_has_passed() {
        $this->coursework->update_attribute('deadline', strtotime('1 hour ago'));
    }

    /**
     * @Given /^the coursework has moderation enabled$/
     */
    public function the_coursework_has_moderation_enabled() {
        $this->coursework->update_attribute('moderationenabled', 1);
    }

    /**
     * @Given /^the coursework has (\d) assessor$/
     * @param $numberofassessors
     */
    public function the_coursework_has_one_assessor($numberofassessors) {
        $this->coursework->update_attribute('numberofmarkers', $numberofassessors);
    }

    /**
     * @Given /^there is( finalised)? feedback for the submission from the teacher$/
     */
    public function there_is_feedback_for_the_submission_from_the_teacher($finalised = false) {
        $feedback = new stdClass();
        $feedback->submissionid = $this->submission->id;
        $feedback->assessorid = $this->teacher->id;
        $feedback->lasteditedbyuser = $this->teacher->id;
        $feedback->grade = 58;
        $feedback->feedbackcomment = 'Blah';
        $feedback->stageidentifier = 'assessor_1';
        $feedback->finalised = $finalised ? 1 : 0;
        $this->feedback = feedback::create($feedback);
    }

    /**
     * @Then /^I should( not)? see the new moderator feedback button for the student$/
     * @param bool $negate
     * @throws coding_exception
     */
    public function i_should_see_the_new_moderator_feedback_button($negate = false) {

        /**
         * @var mod_coursework_behat_single_grading_interface $page
         */
        $page = $this->get_page('single grading interface');
        if ($negate) {
            $page->should_not_have_new_moderator_feedback_button($this->student);
        } else {
            $page->should_have_new_moderator_feedback_button($this->student);
        }
    }

    /**
     * @Given /^the other student is in the moderation set$/
     */
    public function the_other_student_is_in_the_moderation_set() {
        $membership = new stdClass();
        $membership->allocatabletype = 'user';
        $membership->allocatableid = $this->otherstudent->id;
        $membership->courseworkid = $this->coursework->id;
        \mod_coursework\models\assessment_set_membership::create($membership);
    }

    /**
     * @Given /^the student is in the moderation set$/
     */
    public function the_student_is_in_the_moderation_set() {
        $membership = new stdClass();
        $membership->allocatabletype = 'user';
        $membership->allocatableid = $this->student->id;
        $membership->courseworkid = $this->coursework->id;
        \mod_coursework\models\assessment_set_membership::create($membership);
    }

    /**
     * @Given /^the moderator allocation strategy is set to equal$/
     */
    public function the_moderator_allocation_strategy_is_set_to_equal() {
        $this->coursework->update_attribute('moderatorallocationstrategy', 'equal');
    }

    /**
     * @Then /^the student should not have anyone allocated as a moderator$/
     */
    public function the_student_should_not_have_anyone_allocated_as_a_moderator() {
        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        $page->should_not_have_moderator_allocated($this->student);
    }

    /**
     * @Then /^the student should have the manager allocated as the moderator$/
     */
    public function the_student_should_have_the_manager_allocated_as_the_moderator() {
        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        $page->should_have_moderator_allocated($this->student, $this->manager);
    }

    /**
     * @Given /^the coursework has automatic assessor allocations disabled$/
     */
    public function the_coursework_has_automatic_assessor_allocations_disabled() {
        $this->coursework->update_attribute('assessorallocationstrategy', 'none');
    }

    /**
     * @Given /^the coursework has automatic assessor allocations enabled$/
     */
    public function the_coursework_has_automatic_assessor_allocations_enabled() {
        $this->coursework->update_attribute('allocationenabled', '1');
    }

    /**
     * @Given /^I click on the add feedback button for assessor (\d+)$/
     * @param $assessornumber
     * @throws coding_exception
     */
    public function i_click_on_the_new_feedback_button_for_assessor($assessornumber) {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_assessor_new_feedback_button($assessornumber, $this->student);
    }

    /**
     * @Given /^I click on the add feedback button$/
     */
    public function i_click_on_the_new_feedback_button() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_assessor_new_feedback_button(null, $this->student);
    }

    /**
     * @Given /^I click on the edit feedback button for assessor (\d+)$/
     * @param $assessornumber
     * @throws coding_exception
     */
    public function i_click_on_the_edit_feedback_button_for_assessor($assessornumber) {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_assessor_edit_feedback_button($assessornumber, $this->student);
    }

    /**
     * @Given /^I grade the submission as ([\d\.]*) using the grading form$/
     * @param $grade
     * @throws coding_exception
     */
    public function i_grade_the_submission_using_the_grading_form($grade) {
        $fieldnode = $this->find_field('grade');
        $field = behat_field_manager::get_form_field($fieldnode, $this->getsession());
        $field->set_value($grade);

        $this->getsession()->getpage()->findButton('submitbutton')->press();
    }

    /**
     * The UI may contain multiple "New feedback" buttons some of which are not interactable
     * @Given /^I click on the only interactable link with title "(?P<linktitle_string>(?:[^"]|\\")*)"$/
     * @param string $linktitle
     */
    public function i_click_on_the_only_interactable_link_with_title(string $linktitle) {
        $nodes = $this->find_all('link', $linktitle);
        $visible = [];
        foreach ($nodes as $node) {
            if ($node->isVisible()) {
                $visible[] = $node;
            }
        }
        $countvisible = count($visible);
        if ($countvisible !== 1) {
            throw new ExpectationException(
                "Expected one '$linktitle' visible link but found $countvisible",
                $this->getsession()
            );
        }
        reset($visible)->click();
    }

    /**
     * @Given /^I click on the add feedback button for assessor (\d+) for another student$/
     * @param $assessornumber
     * @throws coding_exception
     */
    public function i_click_on_the_new_feedback_button_for_assessor_for_another_student($assessornumber) {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_assessor_new_feedback_button($assessornumber, $this->otherstudent);
    }

    /**
     * @Given /^I publish the grades$/
     */
    public function i_publish_the_grades() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');

        $page->press_publish_button();
        $page->confirm_publish_action();
    }

    /**
     * @Then /^the coursework general feedback is disabled$/
     */
    public function the_coursework_general_feedback_is_disabled() {
        $this->coursework->disable_general_feedback();
    }

    /**
     * @Then /^the coursework general feedback is enabled$/
     */
    public function the_coursework_general_feedback_is_enabled() {
        $this->coursework->enable_general_feedback();
    }

    /**
     * @Then /^the coursework general feedback should be disabled$/
     */
    public function the_coursework_general_feedback_should_be_disabled() {
        $this->get_coursework()->reload();
        if ($this->get_coursework()->is_general_feedback_enabled()) {
            throw new ExpectationException(
                "Feedback is enabled for for coursework ID '{$this->coursework->id}' and should be disabled",
                $this->getsession()
            );
        };
    }

    /**
     * @Given /^blind marking is enabled$/
     */
    public function blind_marking_is_enabled() {
        $this->get_coursework()->update_attribute('blindmarking', 1);
        $this->get_coursework()->update_attribute('renamefiles', 1);
    }

    /**
     * @Then /^I should( not)? see the student's name in the user cell$/
     *
     * @param bool $negate
     */
    public function i_should_see_the_students_name_in_the_user_cell(bool $negate = false) {
        /**
         * @var $page mod_coursework_behat_single_grading_interface
         */
        $page = $this->get_page('single grading interface');
        $found = $page->should_have_user_name_in_user_cell($this->student);

        if ($negate && $found) {
            throw new ExpectationException('User name unexpectedly found in cell', $this->getsession());
        } else if (!$negate && !$found) {
            throw new ExpectationException('User name not found in cell', $this->getsession());
        }
    }

    /**
     * @Then /^I should( not)? see the student's picture in the user cell$/
     *
     * @param bool $negate
     */
    public function i_should_see_the_students_picture_in_the_user_cell($negate = false) {
        /**
         * @var $page mod_coursework_behat_single_grading_interface
         */
        $page = $this->get_page('single grading interface');
        $found = $page->should_have_user_picture_in_user_cell();

        if ($negate && $found) {
            throw new ExpectationException('User picture unexpectedly found in cell', $this->getsession());
        } else if (!$negate && !$found) {
            throw new ExpectationException('User picture not found in cell', $this->getsession());
        }
    }

    /**
     * @Given /^group submissions are enabled$/
     */
    public function group_submissions_are_enabled() {
        $this->get_coursework()->update_attribute('usegroups', 1);
    }

    /**
     * @Given /^the group is part of a grouping for the coursework$/
     */
    public function the_group_is_part_of_a_grouping_for_the_coursework() {
        $generator = testing_util::get_data_generator();
        $grouping = new stdClass();
        $grouping->courseid = $this->course->id;
        $grouping = $generator->create_grouping($grouping);
        groups_assign_grouping($grouping->id, $this->group->id);
        $this->get_coursework()->update_attribute('grouping_id', $grouping->id);
    }

    /**
     * @Then /^I should not see the student's name in the group cell$/
     */
    public function i_should_not_see_the_student_s_name_in_the_group_cell() {
        /**
         * @var $page mod_coursework_behat_single_grading_interface
         */
        $page = $this->get_page('single grading interface');
        $page->should_not_have_user_name_in_group_cell($this->student);
    }

    /**
     * @Then /^I should see the student's name in the group cell$/
     */
    public function i_should_see_the_students_name_in_the_group_cell() {
        /**
         * @var $page mod_coursework_behat_single_grading_interface
         */
        $page = $this->get_page('single grading interface');
        $page->should_have_user_name_in_group_cell($this->student);
    }

    /**
     * @When /^I click on the view icon for the first initial assessor's grade$/
     */
    public function i_click_on_the_view_icon_for_the_first_initial_assessor_s_grade() {
        $feedback = $this->get_initial_assessor_feedback_for_student();
        /**
         * @var $page mod_coursework_behat_multiple_grading_interface
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_feedback_show_icon($feedback);
    }

    /**
     * @Given /^I should not see the show feedback link for assessor 1$/
     */
    public function i_should_not_see_the_show_feedback_link_for_assesor() {
        $feedback = $this->get_initial_assessor_feedback_for_student();
        /**
         * @var $page mod_coursework_behat_multiple_grading_interface
         */
        $page = $this->get_page('multiple grading interface');
        $page->should_not_have_show_feedback_icon($feedback);
    }

    /**
     * @Then /^I should( not)? see the grade from the teacher in the assessor table$/
     * @param bool $negate
     * @throws coding_exception
     */
    public function i_should_not_see_the_grade_from_the_teacher_in_the_assessor_table($negate = false) {
        /**
         * @var $page mod_coursework_behat_multiple_grading_interface
         */
        $page = $this->get_page('multiple grading interface');
        $feedback = $this->get_initial_assessor_feedback_for_student();

        if ($negate) {
            $page->should_not_have_grade_in_assessor_table($feedback);
        } else {
            $page->should_have_grade_in_assessor_table($feedback);
        }
    }

    /**
     * IMPORTANT: CI server borks if this is not done *before* the manager
     * logs in!
     *
     * @Given /^managers do not have the manage capability$/
     */
    public function managers_do_not_have_the_manage_capability() {
        global $DB;

        $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
        $params = ['roleid' => $managerrole->id,
                        'capability' => 'mod/coursework:manage'];
        $permissionsetting = CAP_PROHIBIT;
        $DB->set_field('role_capabilities', 'permission', $permissionsetting, $params);
    }

    /**
     * @Given /^I am allowed to view all students$/
     */
    public function i_am_allowed_to_view_all_students() {
        global $DB;

        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);
        $params = ['roleid' => $teacherrole->id,
                          'capability' => 'mod/coursework:viewallstudents'];
        $permissionsetting = CAP_ALLOW;
        $DB->set_field('role_capabilities', 'permission', $permissionsetting, $params);
    }

    /**
     * IMPORTANT: CI server borks if this is not done *before* the manager
     * logs in!
     *
     * @Given /^teachers have the add agreed grade capability$/
     */
    public function teachers_have_the_add_agreed_grade_capability() {
        global $DB;

        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);
        $params = ['roleid' => $teacherrole->id,
                        'capability' => 'mod/coursework:addagreedgrade',
                        'contextid' => 1,
                        'permission' => CAP_ALLOW];
        $DB->insert_record('role_capabilities', $params);
    }

    /**
     * @Then /^I should see two feedback files on the page$/
     */
    public function i_should_see_two_feedback_files_on_the_page() {
        /**
         * @var mod_coursework_behat_student_page $page
         */
        $page = $this->get_page('student page');

        if ($this->running_javascript()) {
            $this->wait_for_seconds(10);
        }

        $page->should_have_number_of_feedback_files(2);
    }

    /**
     * @Given /^the coursework start date is disabled$/
     */
    public function the_coursework_start_date_is_disabled() {
        $this->coursework->update_attribute('startdate', 0);
    }

    /**
     * @Given /^the coursework start date is now$/
     */
    public function the_coursework_start_date_is_now() {
        $this->coursework->update_attribute('startdate', time());
    }

    /**
     * @Given /^the coursework start date is in the future$/
     */
    public function the_coursework_start_date_is_in_the_future() {
        $this->coursework->update_attribute('startdate', strtotime('+1 week'));
    }

    /**
     * @Given /^the coursework start date is in the past$/
     */
    public function the_coursework_start_date_is_in_the_past() {
        $this->coursework->update_attribute('startdate', strtotime('-1 week'));
    }

    /**
     * @Then /^I should( not)? see the edit feedback button for the teacher's feedback$/
     */
    public function i_should_not_see_the_edit_feedback_button_for_the_teacher_s_feedback($negate = false) {
        /**
         * @var $page mod_coursework_behat_multiple_grading_interface
         */
        $page = $this->get_page('multiple grading interface');
        $feedback = $this->get_initial_assessor_feedback_for_student();

        if ($negate) {
            $page->should_not_have_edit_link_for_feedback($feedback);
        } else {
            $page->should_have_edit_link_for_feedback($feedback);
        }
    }

    /**
     * @Then /^I should( not)? see the add final feedback button$/
     * @param bool $negate
     * @throws coding_exception
     */
    public function i_should_not_see_the_add_final_feedback_button($negate = false) {
        /**
         * @var $page mod_coursework_behat_multiple_grading_interface
         */
        $page = $this->get_page('multiple grading interface');

        if ($negate) {
            $page->should_not_have_add_button_for_final_feedback($this->student->id());
        } else {
            $page->should_have_add_button_for_final_feedback($this->student->id());
        }
    }

    /**
     * @Then /^I should not see the edit final feedback button on the multiple marker page$/
     */
    public function i_should_not_see_the_edit_final_feedback_button_on_the_multiple_marker_page() {
        /**
         * @var $page mod_coursework_behat_multiple_grading_interface
         */

        $allocatable = new stdClass();
        $allocatable->courseworkid = $this->coursework->id;
        $allocatable->allocatableid = $this->student->id();
        $allocatable->allocatabletype = $this->student->type();

        $page = $this->get_page('multiple grading interface');
        $page->should_not_have_edit_link_for_final_feedback($allocatable);
    }

    /**
     * @Given /^the coursework is set to single marker$/
     */
    public function the_coursework_is_set_to_single_marker() {
        $this->get_coursework()->update_attribute('numberofmarkers', 1);
    }

    /**
     * @Given /^the coursework is set to double marker$/
     */
    public function the_coursework_is_set_to_doublele_marker() {
        $this->get_coursework()->update_attribute('numberofmarkers', 2);
    }

    /**
     * @Given /^the coursework individual feedback release date has passed$/
     */
    public function the_coursework_individual_feedback_release_date_has_passed() {
        $this->get_coursework()->update_attribute('individualfeedback', strtotime('1 week ago'));
    }

    /**
     * @Given /^the coursework individual feedback release date has not passed$/
     */
    public function the_coursework_individual_feedback_release_date_has_not_passed() {
        $this->get_coursework()->update_attribute('individualfeedback', strtotime('+1 week'));
    }

    /**
     * @Then /^I should see the name of the teacher in the assessor feedback cell$/
     */
    public function i_should_see_the_name_of_the_teacher_in_the_assessor_feedback_cell() {

        /**
         * @var mod_coursework_behat_single_grading_interface $page
         */
        $page = $this->get_page('single grading interface');
        $page->should_have_assessor_name_in_assessor_feedback_cell($this->teacher);
    }

    /**
     * @Given /^the coursework has assessor allocations enabled$/
     */
    public function the_coursework_has_assessor_allocations_enabled() {
        $this->coursework->update_attribute('allocationenabled', 1);
    }

    /**
     * @Given /^I agree to the confirm message$/
     */
    public function i_agree_to_the_confirm_message() {
        $this->get_page('coursework page')->confirm();
    }

    /**
     * @Given /^the coursework allocation option is disabled$/
     */
    public function the_coursework_allocation_option_is_disabled() {
        $coursework = $this->get_coursework();

        $coursework->allocationenabled = 0;
        $coursework->save();
    }

    /**
     * @Given /^the manager has a capability to allocate students in samplings$/
     */
    public function the_manager_has_a_capability_to_allocate_students_in_samplings() {
        global $DB;

        $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
        $params = ['roleid' => $managerrole->id,
            'capability' => 'mod/coursework:sampleselection'];
        $DB->set_field('role_capabilities', 'permission', CAP_ALLOW, $params);
    }

    /**
     *
     * @Given /^I (select|deselect) (a|another) student as a part of the sample for the second stage$/
     * @param string $selectordeselect
     * @param string $other
     */
    public function i_select_the_student_as_a_part_of_the_sample(string $selectordeselect, string $other) {
        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $student = $other == 'another' ? 'otherstudent' : 'student';
        $page = $this->get_page('allocations page');
        if ($selectordeselect == 'deselect') {
            $page->deselect_for_sample($this->$student, 'assessor_2');
        } else {
            $page->select_for_sample($this->$student, 'assessor_2');
        }
    }

    /**
     * @Given /^the teacher has a capability to mark submissions$/
     */
    public function the_teacher_has_a_capability_to_mark_submissions() {
        global $DB;

        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);

        role_change_permission(
            $teacherrole->id,
            $this->get_coursework()->get_context(),
            'mod/coursework:addinitialgrade',
            CAP_ALLOW
        );
    }

    /**
     * @Given /^the teacher has a capability to edit their own initial feedbacks$/
     */
    public function the_teacher_has_a_capability_to_edit_own_feedbacks() {
        global $DB;

        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);

        role_change_permission(
            $teacherrole->id,
            $this->get_coursework()->get_context(),
            'mod/coursework:editinitialgrade',
            CAP_ALLOW
        );
    }

    /**
     * @Given /^the teacher has a capability to edit their own agreed feedbacks$/
     */
    public function the_teacher_has_a_capability_to_edit_own_agreed_feedbacks() {
        global $DB;

        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);
        role_change_permission(
            $teacherrole->id,
            $this->get_coursework()->get_context(),
            'mod/coursework:editagreedgrade',
            CAP_ALLOW
        );
    }

    /**
     * @Given /^the coursework has sampling enabled$/
     */
    public function the_coursework_has_sampling_enabled() {
        $this->get_coursework()->update_attribute('samplingenabled', '1');
    }

    /**
     * @Given /^there is feedback for the submission from the other teacher$/
     */
    public function there_is_feedback_for_the_submission_from_the_other_teacher() {
        $this->feedback = feedback::create([
            'submissionid' => $this->submission->id,
            'assessorid' => $this->otherteacher->id,
            'grade' => '78',
            'feedbackcomment' => 'Blah',
            'stageidentifier' => 'assessor_1',
        ]);
    }

    /**
     * @Then /^I should (not )?be able to add the second grade for this student$/
     */
    public function i_should_not_be_able_to_add_the_second_grade_for_this_student($negate = false) {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');

        if ($negate) {
            $page->should_not_have_new_feedback_button($this->submission);
        } else {
            $page->should_have_new_feedback_button($this->submission);
        }
    }

    /**
     * @Then /^I should see the grade given by the initial teacher in the provisional grade column$/
     */
    public function i_should_see_the_grade_given_by_the_initial_teacher_in_the_provisional_grade_column() {

        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $provisionalgradefield = $page->get_provisional_grade_field($this->submission);
        $gradefield = $page->get_grade_field($this->submission);

        if ($provisionalgradefield != $gradefield) {
            throw new ExpectationException(
                "Provisional grade '$provisionalgradefield' does not match '$gradefield'",
                $this->getsession()
            );
        };
    }

    /**
     * @Given /^there is an extension for the student that allows them to submit$/
     */
    public function there_is_an_extension_for_the_student_that_allows_them_to_submit() {
        \mod_coursework\models\deadline_extension::create([
           'allocatableid' => $this->student->id(),
           'allocatabletype' => 'user',
           'courseworkid' => $this->coursework->id,
           'extended_deadline' => strtotime('+2 weeks 3:30pm', $this->coursework->deadline),
        ]);
    }

    /**
     * @Given /^there is an extension for the student which has expired$/
     */
    public function there_is_an_extension_for_the_student_which_has_expired() {
        $this->extensiondeadline = strtotime('3:30pm', strtotime('-2 weeks ', $this->coursework->deadline));
        \mod_coursework\models\deadline_extension::create([
                                                              'allocatableid' => $this->student->id(),
                                                              'allocatabletype' => 'user',
                                                              'courseworkid' => $this->coursework->id,
                                                              'extended_deadline' => $this->extensiondeadline,
                                                          ]);
    }

    /**
     * I enter an extension in the form
     * @When /^I enter an extension "(?P<time_string>(?:[^"]|\\")*)" in the form(?: with reason code "(?P<reasoncode_string>(?:[^"]|\\")*)")?$/
     */
    public function i_enter_an_extension_in_the_form(string $timeextension, string $reasoncode = '') {
        $newtime = strtotime('3:30pm', strtotime($timeextension));
        // Put into ISO-8601 format.
        $newtimestring = date('Y-m-d\TH:i', $newtime);
        $script = "const e = document.querySelector('input#extension-extend-deadline');"
            . "e.value = '$newtimestring'; e.dispatchEvent(new Event('change'));";
        behat_base::execute_script_in_session($this->getsession(), $script);
        // The change event is to enable save button.

        if ($reasoncode) {
            $reason = '0'; // 0 is "first reason" in the select menu
            $script = "document.querySelector('select#extension-reason-select').value = '$reason'; ";
            behat_base::execute_script_in_session($this->getsession(), $script);
        }

        $extrainfo = 'Some extra information';
        $script = "document.querySelector('textarea#id_extra_information').value = '$extrainfo'";
        behat_base::execute_script_in_session($this->getsession(), $script);
    }

    /**
     * I should see the extension in the form
     * @When /^I should see the extension "(?P<time_string>(?:[^"]|\\")*)" in the form(?: with reason code "(?P<reason_string>(?:[^"]|\\")*)")?$/
     */
    public function i_should_see_the_extension_in_the_form(string $timeextension, string $reasoncode = '') {
        $newtime = strtotime('3:30pm', strtotime($timeextension));
        // Put into ISO-8601 format.
        $newtimestring = date('Y-m-d\TH:i', $newtime);
        $script = "document.querySelector('input#extension-extend-deadline').value";
        $result = behat_base::evaluate_script_in_session($this->getsession(), $script);
        if ($result != $newtimestring) {
            throw new ExpectationException("Expected time '$newtimestring' got '$result'", $this->getsession());
        }

        if ($reasoncode) {
            // Reason code 0 is "first reason" in the select menu.
            $script = "document.querySelector('select#extension-reason-select').value === '$reasoncode';";
            if (!$resulttwo = behat_base::evaluate_script_in_session($this->getsession(), $script)) {
                throw new ExpectationException("Expected reason code '$reasoncode' got '$resulttwo'", $this->getsession());
            }
        }

        $extrainfo = 'Some extra information';
        $script = "document.querySelector('textarea#id_extra_information').value === '$extrainfo'";
        if (!$resultthree = behat_base::evaluate_script_in_session($this->getsession(), $script)) {
            throw new ExpectationException("Expected time '$newtimestring' got '$resultthree'", $this->getsession());
        }
    }

    /**
     * @Given /^I should see the extended deadline "(?P<time_string>(?:[^"]|\\")*)" in the student row$/
     */
    public function i_should_see_the_extended_deadline_in_the_student_row(string $timestring) {
        $newtime = strtotime('3:30pm', strtotime($timestring));
        // Put into format shown in UI.
        $expectedtimestring = userdate($newtime, '%a, %d %b %Y, %H:%M');
        $node = $this->find('css', '.extension-submission');
        $text = $node->getText();
        if (!str_contains($text, $expectedtimestring)) {
            throw new ExpectationException(
                "Expected to see extension '$expectedtimestring' got '$text'",
                $this->getsession()
            );
        }
    }

    /**
     * @When /^I edit the extension for the student$/
     */
    public function i_add_edit_the_extension_for_the_student() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $multigrader_page
         */
        $multigraderpage = $this->get_page('multiple grading interface');
        $multigraderpage->click_edit_extension_button_for($this->student);

        /**
         * @var mod_coursework_behat_edit_extension_page $edit_extension_page
         */
        $editextensionpage = $this->get_page('edit extension page');
        $this->extensiondeadline = strtotime('3:30pm', strtotime('+4 weeks'));
        $editextensionpage->edit_active_extension($this->extensiondeadline);
    }

    /**
     * @Given /^there are some extension reasons configured at site level$/
     */
    public function there_are_some_extension_reasons_configured_at_site_level() {
        set_config('coursework_extension_reasons_list', "first reason\nsecond reason");
    }

    /**
     * @Given /^I should see the deadline reason in the deadline extension form$/
     */
    public function i_should_see_the_deadline_reason_in_the_student_row() {
        /**
         * @var mod_coursework_behat_edit_extension_page $edit_extension_page
         */
        $editextensionpage = $this->get_page('edit extension page');
        $reason = $editextensionpage->get_extension_reason_for_allocatable();
        if ($reason != 0) {
            throw new ExpectationException("Unexpected extension reason '$reason'", $this->getsession());
        }
    }

    /**
     * @Given /^I should see the extra information in the deadline extension form$/
     */
    public function i_should_see_the_extra_information_in_the_student_row() {
        /**
         * @var mod_coursework_behat_edit_extension_page $edit_extension_page
         */
        $editextensionpage = $this->get_page('edit extension page');
        if (!$editextensionpage->get_extra_information_for_allocatable('Extra info here')) {
            throw new ExpectationException("Extra info not found", $this->getsession());
        }
    }

    /**
     * @When /^I click on the edit extension icon for the student$/
     */
    public function i_click_on_the_edit_extension_icon_for_the_student() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $multigrader_page
         */
        $multigraderpage = $this->get_page('multiple grading interface');
        $multigraderpage->click_edit_extension_button_for($this->student);
    }

    /**
     * @Given /^I submit the extension deadline form$/
     */
    public function i_submit_the_extension_deadline_form() {
        /**
         * @var mod_coursework_behat_new_extension_page $edit_extension_page
         */
        $editextensionpage = $this->get_page('new extension page');
        $editextensionpage->submit_form();
    }

    /**
     * @Given /^I should see the new extended deadline in the student row$/
     */
    public function i_should_see_the_new_extended_deadline_in_the_student_row() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $multigrader_page
         */
        $multigraderpage = $this->get_page('multiple grading interface');
        $multigraderpage->should_show_extension_for_allocatable(
            $this->student,
            $this->extensiondeadline
        );
    }

    /**
     * @Then /^I should see the new deadline reason in the dropdown$/
     */
    public function i_should_see_the_new_deadline_reason_in_the_dropdown() {
        /**
         * @var mod_coursework_behat_edit_extension_page $edit_extension_page
         */
        $editextensionpage = $this->get_page('edit extension page');
        $reason = $editextensionpage->get_extension_reason_for_allocatable();
        if ($reason != 1) {
            throw new ExpectationException("Unexpected extension reason '$reason'", $this->getsession());
        }
    }

    /**
     * @Given /^I should see the new extra deadline information in the deadline extension form$/
     */
    public function i_should_see_the_new_extra_deadline_information_in_the_deadline_extension_form() {
        /**
         * @var mod_coursework_behat_edit_extension_page $edit_extension_page
         */
        $editextensionpage = $this->get_page('edit extension page');
        if (!$editextensionpage->get_extra_information_for_allocatable('New info here')) {
            throw new ExpectationException("New info not found", $this->getsession());
        }
    }

    /**
     * @Given /^I click on the edit submission button for the student$/
     */
    public function i_click_on_the_edit_submission_button_for_the_student() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $multigrader_page
         */
        $multigraderpage = $this->get_page('multiple grading interface');
        $multigraderpage->click_edit_submission_button_for($this->student);
    }

    /**
     * @Given /^the coursework individual extension option is enabled$/
     */
    public function the_coursework_individual_extension_option_is_enabled() {
        $coursework = $this->get_coursework();

        $coursework->extensionsenabled = 1;
        $coursework->save();
    }

    /**
     * @Then /^I should see that the student has two allcations$/
     */
    public function i_should_see_that_the_student_has_two_allcations() {
        /**
         * @var $page mod_coursework_behat_allocations_page
         */
        $page = $this->get_page('allocations page');

        // Teacher - assessor_1.
        $allocatedassessor = $page->user_allocated_assessor($this->student, 'assessor_1');
        if ($allocatedassessor != $this->teacher->name()) {
            $message = 'Expected the allocated teacher name to be ' . $this->teacher->name()
                . ' but got ' . $allocatedassessor . ' instead.';
            throw new ExpectationException($message, $this->getsession());
        }

        // Other teacher - assessor_2.
        $allocatedassessor = $page->user_allocated_assessor($this->student, 'assessor_2');
        if ($allocatedassessor != $this->otherteacher->name()) {
            $message = 'Expected the allocated teacher name to be ' . $this->otherteacher->name()
                . ' but got ' . $allocatedassessor . ' instead.';
            throw new ExpectationException($message, $this->getsession());
        }
    }

    /**
     * @Then /^I should see that both students are allocated to the teacher$/
     */
    public function i_should_see_that_both_students_are_allocated_to_the_teacher() {
        /**
         * @var $page mod_coursework_behat_allocations_page
         */
        $page = $this->get_page('allocations page');

        // Student.
        $allocatedassessor = $page->user_allocated_assessor($this->student, 'assessor_1');
        if ($allocatedassessor != $this->teacher->name()) {
            $message = 'Expected the allocated teacher name to be ' . $this->teacher->name()
                . ' but got ' . $allocatedassessor . ' instead.';
            throw new ExpectationException($message, $this->getsession());
        }

        // Other student.
        $allocatedassessor = $page->user_allocated_assessor($this->otherstudent, 'assessor_1');
        if ($allocatedassessor != $this->teacher->name()) {
            $message = 'Expected the allocated teacher name to be ' . $this->teacher->name()
                . ' but got ' . $allocatedassessor . ' instead.';
            throw new ExpectationException($message, $this->getsession());
        }
    }

    /**
     * @Given /^editing teachers are prevented from adding general feedback$/
     */
    public function editing_teachers_are_prevented_from_adding_general_feedback() {
        global $DB;

        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $params = ['roleid' => $teacherrole->id,
                        'capability' => 'mod/coursework:addgeneralfeedback',
                        'contextid' => 1,
                        ];
        $cap = $DB->get_record('role_capabilities', $params);
        $cap->permission = CAP_PREVENT;
        $DB->update_record('role_capabilities', $cap);
    }

    /**
     * To protect the blind marking aspect, the submission row does not contain any reference to the student id in
     * the HTML. We use the filename hash instead.
     */
    protected function get_submission_grading_table_row_id() {
        return '#submission_' . $this->student_hash();
    }

    /**
     * @return string
     */
    protected function new_final_feedback_link_id() {
        return '#new_final_feedback_' . $this->student_hash();
    }

    /**
     * @return string
     */
    protected function edit_final_feedback_link_id() {
        return '#edit_final_feedback_' . $this->student_hash();
    }

    /**
     * @return string
     */
    protected function new_moderator_feedback_link_id() {
        return '#new_moderator_feedback_' . $this->student_hash();
    }

    /**
     * @return string
     */
    protected function edit_moderator_feedback_link_id() {
        return '#edit_moderator_feedback_' . $this->student_hash();
    }

    /**
     * @return mixed
     */
    protected function student_hash() {
        return $this->coursework->get_allocatable_identifier_hash($this->student);
    }

    /**
     * @return mod_coursework_generator
     */
    protected function get_coursework_generator() {
        return testing_util::get_data_generator()->get_plugin_generator('mod_coursework');
    }

    /**
     * Hacky way to allow page objects to use a protected method.
     *
     * @param string $element CSS
     */
    public function wait_till_element_exists($element) {
        $this->ensure_element_exists($element, 'css_element');
    }

    /**
     * @return bool
     */
    // phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod
    public function running_javascript() {
        return parent::running_javascript();
    }

    // Course steps

    /**
     * @Given /^there is a course$/
     */
    public function there_is_a_course() {
        $course = new stdClass();
        $course->fullname = 'Course 1';
        $course->shortname = 'C1';
        $generator = testing_util::get_data_generator();
        $this->course = $generator->create_course($course);
    }

    /**
     * This is really just a convenience method so that we can chain together the call to create the
     * course and this one, within larger steps.
     *
     * @Given /^the course has been kept for later$/
     */
    public function the_course_has_been_kept_for_later() {
        global $DB;

        $this->course = $DB->get_record('course', ['shortname' => 'C1']);
    }

    /**
     * @Given /^the course has completion enabled$/
     */
    public function the_course_has_completion_enabled() {
        global $DB;

        set_config('enablecompletion', 1); // Global setting.
        $DB->set_field('course', 'enablecompletion', 1, ['id' => $this->course->id]);
    }

    /**
     * @Given /^there is a coursework$/
     */
    public function there_is_a_coursework() {

        /**
         * @var $generator mod_coursework_generator
         */
        $generator = testing_util::get_data_generator()->get_plugin_generator('mod_coursework');

        $coursework = new stdClass();
        $coursework->course = $this->course;
        $this->coursework = coursework::find($generator->create_instance($coursework)->id);
    }

    /**
     * @Then /^I should see the title of the coursework on the page$/
     */
    public function i_should_see_the_title_of_the_coursework_on_the_page() {
        $page = $this->get_page('coursework page');

        if (!$page->get_coursework_name($this->coursework->name)) {
            throw new ExpectationException("Coursework title '{$this->coursework->name}' not seen", $this->getsession());
        }
    }

    /**
     * Custom step because the standard Moodle module header containing the
     * description is hidden on page but "I should see" will find that instead.
     *
     * @Then /^I should see the description of the coursework on the page$/
     */
    public function i_should_see_the_description_of_the_coursework_on_the_page() {
        $page = $this->getsession()->getpage();

        // "Test coursework 1" set by data generator.
        $match = $page->find('xpath', "//h3[text() = 'Description']/following-sibling::*[1][text() = 'Test coursework 1']");

        if (!$match) {
            throw new ExpectationException(
                "Should have seen expected description 'Test coursework 1', but it was not there",
                $this->getsession()
            );
        }
    }

    /**
     * @Then /^the coursework "([\w]+)" setting should be "([\w]*)" in the database$/
     * @param $settingname
     * @param $settingvalue
     * @throws ExpectationException
     */
    public function the_coursework_setting_should_be($settingname, $settingvalue) {
        if ($settingvalue == 'NULL') {
            $settingvalue = null;
        }

        if ($this->get_coursework()->$settingname !== $settingvalue) {
            throw new ExpectationException(
                "The coursework {$settingname} setting should have been {$settingvalue}"
                . "but was {$this->getcoursework()->$settingname}",
                $this->getsession()
            );
        }
    }

    /**
     * @Then /^the coursework "([\w]+)" setting is "([\w]*)" in the database$/
     * @param $settingname
     * @param $settingvalue
     */
    public function the_coursework_setting_is_in_the_database($settingname, $settingvalue) {
        $coursework = $this->get_coursework();
        if ($settingvalue == 'NULL') {
            $settingvalue = null;
        }
        $coursework->$settingname = $settingvalue;
        $coursework->save();
    }

    /**
     * @Then /^there should be ([\d]+) coursework$/
     * @param $expectedcount
     * @throws ExpectationException
     */
    public function there_should_only_be_one_coursework($expectedcount) {
        global $DB;

        $numberindatabase = $DB->count_records('coursework');

        if ($numberindatabase > (int)$expectedcount) {
            throw new ExpectationException(
                "Too many courseworks! There should be {$expectedcount}, but there were {$DB->countrecords('coursework')}",
                $this->getsession()
            );
        }
    }

    /**
     * @Given /^the coursework is set to use the custom form$/
     */
    public function the_coursework_is_set_to_use_the_custom_form() {
        global $DB;

        $coursework = $this->get_coursework();
        $coursework->formid = $this->form->id;
        $coursework->save();

        if (!$DB->record_exists('coursework', ['formid' => $this->form->id])) {
            throw new ExpectationException('no field change', $this->getsession());
        }
    }

    /**
     * @Given /^the coursework deadline has passed$/
     */
    public function the_coursework_deadline_has_passed() {
        $deadline = strtotime('-1 week');
        $this->coursework->update_attribute('deadline', $deadline);
    }

    /**
     * @Given /^the general feedback deadline has passed$/
     */
    public function the_general_feedback_deadline_has_passed() {
        $this->get_coursework()->generalfeedback = strtotime('-1 day');
        $this->get_coursework()->save();
    }

    /**
     * @Given /^I press the release marks button$/
     */
    public function i_press_the_release_marks_button() {
        $this->find('css', '#release-marks-button')->press();
        $this->wait_for_pending_js();
        $this->find_button(get_string('confirm'))->press();
        $this->getsession()->visit($this->locate_path('coursework')); // Quicker than waiting for a redirect.
    }

    /**
     * @Given /^the managers are( not)? allowed to grade$/
     * @param bool $negate
     */
    public function the_managers_are_not_allowed_to_grade($negate = false) {
        global $DB;

        $managerrole = $DB->get_record('role', ['shortname' => 'manager']);
        $params = ['roleid' => $managerrole->id,
                        'capability' => 'mod/coursework:addinitialgrade'];
        if ($negate) {
            $permissionsetting = CAP_PROHIBIT;
        } else {
            $permissionsetting = CAP_ALLOW;
        }
        $DB->set_field('role_capabilities', 'permission', $permissionsetting, $params);
    }

    /**
     * @Given /^the grades have been published$/
     */
    public function the_grades_have_been_published() {
        global $DB;
        // Using publish_grades was causing a user not found DB error so trying to isolate that here.
        $submissions = $this->coursework->get_submissions_to_publish();
        foreach ($submissions as $submission) {
            // First check user exists as this will create DB error in behat if not.
            if ($DB->record_exists('user', ['id' => $submission->userid, 'deleted' => 0])) {
                try {
                    $submission->publish();
                } catch (\Exception $e) {
                    throw new ExpectationException(
                        "Could not publish submission ID $submission->id for User ID $submission->userid."
                        . " Reason: " . $e->getMessage() . " | " . $e->getTraceAsString(),
                        $this->getsession()
                    );
                }
            } else {
                throw new ExpectationException(
                    "User ID $submission->userid not found for submission $submission->id - could not publish"
                    . " - JSON submission: " . json_encode($submission),
                    $this->getsession()
                );
            }
        }
    }

    /**
     * @Given /^the sitewide "([^"]*)" setting is "([^"]*)"$/
     * @param $settingname
     * @param $settingvalue
     */
    public function the_sitewide_setting_is($settingname, $settingvalue) {
        set_config($settingname, $settingvalue);
    }

    // Allocation steps

    /**
     * @Given /^I manually allocate the student to the other teacher$/
     */
    public function i_manually_allocate_the_student_to_the_other_teacher() {

        // Identify the allocation dropdown.
        $dropdownname = 'user_' . $this->student->id . '_assessor_1';
        $node = $this->find_field($dropdownname);

        // We delegate to behat_form_field class, it will
        // guess the type properly as it is a select tag.
        $field = behat_field_manager::get_form_field($node, $this->getsession());
        $field->set_value($this->otherteacher->id);

        $this->find_button('save_manual_allocations_1')->click();
    }

    /**
     * @Given /^I manually allocate the student to the other teacher for the second assessment$/
     */
    public function i_manually_allocate_the_student_to_the_other_teacher_for_the_second_assessment() {

        // Identify the allocation dropdown.
        $dropdownname = 'user_' . $this->student->id . '_assessor_2';
        $node = $this->find_field($dropdownname);

        // We delegate to behat_form_field class, it will
        // guess the type properly as it is a select tag.
        $field = behat_field_manager::get_form_field($node, $this->getsession());
        $field->set_value($this->otherteacher->id);
    }

    /**
     * @Given /^I manually allocate the student to the teacher$/
     */
    public function i_manually_allocate_the_student_to_the_teacher() {

        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        $page->manually_allocate($this->student, $this->teacher, 'assessor_1');
    }

    /**
     * @Given /^I manually allocate the other student to the teacher$/
     */
    public function i_manually_allocate_the_other_student_to_the_teacher() {

        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        $page->manually_allocate($this->otherstudent, $this->teacher, 'assessor_1');
        $page->save_everything();
    }

    /**
     * @Given /^I manually allocate another student to another teacher$/
     */
    public function i_manually_allocate_another_student_to_another_teacher() {

        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        $page->manually_allocate($this->otherstudent, $this->otherteacher, 'assessor_1');
        $page->save_everything();
    }

    /**
     * @Given /^I auto-allocate all students to assessors$/
     */
    public function i_auto_allocate_all_students() {
        $this->find_button('Save everything')->press();
    }

    /**
     * @Given /^I auto-allocate all non-manual students to assessors$/
     */
    public function i_auto_allocate_all_non_manual_students() {
        $this->find_button('auto-allocate-all-non-manual-assessors')->press();
    }

    /**
     * @Given /^I auto-allocate all non-allocated students to assessors$/
     */
    public function i_auto_allocate_all_non_allocated_students() {
        $this->find_button('auto-allocate-all-non-allocated-assessors')->press();
    }

    /**
     * @Given /^I set the allocation strategy to (\d+) percent for the other teacher$/
     * @param $percent
     * @throws Behat\Mink\Exception\ElementNotFoundException
     */
    public function the_allocation_strategy_is_percent_for_the_other_teacher($percent) {

        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        $this->find('css', '#assessorallocationstrategy')->selectOption('percentages');
        $this->getsession()->getpage()->fillField("assessorstrategypercentages[{$this->otherteacher->id}]", $percent);
    }

    /**
     * @Given /^I set the allocation strategy to (\d+) percent for the teacher$/
     * @param $percent
     * @throws Behat\Mink\Exception\ElementNotFoundException
     */
    public function the_allocation_strategy_is_percent_for_the_teacher($percent) {

        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        if ($this->running_javascript()) {
            $page->show_assessor_allocation_settings();
        }
        $this->find('css', '#assessorallocationstrategy')->selectOption('percentages');
        $this->getsession()->getpage()->fillField("assessorstrategypercentages[{$this->teacher->id}]", $percent);
        $this->find('css', '#save_manual_allocations_1')->press();
    }

    /**
     * @Given /^the student is( manually)? allocated to the teacher$/
     * @param bool $manual
     * @throws coding_exception
     */
    public function the_student_is_allocated_to_the_teacher($manual = false) {
        /**
         * @var $generator mod_coursework_generator
         */
        $generator = testing_util::get_data_generator()->get_plugin_generator('mod_coursework');

        $allocation = new stdClass();
        $allocation->allocatableid = $this->student->id;
        $allocation->allocatabletype = 'user';
        $allocation->assessorid = $this->teacher->id;
        $allocation->stageidentifier = 'assessor_1';
        $allocation->courseworkid = $this->get_coursework()->id;
        if ($manual) {
            $allocation->ismanual = 1;
        }
        $generator->create_allocation($allocation);
    }

    /**
     * @Given /^the manager is manually allocated as the moderator for the student$/
     */
    public function the_manager_is_allocated_as_the_moderator_for_the_student() {
        $allocation = new stdClass();
        $allocation->ismanual = 1;
        $allocation->courseworkid = $this->coursework->id;
        $allocation->assessorid = $this->manager->id;
        $allocation->allocatableid = $this->student->id();
        $allocation->allocatabletype = $this->student->type();
        $allocation->stageidentifier = $this->coursework->get_moderator_marking_stage()->identifier();

        $this->allocation = $this->get_coursework_generator()->create_allocation($allocation);
    }

    /**
     * @Given /^the manager is automatically allocated as the moderator for the student$/
     */
    public function the_manager_is_automatically_allocated_as_the_moderator_for_the_student() {
        $allocation = new stdClass();
        $allocation->ismanual = 0;
        $allocation->courseworkid = $this->coursework->id;
        $allocation->assessorid = $this->manager->id;
        $allocation->allocatableid = $this->student->id();
        $allocation->allocatabletype = $this->student->type();
        $allocation->stageidentifier = $this->coursework->get_moderator_marking_stage()->identifier();

        $this->allocation = $this->get_coursework_generator()->create_allocation($allocation);
    }

    /**
     * @Given /^there are no allocations in the db$/
     */
    public function there_are_no_allocations_in_the_db() {
        allocation::destroy_all($this->coursework->id);
    }

    /**
     * @Then /^the student should be allocated to an assessor$/
     */
    public function the_student_should_be_allocated_to_an_assessor() {
        global $DB;

        $params = [
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->student->id,
            'allocatabletype' => 'user',
        ];

        $result = $DB->get_record('coursework_allocation_pairs', $params);

        if (empty($result)) {
            throw new ExpectationException('Expected assessor allocation', $this->getsession());
        }
    }

    // Feedback steps

    /**
     * @Then /^I should( not)? see the final grade on the student page$/
     *
     * @param bool $negate
     */
    public function i_should_see_the_final_grade_on_the_student_page($negate = false) {

        $cssid = '#final_feedback_grade';

        if ($negate) {
            $this->ensure_element_does_not_exist($cssid, 'css_element');
        } else {
            $commentfield = $this->find('css', $cssid);
            $text = $commentfield->getText();
            if ($text != 56) {
                throw new ExpectationException("Expected final grade 56 got $text", $this->getsession());
            }
        }
    }

    /**
     * @Given /^I (should|should not) see the grade comment( "(?P<comment_string>(?:[^"]|\\")*)")? on the student page$/
     * @param string $shouldornot
     * @param string $comment
     */
    public function i_should_see_the_grade_comment_on_the_student_page(string $shouldornot, string $comment = 'New comment') {

        if ($shouldornot == 'should not') {
            $this->ensure_element_does_not_exist('#final_feedback_comment', 'css_element');
        } else {
            $commentfield = $this->find('css', '#final_feedback_comment');
            $text = $commentfield->getText();
            if ($text != $comment) {
                throw new ExpectationException("Got comment '$text' expected '$comment'", $this->getsession());
            }
        }
    }

    /**
     * @Given /^there is some general feedback$/
     */
    public function there_is_some_general_feedback() {
        $this->get_coursework()->feedbackcomment = '<p>Some general feedback comments</p>';
        $this->get_coursework()->save();
    }

    /**
     * @Then /^I should( not)? see the other teacher\'s grade as assessor (\d+)$/
     * @param bool $negate
     * @param int $assessornumber
     * @throws coding_exception
     */
    public function i_should_not_see_the_other_teacher_s_grade($negate = false, $assessornumber = 1) {

        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        if ($negate) {
            $page->assessor_grade_should_not_be_present($this->student, $assessornumber);
        } else {
            $page->assessor_grade_should_be_present($this->student, $assessornumber, '50');
        }
    }

    /**
     * @When /^I click the new final feedback button for the group$/
     */
    public function i_click_the_new_final_feedback_button_group() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_assessor_new_feedback_button('final_agreed', $this->group);
    }

    /**
     * @When /^I click the new multiple final feedback button for the student/
     */
    public function i_click_the_new_multiple_final_feedback_button_student() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_assessor_new_feedback_button('final_agreed', $this->student);
    }

    /**
     * @Given /^I should see the grade in the form on the page$/
     */
    public function i_should_see_the_grade_in_the_form_on_the_page() {
        $commentfield = $this->find('css', '#feedback_grade');
        $expectedvalue = 56;
        $actual = $commentfield->getValue();
        if ($actual != $expectedvalue) {
            throw new ExpectationException("Expected grade $expectedvalue got $actual", $this->getsession());
        }
    }

    /**
     * @Given /^I should see the other teacher's final grade in the form on the page$/
     */
    public function i_should_see_the_other_teachers_final_grade_in_the_form_on_the_page() {
        $commentfield = $this->find('css', '#feedback_grade');
        $expectedvalue = 45;
        $actual = $commentfield->getValue();
        if ($actual != $expectedvalue) {
            throw new ExpectationException("Expected grade $expectedvalue got $actual", $this->getsession());
        }
    }

    /**
     * @Given /^I should see the other teacher's grade in the form on the page$/
     */
    public function i_should_see_the_other_teachers_grade_in_the_form_on_the_page() {
        $commentfield = $this->find('css', '#feedback_grade');
        $expectedvalue = 58;
        $text = $commentfield->getValue();
        if ($text != $expectedvalue) {
            throw new ExpectationException("Expected final grade $expectedvalue got $text", $this->getsession());
        }
    }

    /**
     * @Given /^there is( draft)? final feedback$/
     */
    public function there_is_final_feedback($draft = false) {
        $generator = $this->get_coursework_generator();

        $feedback = new stdClass();
        $feedback->grade = 45;
        $feedback->feedbackcomment = 'blah';
        $feedback->submissionid = $this->submission->id;
        $feedback->assessorid = $this->manager->id;
        $feedback->stageidentifier = 'final_agreed_1';
        $feedback->finalised = $draft ? 0 : 1;

        $this->finalfeedback = $generator->create_feedback($feedback);
    }

    /**
     * @Given /^there is final feedback from the other teacher with grade 45$/
     */
    public function there_is_final_feedback_from_the_other_teacher() {
        $generator = $this->get_coursework_generator();

        $feedback = new stdClass();
        $feedback->grade = 45;
        $feedback->feedbackcomment = 'blah';
        $feedback->isfinalgrade = 1;
        $feedback->submissionid = $this->submission->id;
        $feedback->assessorid = $this->otherteacher->id;
        $feedback->stageidentifier = 'final_agreed_1';

        $this->finalfeedback = $generator->create_feedback($feedback);
    }

    /**
     * @When /^I click the new moderator feedback button$/
     */
    public function i_click_the_new_moderator_feedback_button() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_new_moderator_feedback_button($this->student);
    }

    /**
     * @When /^I click the edit moderator feedback button$/
     */
    public function i_click_the_edit_moderator_feedback_button() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_edit_moderator_feedback_button($this->student);
    }

    /**
     * @When /^I click the edit moderation agreement link$/
     */
    public function i_click_the_edit_moderation_agreement_link() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_edit_moderation_agreement_link($this->student);
    }

    /**
     * @Given /^I should see the moderator grade on the page$/
     */
    public function i_should_see_the_moderator_grade_on_the_page() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        if (!$page->has_moderator_grade_for($this->student, '56')) {
            throw new ExpectationException("Does not have moderator grade");
        }
    }

    /**
     * @When /^I click the edit final feedback button$/
     */
    public function i_click_the_edit_final_feedback_button() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_assessor_edit_feedback_button('final_agreed', $this->student);
    }

    /**
     * @When /^I click the edit feedback button$/
     */
    public function i_click_the_edit_single_feedback_button() {
        /**
         * @var mod_coursework_behat_single_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_assessor_edit_feedback_button(null, $this->student);
    }

    /**
     * @Given /^there are feedbacks from both me and another teacher$/
     */
    public function there_are_feedbacks_from_me_and_another_teacher() {
        /**
         * @var $generator mod_coursework_generator
         */
        $generator = testing_util::get_data_generator()->get_plugin_generator('mod_coursework');

        $feedback = new stdClass();
        $feedback->assessorid = $this->manager->id;
        $feedback->submissionid = $this->submission->id;
        $feedback->feedbackcomment = 'New comment here';
        $feedback->stageidentifier = 'assessor_1';
        $feedback->feedbackcomment = 'New comment here';
        $feedback->grade = 67;

        $generator->create_feedback($feedback);

        $feedback = new stdClass();
        $feedback->assessorid = $this->otherteacher->id;
        $feedback->submissionid = $this->submission->id;
        $feedback->stageidentifier = 'assessor_2';
        $feedback->feedbackcomment = 'New comment here';
        $feedback->grade = 63;

        $generator->create_feedback($feedback);
    }

    /**
     * @Given /^there are( draft)? feedbacks from both teachers$/
     */
    public function there_are_feedbacks_from_both_teachers($draft = false) {
        /**
         * @var $generator mod_coursework_generator
         */
        $generator = testing_util::get_data_generator()->get_plugin_generator('mod_coursework');

        $feedback = new stdClass();
        $feedback->assessorid = $this->teacher->id;
        $feedback->submissionid = $this->submission->id;
        $feedback->stageidentifier = 'assessor_1';
        $feedback->feedbackcomment = 'New comment here';
        $feedback->grade = 67;
        $feedback->finalised = $draft ? 0 : 1;

        $generator->create_feedback($feedback);

        $feedback = new stdClass();
        $feedback->assessorid = $this->otherteacher->id;
        $feedback->submissionid = $this->submission->id;
        $feedback->stageidentifier = 'assessor_2';
        $feedback->feedbackcomment = 'New comment here';
        $feedback->grade = 63;
        $feedback->finalised = $draft ? 0 : 1;

        $generator->create_feedback($feedback);
    }

    /**
     * @Then /^I should see the final grade(?: as )?([\.\d]+)?$/
     * @param int $grade
     * @throws ExpectationException
     * @throws coding_exception
     */
    public function i_should_see_the_final_single_grade_on_the_page($grade = 56) {
        $page = $this->get_page('multiple grading interface');
        $page->assessor_grade_should_be_present($this->student, '1', $grade);
    }

    /**
     * @Then /^I should see the final agreed grade(?: as )?([\.\d]+)?$/
     * @param int $grade
     * @throws ExpectationException
     * @throws coding_exception
     */
    public function i_should_see_the_final_agreed_grade_on_the_page($grade = 56) {
        $page = $this->get_page('multiple grading interface');
        $page->assessor_grade_should_be_present($this->student, 'final_agreed', $grade);
    }

    /**
     *
     * @When /^I should see the final agreed grade status "(?P<expectedstatus>(?:[^"]|\\")*)"$/
     * @param int $expectedstatus
     */
    public function i_should_see_the_final_agreed_grade_status_on_the_page($expectedstatus) {
        $page = $this->get_page('multiple grading interface');
        $page->grade_status_should_be_present($this->student, 'final_agreed', $expectedstatus);
    }

    /**
     * @Given /^I have an assessor feedback at grade 67$/
     */
    public function i_have_an_assessor_feedback() {
        /**
         * @var $generator mod_coursework_generator
         */
        $generator = testing_util::get_data_generator()->get_plugin_generator('mod_coursework');
        $feedback = new stdClass();
        $feedback->assessorid = $this->teacher->id;
        $feedback->submissionid = $this->submission->id;
        $feedback->stageidentifier = 'assessor_1';
        $feedback->feedbackcomment = 'New comment here';
        $feedback->grade = 67;

        $this->feedback = $generator->create_feedback($feedback);
    }

    /**
     * @Then /^the grade comment textarea field matches "(?P<comment_string>(?:[^"]|\\")*)"$/
     * @param string $expectedvalue
     */
    public function the_grade_comment_textarea_field_matches($expectedvalue) {
        $script = "document.querySelector('textarea#id_feedbackcomment').value;";
        $actual = strip_tags(behat_base::evaluate_script_in_session($this->getsession(), $script));
        if ($actual != $expectedvalue) {
            throw new ExpectationException("Expected comment '$expectedvalue' got '$actual'", $this->getsession());
        }
    }

    /**
     * @Then /^I should see the grade on the page$/
     */
    public function i_should_see_the_grade_on_the_page() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->assessor_grade_should_be_present($this->student, 1, 56);
    }

    /**
     * @Then /^I should not see the final grade on the multiple marker page$/
     */
    public function i_should_not_see_the_grade_on_the_page() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->assessor_grade_should_not_be_present($this->student, 'final_agreed');
    }

    /**
     * @Then /^I should see the rubric grade on the page$/
     */
    public function i_should_see_the_rubric_grade_on_the_page() {
        $celltext = $this->find('css', '#final_feedback_grade')->getText();
        if (strpos($celltext, '50') === false) {
            throw new ExpectationException(
                "Expected rubric grade 50 got '$celltext'",
                $this->getsession()
            );
        }
    }

    /**
     * Check rubric comment on student page.
     * @Then /^I should see the rubric comment "(?P<comment_string>(?:[^"]|\\")*)"$/
     */
    public function i_should_see_the_rubric_comment_on_the_page(string $comment) {
        $celltext = $this->find('css', '#rubric-rubric0 td.remark')->getText();
        if ($comment !== $celltext) {
            throw new ExpectationException("Expected commennt '$comment' got '$celltext'", $this->getsession());
        }
    }

    /**
     * @When /^I grade the submission(?: as )?(\d*\.?\d+)? using the simple form(?: with comment "(?P<comment_string>(?:[^"]|\\")*)")?$/
     *
     * @param int $grade
     * @throws Behat\Mink\Exception\ElementException
     * @throws Behat\Mink\Exception\ElementNotFoundException
     */
    public function i_grade_the_submission_using_the_simple_form($grade = 56, $comment = "New comment") {
        // Markers' form Grade field is <select id="feedback_grade">.
        // Assessors' form Grade field is <input type="text" id="id_grade">.
        $nodeelement = $this->getsession()->getpage()->findById('feedback_grade') ?: $this->getsession()->getpage()->findById('id_grade');

        if ($nodeelement) {
            $nodeelement->setValue($grade);
        }

        $this->getsession()->executeScript(
            "tinyMCE.get('id_feedbackcomment').setContent('$comment');"
        );

        $this->getsession()->getpage()->findButton('submitbutton')->press();

        $this->feedback = feedback::last();
    }

    /**
     * @Given /^I set the feedback comment to "(?P<comment_string>(?:[^"]|\\")*)"$/
     * @param string $comment
     * @throws coding_exception
     */
    public function i_set_the_feedback_comment_to(string $comment) {
        $script = "document.querySelector('textarea#id_feedbackcomment').value = '$comment'";
        behat_base::execute_script_in_session($this->getsession(), $script);
    }

    /**
     * @Then /^I should see the final grade for the group in the grading interface$/
     *
     */
    public function i_should_see_the_final_grade_for_the_group_in_the_grading_interface() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        if (!$page->group_has_a_final_multiple_grade($this->group)) {
            $message = "Should be a grade in the student row final grade cell, but there's not";
            throw new ExpectationException($message, $this->getsession());
        };
    }

    /**
     * @Then /^I should see the grade for the group submission$/
     */
    public function i_should_see_the_grade_for_the_group_submission() {
        /**
         * @var mod_coursework_behat_student_page $page
         */
        $page = $this->get_page('student page');
        $visiblegrade = $page->get_visible_grade();
        if ($visiblegrade != 45) {
            throw new ExpectationException(
                "Expected the final grade to be '45', but got '{$visiblegrade}'",
                $this->getsession()
            );
        }
    }

    /**
     * @Then /^I should see the feedback for the group submission$/
     */
    public function i_should_see_the_feedback_for_the_group_submission() {
        /**
         * @var mod_coursework_behat_student_page $page
         */
        $page = $this->get_page('student page');
        $visiblefeedback = $page->get_visible_feedback('blah');
        if ($visiblefeedback != 'blah') {
            throw new ExpectationException(
                "Expected the feedback to be 'blah', but got '{$visiblefeedback}'",
                $this->getsession()
            );
        }
    }

    /**
     * @Then /^I should see the grade in the gradebook$/
     */
    public function i_should_see_the_grade_in_the_gradebook() {
        /**
         * @var mod_coursework_behat_gradebook_page $page
         */
        $page = $this->get_page('gradebook page');
        $grade = $page->get_coursework_grade_for_student($this->coursework);
        if ($grade != 45) {
            throw new ExpectationException("Expected grade '45' found '$grade'", $this->getsession());
        }
    }

    /**
     * @Then /^I should see the rubric grade "(\d+)" in the gradebook$/
     */
    public function i_should_see_the_rubric_grade_in_the_gradebook(string $grade) {
        /**
         * @var mod_coursework_behat_gradebook_page $page
         */
        $page = $this->get_page('gradebook page');
        $actual = $page->get_coursework_grade_for_student($this->coursework);
        if ($actual != $grade) {
            throw new ExpectationException("Expected grade '$grade' found '$actual'", $this->getsession());
        }
    }

    /**
     * @Given /^there is a rubric defined for the coursework$/
     */
    public function there_is_a_rubric_defined_for_the_coursework() {
        global $DB;

        $gradingarea = new stdClass();
        $gradingarea->contextid = $this->coursework->get_context_id();
        $gradingarea->component = 'mod_coursework';
        $gradingarea->areaname = 'submissions';
        $gradingarea->activemethod = 'rubric';
        $gradingarea->id = $DB->insert_record('grading_areas', $gradingarea);

        // Make the rubric
        $gradingdefinition = new stdClass();
        $gradingdefinition->areaid = $gradingarea->id;
        $gradingdefinition->method = 'rubric';
        $gradingdefinition->name = 'Test rubric';
        $gradingdefinition->description = 'Rubric description';
        $gradingdefinition->descriptionformat = 1;
        $gradingdefinition->status = 20;
        $gradingdefinition->timecreated = time();
        $gradingdefinition->usercreated = 2;
        $gradingdefinition->timemodified = time();
        $gradingdefinition->usermodified = 2;
        $gradingdefinition->options =
            '{"sortlevelsasc":"1","alwaysshowdefinition":"1","showdescriptionteacher":"1","showdescriptionstudent":"1","showscoreteacher":"1","showscorestudent":"1","enableremarks":"1","showremarksstudent":"1"}';
        $gradingdefinition->id = $DB->insert_record('grading_definitions', $gradingdefinition);

        $rubriccriteria = new stdClass();
        $rubriccriteria->definitionid = $gradingdefinition->id;
        $rubriccriteria->sortorder = 1;
        $rubriccriteria->description = 'first criterion';
        $rubriccriteria->descriptionformat = 0;
        $rubriccriteria->id = $DB->insert_record('gradingform_rubric_criteria', $rubriccriteria);

        $rubriclevel = new stdClass();
        $rubriclevel->criterionid = $rubriccriteria->id;
        $rubriclevel->score = 0;
        $rubriclevel->definition = 'Bad';
        $rubriclevel->definitionformat = 0;
        $DB->insert_record('gradingform_rubric_levels', $rubriclevel);
        $rubriclevel->score = 1;
        $rubriclevel->definition = 'OK';
        $DB->insert_record('gradingform_rubric_levels', $rubriclevel);
        $rubriclevel->score = 2;
        $rubriclevel->definition = 'Good';
        $DB->insert_record('gradingform_rubric_levels', $rubriclevel);
    }

    /**
     * @Then /^I should not see a link to add feedback$/
     */
    public function i_should_not_see_a_link_to_add_feedback() {
        /**
         * @var mod_coursework_behat_single_grading_interface $grading_interface
         */
        if ($this->coursework->has_multiple_markers()) {
            $gradinginterface = $this->get_page('multiple grading interface');
        } else {
            $gradinginterface = $this->get_page('single grading interface');
        }

        $count = count($this->getsession()->getpage()->findAll('css', '#add-feedback-' . $this->student->id()));

        if ($count !== 0) {
            throw new ExpectationException('Feedback link is present', $this->getsession());
        };
    }

    // General web steps

    /**
     * @Given /^I visit the ([\w ]+) page$/
     * @param $pathname
     */
    public function visit_page($pathname) {
        $this->getsession()->visit($this->locate_path($pathname, false));
    }

    /**
     * @Then /^I should be on the ([\w ]+) page(, ignoring parameters)?$/
     * @param $pagename
     * @param bool $ignoreparams
     */
    public function i_should_be_on_the_page($pagename, $ignoreparams = false) {
        $ignoreparams = (bool)$ignoreparams;

        if ($this->running_javascript()) {
            $this->wait_for_seconds(10);
        }

        $currenturl = $this->getsession()->getCurrentUrl();
        $currentanchor = parse_url($currenturl, PHP_URL_FRAGMENT);
        $currenturlwithoutanchor = str_replace('#' . $currentanchor, '', $currenturl);

        $desiredurl = $this->locate_path($pagename, false);

        // Strip the params if we need to. Can be handy if we have unpredictable landing page e.g. after create there will
        // possibly be a new id in there.
        if ($ignoreparams) {
            $currentpath = parse_url($currenturl, PHP_URL_PATH);
            $message = "Should be on the " . $desiredurl . " page but instead the url is " . $currentpath;
            if ($currentpath != $desiredurl) {
                throw new ExpectationException($message, $this->getsession());
            }
        } else {
            $message = "Should be on the " . $desiredurl . " page but instead the url is " . $currenturlwithoutanchor;
            if ($currenturlwithoutanchor != $desiredurl) {
                throw new ExpectationException($message, $this->getsession());
            }
        }
    }

    /**
     * @Then /^show me a screenshot$/
     * @param string $filename
     */
    public function show_me_a_screenshot($filename = 'behat_screenshot.jpg') {
        global $CFG;

        $this->saveScreenshot($filename, $CFG->dataroot . '/temp');
        $this->open_screenshot($filename, $CFG->dataroot . '/temp');
    }

    /**
     * @Then /^show me the page$/
     * @param string $filename
     */
    public function show_me_the_page($filename = 'behat_page.html') {
        global $CFG;

        $htmldata = $this->getsession()->getDriver()->getContent();
        $fileandpath = $CFG->dataroot . '/temp/' . $filename;
        file_put_contents($fileandpath, $htmldata);
        $this->open_html_page($fileandpath);
    }

    /**
     * @Given /^max debugging$/
     */
    public function max_debugging() {
        set_config('debug', DEBUG_DEVELOPER);
        set_config('debugdisplay', 1);
    }

    /**
     * @Given /^wait for (\d+) seconds$/
     * @param $seconds
     */
    public function wait_for_seconds($seconds) {
        $this->getsession()->wait($seconds * 1000);
    }

    // And I click on ".moodle-dialogue-focused.filepicker .yui3-button.closebutton" "css_element"
    public function dismiss() {
        $this->find('css', ".moodle-dialogue-focused.filepicker .yui3-button.closebutton")->click();
    }

    // Submission steps

    /**
     * @Given /^(?:I have|the student has) a submission$/
     */
    public function i_have_a_submission() {
        /**
         * @var $generator mod_coursework_generator
         */
        $generator = testing_util::get_data_generator()->get_plugin_generator('mod_coursework');

        $submission = new stdClass();
        $submission->allocatableid = $this->student->id();
        $submission->allocatabletype = $this->student->type();
        $this->submission = $generator->create_submission($submission, $this->coursework);
    }

    /**
     * Named student has a submission.
     * @Given /^the student called "(?P<name>(?:[^"]|\\")*)" has a( finalised)? submission*$/
     */
    public function named_student_has_a_submission(string $fullname, bool $finalised = false) {
        global $DB;
        $generator = testing_util::get_data_generator()->get_plugin_generator('mod_coursework');

        // Check if the name consists of first- and last name.
        $nameparts = explode(' ', $fullname, 2);
        $firstname = $nameparts[0];
        $lastname = $nameparts[1] ?? '';

        if (empty($lastname)) {
            $userid = $DB->get_field_sql(
                "SELECT id FROM {user} WHERE firstname = ? AND lastname LIKE 'student%'",
                [$firstname]
            );
        } else {
            $userid = $DB->get_field_sql(
                "SELECT id FROM {user} WHERE firstname = ? AND lastname = ?",
                [$firstname, $lastname]
            );
        }
        if ($userid) {
            $submission = new stdClass();
            $submission->allocatableid = $userid;
            $submission->allocatabletype = 'user';
            $this->submission = $generator->create_submission($submission, $this->coursework);
            if ($finalised) {
                $this->submission->finalisedstatus = submission::FINALISED_STATUS_FINALISED;
                $DB->set_field(
                    'coursework_submissions',
                    'finalisedstatus',
                    submission::FINALISED_STATUS_FINALISED,
                    ['id' => $this->submission->id]
                );
            }
        } else {
            throw new ExpectationException("User $firstname not found", $this->getsession());
        }
    }

    /**
     * @Given /^another student has another submission$/
     */
    public function another_student_has_another_submission() {
        /**
         * @var $generator mod_coursework_generator
         */
        $generator = testing_util::get_data_generator()->get_plugin_generator('mod_coursework');

        $submission = new stdClass();
        $submission->allocatableid = $this->otherstudent->id();
        $submission->allocatabletype = $this->otherstudent->type();
        $this->othersubmission = $generator->create_submission($submission, $this->coursework);
    }

    /**
     * @Given /^the group has a submission$/
     */
    public function ithe_group_has_a_submission() {
        /**
         * @var $generator mod_coursework_generator
         */
        $generator = testing_util::get_data_generator()->get_plugin_generator('mod_coursework');

        $submission = new stdClass();
        $submission->allocatableid = $this->group->id();
        $submission->allocatabletype = $this->group->type();
        $submission->lastupdatedby = $this->student->id();
        $submission->createdby = $this->student->id();
        $this->submission = $generator->create_submission($submission, $this->get_coursework());
    }

    /**
     * @Then /^the submission should( not)? be finalised$/
     * @param bool $negate
     */
    public function the_submission_should_be_finalised($negate = false) {
        global $DB;

        $finalised = $DB->get_field('coursework_submissions', 'finalisedstatus', ['id' => $this->submission->id]);
        if ($negate && $finalised == submission::FINALISED_STATUS_FINALISED) {
            throw new ExpectationException('Submission is finalised and should not be', $this->getsession());
        } else if (!$negate && $finalised != submission::FINALISED_STATUS_FINALISED) {
            throw new ExpectationException('Submission is not finalised and should be', $this->getsession());
        }
    }

    /**
     * @Then /^I should( not)? see the student\'s submission on the page$/
     * @param bool $negate
     */
    public function i_should_see_the_student_s_submission_on_the_page($negate = false) {
        $fields =
            $this->getsession()->getpage()->findAll('css', ".submission-{$this->submission->id}");
        $countfields = count($fields);
        if ($countfields == 0 && !$negate) {
            throw new ExpectationException('Student submission is not on page and should be', $this->getsession());
        } else if ($negate && $countfields > 0) {
            throw new ExpectationException('Student submission is on page and should not be', $this->getsession());
        }
    }

    /**
     * @Given /^the submission is (not )?finalised$/
     */
    public function the_submission_is_finalised($negate = false) {
        $this->submission->finalisedstatus = $negate
            ? submission::FINALISED_STATUS_NOT_FINALISED : submission::FINALISED_STATUS_FINALISED;
        $this->submission->save();
    }

    /**
     * @Then /^the file upload button should not be visible$/
     */
    public function the_file_upload_button_should_not_be_visible() {
        $button = $this->find('css', 'div.fp-btn-add a');
        if ($button->isVisible()) {
            throw new ExpectationException("The file picker upload button should be hidden, but it isn't", $this->getsession());
        }
    }

    /**
     * @Given /^I click on the (\w+) submission button$/
     * @param $action
     */
    public function i_click_on_the_new_submission_button($action) {
        if ($action == 'edit') {
            $locator = "//div[@class='editsubmissionbutton']";
        } else if ($action == 'new') {
            $locator = "//div[@class='newsubmissionbutton']";
        } else if ($action == 'finalise') {
            $locator = "//div[@class='finalisesubmissionbutton']";
        } else if ($action == 'save') {
            $locator = "//div[@class='newsubmissionbutton']";
        }

        // Behat generates button type submit whereas code does input.
        $page = $this->getsession()->getpage();
        $inputtype = $page->find('xpath', $locator . "//input[@type='submit']");
        $buttontype = $page->find('xpath', $locator . "//button[@type='submit']");

        // Check how element was created and use it to find the button.
        $button = ($inputtype !== null) ? $inputtype : $buttontype;

        if (!$button) {
            throw new ExpectationException(
                "Button not found ($action): " . $button->getXpath(),
                $this->getsession()
            );
        }

        $button->press();
    }

    /**
     * @Then /^I should( not)? see the (\w+) submission button$/
     * @param bool $negate
     * @param string $action
     */
    public function i_should_not_see_the_edit_submission_button($negate = false, $action = 'new') {
        $link = $this->getsession()->getpage()
            ->findAll('xpath', "//a[@class='btn btn-primary btn-block'][text()='" . ucfirst($action) . " your submission']");
        $button = $this->getsession()->getpage()
            ->findAll('xpath', "//button[text()='" . ucfirst($action) . " your submission']");
        $buttons = ($link) ? $link : $button;// check how element was created and use it to find the button
        $countbuttons = count($buttons);

        $countbuttons = count($buttons);
        if ($countbuttons > 0 && $negate) {
            throw new ExpectationException('I see the button when I should not', $this->getsession());
        } else if ($countbuttons == 0 && !$negate) {
            throw new ExpectationException('I do not see the button when I should', $this->getsession());
        }
    }

    /**
     * @Then /^I should see both the submission files on the page$/
     */
    public function i_should_see_both_the_files_on_the_page() {

        /**
         * @var mod_coursework_behat_student_page $student_page
         */
        $studentpage = $this->get_page('student page');
        $studentpage->should_have_two_submission_files();
    }

    /**
     * @Given /^I should see that the submission was made by the (.+)$/
     * @param string $rolename
     */
    public function i_should_see_that_the_submission_was_made_by_the_other_student($rolename) {
        $rolename = str_replace(' ', '_', $rolename);

        /**
         * @var mod_coursework_behat_student_page $student_page
         */
        $studentpage = $this->get_page('student page');
        $studentpage->should_show_the_submitter_as($rolename);
    }

    // User steps

    /**
     * @Given /^I (?:am logged|log) in as (?:a|an|the) (?P<role_name_string>(?:[^"]|\\")*)$/
     * @param $rolename
     * @throws coding_exception
     */
    public function i_am_logged_in_as_a($rolename) {
        $rolename = str_replace(' ', '', $rolename);

        if (empty($this->$rolename)) {
            $this->$rolename = $this->create_user($rolename);
        }

        /**
         * @var mod_coursework_behat_login_page $login_page
         */
        $loginpage = $this->get_page('login page');
        $loginpage->load();
        $loginpage->login($this->$rolename);
    }

    /**
     * This is really just a convenience method so that we can chain together the call to create the
     * course and this one, within larger steps.
     *
     * @Given /^the ([\w]+) user has been kept for later$/
     * @param $rolename
     */
    public function the_user_has_been_kept_for_later($rolename) {
        global $DB;

        $this->$rolename = $DB->get_record('user', ['username' => "user{$this->usersuffix}"]);
    }

    /**
     * Role names might be fed through from another step that has already removed the spaces, so
     * make sure you add both options. Don't use a wildcard, as it causes collisions with other steps.
     *
     * @Given /^there is (a|another|an) (teacher|editing teacher|editingteacher|manager|student)(?: called "([\w]+)")*$/
     * @param $other
     * @param $rolename
     * @param string $firstname optional arg to set the user's first name to enable their row to be identifed in grading table.
     * @throws coding_exception
     */
    public function there_is_a_user($other, $rolename, $firstname = '') {

        $other = ($other == 'another');

        $rolename = str_replace(' ', '', $rolename);

        $rolenametosave = $other ? 'other' . $rolename : $rolename;
        $this->$rolenametosave = $this->create_user($rolename, $firstname ?: $rolenametosave);
    }

    /**
     * @param $rolename
     * @param string $displayname
     * @throws coding_exception
     * @return mixed|moodle_database|mysqli_native_moodle_database
     */
    protected function create_user($rolename, $displayname = '') {
        global $DB;

        $this->usersuffix++;

        $generator = testing_util::get_data_generator();

        $user = new stdClass();
        $user->username = 'user' . $this->usersuffix;
        $user->password = 'user' . $this->usersuffix;
        $user->firstname = $displayname ? $displayname : $rolename . $this->usersuffix;
        $user->lastname = $rolename . $this->usersuffix;
        $user = $generator->create_user($user);
        $user = \mod_coursework\models\user::find($user);
        $user->password = 'user' . $this->usersuffix;

        // If the role name starts with 'other_' here (e.g. 'other_teacher') we need to remove it.
        $rolename = str_replace('other_', '', $rolename);
        $roleid = $DB->get_field('role', 'id', ['shortname' => $rolename]);

        if (!$roleid) {
            throw new coding_exception("Cannot find role shortname '$rolename' in role table");
        }
        if (empty($this->course)) {
            throw new coding_exception('Must have a course to enrol the user onto');
        }

        $generator->enrol_user(
            $user->id,
            $this->course->id,
            $roleid
        );

        return $user;
    }

    /**
     * @Given /^the student is a member of a group$/
     */
    public function i_am_a_member_of_a_group() {

        $generator = testing_util::get_data_generator();

        $group = new stdClass();
        $group->name = 'My group';
        $group->courseid = $this->course->id;
        $group = $generator->create_group($group);
        $this->group = group::find($group);

        $membership = new stdClass();
        $membership->groupid = $this->group->id;
        $membership->userid = $this->student->id;
        $generator->create_group_member($membership);
    }

    /**
     * @Given /^the other student is a member of the group$/
     */
    public function the_other_student_is_a_member_of_the_group() {

        $generator = testing_util::get_data_generator();

        $membership = new stdClass();
        $membership->groupid = $this->group->id;
        $membership->userid = $this->otherstudent->id;
        $generator->create_group_member($membership);
    }

    /**
     * @Given /^I save everything$/
     */
    public function i_save_everything() {
        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        $page->save_everything();
    }

    /**
     * @Given /^I should see the date when the individual feedback will be released$/
     */
    public function i_see_the_date_when_individual_feedback_is_released() {
        /**
         * @var mod_coursework_behat_coursework_page $page
         */
        $page = $this->get_page('coursework page');
        if (!$page->individual_feedback_date_present()) {
            throw new ExpectationException('I do not see the feedback release date', $this->getsession());
        }
    }

    /**
     * @Given /^I should not see the date when the individual feedback will be released$/
     */
    public function i_do_not_see_the_date_when_individual_feedback_is_released() {
        /**
         * @var mod_coursework_behat_coursework_page $page
         */
        $page = $this->get_page('coursework page');

        if ($page->individual_feedback_date_present()) {
            throw new ExpectationException('I see the feedback release date when I should not', $this->getsession());
        }
    }

    /**
     * @Given /^I should not see the date when the general feedback will be released$/
     */
    public function i_do_not_see_the_date_when_general_feedback_is_released() {
        /**
         * @var mod_coursework_behat_coursework_page $page
         */
        $page = $this->get_page('coursework page');
        if ($page->general_feedback_date_present()) {
            throw new ExpectationException('I see the general feedback release date when I should not', $this->getsession());
        }
    }

    /**
     * @Given /^I should see the date when the general feedback will be released$/
     */
    public function i_do_see_the_date_when_general_feedback_is_released() {
        /**
         * @var mod_coursework_behat_coursework_page $page
         */
        $page = $this->get_page('coursework page');
        if (!$page->general_feedback_date_present()) {
            throw new ExpectationException(
                'I do not see the general feedback release date when I should',
                $this->getsession()
            );
        }
    }

    /**
     * @Then /^I should see the first initial assessors grade and comment$/
     */
    public function i_should_see_the_first_initial_assessors_grade_and_comment() {
        /**
         * @var mod_coursework_behat_show_feedback_page $page
         */
        $page = $this->get_page('show feedback page');
        $page->set_feedback($this->get_initial_assessor_feedback_for_student());
        if (!$page->has_comment('New comment here')) {
            throw new ExpectationException('Comment not found', $this->getsession());
        }
        if (!$page->has_grade('67')) {
            throw new ExpectationException('Grade 67 not found', $this->getsession());
        }
    }

    /**
     * @return mixed
     */
    protected function get_initial_assessor_feedback_for_student() {
        $submissionparams = ['courseworkid' => $this->get_coursework()->id,
                                   'allocatableid' => $this->student->id,
                                   'allocatabletype' => 'user'];
        $submission = submission::find($submissionparams);

        $feedbackparams = [
            'stageidentifier' => 'assessor_1',
            'submissionid' => $submission->id,
        ];
        $feedback = \mod_coursework\models\feedback::find($feedbackparams);
        return $feedback;
    }

    /**
     * @param string $filename
     * @param string $filepath
     */
    private function open_screenshot($filename, $filepath) {
        if (PHP_OS === "Darwin" && PHP_SAPI === "cli") {
            exec('open -a "Preview.app" ' . $filepath . '/' . $filename);
        }
    }

    /**
     * @param string $fileandpath
     */
    private function open_html_page($fileandpath) {
        if (PHP_OS === "Darwin" && PHP_SAPI === "cli") {
            exec('open -a "Safari.app" ' . $fileandpath);
        }
    }

    /**
     * @Given /^I click show all students button$/
     */
    public function i_click_on_show_all_students_button() {
        $page = $this->get_page('coursework page');
        $page->show_hide_non_allocated_students();
    }

    /**
     *
     * @When /^I enable automatic sampling for stage ([1-3])$/
     *
     */
    public function i_enable_automatic_sampling_for_stage($stage) {

        $page = $this->get_page('allocations page');
        self::expand_sampling_strategy_div();
        $page->enable_atomatic_sampling_for($stage);
    }

    /**
     * Expand sampling strategy div.
     * I.e. if div is hidden, click the heading to expand it.
     * @return void
     */
    private function expand_sampling_strategy_div() {
        $hiddendiv = $this->find('css', '.sampling-rules');
        if ($hiddendiv->getAttribute('style') == 'display: none;') {
            $heading = "#sampling_strategy_settings_header";
            $headingnode = $this->find('css', $heading);
            behat_general::wait_for_pending_js_in_session($this->getsession());
            $this->wait_for_seconds(1);
            if ($headingnode) {
                // Expand the div by clicking heading.
                $headingnode->click();
                behat_general::wait_for_pending_js_in_session($this->getsession());
                $this->wait_for_seconds(2);
            }
        }
    }

    /**
     * @Given /^I enable total rule for stage (\d+)$/
     *
     * @param $stage
     * @throws coding_exception
     */
    public function i_enable_total_rule_for_stage($stage) {
        $page = $this->get_page('allocations page');
        self::expand_sampling_strategy_div();
        $page->enable_total_rule_for_stage($stage);
    }

    /**
     * @Given /^I add grade range rule for stage (\d+)$/
     *
     * @param $stage
     * @throws coding_exception
     */
    public function i_add_grade_range_rule_for_stage($stage) {
        $page = $this->get_page('allocations page');
        $page->add_grade_range_rule_for_stage($stage);
    }

    /**
     * @Given /^I enable grade range rule (\d+) for stage (\d+)$/
     *
     * @param $ruleno
     * @param $stage
     * @throws coding_exception
     */
    public function i_enable_grade_range_rule_for_stage($ruleno, $stage) {
        $ruleno = $ruleno - 1;
        $page = $this->get_page('allocations page');
        $page->enable_grade_range_rule_for_stage($stage, $ruleno);
    }

    /**
     * @Then  /^I select limit type for grade range rule (\d+) in stage (\d+) as "([\w]*)"$/
     *
     * @param $ruleno
     * @param $stage
     * @param $type
     * @throws coding_exception
     */
    public function i_select_limit_type_for_grade_range_rule_in_stage_as($ruleno, $stage, $type) {
        $ruleno = $ruleno - 1;
        $page = $this->get_page('allocations page');
        $page->select_type_of_grade_range_rule_for_stage($stage, $ruleno, $type);
    }

    /**
     * @Then  /^I select "([\w]*)" grade limit for grade range rule (\d+) in stage (\d+) as "(\d+)"$/
     *
     * @param $range
     * @param $ruleno
     * @param $stage
     * @param $value
     * @throws coding_exception
     */
    public function i_select_grade_limit_type_for_grade_range_rule_in_stage_as($range, $ruleno, $stage, $value) {
        $ruleno = $ruleno - 1;
        $page = $this->get_page('allocations page');
        $page->select_range_for_grade_range_rule_for_stage($range, $stage, $ruleno, $value);
    }

    /**
     * @Given /^I select (\d+)% of total students in stage (\d+)$/
     *
     * @param $percentage
     * @param $stage
     * @throws coding_exception
     */
    public function i_select_total_submissions_in_stage($percentage, $stage) {
        $page = $this->get_page('allocations page');
        self::expand_sampling_strategy_div();
        $page->select_total_percentage_for_stage($percentage, $stage);
    }

    /**
     * @Then /^(a|another) student( or another student)? should( not)? be automatically included in sample for stage (\d+)$/
     *
     * @param $other
     * @param $another
     * @param $negate
     * @param $stage
     * @throws ExpectationException
     * @throws coding_exception
     */
    public function student_automatically_included_in_sample_for_stage($other, $another, $negate, $stage) {
        $page = $this->get_page('allocations page');
        $another = (!empty($another)) ? $this->otherstudent : '';
        $other = ($other == 'another');
        $student = $other ? 'otherstudent' : 'student';

        $included = $page->automatically_included_in_sample($this->coursework, $this->$student, $another, $stage);
        if ($included && $negate) {
            throw new ExpectationException('Student included in sample and should not be', $this->getsession());
        } else if (!$included && !$negate) {
            throw new ExpectationException('Student not included in sample and should be', $this->getsession());
        }
    }

    /**
     * @Given /^I save sampling strategy$/
     */
    public function i_save_sampling_strategy() {
        /**
         * @var mod_coursework_behat_allocations_page $page
         */
        $page = $this->get_page('allocations page');
        $page->save_sampling_strategy();
    }

    /**
     * @Given /^teachers hava a capability to administer grades$/
     */
    public function teachers_hava_a_capability_to_administer_grades() {
        global $DB;

        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);
        role_change_permission(
            $teacherrole->id,
            $this->get_coursework()->get_context(),
            'mod/coursework:administergrades',
            CAP_ALLOW
        );
    }

    /**
     * Check "Due" and "Extended deadline" dates at top of page.
     * @Given /^I should see (due|extension) date "(?P<value>(?:[^"]|\\")*)"$/
     */
    public function i_should_see_duedate($date, $value) {
        if ($date === "due") {
            $date = "Due";
        } else if ($date === "extension") {
            $date = "Extended deadline";
        }

        $page = $this->getsession()->getpage();
        $due = $page->find('xpath', "//h3[text() = '$date']/following-sibling::p[starts-with(text(), '$value')]");

        if (!$due) {
            throw new ExpectationException(
                'Should have seen due date, but it was not there',
                $this->getsession()
            );
        }
    }

    /**
     * For example, the coursework deadline can be set with:
     *   And the coursework deadline date is "##+1 week##"
     * @Given /^the coursework ([\w]+) date is "(?P<value>(?:[^"]|\\")*)"$/
     */
    public function the_coursework_date_is($name, $value) {
        $this->the_coursework_setting_is_in_the_database($name, $value);
    }

    /**
     * @Given /^the student personaldeadline is "(?P<value>(?:[^"]|\\")*)"$/
     */
    public function the_student_personaldeadline_is($value) {
        \mod_coursework\models\personaldeadline::create([
           'allocatableid' => $this->student->id(),
           'allocatabletype' => 'user',
           'courseworkid' => $this->coursework->id,
           'personaldeadline' => $value,
           'createdbyid' => get_admin()->id,
        ]);
    }

    /**
     * For example:
     *   I should see submission status "Submitted, but not finalised"
     *
     * @Given /^I should see submission status "(?P<status>(?:[^"]|\\")*)"$/
     */
    public function i_should_see_submission_status($status) {
        $page = $this->getsession()->getpage();
        $match = $page->find('xpath', "//li[normalize-space(string()) = 'Status $status']");

        if (!$match) {
            throw new ExpectationException(
                'Should have seen expected submission status, but it was not there',
                $this->getsession()
            );
        }
    }

    /**
     * @Given /^grades have been released$/
     */
    public function grades_have_been_released() {
        $this->coursework->publish_grades();
    }

    /**
     * @Given /^I should see mark (\d+)$/
     */
    public function i_should_see_mark($mark) {
        $page = $this->getsession()->getpage();
        $match = $page->find('xpath', "//li[normalize-space(string()) = 'Mark $mark']");

        if (!$match) {
            throw new ExpectationException(
                'Should have seen expected mark, but it was not there',
                $this->getsession()
            );
        }
    }

    /**
     * For matching the submitted date ignoring the time part, for example,
     *   I should see submitted date 4 July 2025
     *
     * @Given /^I should see submitted date "(?P<date>(?:[^"]|\\")*)"$/
     */
    public function i_should_see_submitted_date($date) {
        $page = $this->getsession()->getpage();
        $match = $page->find('xpath', "//li[starts-with(normalize-space(string()), 'Submitted $date')]");

        if (!$match) {
            throw new ExpectationException(
                "Should have seen expected submitted date $date, but it was not there",
                $this->getsession()
            );
        }
    }

    /**
     * For matching a late submitted date ignoring the time part
     *
     * Example: I should see late submitted date 4 July 2025
     *
     * @Given /^I should see late submitted date "(?P<date>(?:[^"]|\\")*)"$/
     */
    public function i_should_see_late_submitted_date($date) {
        $page = $this->getsession()->getpage();
        $match = $page->find('xpath', "//li[starts-with(normalize-space(string()), 'Submitted Late $date')]");

        if (!$match) {
            throw new ExpectationException(
                "Should have seen expected submitted date $date, but it was not there",
                $this->getsession()
            );
        }
    }

    /**
     * @Given /^sample marking includes student for stage (\d)$/
     */
    public function sample_marking_includes_student_for_stage($stage) {
        \mod_coursework\models\assessment_set_membership::create([
           'allocatableid' => $this->student->id(),
           'allocatabletype' => 'user',
           'courseworkid' => $this->coursework->id,
           'stageidentifier' => "assessor_$stage",
        ]);
    }

    /**
     * @Given /^I should see marking summary:$/
     * @param TableNode $data The marking summary field and value pairs.
     */
    public function i_should_see_marking_summary(TableNode $table) {
        $page = $this->getsession()->getpage();
        $match = $page->find('css', "#marking-summary-title");

        if (!$match) {
            throw new ExpectationException(
                "Should have seen expected \"Marking summary\" heading, but it was not there",
                $this->getsession()
            );
        }

        $datahash = $table->getRowsHash();

        foreach ($datahash as $locator => $value) {
            $match = $page->find('xpath', "//li[normalize-space(string()) = '$locator $value']");

            if (!$match) {
                throw new ExpectationException(
                    "Should have seen expected value for $locator, but it was not there",
                    $this->getsession()
                );
            }
        }
    }

    // File renaming test step definitions.

    /**
     * Verify that the uploaded file has been renamed to match the expected pattern.
     *
     * @Then /^the uploaded file should be renamed with pattern "([^"]*)"$/
     */
    public function the_uploaded_file_should_be_renamed_with_pattern($pattern) {
        // Find the student's submission.
        $submission = $this->find_student_submission();

        $files = $submission->get_submission_files();
        $file = $files->get_first_submitted_file();

        if (!$file) {
            throw new ExpectationException('No submission files found', $this->getsession());
        }

        $actualfilename = $file->get_filename();

        // Convert pattern to regex - the pattern already contains valid regex syntax.
        $regex = '/^' . $pattern . '$/';

        if (!preg_match($regex, $actualfilename)) {
            throw new ExpectationException(
                "File name '$actualfilename' does not match pattern '$pattern'",
                $this->getsession()
            );
        }
    }

    /**
     * Verify that the uploaded file has kept its original name.
     *
     * @Then /^the uploaded file should keep original name "([^"]*)"$/
     */
    public function the_uploaded_file_should_keep_original_name($expectedname) {
        // Find the student's submission.
        $submission = $this->find_student_submission();

        $files = $submission->get_submission_files();
        $file = $files->get_first_submitted_file();

        if (!$file) {
            throw new ExpectationException('No submission files found', $this->getsession());
        }

        $actualname = $file->get_filename();

        if ($actualname !== $expectedname) {
            throw new ExpectationException(
                "Expected filename '$expectedname', got '$actualname'",
                $this->getsession()
            );
        }
    }

    /**
     * Verify that the uploaded files have been renamed to match the expected sequential patterns.
     *
     * @Then /^the uploaded files should be renamed with sequential patterns:$/
     */
    public function the_uploaded_files_should_be_renamed_with_sequential_patterns(TableNode $table): void {
        // Find the student's submission.
        $submission = $this->find_student_submission();

        $files = $submission->get_submission_files();
        $patterns = $table->getHash();

        if (count($files) !== count($patterns)) {
            throw new ExpectationException(
                'Number of files (' . count($files) . ') does not match number of patterns (' . count($patterns) . ')',
                $this->getsession()
            );
        }

        $filenumber = 0;
        foreach ($files as $file) {
            $actualfilename = $file->get_filename();
            $pattern = $patterns[$filenumber]['pattern'];

            // Convert pattern to regex - the pattern already contains valid regex syntax.
            $regex = '/^' . $pattern . '$/';

            if (!preg_match($regex, $actualfilename)) {
                throw new ExpectationException(
                    "File " . ($filenumber + 1) . " name '$actualfilename' does not match pattern '$pattern'",
                    $this->getsession()
                );
            }
            $filenumber++;
        }
    }

    /**
     * Find the student's submission for testing.
     *
     * @throws ExpectationException
     */
    private function find_student_submission(): submission {
        global $DB;

        // Get the submission record.
        $submissionrecord = $DB->get_record('coursework_submissions', [
            'courseworkid' => $this->coursework->id,
            'userid' => $this->student->id,
        ]);

        if (!$submissionrecord) {
            throw new ExpectationException('No submission found for student', $this->getsession());
        }

        return submission::find($submissionrecord->id);
    }

    /**
     * @Given /^the candidate number for the student is "([^"]*)"$/
     */
    public function the_candidate_number_for_the_student_is(string $candidatenumber): void {
        global $CFG;
        // Specify the mock provider file and candidate number for the manager to use.
        set_config('behat_mock_provider_filepath', $CFG->dirroot . '/mod/coursework/tests/behat/fixtures/mock_candidate_provider.php');
        set_config('behat_mock_provider_class', '\\mod_coursework\\behat\\fixtures\\mock_candidate_provider');
        set_config('behat_mock_candidate_number', $candidatenumber);
    }

    /**
     * Verify that the uploaded file has been renamed to the expected filename.
     *
     * @Then /^the uploaded file should be renamed to "([^"]*)"$/
     */
    public function the_uploaded_file_should_be_renamed_to(string $expectedname): void {
        // Find the student's submission.
        $submission = $this->find_student_submission();
        $files = $submission->get_submission_files();
        $file = $files->get_first_submitted_file();

        if (!$file) {
            throw new ExpectationException('No submission files found', $this->getsession());
        }

        $actualname = $file->get_filename();

        if ($actualname !== $expectedname) {
            throw new ExpectationException(
                "Expected filename '$expectedname', got '$actualname'",
                $this->getsession()
            );
        }
    }

    /**
     * Verify that the uploaded files have been renamed to the expected filenames.
     *
     * @Then /^the uploaded files should be renamed to:$/
     */
    public function the_uploaded_files_should_be_renamed_to(TableNode $table): void {
        // Find the student's submission.
        $submission = $this->find_student_submission();
        $files = $submission->get_submission_files();
        $expectedfilenames = $table->getHash();

        if (count($files) !== count($expectedfilenames)) {
            throw new ExpectationException(
                'Number of files (' . count($files) . ') does not match number of expected filenames (' . count($expectedfilenames) . ')',
                $this->getsession()
            );
        }

        $actualfilenames = [];
        foreach ($files as $file) {
            $actualfilenames[] = $file->get_filename();
        }

        $expected = [];
        foreach ($expectedfilenames as $row) {
            $expected[] = $row['filename'];
        }

        sort($actualfilenames);
        sort($expected);

        for ($i = 0; $i < count($actualfilenames); $i++) {
            if ($actualfilenames[$i] !== $expected[$i]) {
                throw new ExpectationException(
                    "Expected filename '{$expected[$i]}', got '{$actualfilenames[$i]}'",
                    $this->getsession()
                );
            }
        }
    }

    /**
     * Default auto grading grade class boundaries admin setting exists.
     *
     * @Then /^the admin setting for auto grade class boundaries is set using the example$/
     */
    public function default_grade_class_boundary_admin_setting_exists() {
        set_config(
            'autogradeclassboundaries',
            average_grade_no_straddle::get_example_setting(),
            'coursework'
        );
    }


    /**
     * Override this to replace "\n" with chr(10) otherwise populating textarea with text including line break chars fails.
     * Sets the specified value to the field.
     *
     * @Given /^I set the field "(?P<field_string>(?:[^"]|\\")*)" to "(?P<field_value_string>(?:[^"]|\\")*)" replacing line breaks$/
     * @param string $field
     * @param string $value
     * @return void
     * @throws ElementNotFoundException Thrown by behat_base::find
     */
    public function i_set_the_field_to_replacing_line_breaks($field, $value) {
        $value = str_replace('\n', chr(10), $value);
        $this->execute([behat_forms::class, 'i_set_the_field_to'], [$field, $value]);
    }

    /**
     * Sets an extension deadline for a student in a coursework.
     *
     * Example: And the coursework extension for "Student 1" in "Coursework 1" is "1 January 2027 08:00"
     * Example: And the coursework extension for "Student 1" in "Coursework 1" is "## + 1 month ##"
     *
     * @Given /^the coursework extension for "(?P<fullname_string>(?:[^"]|\\")*)" in "(?P<cwname>(?:[^"]|\\")*)" is "(?P<datestr>(?:[^"]|\\")*)"$/
     */
    public function set_extension_for_user($fullname, $cwname, $datestr) {
        global $DB;

        // Check date string.
        if (is_int($datestr) || (is_string($datestr) && ctype_digit($datestr))) {
            $extendeddeadline = (int)$datestr;
        } else {
            $extendeddeadline = strtotime($datestr);
            if ($extendeddeadline === false) {
                throw new \InvalidArgumentException('Invalid user extension date string: ' . $datestr);
            }
        }

        // Find the coursework by name.
        $cw = $DB->get_record('coursework', ['name' => $cwname], '*', MUST_EXIST);

        $user = $this->get_user_from_fullname($fullname);

        // See if an extension already exists.
        $existing = $DB->get_record('coursework_extensions', [
            'courseworkid' => $cw->id,
            'allocatableid' => $user->id,
            'allocatabletype' => 'user',
        ]);

        $record = new stdClass();
        $record->courseworkid = $cw->id;
        $record->allocatableid = $user->id;
        $record->allocatabletype = 'user';
        $record->extended_deadline = $extendeddeadline;
        $record->createdbyid = 2;  // Admin ID.

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('coursework_extensions', $record);
        } else {
            $DB->insert_record('coursework_extensions', $record);
        }
    }

    /**
     * @Given /^the following markers are allocated:$/
     *
     * @param TableNode $allocations Students and their markers.
     */
    public function the_following_markers_are_allocated(TableNode $allocations) {
        global $DB;

        $datahash = $allocations->getHash();

        foreach ($datahash as $allocate) {
            $stages = ['assessor_1'];
            if (isset($allocate['moderator'])) {
                $stages[] = 'moderator';
            } else if (isset($allocate['assessor_2'])) {
                $stages[] = 'assessor_2';
            }

            $student = $this->get_user_from_fullname($allocate['student']);
            foreach ($stages as $stage) {
                $marker = $this->get_user_from_fullname($allocate[$stage]);

                $record = $DB->get_record('coursework_allocation_pairs', [
                    'courseworkid' => $this->coursework->id,
                    'allocatableid' => $student->id,
                    'allocatableuser' => $student->id,
                    'stageidentifier' => $stage,
                    'allocatabletype' => 'user',
                ]);
                if ($record) {
                    $record->assessorid = $marker->id;
                    $record->ismanual = 1;
                    $DB->update_record('coursework_allocation_pairs', $record);
                } else {
                    $record = new stdClass();
                    $record->courseworkid = $this->coursework->id;
                    $record->allocatableid = $student->id;
                    $record->allocatableuser = $student->id;
                    $record->assessorid = $marker->id;
                    $record->stageidentifier = $stage;
                    $record->allocatabletype = 'user';
                    $record->ismanual = 1;
                    $DB->insert_record('coursework_allocation_pairs', $record);
                }
            }
        }
        allocation::remove_cache($this->coursework->id);
    }

    /**
     * Return a user record from a given full name ("firstname lastname")
     *
     * @param string $fullname
     * @return mixed|stdClass
     * @throws coding_exception
     */
    private function get_user_from_fullname(string $fullname) {
        global $DB;

        // Check if the name consists of first- and last name.
        $nameparts = explode(' ', $fullname, 2);
        $firstname = $nameparts[0];
        $lastname = $nameparts[1] ?? '';

        if (empty($lastname)) {
            throw new coding_exception("Full name '{$fullname}' must contain at least one space between first and last name.");
        }

        // Find user by full name (firstname + lastname).
        $user = $DB->get_record('user', [
            'firstname' => $firstname,
            'lastname'  => $lastname,
        ]);

        if (!$user) {
            throw new coding_exception("Could not find user with name '{$fullname}'.");
        }
        return $user;
    }

    /**
     * Inserts a grade directly into coursework_feedbacks table.
     *
     * @When /^the submission from "(?P<studentfullname>[^"]*)" is marked by "(?P<markerfullname>[^"]*)" with:$/
     */
    public function mark_coursework_submission_directly(
        string $studentfullname,
        string $markerfullname,
        TableNode $table
    ) {
        global $DB;

        $student = $this->get_user_from_fullname($studentfullname);
        $marker = $this->get_user_from_fullname($markerfullname);

        // Resolve submission for this student.
        $submission = $DB->get_record('coursework_submissions', [
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $student->id,
        ]);
        if (!$submission) {
            throw new ExpectationException("Submission for '$studentfullname' not found", $this->getSession());
        }

        // Resolve marker allocation.
        $allocation = $DB->get_record('coursework_allocation_pairs', [
            'courseworkid' => $this->coursework->id,
            'assessorid' => $marker->id,
            'allocatableid' => $student->id,
        ]);
        if (!$allocation) {
            throw new ExpectationException("Marker '$markerfullname' for '$studentfullname' not found", $this->getSession());
        }

        // Extract the provided table values.
        $data = $table->getRowsHash();

        $mark = isset($data['Mark']) ? floatval($data['Mark']) : null;
        $comment = $data['Comment'] ?? '';
        $finalised = $data['Finalised'] ?? '';

        if ($mark === null) {
            throw new ExpectationException("Missing 'Mark' value in table", $this->getSession());
        }

        // Check if there is already a feedback record.
        $existing = $DB->get_record('coursework_feedbacks', [
            'submissionid' => $submission->id,
            'assessorid' => $marker->id,
            'stageidentifier' => $allocation->stageidentifier,
        ]);

        // Insert/update feedback record.
        $feedback = new stdClass();
        $feedback->submissionid = $submission->id;
        $feedback->assessorid = $marker->id;
        $feedback->stageidentifier = $allocation->stageidentifier;
        $feedback->grade = $mark;
        $feedback->feedbackcomment = $comment;
        $feedback->lasteditedbyuser = $marker->id;
        $feedback->finalised = $finalised;
        $feedback->timecreated = time();
        $feedback->timemodified = time();

        if ($existing) {
            $feedback->id = $existing->id;
            $DB->update_record('coursework_feedbacks', $feedback);
        } else {
            $DB->insert_record('coursework_feedbacks', $feedback);
        }
    }

    /**
     * Allows one role to assign another role.
     *
     * Example:
     * Given the role "Manager" is allowed to assign role "Teacher".
     *
     * @Given /^the role "(?P<fromrole_string>(?:[^"]|\\")*)" is allowed to assign role "(?P<trole_string>(?:[^"]|\\")*)"$/
     *
     * @param string $fromrolename
     * @param string $torolename
     * @return void
     * @throws coding_exception
     */
    public function allow_role_to_assign_role(string $fromrolename, string $torolename) {
        global $DB;

        // Get roles by shortname or fullname.
        $fromrole = $DB->get_record('role', ['shortname' => $fromrolename]);
        if (!$fromrole) {
            $fromrole = $DB->get_record('role', ['name' => $fromrolename]);
        }
        if (!$fromrole) {
            throw new coding_exception("Role '$fromrolename' could not be found.");
        }

        $torole = $DB->get_record('role', ['shortname' => $torolename]);
        if (!$torole) {
            $torole = $DB->get_record('role', ['name' => $torolename]);
        }
        if (!$torole) {
            throw new coding_exception("Role '$torolename' could not be found.");
        }

        // Check if record already exists.
        $exists = $DB->record_exists('role_allow_assign', [
            'roleid'        => $fromrole->id,
            'allowassign'   => $torole->id,
        ]);

        if (!$exists) {
            core_role_set_assign_allowed($fromrole->id, $torole->id);
        }
    }

    /**
     * Clicks a link inside the given table row.
     *
     * Example:
     *   Given I follow "Agree marking" in row "2"
     *
     * @Given /^I follow "(?P<linktext_string>[^"]*)" in row "(?P<rownumber>\d+)"$/
     */
    public function i_follow_in_row($linktext, $rownumber) {
        $linktext = behat_context_helper::escape($linktext);
        $xpath = "(//table[contains(@class,'mod-coursework-submissions-table')]/tbody/tr)[{$rownumber}]
        //*[self::a or self::button][normalize-space(.) = {$linktext}]";

        $element = $this->getSession()->getPage()->find('xpath', $xpath);

        if (!$element) {
            $xpath = "(//table[contains(@class,'mod-coursework-submissions-table')]/tbody/tr)[{$rownumber}]
            //button[normalize-space(.) = {$linktext}]";
            $element = $this->getSession()->getPage()->find('xpath', $xpath);
        }

        if (!$element) {
            throw new ExpectationException(
                "Could not find a link or button '{$linktext}' in row {$rownumber}.",
                $this->getSession()
            );
        }

        // Click it.
        $element->click();
    }

    /**
     * Confirm that text is not showing in a row
     *
     * @Then /^I should( not)? see "(?P<text>[^"]*)" in row "(?P<row>\d+)"$/
     *
     * @param string $notvisible
     * @param string $text
     * @param int $rownumber
     * @return void
     * @throws coding_exception
     */
    public function i_should_see_text_in_row($notvisible, $text, $rownumber) {
        $session = $this->getSession();
        $page = $session->getPage();

        // Find the table body.
        $tbody = $page->find('css', 'table.mod-coursework-submissions-table tbody');
        if (!$tbody) {
            throw new ExpectationException('Could not find coursework submissions table.', $this->getsession());
        }

        // Get all rows.
        $rows = $tbody->findAll('css', 'tr');
        if (!isset($rows[$rownumber - 1])) {
            throw new ExpectationException("Row {$rownumber} does not exist in the submissions table.", $this->getsession());
        }

        $row = $rows[$rownumber - 1];
        $rowtext = $row->getText();
        $contains = strpos($rowtext, $text) !== false;

        // Check text inside the row.
        // If a negation (e.g. "not") was present in the step.
        if (!empty($notvisible)) {
            if ($contains) {
                throw new ExpectationException(
                    "'{$text}' text was found in row {$rownumber}.\nRow contents: {$rowtext}",
                    $this->getsession()
                );
            }
            return; // OK.
        }

        // Normal positive check.
        if (!$contains) {
            throw new ExpectationException(
                "'{$text}' was not found in row {$rownumber}.\nRow contents: {$rowtext}",
                $this->getsession()
            );
        }
    }

    /**
     * Inserts a moderation directly into coursework_mod_agreements table.
     *
     * @When /^the submission from "(?P<studentfullname>[^"]*)" is moderated by "(?P<moderatorfullname>[^"]*)" with:$/
     */
    public function moderate_submission_directly(
        string $studentfullname,
        string $moderatorfullname,
        TableNode $table
    ) {
        global $DB;

        $student = $this->get_user_from_fullname($studentfullname);
        $moderator = $this->get_user_from_fullname($moderatorfullname);

        // Resolve submission for this student.
        $submission = $DB->get_record('coursework_submissions', [
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $student->id,
        ]);
        if (!$submission) {
            throw new ExpectationException("Submission for '$studentfullname' not found", $this->getSession());
        }

        // Resolve moderator allocation.
        $allocation = $DB->get_record('coursework_allocation_pairs', [
            'courseworkid' => $this->coursework->id,
            'assessorid' => $moderator->id,
            'allocatableid' => $student->id,
            'stageidentifier' => 'moderator',
        ]);
        if (!$allocation) {
            throw new ExpectationException("Moderator '$moderatorfullname' for '$studentfullname' not found", $this->getSession());
        }

        // Resolve feedback.
        $params = ['submissionid' => $submission->id];
        $sql = "SELECT *
                FROM {coursework_feedbacks}
                WHERE submissionid = :submissionid
                AND stageidentifier LIKE 'assessor_%'";
        $feedback = $DB->get_record_sql($sql, $params);

        if (!$feedback) {
            throw new ExpectationException("Feedback for '$studentfullname' not found", $this->getSession());
        }

        // Extract the provided table values.
        $data = $table->getRowsHash();
        $agreementtext = $data['Agreement'] ?? '';
        $comment = $data['Comment'] ?? '';

        // Check if there is already an agreement record.
        $existing = $DB->get_record('coursework_mod_agreements', [
            'feedbackid' => $feedback->id,
            'moderatorid' => $moderator->id,
        ]);

        // Insert/update agreement record.
        $agreement = new stdClass();
        $agreement->feedbackid = $feedback->id;
        $agreement->moderatorid = $moderator->id;
        $agreement->agreement = $agreementtext;
        $agreement->modcomment = $comment;
        $agreement->modcommentformat = FORMAT_HTML;
        $agreement->lasteditedby = $moderator->id;
        $agreement->timecreated = time();
        $agreement->timemodified = time();

        if ($existing) {
            $agreement->id = $existing->id;
            $DB->update_record('coursework_mod_agreements', $agreement);
        } else {
            $DB->insert_record('coursework_mod_agreements', $agreement);
        }
    }
}
