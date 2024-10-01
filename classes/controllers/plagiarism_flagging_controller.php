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
use mod_coursework\exceptions\access_denied;
use mod_coursework\forms\plagiarism_flagging_mform;
use mod_coursework\models\plagiarism_flag;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use mod_coursework\models\moderation;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL' || die());

global $CFG;

require_once($CFG->dirroot . '/lib/adminlib.php');
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/mod/coursework/renderer.php');

/**
 * Class plagiarism_flagging_controller controls the page generation for all of the pages in the coursework module.
 *
 * It is the beginning of the process of tidying things up to make them a bit more MVC where possible.
 *
 */
class plagiarism_flagging_controller extends controller_base {

    /**
     * @var plagiarism_flag
     */
    protected $plagiarismflag;

    /**
     * This deals with the page that the assessors see when they want to add component feedbacks.
     *
     * @throws \moodle_exception
     */
    protected function new_plagiarism_flag() {

        global $PAGE, $USER;

        $plagiarismflag = new plagiarism_flag();
        $plagiarismflag->submissionid = $this->params['submissionid'];
        $plagiarismflag->courseworkid = $this->coursework->id;

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('new', $plagiarismflag);

        $urlparams = [];
        $urlparams['submissionid'] = $plagiarismflag->submissionid;

        $PAGE->set_url('/mod/coursework/actions/moderations/new.php', $urlparams);

        $renderer = $this->get_page_renderer();
        $renderer->new_plagiarism_flag_page($plagiarismflag);

    }

    /**
     * This deals with the page that the assessors see when they want to add component plagiarism flag.
     *
     * @throws moodle_exception
     */
    protected function edit_plagiarism_flag() {

        global $DB, $PAGE, $USER;

        $plagiarismflag = new plagiarism_flag($this->params['flagid']);

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('edit', $plagiarismflag);

        $urlparams = ['flagid' => $this->params['flagid']];
        $PAGE->set_url('/mod/coursework/actions/plagiarism_flagging/edit.php', $urlparams);

        $creator = $DB->get_record('user', ['id' => $plagiarismflag->createdby]);
        if (!empty($plagiarismflag->lastmodifiedby)) {
            $editor = $DB->get_record('user', ['id' => $plagiarismflag->lastmodifiedby]);
        } else {
            $editor = $creator;
        }

        $renderer = $this->get_page_renderer();
        $renderer->edit_plagiarism_flag_page($plagiarismflag, $creator, $editor);
    }

    /**
     * Saves the new plagiarism flag for the first time.
     */
    protected function create_plagiarism_flag() {

        global $USER, $PAGE;

        $plagiarismflag = new plagiarism_flag();
        $plagiarismflag->courseworkid = $this->coursework->id();
        $plagiarismflag->submissionid = $this->params['submissionid'];
        $plagiarismflag->createdby = $USER->id;

        $submission = submission::find($this->params['submissionid']);
        $pathparams = ['submission' => $submission];
        $url = $this->get_router()->get_path('new plagiarism flag', $pathparams, true);
        $PAGE->set_url($url);

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('new', $plagiarismflag);

        $form = new plagiarism_flagging_mform(null, ['plagiarism_flag' => $plagiarismflag]);

        $courseworkpageurl = $this->get_path('coursework', ['coursework' => $plagiarismflag->get_coursework()]);
        if ($form->is_cancelled()) {
            redirect($courseworkpageurl);
        }

        $data = $form->get_data();

        if ($data) {
            $plagiarismflag = $form->process_data($plagiarismflag);
            $plagiarismflag->save();

            redirect($courseworkpageurl);
        } else {
            $renderer = $this->get_page_renderer();
            $renderer->new_plagiarism_flag_page($plagiarismflag);
        }
    }

    /**
     * Updates plagiarism flag
     */
    protected function update_plagiarism_flag() {

        global $USER, $DB;

        $flagid = $this->params['flagid'];
        $plagiarismflag = new plagiarism_flag($this->params['flagid']);
        $plagiarismflag->lastmodifiedby = $USER->id;

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('edit', $plagiarismflag);

        $form = new plagiarism_flagging_mform(null, ['plagiarism_flag' => $plagiarismflag]);

        $courseworkpageurl = $this->get_path('coursework', ['coursework' => $plagiarismflag->get_coursework()]);
        if ($form->is_cancelled()) {
            redirect($courseworkpageurl);
        }

        $plagiarismflag = $form->process_data($plagiarismflag);

        // add to log here
        $oldstatus = $DB->get_field(plagiarism_flag::get_table_name(), 'status', ['id' => $flagid]); // Retrieve old status before saving new
        $params = [
            'context' => \context_module::instance($this->coursework->get_course_module()->id),
            'courseid' => $this->coursework->get_course()->id,
            'objectid' => $this->coursework->id,
            'other' => [
                'courseworkid' => $this->coursework->id,
                'submissionid' => $plagiarismflag->submissionid,
                'flagid' => $flagid,
                'oldstatus' => $oldstatus,
                'newstatus' => $plagiarismflag->status,
            ],
        ];

        $event = \mod_coursework\event\coursework_plagiarism_flag_updated::create($params);
        $event->trigger();

        $plagiarismflag->save();

        redirect($courseworkpageurl);
    }

    /**
     * Get any plagiarism flag-specific stuff.
     */
    protected function prepare_environment() {
        global $DB;

        if (!empty($this->params['flagid'])) {
            $plagiarismflag = $DB->get_record('coursework_plagiarism_flags',
                ['id' => $this->params['flagid']],
                '*',
                MUST_EXIST);
            $this->flag = new plagiarism_flag($plagiarismflag);
            $this->params['courseworkid'] = $this->flag->get_coursework()->id;
        }

        if (!empty($this->params['submissionid'])) {
            $submission = $DB->get_record('coursework_submissions',
                ['id' => $this->params['submissionid']],
                '*',
                MUST_EXIST);
            $this->submission = submission::find($submission);
            $this->params['courseworkid'] = $this->submission->courseworkid;
        }

        if (!empty($this->params['moderationid'])) {
            $moderation = $DB->get_record('coursework_mod_agreements',
                ['id' => $this->params['moderationid']],
                '*',
                MUST_EXIST);
            $this->moderation = moderation::find($moderation);
            $this->params['courseworkid'] = $this->moderation->get_coursework()->id;
        }

        parent::prepare_environment();
    }
}
