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

namespace mod_coursework\controllers;

use mod_coursework\ability;
use mod_coursework\auto_grader\auto_grader;
use mod_coursework\exceptions\access_denied;
use mod_coursework\forms\assessor_feedback_mform;
use html_writer;
use mod_coursework\models\feedback;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use mod_coursework\models\group;
use mod_coursework\models\coursework;
use mod_coursework\assessor_feedback_row;
use mod_coursework\decorators\coursework_groups_decorator;
use mod_coursework\grading_table_row_multi;
use mod_coursework\grading_table_row_single;
use mod_coursework\render_helpers\grading_report\cells\grade_for_gradebook_cell;
use mod_coursework\render_helpers\grading_report\cells\multiple_agreed_grade_cell;
use mod_coursework\render_helpers\grading_report\cells\single_assessor_feedback_cell;
use mod_coursework\render_helpers\grading_report\sub_rows\multi_marker_feedback_sub_rows;
use mod_coursework\stages\assessor;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL' || die());

global $CFG;

require_once($CFG->dirroot . '/lib/adminlib.php');
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/mod/coursework/renderer.php');

/**
 * Class mod_coursework_controller controls the page generation for all of the pages in the coursework module.
 *
 * It is the beginning of the process of tidying things up to make them a bit more MVC where possible.
 *
 */
class feedback_controller extends controller_base {

    /**
     * @var feedback
     */
    protected $feedback;

    protected function show_feedback() {
        global $PAGE, $USER;

        $urlparams = ['feedbackid' => $this->params['feedbackid']];
        $PAGE->set_url('/mod/coursework/actions/feedbacks/show.php', $urlparams);
        $ajax = (isset($this->params['ajax'])) ? $this->params['ajax'] : 0;

        $teacherfeedback = new feedback($this->params['feedbackid']);

        $user = user::find($USER);
        if ($user) {
            $ability = new ability($user, $this->coursework);
            $ability->require_can('show', $teacherfeedback);

            $renderer = $this->get_page_renderer();
            $html = $renderer->show_feedback_page($teacherfeedback, (bool)$ajax);

            if (empty($ajax)) {
                echo $html;
            } else {
                echo json_encode(['success' => true, 'formhtml' => $html]);
            }
        }
    }

    /**
     * This deals with the page that the assessors see when they want to add component feedbacks.
     *
     * @throws \moodle_exception
     */
    protected function new_feedback() {

        global $PAGE, $USER;

        $teacherfeedback = new feedback();
        $teacherfeedback->submissionid = $this->params['submissionid'];
        $teacherfeedback->assessorid = $this->params['assessorid'];
        $teacherfeedback->isfinalgrade = $this->params['isfinalgrade'];
        $teacherfeedback->ismoderation = $this->params['ismoderation'];
        $teacherfeedback->stage_identifier = $this->params['stage_identifier'];
        $teacherfeedback->courseworkid = $this->params['courseworkid'];

        $conditions = ['submissionid' => $this->params['submissionid'],
                            'stage_identifier' => $this->params['stage_identifier']];
        if (feedback::exists($conditions)) {
            if ($this->space_for_another_feedback($teacherfeedback)) {
                $teacherfeedback->stage_identifier = $this->next_available_stage($teacherfeedback);
            }
        }

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('new', $teacherfeedback);

        $this->check_stage_permissions($this->params['stage_identifier']);

        $urlparams = [];
        $urlparams['submissionid'] = $teacherfeedback->submissionid;
        $urlparams['assessorid'] = $teacherfeedback->assessorid;
        $urlparams['isfinalgrade'] = $teacherfeedback->isfinalgrade;
        $urlparams['ismoderation'] = $teacherfeedback->ismoderation;
        $urlparams['stage_identifier'] = $teacherfeedback->stage_identifier;
        $PAGE->set_url('/mod/coursework/actions/feedbacks/new.php', $urlparams);

        $renderer = $this->get_page_renderer();
        $renderer->new_feedback_page($teacherfeedback, $this->params['ajax']);

    }

    /**
     * This deals with the page that the assessors see when they want to add component feedbacks.
     *
     * @throws moodle_exception
     */
    protected function edit_feedback() {

        global $DB, $PAGE, $USER;

        $teacherfeedback = new feedback($this->params['feedbackid']);
        $this->check_stage_permissions($teacherfeedback->stage_identifier);

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('edit', $teacherfeedback);

        $urlparams = ['feedbackid' => $this->params['feedbackid']];
        $PAGE->set_url('/mod/coursework/actions/feedbacks/edit.php', $urlparams);

        $assessor = $DB->get_record('user', ['id' => $teacherfeedback->assessorid]);
        if (!empty($teacherfeedback->lasteditedbyuser)) {
            $editor = $DB->get_record('user', ['id' => $teacherfeedback->lasteditedbyuser]);
        } else {
            $editor = $assessor;
        }

        $renderer = $this->get_page_renderer();
        $renderer->edit_feedback_page($teacherfeedback, $assessor, $editor, $this->params['ajax']);
    }

    /**
     * Saves the new feedback form for the first time.
     */
    protected function create_feedback() {

        global $USER, $PAGE, $CFG;

        $this->check_stage_permissions($this->params['stage_identifier']);

        $teacherfeedback = new feedback();
        $teacherfeedback->submissionid = $this->params['submissionid'];
        $teacherfeedback->assessorid = $this->params['assessorid'];
        $teacherfeedback->isfinalgrade = $this->params['isfinalgrade'];
        $teacherfeedback->ismoderation = $this->params['ismoderation'];
        $teacherfeedback->stage_identifier = $this->params['stage_identifier'];
        $teacherfeedback->lasteditedbyuser = $USER->id;
        $teacherfeedback->finalised = $this->params['finalised'] ? 1 : 0;

        $submission = submission::find($this->params['submissionid']);
        $pathparams = [
            'submission' => $submission,
            'assessor' => \core_user::get_user($this->params['assessorid']),
            'stage' => $teacherfeedback->get_stage(),

        ];
        $url = $this->get_router()->get_path('new feedback', $pathparams, true);
        $PAGE->set_url($url);

        $conditions = ['submissionid' => $this->params['submissionid'],
                            'stage_identifier' => $this->params['stage_identifier']];
        if (feedback::exists($conditions)) {

            if ($this->space_for_another_feedback($teacherfeedback)) {
                $teacherfeedback->stage_identifier = $this->next_available_stage($teacherfeedback);
            } else {
                $renderer = $this->get_page_renderer();
                $renderer->new_feedback_page($teacherfeedback);
                return;
            }
        }

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('create', $teacherfeedback);

        $form = new assessor_feedback_mform(null, ['feedback' => $teacherfeedback]);

        $courseworkpageurl = $this->get_path('coursework', ['coursework' => $teacherfeedback->get_coursework()]);
        if ($form->is_cancelled()) {
            redirect($courseworkpageurl);
        }

        $ajax = !empty($this->params['ajax']);
        $data = $form->get_data();

        if ($data && $form->validate_grade($data)) {
            $teacherfeedback->save(); // Need an id so we can save the advanced grading here.

            $teacherfeedback = $form->process_data($teacherfeedback);

            $teacherfeedback->save();

            $form->save_feedback_files($teacherfeedback);

            if ($submission->is_published()) { // Keep the gradebook updated
                $this->coursework->grade_changed_event();
                $submission->publish();
            }

            // Only implement auto feedback (automatic agreement) if the settings is set to disabled.
            // Otherwise, we will do this in the cron.
            $gradeeditingtime = $teacherfeedback->get_coursework()->get_grade_editing_time();

            if (empty($gradeeditingtime) || time() > $teacherfeedback->timecreated + $gradeeditingtime) {
                $this->try_auto_feedback_creation($teacherfeedback->get_submission());
            }
            if ($ajax) {
                $coursework = $teacherfeedback->get_coursework();
                $coursework->clear_stage($teacherfeedback->stage_identifier);
                if ($coursework instanceof coursework_groups_decorator) {
                    $coursework = $coursework->wrapped_object();
                }
                // feedback::$pool[$coursework->id] = null;
                $participant = $submission->get_allocatable();
                $cellclass = $this->params['cell_type'];
                $stage = new assessor($coursework, $teacherfeedback->stage_identifier);
                $provisional = new grade_for_gradebook_cell(['coursework' => $coursework]);

                $jsonarray = ['success' => true];

                if (strpos($cellclass, 'multi_marker_feedback_sub_rows') !== false) {
                    $feedbackrow = new assessor_feedback_row($stage, $participant, $this->coursework);
                    $cellobject = new $cellclass($coursework, $participant);
                    $html = $cellobject->get_grade_cell_content($feedbackrow, $this->coursework);

                    if ($teacherfeedback->stage_identifier == 'assessor_1' || $teacherfeedback->stage_identifier == 'assessor_2') {

                        $jsonarray['assessorname'] = (empty($feedbackrow->get_assessor()->id()) && $coursework->allocation_enabled()) ?
                            get_string('assessornotallocated', 'mod_coursework') : $cellobject->profile_link($feedbackrow);
                        $jsonarray['assessdate'] = $cellobject->date_for_column($feedbackrow);

                        if ($teacherfeedback->stage_identifier == 'assessor_1') {
                            $ability = new ability(user::find($USER, false), $coursework);
                            $stage = new assessor($coursework, 'assessor_2');
                            $assessorfeedbackrow = new assessor_feedback_row($stage, $feedbackrow->get_allocatable(), $coursework);

                            $assessortwocell = $cellobject->get_grade_cell_content($assessorfeedbackrow, $coursework, $ability);
                            // $jsonarray['assessortwo'] =$assessortwocell;
                            if (strpos($assessortwocell, 'new_feedback') !== false) {
                                $jsonarray['assessortwo'] = $assessortwocell;
                            }

                        }

                        $finalfeedback = $feedbackrow->get_submission()->get_final_feedback();
                        $finalsubmission = $feedbackrow->get_submission();

                        if ($coursework->automaticagreementrange != 'none' && !empty($finalfeedback) && $finalsubmission->all_inital_graded()) {
                            $finalstage = new assessor($coursework, "final_agreed_1");
                            $finalfeedbackrow = new assessor_feedback_row($finalstage, $participant, $coursework);
                            $agreedgradeobject = new multiple_agreed_grade_cell(['coursework' => $coursework, 'stage' => $finalstage]);
                            $jsonarray['finalhtml'] = $agreedgradeobject->get_table_cell($finalfeedbackrow);
                            $jsonarray['allocatableid'] = $submission->get_allocatable()->id();
                        }

                    } else {

                        $jsonarray['extrahtml'] = $provisional->get_table_cell($feedbackrow);

                    }

                } else {
                    $rowclass = $coursework->has_multiple_markers() ?
                        '\\mod_coursework\\grading_table_row_multi' : '\\mod_coursework\\grading_table_row_single';
                    $rowobject = new $rowclass($coursework, $participant);
                    $cellobject = new $cellclass(['coursework' => $coursework, 'stage' => $stage]);
                    $html = $cellobject->get_content($rowobject);
                    $jsonarray['extrahtml'] = $provisional->get_table_cell($rowobject);

                }

                $jsonarray['html'] = $html;

                echo json_encode($jsonarray);
            } else {
                redirect($courseworkpageurl);
            }
        } else {
            if ($ajax) {
                echo json_encode(['success' => false, 'message' => get_string('guidenotcompleted', 'gradingform_guide')]);
            } else {
                $renderer = $this->get_page_renderer();
                $renderer->new_feedback_page($teacherfeedback);
            }
        }

    }

    /**
     * Saves the new feedback form for the first time.
     */
    protected function update_feedback() {

        global $USER, $PAGE;

        $teacherfeedback = new feedback($this->params['feedbackid']);
        $teacherfeedback->lasteditedbyuser = $USER->id;
        $teacherfeedback->finalised = $this->params['finalised'] ? 1 : 0;

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('update', $teacherfeedback);
        $courseworkpageurl = $this->get_path('coursework', ['coursework' => $teacherfeedback->get_coursework()]);

        // remove feedback comments and associated feedback files if 'Remove feedback' button pressed
        if ($this->params['remove']) {
            if (!$this->params['confirm']) {

                $urlparams = ['confirm' => $this->params['confirm'],
                    'remove' => $this->params['remove'], 'feedbackid' => $this->params['feedbackid'], 'finalised' => $this->params['finalised']];

                $PAGE->set_url('/mod/coursework/actions/feedbacks/edit.php', $urlparams);

                // Ask the user for confirmation.
                $confirmurl = new \moodle_url('/mod/coursework/actions/feedbacks/update.php');
                $confirmurl->param('confirm', 1);
                $confirmurl->param('removefeedbackbutton', 1);
                $confirmurl->param('feedbackid', $this->params['feedbackid']);
                $confirmurl->param('finalised', $this->params['finalised']);

                $cancelurl = clone $PAGE->url;
                $cancelurl->param('removefeedbackbutton', 0);
                $cancelurl->param('feedbackid', $this->params['feedbackid']);
                $cancelurl->param('finalised', $this->params['finalised']);
                $renderer = $this->get_page_renderer();
                return  $renderer->confirm_feedback_removal_page($teacherfeedback, $confirmurl, $cancelurl);

                 // $OUTPUT->confirm(get_string('confirmremovefeedback', 'mod_coursework'), $confirmurl, $PAGE->url);

            } else {
                $teacherfeedback->destroy();
                // Remove associated files
                $fs = get_file_storage();
                $fs->delete_area_files($teacherfeedback->get_coursework()->get_context()->id, 'mod_coursework', 'feedback', $teacherfeedback->id());

                $ajax = !empty($this->params['ajax']);
                if ($ajax) {

                    $coursework = $teacherfeedback->get_coursework();
                    $coursework->clear_stage($teacherfeedback->stage_identifier);
                    if ($coursework instanceof coursework_groups_decorator) {
                        $coursework = $coursework->wrapped_object();
                    }
                    // feedback::$pool[$coursework->id] = null;
                    $submission = $teacherfeedback->get_submission();
                    $participant = $submission->get_allocatable();
                    $cellclass = $this->params['cell_type'];
                    $stage = new assessor($coursework, $teacherfeedback->stage_identifier);
                    if (strpos($cellclass, 'multi_marker_feedback_sub_rows') !== false) {
                        $feedbackrow = new assessor_feedback_row($stage, $participant, $coursework);
                        $cellobject = new $cellclass($coursework, $participant);
                        $html = $cellobject->get_grade_cell_content($feedbackrow, $coursework);
                    } else {
                        $rowclass = $coursework->has_multiple_markers() ?
                            '\\mod_coursework\\grading_table_row_multi' : '\\mod_coursework\\grading_table_row_single';
                        $rowobject = new $rowclass($coursework, $participant);
                        $cellobject = new $cellclass(['coursework' => $coursework, 'stage' => $stage]);
                        $html = $cellobject->get_content($rowobject);

                        $finalfeedback = $rowobject->get_submission()->get_final_feedback();

                    }

                    echo json_encode(['success' => true, 'html' => $html]);
                    exit;
                } else {
                    redirect($courseworkpageurl);
                }
            }
        }

        $this->check_stage_permissions($teacherfeedback->stage_identifier);

        $form = new assessor_feedback_mform(null, ['feedback' => $teacherfeedback]);

        $courseworkpageurl = $this->get_path('coursework', ['coursework' => $teacherfeedback->get_coursework()]);
        if ($form->is_cancelled()) {
            redirect($courseworkpageurl);
        }

        $teacherfeedback = $form->process_data($teacherfeedback);

        $teacherfeedback->save();
        $form->save_feedback_files($teacherfeedback);

        $gradeeditingtime = $teacherfeedback->get_coursework()->get_grade_editing_time();
        if (empty($gradeeditingtime) || time() > $teacherfeedback->timecreated + $gradeeditingtime) {
            $this->try_auto_feedback_creation($teacherfeedback->get_submission());
        }

        if ($teacherfeedback->get_submission()->is_published()) { // Keep the gradebook updated
            $this->coursework->grade_changed_event();
            $teacherfeedback->get_submission()->publish();
        }

        $ajax = !empty($this->params['ajax']);
        if ($ajax) {
            $coursework = $teacherfeedback->get_coursework();
            $coursework->clear_stage($teacherfeedback->stage_identifier);
            if ($coursework instanceof coursework_groups_decorator) {
                $coursework = $coursework->wrapped_object();
            }
            // feedback::$pool[$coursework->id] = null;
            $submission = $teacherfeedback->get_submission();
            $participant = $submission->get_allocatable();
            $cellclass = $this->params['cell_type'];
            $stage = new assessor($coursework, $teacherfeedback->stage_identifier);
            $provisional = new grade_for_gradebook_cell(['coursework' => $coursework]);
            $jsonarray = ['success' => true];

            if (strpos($cellclass, 'multi_marker_feedback_sub_rows') !== false) {
                $feedbackrow = new assessor_feedback_row($stage, $participant, $coursework);
                $cellobject = new $cellclass($coursework, $participant);
                $html = $cellobject->get_grade_cell_content($feedbackrow, $coursework);

                if ($teacherfeedback->stage_identifier == 'assessor_1' || $teacherfeedback->stage_identifier == 'assessor_2') {
                    $jsonarray['assessorname'] = (empty($feedbackrow->get_assessor()->id()) && $coursework->allocation_enabled()) ?
                        get_string('assessornotallocated', 'mod_coursework') : $cellobject->profile_link($feedbackrow);
                    $jsonarray['assessdate'] = $cellobject->date_for_column($feedbackrow);

                    if ($teacherfeedback->stage_identifier == 'assessor_1') {
                        $ability = new ability(user::find($USER, false), $coursework);
                        $stage = new assessor($coursework, 'assessor_2');
                        $assessorfeedbackrow = new assessor_feedback_row($stage, $feedbackrow->get_allocatable(), $coursework);

                        $assessortwocell = $cellobject->get_grade_cell_content($assessorfeedbackrow, $coursework, $ability);
                        // $jsonarray['assessortwo'] =$assessortwocell;
                        if (strpos($assessortwocell, 'new_feedback') !== false) {
                            $jsonarray['assessortwo'] = $assessortwocell;
                        }

                    }

                    $finalfeedback = $submission->get_final_feedback();

                    if ($coursework->automaticagreementrange != 'none' && !empty($finalfeedback)) {
                        $finalstage = new assessor($coursework, "final_agreed_1");

                        $finalfeedbackrowobject = new \mod_coursework\grading_table_row_multi($coursework, $participant);

                        $agreedgradecell = new multiple_agreed_grade_cell(['coursework' => $coursework, 'stage' => $finalstage]);
                        $jsonarray['finalhtml'] = $agreedgradecell->get_content($finalfeedbackrowobject);
                        $jsonarray['allocatableid'] = $submission->get_allocatable()->id();
                    }

                } else {
                    $jsonarray['extrahtml'] = strip_tags($provisional->get_table_cell($feedbackrow));
                }

            } else {
                $rowclass = $coursework->has_multiple_markers() ?
                    '\\mod_coursework\\grading_table_row_multi' : '\\mod_coursework\\grading_table_row_single';
                $rowobject = new $rowclass($coursework, $participant);
                $cellobject = new $cellclass(['coursework' => $coursework, 'stage' => $stage]);
                $html = $cellobject->get_content($rowobject);
                $jsonarray['extrahtml'] = strip_tags($provisional->get_table_cell($rowobject));
            }

            $jsonarray['html'] = $html;

            echo json_encode($jsonarray);
        } else {
            redirect($courseworkpageurl);
        }
    }

    /**
     * Get any feedback-specific stuff.
     */
    protected function prepare_environment() {
        global $DB;

        if (!empty($this->params['feedbackid'])) {
            $feedback = $DB->get_record('coursework_feedbacks',
                                          ['id' => $this->params['feedbackid']],
                                          '*',
                                          MUST_EXIST);
            $this->feedback = new feedback($feedback);
            $this->params['courseworkid'] = $this->feedback->get_coursework()->id;
        }

        if (!empty($this->params['submissionid'])) {
            $submission = $DB->get_record('coursework_submissions',
                                          ['id' => $this->params['submissionid']],
                                          '*',
                                          MUST_EXIST);
            $this->submission = submission::find($submission);
            $this->params['courseworkid'] = $this->submission->courseworkid;
        }

        if (!array_key_exists('isfinalgrade', $this->params)) {
            $this->params['isfinalgrade'] = 0;
        }

        if (!array_key_exists('ismoderation', $this->params)) {
            $this->params['ismoderation'] = 0;
        }

        parent::prepare_environment();
    }

    /**
     * @param string $identifier
     * @throws access_denied
     */
    protected function check_stage_permissions($identifier) {
        global $USER;

        $stage = $this->coursework->get_stage($identifier);
        if (!$stage->user_is_assessor($USER)) {
            if (!(has_capability('mod/coursework:administergrades', $this->coursework->get_context()) ||
                  has_capability('mod/coursework:addallocatedagreedgrade', $this->coursework->get_context())) ) {
                throw new access_denied($this->coursework, 'You are not authorised to add feedback at this stage');
            }
        }
    }

    /**
     * If assessor_1 has been added by another teacher, then can we take this about-to-be-created feedback and
     * make it into assessor_2?
     *
     * @param feedback $feedback
     * @return bool
     */
    private function space_for_another_feedback($feedback) {
        if ($feedback->get_stage()->type() !== 'assessor') {
            return false;
        }

        if (!$this->coursework->has_multiple_markers()) {
            return false;
        }

        if ($this->coursework->allocation_enabled()) {
            return false;
        }

        if (feedback::count(['submissionid' => $feedback->submissionid]) >= $this->coursework->numberofmarkers) {
            return false;
        }

        return true;
    }

    /**
     * @param feedback $feedback
     * @return mixed
     */
    private function next_available_stage($feedback) {
        global $DB;
        // get count of feedbacks that already exist
        $sql = "SELECT COUNT(*) as total
                FROM {coursework_feedbacks}
                WHERE submissionid = $feedback->submissionid
                AND stage_identifier <> 'final_agreed_1'";

        $usedstages = $DB->get_record_sql($sql);
        $newstage = $usedstages->total + 1;
        $stageidentifier = 'assessor_'.$newstage;

        return $stageidentifier;
    }

    /**
     * @param $submission
     */
    protected function try_auto_feedback_creation($submission) {
        // automatic agreement if necessary
        $autofeedbackclassname = '\mod_coursework\auto_grader\\' . $this->coursework->automaticagreementstrategy;
        /**
         * @var auto_grader $auto_grader
         */
        $autograder = new $autofeedbackclassname($this->coursework,
                                                    $submission->get_allocatable());
        $autograder->create_auto_grade_if_rules_match();
    }
}
