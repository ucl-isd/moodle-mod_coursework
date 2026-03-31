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

use coding_exception;
use core_user;
use mod_coursework\ability;
use mod_coursework\exceptions\access_denied;
use mod_coursework\forms\moderator_agreement_mform;
use mod_coursework\models\feedback;
use mod_coursework\models\moderation;
use mod_coursework\models\submission;
use mod_coursework\renderers\grading_report_renderer;
use moodle_exception;
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
class moderations_controller extends controller_base {
    /**
     * @var moderation
     */
    protected $moderation;

    /**
     * @var feedback
     */
    protected $feedback;

    public function __construct($params = []) {
        global $DB;

        if (!empty($params['feedbackid'])) {
            $feedback = $DB->get_record(
                'coursework_feedbacks',
                ['id' => $params['feedbackid']],
                '*',
                MUST_EXIST
            );
            $this->feedback = new feedback($feedback);
            $params['courseworkid'] = $this->feedback->get_coursework()->id;
        }

        if (!empty($params['submissionid'])) {
            $submission = $DB->get_record(
                'coursework_submissions',
                ['id' => $params['submissionid']],
                '*',
                MUST_EXIST
            );
            $this->submission = submission::find($submission);
            $params['courseworkid'] = $this->submission->courseworkid;
        }

        if (!empty($params['moderationid'])) {
            $moderation = $DB->get_record(
                'coursework_mod_agreements',
                ['id' => $params['moderationid']],
                '*',
                MUST_EXIST
            );
            $this->moderation = moderation::find($moderation);
            $params['courseworkid'] = $this->moderation->get_coursework()->id;
        }

        parent::__construct($params);
    }

    /**
     * This deals with the page that the assessors see when they want to add component feedbacks.
     *
     * @throws moodle_exception
     */
    public function new_moderation() {

        global $PAGE, $USER;

        $moderatoragreement = new moderation();
        $moderatoragreement->submissionid = $this->params['submissionid'];
        $moderatoragreement->moderatorid = $this->params['moderatorid'];
        $moderatoragreement->stageidentifier = $this->params['stageidentifier'];
        $moderatoragreement->courseworkid = $this->params['courseworkid'];
        $moderatoragreement->feedbackid = $this->params['feedbackid'];

        $ability = new ability($USER->id, $this->coursework);
        $ability->require_can('new', $moderatoragreement);

        $this->check_stage_permissions($this->params['stageidentifier']);

        $urlparams = [];
        $urlparams['submissionid'] = $moderatoragreement->submissionid;
        $urlparams['moderatorid'] = $moderatoragreement->moderatorid;
        $urlparams['s'] = $moderatoragreement->stageidentifier;
        $urlparams['feedbackid'] = $moderatoragreement->feedbackid;
        $PAGE->set_url('/mod/coursework/actions/moderations/new.php', $urlparams);

        $renderer = $this->get_page_renderer();
        $renderer->edit_moderation_page($moderatoragreement);
    }

    /**
     * This deals with the page that the assessors see when they want to add component moderations.
     *
     * @throws moodle_exception
     */
    public function edit_moderation() {

        global $DB, $PAGE, $USER;

        $moderation = new moderation($this->params['moderationid']);
        $this->check_stage_permissions($moderation->stageidentifier);

        $ability = new ability($USER->id, $this->coursework);
        $ability->require_can('edit', $moderation);

        $urlparams = ['moderationid' => $this->params['moderationid']];
        $PAGE->set_url('/mod/coursework/actions/moderations/edit.php', $urlparams);

        $renderer = $this->get_page_renderer();
        $renderer->edit_moderation_page($moderation);
    }

    /**
     * Saves the new feedback form for the first time.
     */
    public function create_moderation() {
        global $USER, $PAGE;

        $this->check_stage_permissions($this->params['stageidentifier']);

        $moderatoragreement = new moderation();
        $moderatoragreement->submissionid = $this->params['submissionid'];
        $moderatoragreement->moderatorid = $this->params['moderatorid'];
        $moderatoragreement->stageidentifier = $this->params['stageidentifier'];
        $moderatoragreement->lasteditedby = $USER->id;
        $moderatoragreement->feedbackid = $this->params['feedbackid'];

        $submission = submission::get_from_id($this->params['submissionid']);
        $pathparams = [
            'submission' => $submission,
            'moderator' => core_user::get_user($this->params['moderatorid']),
            'stage' => 'moderator',
        ];
        $url = $this->get_router()->get_path('new moderation', $pathparams, true);
        $PAGE->set_url($url);

        $ability = new ability($USER->id, $this->coursework);
        $ability->require_can('new', $moderatoragreement);

        $form = new moderator_agreement_mform(null, ['moderation' => $moderatoragreement]);
        $coursework = $moderatoragreement->get_coursework();
        $courseworkpageurl = new moodle_url(
            '/mod/coursework/view.php', ['id' => $coursework->get_coursemodule_id()], "submission-" . $submission->id()
        );
        if ($form->is_cancelled()) {
            grading_report_renderer::add_notification(
                $coursework->id(),
                $submission->id(),
                get_string('cancelled'),
                \core\notification::INFO
            );
            redirect($courseworkpageurl);
        }

        $data = $form->get_data();

        if ($data) {
            $moderatoragreement = $form->process_data($moderatoragreement);
            $moderatoragreement->save();
            grading_report_renderer::add_notification(
                $coursework->id(),
                $submission->id(),
                get_string('changessaved'),
                \core\notification::SUCCESS
            );
            redirect($courseworkpageurl);
        } else {
            $renderer = $this->get_page_renderer();
            $renderer->new_moderation_page($moderatoragreement);
        }
    }

    /**
     * Saves the new feedback form for the first time.
     */
    public function update_moderation() {
        global $USER;

        $moderatoragreement = new moderation($this->params['moderationid']);
        $moderatoragreement->lasteditedby = $USER->id;

        $ability = new ability($USER->id, $this->coursework);
        $ability->require_can('edit', $moderatoragreement);

        $this->check_stage_permissions($moderatoragreement->stageidentifier);

        $form = new moderator_agreement_mform(null, ['moderation' => $moderatoragreement]);
        $coursework = $moderatoragreement->get_coursework();
        $submission = $moderatoragreement->get_submission();
        $courseworkpageurl = new moodle_url(
            '/mod/coursework/view.php', ['id' => $coursework->get_coursemodule_id()], "submission-" . $submission->id()
        );
        if ($form->is_cancelled()) {
            grading_report_renderer::add_notification(
                $coursework->id(),
                $submission->id(),
                get_string('cancelled'),
                \core\notification::INFO
            );
        } else {
            $moderatoragreement = $form->process_data($moderatoragreement);
            $moderatoragreement->save();
            grading_report_renderer::add_notification(
                $coursework->id(),
                $moderatoragreement->get_submission()->id(),
                get_string('changessaved'),
                \core\notification::SUCCESS
            );
        }
        redirect($courseworkpageurl);
    }

    /**
     * Shows the moderation as 'view only'
     *
     * @throws coding_exception
     * @throws access_denied
     */
    public function show_moderation() {
        global $PAGE, $USER;

        $urlparams = ['moderationid' => $this->params['moderationid']];
        $PAGE->set_url('/mod/coursework/actions/moderations/show.php', $urlparams);

        $moderation = new moderation($this->params['moderationid']);

        $ability = new ability($USER->id, $this->coursework);
        $ability->require_can('show', $moderation);

        $renderer = $this->get_page_renderer();
        $renderer->show_moderation_page($moderation);
    }

    /**
     * @param string $identifier
     * @throws access_denied
     * @throws coding_exception
     */
    private function check_stage_permissions($identifier) {
        global $USER;

        $stage = $this->coursework->get_stage($identifier);
        if (!$stage->user_is_moderator($USER)) {
            if (!(has_capability('mod/coursework:moderate', $this->coursework->get_context()) )) {
                throw new access_denied($this->coursework, 'You are not authorised to moderte this feedback');
            }
        }
    }
}
