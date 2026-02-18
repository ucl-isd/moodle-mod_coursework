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

use core\exception\moodle_exception;
use core\output\notification;
use core_user;
use Exception;
use mod_coursework\ability;
use mod_coursework\auto_grader\auto_grader;
use mod_coursework\exceptions\access_denied;
use mod_coursework\forms\assessor_feedback_mform;
use mod_coursework\models\feedback;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use moodle_url;

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
        $teacherfeedback = new feedback($this->params['feedbackid']);

        $ability = new ability($USER->id, $this->coursework);
        $ability->require_can('show', $teacherfeedback);
        $renderer = $this->get_page_renderer();
        echo $renderer->show_feedback_page($teacherfeedback);
    }

    protected function viewpdf() {
        global $PAGE, $USER;

        if (empty($this->coursework->enablepdfjs())) {
            throw new Exception('coursework enablepdfjs not enabled');
        }

        $urlparams = ['submissionid' => $this->params['submissionid']];
        $PAGE->set_url('/mod/coursework/actions/feedbacks/viewpdf.php', $urlparams);

        $ability = new ability($USER->id, $this->coursework);
        $submission = submission::find($this->params['submissionid']);

        if ($ability->cannot('show', $submission)) {
            return false;
        }

        $renderer = $this->get_page_renderer();
        echo $renderer->show_viewpdf_page($submission);
    }

    /**
     * This deals with the page that the assessors see when they want to add component feedbacks.
     *
     * @throws \moodle_exception
     */
    protected function new_feedback() {
        global $PAGE, $USER, $DB;

        $teacherfeedback = new feedback();
        $teacherfeedback->submissionid = $this->params['submissionid'];
        $teacherfeedback->assessorid = $this->params['assessorid'];
        $teacherfeedback->isfinalgrade = $this->params['isfinalgrade'];
        $teacherfeedback->ismoderation = $this->params['ismoderation'];
        $teacherfeedback->stageidentifier = $this->params['stageidentifier'];
        $teacherfeedback->courseworkid = $this->params['courseworkid'];

        $coursework = $teacherfeedback->get_coursework();

        if (
            feedback::exists([
            'submissionid' => $teacherfeedback->submissionid,
            'stageidentifier' => $teacherfeedback->stageidentifier,
            ])
        ) {
            if ($this->space_for_another_feedback($teacherfeedback)) {
                $teacherfeedback->stageidentifier = $this->next_available_stage($teacherfeedback);
            } else {
                redirect(
                    new moodle_url('/mod/coursework/view.php', ['id' => $coursework->get_course_module()->id]),
                    get_string('anotheruseralreadysubmittedfeedback', 'mod_coursework')
                );
            }
        }

        $ability = new ability($USER->id, $this->coursework);
        if (!$ability->can('new', $teacherfeedback)) {
            throw new access_denied($this->coursework, $ability->get_last_message());
        }
        $this->check_stage_permissions($this->params['stageidentifier']);

        $urlparams = [];
        $urlparams['submissionid'] = $teacherfeedback->submissionid;
        $urlparams['assessorid'] = $teacherfeedback->assessorid;
        $urlparams['isfinalgrade'] = $teacherfeedback->isfinalgrade;
        $urlparams['ismoderation'] = $teacherfeedback->ismoderation;
        $urlparams['stageidentifier'] = $teacherfeedback->stageidentifier;
        $PAGE->set_url('/mod/coursework/actions/feedbacks/new.php', $urlparams);

        // auto-populate Agreed Feedback with comments from initial marking
        if ($coursework && $coursework->autopopulatefeedbackcomment_enabled() && $teacherfeedback->stageidentifier == 'final_agreed_1') {
            // get all initial stages feedbacks for this submission
            $initialfeedbacks = $DB->get_records('coursework_feedbacks', ['submissionid' => $teacherfeedback->submissionid]);

            $count = 1;
            $feedbackcomments = [];
            // put all initial feedbacks together for the comment field
            foreach ($initialfeedbacks as $initialfeedback) {
                $feedbackcomments[] = get_string('markercomments', 'mod_coursework', $count) . $initialfeedback->feedbackcomment;
                $count++;
            }

            $teacherfeedback->feedbackcomment = ['text' => implode('<br/>', $feedbackcomments)];
        }

        $submiturl = $this->get_router()->get_path('create feedback', ['feedback' => $teacherfeedback]);
        $simpleform = new assessor_feedback_mform($submiturl, ['feedback' => $teacherfeedback]);
        $simpleform->set_data($teacherfeedback);

        $renderer = $this->get_page_renderer();
        $renderer->edit_feedback_page($teacherfeedback, $simpleform);
    }

    /**
     * This deals with the page that the assessors see when they want to add component feedbacks.
     *
     * @throws moodle_exception
     */
    protected function edit_feedback() {
        global $PAGE, $USER;

        $teacherfeedback = new feedback($this->params['feedbackid']);
        $this->check_stage_permissions($teacherfeedback->stageidentifier);

        $ability = new ability($USER->id, $this->coursework);
        $ability->require_can('edit', $teacherfeedback);

        $urlparams = ['feedbackid' => $this->params['feedbackid']];
        $PAGE->set_url('/mod/coursework/actions/feedbacks/edit.php', $urlparams);

        $teacherfeedback->grade = is_numeric($teacherfeedback->grade)
            ? format_float($teacherfeedback->grade, $this->coursework->get_grade_item()->get_decimals())
            : null;

        $teacherfeedback->feedbackcomment = [
            'text' => $teacherfeedback->feedbackcomment,
            'format' => $teacherfeedback->feedbackcommentformat,
        ];

        // Load any files into the file manager.
        $teacherfeedback->feedback_manager = file_get_submitted_draft_itemid('feedback_manager');
        file_prepare_draft_area(
            $teacherfeedback->feedback_manager,
            $teacherfeedback->get_context()->id,
            'mod_coursework',
            'feedback',
            $teacherfeedback->id
        );

        $submiturl = $this->get_router()->get_path('update feedback', ['feedback' => $teacherfeedback]);
        $simpleform = new assessor_feedback_mform($submiturl, ['feedback' => $teacherfeedback]);
        $simpleform->set_data($teacherfeedback);

        $renderer = $this->get_page_renderer();
        $renderer->edit_feedback_page($teacherfeedback, $simpleform);
    }

    /**
     * Saves the new feedback form for the first time.
     */
    protected function create_feedback() {

        global $USER, $PAGE;

        $this->check_stage_permissions($this->params['stageidentifier']);

        $teacherfeedback = new feedback();
        $teacherfeedback->submissionid = $this->params['submissionid'];
        $teacherfeedback->assessorid = $this->params['assessorid'];
        $teacherfeedback->isfinalgrade = $this->params['isfinalgrade'];
        $teacherfeedback->ismoderation = $this->params['ismoderation'];
        $teacherfeedback->stageidentifier = $this->params['stageidentifier'];
        $teacherfeedback->lasteditedbyuser = $USER->id;
        $teacherfeedback->finalised = $this->params['finalised'] ? 1 : 0;

        $submission = submission::find($this->params['submissionid']);
        $pathparams = [
            'submission' => $submission,
            'assessor' => core_user::get_user($this->params['assessorid']),
            'stage' => $teacherfeedback->get_stage(),

        ];
        $url = $this->get_router()->get_path('new feedback', $pathparams, true);
        $PAGE->set_url($url);

        $conditions = ['submissionid' => $this->params['submissionid'],
                            'stageidentifier' => $this->params['stageidentifier']];
        if (feedback::exists($conditions)) {
            if ($this->space_for_another_feedback($teacherfeedback)) {
                $teacherfeedback->stageidentifier = $this->next_available_stage($teacherfeedback);
            } else {
                $form = new assessor_feedback_mform(null, ['feedback' => $teacherfeedback]);
                $renderer = $this->get_page_renderer();
                $renderer->edit_feedback_page($teacherfeedback, $form);
                return;
            }
        }

        $ability = new ability($USER->id, $this->coursework);
        $ability->require_can('create', $teacherfeedback);

        $form = new assessor_feedback_mform(null, ['feedback' => $teacherfeedback]);

        $courseworkpageurl = $this->get_path('coursework', ['coursework' => $teacherfeedback->get_coursework()]);
        if ($form->is_cancelled()) {
            redirect($courseworkpageurl, get_string('cancelled'), null, notification::NOTIFY_SUCCESS);
        }

        if ($form->get_data()) {
            $teacherfeedback->save(); // Need an id so we can save the advanced grading here.

            $teacherfeedback = $form->process_data();

            $teacherfeedback->save();

            $form->save_feedback_files();

            if ($submission->is_published()) { // Keep the gradebook updated
                $this->coursework->grade_changed_event();
                $submission->publish();
            }

            $this->try_auto_feedback_creation($teacherfeedback->get_submission());

            redirect(
                $courseworkpageurl,
                get_string('changessaved', 'mod_coursework'),
                null,
                notification::NOTIFY_SUCCESS
            );
        } else {
            $renderer = $this->get_page_renderer();
            $renderer->edit_feedback_page($teacherfeedback, $form);
        }
    }

    /**
     * Updates the feedback.
     */
    protected function update_feedback() {
        global $USER, $PAGE;

        $PAGE->set_url(new moodle_url('/mod/coursework/actions/feedbacks/update.php', $this->params));
        $teacherfeedback = new feedback($this->params['feedbackid']);
        $teacherfeedback->lasteditedbyuser = $USER->id;
        $teacherfeedback->finalised = $this->params['finalised'] ? 1 : 0;

        $ability = new ability($USER->id, $this->coursework);
        $ability->require_can('update', $teacherfeedback);
        $courseworkpageurl = $this->get_path('coursework', ['coursework' => $teacherfeedback->get_coursework()]);

        // remove feedback comments and associated feedback files if 'Remove feedback' button pressed
        if ($this->params['remove']) {
            if (!$this->params['confirm']) {
                $urlparams = ['confirm' => $this->params['confirm'],
                    'remove' => $this->params['remove'], 'feedbackid' => $this->params['feedbackid'], 'finalised' => $this->params['finalised']];

                $PAGE->set_url('/mod/coursework/actions/feedbacks/edit.php', $urlparams);

                // Ask the user for confirmation.
                $confirmurl = new moodle_url('/mod/coursework/actions/feedbacks/update.php');
                $confirmurl->param('confirm', 1);
                $confirmurl->param('removefeedbackbutton', 1);
                $confirmurl->param('feedbackid', $this->params['feedbackid']);
                $confirmurl->param('finalised', $this->params['finalised']);

                $cancelurl = clone $PAGE->url;
                $cancelurl->param('removefeedbackbutton', 0);
                $cancelurl->param('feedbackid', $this->params['feedbackid']);
                $cancelurl->param('finalised', $this->params['finalised']);
                $this->get_page_renderer()->confirm_feedback_removal_page($teacherfeedback, $confirmurl);
                return;
            } else {
                feedback::remove_cache($teacherfeedback->get_courseworkid());
                submission::remove_cache($teacherfeedback->get_courseworkid());

                // Remove associated files.
                $fs = get_file_storage();
                $fs->delete_area_files(
                    $teacherfeedback->get_coursework()->get_context()->id,
                    'mod_coursework',
                    'feedback',
                    $teacherfeedback->id()
                );

                $teacherfeedback->destroy();
                redirect($courseworkpageurl, get_string('deleted'), null, notification::NOTIFY_ERROR);
            }
        }

        $this->check_stage_permissions($teacherfeedback->stageidentifier);

        $form = new assessor_feedback_mform(null, ['feedback' => $teacherfeedback]);

        $courseworkpageurl = $this->get_path('coursework', ['coursework' => $teacherfeedback->get_coursework()]);
        if ($form->is_cancelled()) {
            redirect($courseworkpageurl, get_string('cancelled'), null, notification::NOTIFY_SUCCESS);
        } else if ($form->get_data()) {
            $teacherfeedback = $form->process_data();

            $teacherfeedback->save();
            $form->save_feedback_files();

            $this->try_auto_feedback_creation($teacherfeedback->get_submission());

            if ($teacherfeedback->get_submission()->is_published()) { // Keep the gradebook updated
                $this->coursework->grade_changed_event();
                $teacherfeedback->get_submission()->publish();
            }
            redirect(
                $courseworkpageurl,
                get_string('changessaved', 'mod_coursework'),
                null,
                notification::NOTIFY_SUCCESS
            );
        } else {
            // Grade validation error - redisplay form with messages.
            $renderer = $this->get_page_renderer();
            $renderer->edit_feedback_page($teacherfeedback, $form);
            die();
        }
    }

    /**
     * Get any feedback-specific stuff.
     */
    protected function prepare_environment() {
        global $DB;

        if (!empty($this->params['feedbackid'])) {
            $feedback = $DB->get_record(
                'coursework_feedbacks',
                ['id' => $this->params['feedbackid']],
                '*',
                MUST_EXIST
            );
            $this->feedback = new feedback($feedback);
            $this->params['courseworkid'] = $this->feedback->get_coursework()->id;
        }

        if (!empty($this->params['submissionid'])) {
            $submission = $DB->get_record(
                'coursework_submissions',
                ['id' => $this->params['submissionid']],
                '*',
                MUST_EXIST
            );
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
     * Check permissions.
     * @param string $identifier
     * @throws \coding_exception
     * @throws access_denied
     */
    protected function check_stage_permissions($identifier) {
        global $USER;

        $stage = $this->coursework->get_stage($identifier);
        if (
            !$stage->user_is_assessor($USER->id)
            &&
            !has_any_capability(
                ['mod/coursework:administergrades', 'mod/coursework:addallocatedagreedgrade'],
                $this->coursework->get_context()
            )
        ) {
            throw new access_denied(
                $this->coursework,
                'You are not authorised to add feedback at this stage'
            );
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
     * @return string
     * @throws \dml_exception
     */
    private function next_available_stage($feedback) {
        global $DB;
        // get count of feedbacks that already exist
        $sql = "SELECT COUNT(*) as total
                FROM {coursework_feedbacks}
                WHERE submissionid = $feedback->submissionid
                AND stageidentifier <> 'final_agreed_1'";

        $usedstages = $DB->get_record_sql($sql);
        $newstage = $usedstages->total + 1;
        return 'assessor_' . $newstage;
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
        $autograder = new $autofeedbackclassname(
            $this->coursework,
            $submission->get_allocatable()
        );
        $autograder->create_auto_grade_if_rules_match();
    }
}
