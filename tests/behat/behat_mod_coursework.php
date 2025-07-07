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

use Behat\Behat\Context\Step\Given as Given;
use Behat\Behat\Context\Step\When as When;
use Behat\Behat\Context\Step\Then as Then;
use Behat\Mink\Exception\ExpectationException as ExpectationException;
use mod_coursework\models\group;
use mod_coursework\router;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\models\submission;
use mod_coursework\stages\base as stage_base;

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

        $pagename = str_replace(' ', '_', $pagename); // 'student page' => 'student_page'

        $filepath = $CFG->dirroot.'/mod/coursework/tests/behat/pages/'.$pagename.'.php';

        if (file_exists($filepath)) {
            require_once($filepath);
            $classname = 'mod_coursework_behat_' . $pagename;
            return new $classname($this);
        }

        throw new coding_exception('Asked for a behat page class which does not exist: '.$pagename);

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

        switch($path) {
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
                return parent::locate_path('/mod/coursework/view.php?id='. $this->get_coursework()->get_course_module()->id);
                break;

            case 'allocations':
                return parent::locate_path('/mod/coursework/actions/allocate.php?id='.$this->get_coursework()->get_course_module()->id);

            case 'assessor grading':
                return parent::locate_path('/mod/coursework/actions/feedback/new.php?submissionid=' . $this->submission->id.'&assessorid='.$this->teacher->id);

            case 'new feedback':
                return $this->get_router()->get_path('new feedback',
                                                     ['submission' => $this->submission,
                                                           'assessor' => $this->teacher,
                                                           'stage' => $this->get_first_assesor_stage()],
                                                     false,
                                                     $escape);
            case 'create feedback':
                return $this->get_router()->get_path('create feedback',
                                                     ['coursework' => $this->coursework],
                                                     false,
                                                     $escape);

            case 'new submission':
                $submission = submission::build([
                                                    'courseworkid' => $this->coursework->id,
                                                    'allocatableid' => $this->student->id,
                                                    'allocatabletype' => 'user',
                                                ]);
                return $this->get_router()->get_path('new submission',
                                                     ['submission' => $submission], false, $escape);

            case 'create submission':
                return $this->get_router()->get_path('create submission',
                                                     ['coursework' => $this->coursework],
                                                     false,
                                                     $escape);

            case 'edit submission':
                return $this->get_router()->get_path('edit submission',
                                                     ['submission' => $this->submission],
                                                     false,
                                                     $escape);

            case 'update submission':
                return $this->get_router()->get_path('update submission',
                                                     ['submission' => $this->submission],
                                                     false,
                                                     $escape);

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
        $filecount = count($this->getSession()->getPage()->findAll('css', '.submissionfile'));
        if (!$negate && !$filecount) {
            throw new ExpectationException('No files found', $this->getSession());
        } else if ($negate && $filecount) {
            throw new ExpectationException('Files found, but there should be none', $this->getSession());
        }
    }

    /**
     * @Then /^I should see (\d+) file(?:s)? on the page$/
     *
     * @param $numberoffiles
     * @throws ExpectationException
     */
    public function i_should_see_files_on_the_page($numberoffiles) {
        $filecount = count($this->getSession()->getPage()->findAll('css', '.submissionfile'));

        if ($numberoffiles != $filecount) {
            throw new ExpectationException($filecount.' files found, but there should be '.$numberoffiles, $this->getSession());
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
                $this->getSession()
            );
        } else if ($should && !$studentfound) {
            throw new ExpectationException(
                "Student '$studentname' not found but should be",
                $this->getSession()
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
            throw new ExpectationException($message, $this->getSession());
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
            throw new ExpectationException($message, $this->getSession());
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
                $this->getSession()
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
            throw new ExpectationException('Should not have finalise button', $this->getSession());
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
            throw new ExpectationException("Should not have save and finalise button", $this->getSession());
        } else if (!$negate && !$page->has_the_save_and_finalise_button()) {
            throw new ExpectationException("Should have save and finalise button", $this->getSession());
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
     * @Given /^there is feedback for the submission from the teacher$/
     */
    public function there_is_feedback_for_the_submission_from_the_teacher() {
        $feedback = new stdClass();
        $feedback->submissionid = $this->submission->id;
        $feedback->assessorid = $this->teacher->id;
        $feedback->grade = 58;
        $feedback->feedbackcomment = 'Blah';
        $feedback->stage_identifier = 'assessor_1';
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
     * @Given /^I click on the new feedback button for assessor (\d+)$/
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
                "Expected one '$linktitle' visible link but found $countvisible", $this->getSession()
            );
        }
        reset($visible)->click();
    }

    /**
     * @Given /^I click on the new feedback button for assessor (\d+) for another student$/
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

        if ($this->running_javascript()) {
            $this->wait_for_seconds(10);
        }

        $page->press_publish_button();

        if ($this->running_javascript()) {
            $this->wait_for_seconds(10);
        }
        $page->confirm_publish_action();
        if ($this->running_javascript()) {
            $this->wait_for_seconds(10);
        }

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
                $this->getSession()
            );
        };
    }

    /**
     * @Given /^blind marking is enabled$/
     */
    public function blind_marking_is_enabled() {
        $this->get_coursework()->update_attribute('blindmarking', 1);
    }

    /**
     * @Then /^I should not see the student's name in the user cell$/
     */
    public function i_should_not_see_the_students_name_in_the_user_cell() {
        /**
         * @var $page mod_coursework_behat_single_grading_interface
         */
        $page = $this->get_page('single grading interface');
        $page->should_not_have_user_name_in_user_cell($this->student);
    }

    /**
     * @Then /^I should see the student's name in the user cell$/
     */
    public function i_should_see_the_students_name_in_the_user_cell() {
        /**
         * @var $page mod_coursework_behat_single_grading_interface
         */
        $page = $this->get_page('single grading interface');
        $page->should_have_user_name_in_user_cell($this->student);
    }

    /**
     * @Given /^group submissions are enabled$/
     */
    public function group_submissions_are_enabled() {
        $this->get_coursework()->update_attribute('use_groups', 1);
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

        role_change_permission($teacherrole->id,
                               $this->get_coursework()->get_context(),
                               'mod/coursework:addinitialgrade',
                               CAP_ALLOW);
    }

    /**
     * @Given /^the teacher has a capability to edit their own initial feedbacks$/
     */
    public function the_teacher_has_a_capability_to_edit_own_feedbacks() {
        global $DB;

        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);

        role_change_permission($teacherrole->id, $this->get_coursework()->get_context(),
                               'mod/coursework:editinitialgrade', CAP_ALLOW);

    }

    /**
     * @Given /^the teacher has a capability to edit their own agreed feedbacks$/
     */
    public function the_teacher_has_a_capability_to_edit_own_agreed_feedbacks() {
        global $DB;

        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);
        role_change_permission($teacherrole->id,
                               $this->get_coursework()->get_context(),
                               'mod/coursework:editagreedgrade',
                               CAP_ALLOW);
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
            'stage_identifier' => 'assessor_1',
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
                $this->getSession()
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
        behat_base::execute_script_in_session($this->getSession(), $script);
        // The change event is to enable save button.

        if ($reasoncode) {
            $reason = '0'; // 0 is "first reason" in the select menu
            $script = "document.querySelector('select#extension-reason-select').value = '$reason'; ";
            behat_base::execute_script_in_session($this->getSession(), $script);
        }

        $extrainfo = 'Some extra information';
        $script = "document.querySelector('textarea#id_extra_information').value = '$extrainfo'";
        behat_base::execute_script_in_session($this->getSession(), $script);
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
        $result = behat_base::evaluate_script_in_session($this->getSession(), $script);
        if ($result != $newtimestring) {
            throw new ExpectationException("Expected time '$newtimestring' got '$result'", $this->getSession());
        }

        if ($reasoncode) {
            // Reason code 0 is "first reason" in the select menu.
            $script = "document.querySelector('select#extension-reason-select').value === '$reasoncode';";
            if (!$resulttwo = behat_base::evaluate_script_in_session($this->getSession(), $script)) {
                throw new ExpectationException("Expected reason code '$reasoncode' got '$resulttwo'", $this->getSession());
            }
        }

        $extrainfo = 'Some extra information';
        $script = "document.querySelector('textarea#id_extra_information').value === '$extrainfo'";
        if (!$resultthree = behat_base::evaluate_script_in_session($this->getSession(), $script)) {
            throw new ExpectationException("Expected time '$newtimestring' got '$resultthree'", $this->getSession());
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
                "Expected to see extension '$expectedtimestring' got '$text'", $this->getSession()
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
            throw new ExpectationException("Unexpected extension reason '$reason'", $this->getSession());
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
            throw new ExpectationException("Extra info not found", $this->getSession());
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
            $this->student, $this->extensiondeadline
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
            throw new ExpectationException("Unexpected extension reason '$reason'", $this->getSession());
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
            throw new ExpectationException("New info not found", $this->getSession());
        }
    }

    /**
     * @Given /^I click on the new submission button for the student$/
     */
    public function i_click_on_the_new_submission_button_for_the_student() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $multigrader_page
         */
        $multigraderpage = $this->get_page('multiple grading interface');
        $multigraderpage->click_new_submission_button_for($this->student);
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
            throw new ExpectationException($message, $this->getSession());
        }

        // Other teacher - assessor_2.
        $allocatedassessor = $page->user_allocated_assessor($this->student, 'assessor_2');
        if ($allocatedassessor != $this->otherteacher->name()) {
            $message = 'Expected the allocated teacher name to be ' . $this->otherteacher->name()
                . ' but got ' . $allocatedassessor . ' instead.';
            throw new ExpectationException($message, $this->getSession());
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
            throw new ExpectationException($message, $this->getSession());
        }

        // Other student.
        $allocatedassessor = $page->user_allocated_assessor($this->otherstudent, 'assessor_1');
        if ($allocatedassessor != $this->teacher->name()) {
            $message = 'Expected the allocated teacher name to be ' . $this->teacher->name()
                . ' but got ' . $allocatedassessor . ' instead.';
            throw new ExpectationException($message, $this->getSession());
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
        return '#submission_'. $this->student_hash();
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
            throw new ExpectationException("Coursework title '{$this->coursework->name}' not seen", $this->getSession());
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
                $this->getSession()
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
            throw new ExpectationException("Too many courseworks! There should be {$expectedcount}, but there were {$DB->countrecords('coursework')}",
                                           $this->getSession());
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
            throw new ExpectationException('no field change', $this->getSession());
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
     * @Given /^I press the publish button$/
     */
    public function i_press_the_publish_button() {
        $this->find('css', '#id_publishbutton')->press();
        $this->find_button('Continue')->press();
        $this->getSession()->visit($this->locate_path('coursework')); // Quicker than waiting for a redirect
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
        // $this->coursework->publish_grades();

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
                        $this->getSession()
                    );
                }
            } else {
                throw new ExpectationException(
                    "User ID $submission->userid not found for submission $submission->id - could not publish"
                    . " - JSON submission: " . json_encode($submission),
                    $this->getSession()
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
        $field = behat_field_manager::get_form_field($node, $this->getSession());
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
        $field = behat_field_manager::get_form_field($node, $this->getSession());
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
        if ($this->running_javascript()) {
            $page->show_assessor_allocation_settings();
        }
        $this->find('css', '#menuassessorallocationstrategy')->selectOption('percentages');
        $this->getSession()->getPage()->fillField("assessorstrategypercentages[{$this->otherteacher->id}]", $percent);
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
        $this->find('css', '#menuassessorallocationstrategy')->selectOption('percentages');
        $this->getSession()->getPage()->fillField("assessorstrategypercentages[{$this->teacher->id}]", $percent);
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
        $allocation->stage_identifier = 'assessor_1';
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
        $allocation->stage_identifier = $this->coursework->get_moderator_marking_stage()->identifier();

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
        $allocation->stage_identifier = $this->coursework->get_moderator_marking_stage()->identifier();

        $this->allocation = $this->get_coursework_generator()->create_allocation($allocation);
    }

    /**
     * @Given /^there are no allocations in the db$/
     */
    public function there_are_no_allocations_in_the_db() {
        global $DB;

        $DB->delete_records('coursework_allocation_pairs');
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
            throw new ExpectationException('Expected assessor allocation', $this->getSession());
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
                throw new ExpectationException("Expected final grade 56 got $text", $this->getSession());
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
                throw new ExpectationException("Got comment '$text' expected '$comment'", $this->getSession());
            }
        }
    }

    /**
     * @Given /^there is some general feedback$/
     */
    public function there_is_some_general_feedback() {
        $this->get_coursework()->feedbackcomment = 'Some comments';
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
            $page->assessor_grade_should_not_be_present($this->student, $assessornumber, '50');
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
        $page->click_new_final_feedback_button($this->group);
    }

    /**
     * @When /^I click the new multiple final feedback button for the student/
     */
    public function i_click_the_new_multiple_final_feedback_button_student() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $page->click_new_final_feedback_button($this->student);
    }

    /**
     * @When /^I click the new single final feedback button for the student/
     */
    public function i_click_the_new_single_final_feedback_button_student() {
        /**
         * @var mod_coursework_behat_single_grading_interface $page
         */
        $page = $this->get_page('single grading interface');
        $page->click_new_final_feedback_button($this->student);
    }

    /**
     * @Given /^I should see the grade in the form on the page$/
     */
    public function i_should_see_the_grade_in_the_form_on_the_page() {
        $commentfield = $this->find('css', '#feedback_grade');
        $expectedvalue = 56;
        $actual = $commentfield->getValue();
        if ($actual != $expectedvalue) {
            throw new ExpectationException("Expected grade $expectedvalue got $actual", $this->getSession());
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
            throw new ExpectationException("Expected grade $expectedvalue got $actual", $this->getSession());
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
            throw new ExpectationException("Expected final grade $expectedvalue got $text", $this->getSession());
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
        $feedback->isfinalgrade = 1;
        $feedback->submissionid = $this->submission->id;
        $feedback->assessorid = $this->manager->id;
        $feedback->stage_identifier = 'final_agreed_1';
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
        $feedback->stage_identifier = 'final_agreed_1';

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
        // if (!$this->find('xpath', $this->xpath_tag_class_contains_text('td', 'moderated', '56'))) {
        // throw new ExpectationException('Could not find the moderated grade', $this->getSession());
        // }
    }

    /**
     * @When /^I click the edit final feedback button$/
     */
    public function i_click_the_edit_final_feedback_button() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        $button = $page->get_edit_final_feedback_button($this->student);
        if (!$button) {
            throw new ExpectationException('Edit feedback button not present', $this->getSession());
        }
        $button->click();
    }

    /**
     * @When /^I click the edit single assessor feedback button$/
     */
    public function i_click_the_edit_single_feedback_button() {
        /**
         * @var mod_coursework_behat_single_grading_interface $page
         */
        $page = $this->get_page('single grading interface');
        $page->click_edit_feedback_button($this->student);
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
        $feedback->stage_identifier = 'assessor_1';
        $feedback->feedbackcomment = 'New comment here';
        $feedback->grade = 67;

        $generator->create_feedback($feedback);

        $feedback = new stdClass();
        $feedback->assessorid = $this->otherteacher->id;
        $feedback->submissionid = $this->submission->id;
        $feedback->stage_identifier = 'assessor_2';
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
        $feedback->stage_identifier = 'assessor_1';
        $feedback->feedbackcomment = 'New comment here';
        $feedback->grade = 67;
        $feedback->finalised = $draft ? 0 : 1;

        $generator->create_feedback($feedback);

        $feedback = new stdClass();
        $feedback->assessorid = $this->otherteacher->id;
        $feedback->submissionid = $this->submission->id;
        $feedback->stage_identifier = 'assessor_2';
        $feedback->feedbackcomment = 'New comment here';
        $feedback->grade = 63;
        $feedback->finalised = $draft ? 0 : 1;

        $generator->create_feedback($feedback);
    }

    /**
     * @Then /^I should( not)? see the final grade(?: as )?(\d*\.?\d+)? on the multiple marker page$/
     * @param bool $negate
     * @param float $grade
     * @throws ExpectationException
     * @throws coding_exception
     */
    public function i_should_see_the_final_multiple_grade_on_the_page($negate = false, $grade = 56) {
        try {
            $grade = count($this->find_all('xpath', $this->xpath_tag_class_contains_text('td', 'multiple_agreed_grade_cell', $grade)));
        } catch(Exception $e) {
            $grade = false;
        }
        $ishouldseegrade = $negate == false;
        $ishouldnotseegrade = $negate == true;
        if (!$grade && $ishouldseegrade) {
            throw new ExpectationException('Could not find the final grade', $this->getSession());
        } else {
            if ($grade && $ishouldnotseegrade) {
                throw new ExpectationException('Grade found, but there should be none', $this->getSession());
            }
        }
    }

    /**
     * @Then /^I should see the final grade(?: as )?(\d+)? on the single marker page$/
     * @param int $grade
     * @throws ExpectationException
     * @throws coding_exception
     */
    public function i_should_see_the_final_single_grade_on_the_page($grade = 56) {
        $actualgrade = $this->find('css', 'td.single_assessor_feedback_cell')->getText();
        if (strpos($actualgrade, (string)$grade) === false) {
            throw new ExpectationException('Could not find the final grade. Got '.$actualgrade.' instead', $this->getSession());
        }
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
        $feedback->stage_identifier = 'assessor_1';
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
        $actual = strip_tags(behat_base::evaluate_script_in_session($this->getSession(), $script));
        if ($actual != $expectedvalue) {
            throw new ExpectationException("Expected comment '$expectedvalue' got '$actual'", $this->getSession());
        }
    }

    /**
     * @Given /^I click on the edit feedback icon$/
     */
    public function i_click_on_the_edit_feedback_icon() {

        if ($this->running_javascript()) {
            $this->wait_for_seconds(10);
        }

        $this->find('css', "#edit_feedback_{$this->get_feedback()->id}")->click();

        if ($this->running_javascript()) {
            $this->wait_for_seconds(10);
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
        // $xpath = $this->xpath_tag_class_contains_text('td', 'cfeedbackcomment', '56');
        // if (!$this->getSession()->getPage()->has('xpath', $xpath)) {
        // throw new ExpectationException('Should have seen the grade ("56"), but it was not there',
        // $this->getSession());
        // }
    }

    /**
     * @Then /^I should see the rubric grade on the page$/
     */
    public function i_should_see_the_rubric_grade_on_the_page() {
        $celltext = $this->find('css', '#final_feedback_grade')->getText();
        if (strpos($celltext, '50') === false) {
            throw new ExpectationException(
                "Expected rubric grade 50 got '$celltext'",
               $this->getSession()
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
            throw new ExpectationException("Expected commennt '$comment' got '$celltext'", $this->getSession());
        }
    }

    /**
     * @When /^I grade the submission(?: as )?(\d+)?( without comments)? using the simple form$/
     *
     * @param int $grade
     * @throws Behat\Mink\Exception\ElementException
     * @throws Behat\Mink\Exception\ElementNotFoundException
     */
    public function i_grade_the_submission_using_the_simple_form($grade = 56, $withoutcomments = false) {
        $nodeelement = $this->getSession()->getPage()->findById('feedback_grade');
        if ($nodeelement) {
            $nodeelement->selectOption($grade);
        }

        if (empty($withoutcomments)) {
            $nodeelement1 = $this->find('css', '#feedback_comment');
            if ($nodeelement1) {
                $nodeelement1->setValue('New comment here');
            }
        }

        $this->getSession()->getPage()->findButton('submitbutton')->press();

        $this->feedback = feedback::last();
    }

    /**
     * Launch the grade submission modal and complete with grade/comment.
     * @When /^I grade the submission(?: as )?(\d*\.?\d+)? using the ajax form(?: with comment "(?P<comment_string>(?:[^"]|\\")*)")?$/
     *
     * @param float $grade
     * @param string $comment
     * @throws Behat\Mink\Exception\ElementException
     * @throws Behat\Mink\Exception\ElementNotFoundException
     */
    public function i_grade_the_submission_using_the_ajax_form($grade = 56, $comment = "New comment") {
        // Form loaded and sent by AJAX now so wait for it to load.
        $this->wait_for_pending_js();
        $this->wait_for_seconds(1);
        $this->execute('behat_forms::i_set_the_field_to', [$this->escape("Grade"), $grade]);
        self::i_set_the_feedback_comment_to($comment);
        $this->wait_for_pending_js();
        $this->execute(
            'behat_general::i_click_on', [get_string('saveandfinalise', 'coursework'), 'button']
        );
        $this->wait_for_pending_js();
        $this->wait_for_seconds(2);
        $this->assertSession()->pageTextContains(get_string('alert_feedback_save_successful', 'coursework'));
        $this->feedback = feedback::last();
    }

    /**
     * @Given /^I set the feedback comment to "(?P<comment_string>(?:[^"]|\\")*)"$/
     * @param string $comment
     * @throws coding_exception
     */
    public function i_set_the_feedback_comment_to(string $comment) {
        $script = "document.querySelector('textarea#id_feedbackcomment').value = '$comment'";
        behat_base::execute_script_in_session($this->getSession(), $script);
    }

    /**
     * Complete a rubric form.
     * @Given /^I click the rubric score box "(\d+)?" and add the comment "(?P<comment_string>(?:[^"]|\\")*)"$/
     * @param string $boxnumber
     * @param string $comment
     * @throws coding_exception
     */
    public function i_click_the_rubric_box_and_set_comment($boxnumber, $comment) {
        $script = "document.querySelectorAll('#rubric-advancedgrading input[type=\"radio\"]')[" . $boxnumber . "].click();";
        behat_base::execute_script_in_session($this->getSession(), $script);

        $script = "(document.querySelector('td.remark textarea')).value = '" . $comment . "';";
        behat_base::execute_script_in_session($this->getSession(), $script);
    }

    /**
     * Expand the row in the grading form to expose feedback button.
     * @When /^I expand the coursework grading row ?(\d+)?$/
     * @return void
     */
    public function i_expand_the_grading_row(int $rownumber = 1) {
        $this->execute(
            'behat_general::i_click_on', [".details-control.row-$rownumber", 'css_element']
        );
        $this->wait_for_pending_js();
        $this->wait_for_seconds(1);
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
            throw new ExpectationException($message, $this->getSession());
        };
    }

    /**
     * @Given /^I should see the group grade assigned to the other student$/
     */
    public function i_should_see_the_group_grade_assigned_to_the_other_student() {
        /**
         * @var mod_coursework_behat_multiple_grading_interface $page
         */
        $page = $this->get_page('multiple grading interface');
        if ($page->student_has_a_final_grade($this->otherstudent)) {
            throw new ExpectationException(
                $message = "Should be a grade in the student row final grade cell, but there's not",
                $this->getSession()
            );
        }
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
                "Expected the final grade to be '45', but got '{$visiblegrade}'", $this->getSession()
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
                "Expected the feedback to be 'blah', but got '{$visiblefeedback}'", $this->getSession()
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
            throw new ExpectationException("Expected grade '45' found '$grade'", $this->getSession());
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
            throw new ExpectationException("Expected grade '$grade' found '$actual'", $this->getSession());
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
        if ($gradinginterface->there_is_a_feedback_icon($this->student)) {
            throw new ExpectationException('Feedback link is present', $this->getSession());
        };
    }

    // General web steps

    /**
     * @Given /^I visit the ([\w ]+) page$/
     * @param $pathname
     */
    public function visit_page($pathname) {
        $this->getSession()->visit($this->locate_path($pathname, false));
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

        $currenturl = $this->getSession()->getCurrentUrl();
        $currentanchor = parse_url($currenturl, PHP_URL_FRAGMENT);
        $currenturlwithoutanchor = str_replace('#' . $currentanchor, '', $currenturl);

        $desiredurl = $this->locate_path($pagename, false);

        // Strip the params if we need to. Can be handy if we have unpredictable landing page e.g. after create there will
        // possibly be a new id in there.
        if ($ignoreparams) {
            $currentpath = parse_url($currenturl, PHP_URL_PATH);
            $message = "Should be on the " . $desiredurl . " page but instead the url is " . $currentpath;
            if ($currentpath != $desiredurl) {
                throw new ExpectationException($message, $this->getSession());
            }
        } else {
            $message = "Should be on the " . $desiredurl . " page but instead the url is " . $currenturlwithoutanchor;
            if ($currenturlwithoutanchor != $desiredurl) {
                throw new ExpectationException($message, $this->getSession());
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

        $htmldata = $this->getSession()->getDriver()->getContent();
        $fileandpath = $CFG->dataroot . '/temp/'.$filename;
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
        $this->getSession()->wait($seconds * 1000);
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

        $finalised = $DB->get_field('coursework_submissions', 'finalised', ['id' => $this->submission->id]);
        if ($negate && $finalised == 1) {
            throw new ExpectationException('Submission is finalised and should not be', $this->getSession());
        } else if (!$negate && $finalised == 0) {
            throw new ExpectationException('Submission is not finalised and should be', $this->getSession());
        }
    }

    /**
     * @Then /^I should( not)? see the student\'s submission on the page$/
     * @param bool $negate
     */
    public function i_should_see_the_student_s_submission_on_the_page($negate = false) {
        $fields =
            $this->getSession()->getPage()->findAll('css', ".submission-{$this->submission->id}");
        $countfields = count($fields);
        if ($countfields == 0 && !$negate) {
            throw new ExpectationException('Student submission is not on page and should be', $this->getSession());
        } else if ($negate && $countfields > 0) {
            throw new ExpectationException('Student submission is on page and should not be', $this->getSession());
        }
    }

    /**
     * @Given /^the submission is finalised$/
     */
    public function the_submission_is_finalised() {
        $this->submission->finalised = 1;
        $this->submission->save();
    }

    /**
     * @Then /^the file upload button should not be visible$/
     */
    public function the_file_upload_button_should_not_be_visible() {
        $button = $this->find('css', 'div.fp-btn-add a');
        if ($button->isVisible()) {
            throw new ExpectationException("The file picker upload button should be hidden, but it isn't", $this->getSession());
        }
    }

    /**
     * @Given /^I click on the (\w+) submission button$/
     * @param $action
     */
    public function i_click_on_the_new_submission_button($action) {
        /**
         * @var mod_coursework_behat_student_page $page
         */
        //        $page = $this->get_page('student page');
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
        $page = $this->getSession()->getPage();
        $inputtype = $page->find('xpath', $locator ."//input[@type='submit']");
        $buttontype = $page->find('xpath',  $locator ."//button[@type='submit']");

        // Check how element was created and use it to find the button.
        $button = ($inputtype !== null) ? $inputtype : $buttontype;

        if (!$button) {
            throw new ExpectationException(
                "Button not found ($action): " . $button->getXpath(), $this->getSession()
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
        $link = $this->getSession()->getPage()
            ->findAll('xpath', "//a[@class='btn btn-primary btn-block'][text()='" . ucfirst($action) . " your submission']");
        $button = $this->getSession()->getPage()
            ->findAll('xpath', "//button[text()='" . ucfirst($action) . " your submission']");
        $buttons = ($link) ? $link : $button;// check how element was created and use it to find the button
        $countbuttons = count($buttons);

        $countbuttons = count($buttons);
        if ($countbuttons > 0 && $negate) {
            throw new ExpectationException('I see the button when I should not', $this->getSession());
        } else if ($countbuttons == 0 && !$negate) {
            throw new ExpectationException('I do not see the button when I should', $this->getSession());
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
     * @Given /^there is (a|another|an) (teacher|editing teacher|editingteacher|manager|student)$/
     * @param $other
     * @param $rolename
     * @throws coding_exception
     */
    public function there_is_another_teacher($other, $rolename) {

        $other = ($other == 'another');

        $rolename = str_replace(' ', '', $rolename);

        $rolenametosave = $other ? 'other' . $rolename : $rolename;

        $this->$rolenametosave = $this->create_user($rolename, $rolenametosave);
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

        $generator->enrol_user($user->id,
                               $this->course->id,
                               $roleid);

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
            throw new ExpectationException('I do not see the feedback release date', $this->getSession());
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
            throw new ExpectationException('I see the feedback release date when I should not', $this->getSession());
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
        if($page->general_feedback_date_present()) {
            throw new ExpectationException('I see the general feedback release date when I should not', $this->getSession());
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
                'I do not see the general feedback release date when I should', $this->getSession()
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
            throw new ExpectationException('Comment not found', $this->getSession());
        }
        if (!$page->has_grade('67')) {
            throw new ExpectationException('Grade 67 not found', $this->getSession());
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
            'stage_identifier' => 'assessor_1',
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
            exec('open -a "Preview.app" ' . $filepath.'/'.$filename);
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
        // $this->find('id', "id_displayallstudentbutton")->click();
        $page = $this->get_page('coursework page');
        // $page->clickLink("Show submissions for other students");

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
            behat_general::wait_for_pending_js_in_session($this->getSession());
            $this->wait_for_seconds(1);
            if ($headingnode) {
                // Expand the div by clicking heading.
                $headingnode->click();
                behat_general::wait_for_pending_js_in_session($this->getSession());
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
            throw new ExpectationException('Student included in sample and should not be', $this->getSession());
        } else if (!$included && !$negate) {
            throw new ExpectationException('Student not included in sample and should be', $this->getSession());
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
        role_change_permission($teacherrole->id,
                               $this->get_coursework()->get_context(),
                               'mod/coursework:administergrades',
                               CAP_ALLOW);
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

        $page = $this->getSession()->getPage();
        $due = $page->find('xpath', "//h3[text() = '$date']/following-sibling::p[starts-with(text(), '$value')]");

        if (!$due) {
            throw new ExpectationException('Should have seen due date, but it was not there',
            $this->getSession());
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
        \mod_coursework\models\personal_deadline::create([
           'allocatableid' => $this->student->id(),
           'allocatabletype' => 'user',
           'courseworkid' => $this->coursework->id,
           'personal_deadline' => $value,
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
        $page = $this->getSession()->getPage();
        $match = $page->find('xpath', "//li[normalize-space(string()) = 'Status $status']");

        if (!$match) {
            throw new ExpectationException('Should have seen expected submission status, but it was not there',
            $this->getSession());
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
        $page = $this->getSession()->getPage();
        $match = $page->find('xpath', "//li[normalize-space(string()) = 'Mark $mark']");

        if (!$match) {
            throw new ExpectationException('Should have seen expected mark, but it was not there',
            $this->getSession());
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
           'stage_identifier' => "assessor_$stage",
        ]);
    }

    /**
     * Take screenshot when step fails. Works only with Selenium2Driver.
     *
     * Screenshot is saved at [Date]/[Feature]/[Scenario]/[Step].jpg .
     *
     * @AfterStep
     * @param \Behat\Behat\Event\StepEvent $event
     */
    // public function take_screenshot_after_failed_step(Behat\Behat\Event\StepEvent $event) {
    // if ($event->getResult() === Behat\Behat\Event\StepEvent::FAILED) {
    //
    // $step = $event->getStep();
    // $path = array(
    // 'date' => date("Ymd-Hi"),
    // 'feature' => $step->getParent()->getFeature()->getTitle(),
    // 'scenario' => $step->getParent()->getTitle(),
    // 'step' => $step->getType() . ' ' . $step->getText(),
    // );
    // $path = preg_replace('/[^\-\.\w]/', '_', $path);
    // $filename = implode($path);
    //
    // $driver = $this->getSession()->getDriver();
    // if ($driver instanceof Behat\Mink\Driver\Selenium2Driver) {
    // $filename .= '_screenshot.jpg';
    // $this->show_me_a_screenshot($filename);
    // } else {
    // $filename .= '_page.html';
    // $this->show_me_the_page($filename);
    // }
    // }
    // }
}
