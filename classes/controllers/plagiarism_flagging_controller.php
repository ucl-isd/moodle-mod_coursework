<?php

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
    protected $plagiarism_flag;


    /**
     * This deals with the page that the assessors see when they want to add component feedbacks.
     *
     * @throws \moodle_exception
     */
    protected function new_plagiarism_flag() {

        global $PAGE, $USER;

        $plagiarismflag = new plagiarism_flag();
        $plagiarismflag->submissionid = $this->params['submissionid'];
        $plagiarismflag->courseworkid =  $this->coursework->id;


        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('new', $plagiarismflag);


        $urlparams = array();
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

        $plagiarism_flag = new plagiarism_flag($this->params['flagid']);

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('edit', $plagiarism_flag);

        $urlparams = array('flagid' => $this->params['flagid']);
        $PAGE->set_url('/mod/coursework/actions/plagiarism_flagging/edit.php', $urlparams);

        $creator = $DB->get_record('user', array('id' => $plagiarism_flag->createdby));
        if (!empty($plagiarism_flag->lastmodifiedby)) {
            $editor = $DB->get_record('user', array('id' => $plagiarism_flag->lastmodifiedby));
        } else {
            $editor = $creator;
        }

        $renderer = $this->get_page_renderer();
        $renderer->edit_plagiarism_flag_page($plagiarism_flag, $creator, $editor);
    }


    /**
     * Saves the new plagiarism flag for the first time.
     */
    protected function create_plagiarism_flag() {

        global $USER, $PAGE;

        $plagiarismflag = new plagiarism_flag();
        $plagiarismflag->courseworkid =  $this->coursework->id();
        $plagiarismflag->submissionid = $this->params['submissionid'];
        $plagiarismflag->createdby = $USER->id;

        $submission = submission::find($this->params['submissionid']);
        $path_params = array('submission' => $submission);
        $url = $this->get_router()->get_path('new plagiarism flag', $path_params, true);
        $PAGE->set_url($url);

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('new', $plagiarismflag);

        $form = new plagiarism_flagging_mform(null, array('plagiarism_flag' => $plagiarismflag));

        $coursework_page_url = $this->get_path('coursework', array('coursework' => $plagiarismflag->get_coursework()));
        if ($form->is_cancelled()) {
            redirect($coursework_page_url);
        }

        $data = $form->get_data();

        if ($data) {
            $plagiarismflag = $form->process_data($plagiarismflag);
            $plagiarismflag->save();

            redirect($coursework_page_url);
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

        $form = new plagiarism_flagging_mform(null, array('plagiarism_flag' => $plagiarismflag));

        $coursework_page_url = $this->get_path('coursework', array('coursework' => $plagiarismflag->get_coursework()));
        if ($form->is_cancelled()) {
            redirect($coursework_page_url);
        }

        $plagiarismflag = $form->process_data($plagiarismflag);

        // add to log here
        $oldstatus =  $DB->get_field(plagiarism_flag::get_table_name(), 'status', array('id' => $flagid)); //retrieve old status before saving new
        $params = array(
            'context' => \context_module::instance($this->coursework->get_course_module()->id),
            'courseid' => $this->coursework->get_course()->id,
            'objectid' => $this->coursework->id,
            'other' => array(
                'courseworkid' =>  $this->coursework->id,
                'submissionid' =>  $plagiarismflag->submissionid,
                'flagid' =>  $flagid,
                'oldstatus' => $oldstatus,
                'newstatus' => $plagiarismflag->status
            )
        );

        $event = \mod_coursework\event\coursework_plagiarism_flag_updated::create($params);
        $event->trigger();

        $plagiarismflag->save();

        redirect($coursework_page_url);
    }



    /**
     * Get any plagiarism flag-specific stuff.
     */
    protected function prepare_environment() {
        global $DB;

        if (!empty($this->params['flagid'])) {
            $plagiarism_flag = $DB->get_record('coursework_plagiarism_flags',
                array('id' => $this->params['flagid']),
                '*',
                MUST_EXIST);
            $this->flag = new plagiarism_flag($plagiarism_flag);
            $this->params['courseworkid'] = $this->flag->get_coursework()->id;
        }

        if (!empty($this->params['submissionid'])) {
            $submission = $DB->get_record('coursework_submissions',
                array('id' => $this->params['submissionid']),
                '*',
                MUST_EXIST);
            $this->submission = submission::find($submission);
            $this->params['courseworkid'] = $this->submission->courseworkid;
        }

        if (!empty($this->params['moderationid'])) {
            $moderation = $DB->get_record('coursework_mod_agreements',
                array('id' => $this->params['moderationid']),
                '*',
                MUST_EXIST);
            $this->moderation = moderation::find($moderation);
            $this->params['courseworkid'] = $this->moderation->get_coursework()->id;
        }

        parent::prepare_environment();
    }
}