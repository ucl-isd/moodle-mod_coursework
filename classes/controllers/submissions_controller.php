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
use mod_coursework\event\assessable_submitted;
use mod_coursework\exceptions\access_denied;
use mod_coursework\exceptions\late_submission;
use mod_coursework\forms\student_submission_form;
use mod_coursework\mailer;
use mod_coursework\models\coursework;
use mod_coursework\models\submission;
use mod_coursework\models\user;

defined('MOODLE_INTERNAL' || die());

/**
 * Class submissions_controller
 * @package mod_coursework\controllers
 */
class submissions_controller extends controller_base {

    /**
     * @var submission
     */
    protected $submission;

    /**
     * Shared logic for rendering the submission page (new or edit).
     *
     * @param student_submission_form $submitform
     * @param submission $submission
     * @param bool $isnew
     * @return bool|null
     */
    protected function submission_page($submitform, $submission, $isnew) {
        $validation = false;
        if ($submitform->is_submitted()) {
            $validation = $submitform->validate_defined_fields();
        }
        if ($isnew) {
            // No need to set form data for new submission
        } else {
            $submitform->set_data($submission);
        }
        if ($validation !== true) {
            $this->get_page_renderer()->submission_page($submitform, $submission, $isnew);
            return true;
        }
        return null;
    }

    /**
     * Makes the page where a user can create a new submission.
     *
     * @throws \coding_exception
     * @throws \unauthorized_access_exception
     */
    protected function new_submission() {
        global $USER, $PAGE;

        $submission = submission::build([
            'allocatableid' => $this->params['allocatableid'],
            'allocatabletype' => $this->params['allocatabletype'],
            'courseworkid' => $this->coursework->id,
            'createdby' => $USER->id
        ]);

        $ability = new ability($USER->id, $this->coursework);
        if (!$ability->can('new', $submission)) {
            throw new access_denied($this->coursework);
        }

        $this->check_coursework_is_open($this->coursework);

        $urlparams = ['courseworkid' => $this->params['courseworkid']];
        $PAGE->set_url('/mod/coursework/actions/submissions/new.php', $urlparams);

        $path = $this->get_router()->get_path('create submission', ['coursework' => $this->coursework]);
        $submitform = new student_submission_form($path, [
            'coursework' => $this->coursework,
            'submission' => $submission,
        ]);

        return $this->submission_page($submitform, $submission, true);
    }

    /**
     * Makes the page where a user can edit an existing submission.
     * Might be someone editing the group feedback thing too, so we load based on the submission
     * user, not the current user.
     *
     * @throws \coding_exception
     * @throws \unauthorized_access_exception
     */
    protected function edit_submission() {
        global $USER, $PAGE;

        $submission = submission::find($this->params['submissionid']);

        $ability = new ability($USER->id, $this->coursework);
        if (!$ability->can('edit', $submission)) {
            throw new access_denied($this->coursework);
        }

        $urlparams = ['submissionid' => $this->params['submissionid']];
        $PAGE->set_url('/mod/coursework/actions/submissions/edit.php', $urlparams);

        $path = $this->get_router()->get_path('update submission', ['submission' => $submission]);
        $submitform = new student_submission_form($path, [
            'coursework' => $this->coursework,
            'submission' => $submission,
        ]);

        return $this->submission_page($submitform, $submission, false);
    }

    /**
     * Receives the form input from the new submission page and saves it.
     */
    protected function create_submission() {
        global $USER, $CFG, $DB;

        $courseworkpageurl = $this->get_path('coursework', ['coursework' => $this->coursework]);
        if ($this->cancel_button_was_pressed()) {
            redirect($courseworkpageurl);
        }

        $validated = $this->new_submission();

        if ($validated == true) {
            return;
        }

        $submission = new submission();
        $submission->courseworkid = $this->coursework->id;
        $submission->finalised = $this->params['finalised'] ? 1 : 0;
        $submission->allocatableid = $this->params['allocatableid'];
        $submission->createdby = $USER->id;
        $submission->lastupdatedby = $USER->id;
        $submission->allocatabletype = $this->params['allocatabletype'];
        $submission->authorid = $submission->get_author_id(); // Create new function to get the author id depending on whether the current user is submitting on behalf
        $submission->timesubmitted = time();

        // Automatically finalise any submissions that's past the deadline/personal deadline and doesn't have valid extension
        if ($this->coursework->personal_deadlines_enabled()) {
            // Check is submission has a valid personal deadline or a valid extension
            if (!$this->has_valid_personal_deadline($submission) && !$this->has_valid_extension($submission)) {
                $submission->finalised = 1;
            }
        } else if ($this->coursework->deadline_has_passed() && !$this->has_valid_extension($submission)) {
            $submission->finalised = 1;
        }

        $ability = new ability($USER->id, $this->coursework);

        $this->exception_if_late($submission);

        if (!$ability->can('create', $submission)) {
            // if submission already exists, redirect user to cw instead of throwing an error
            if ($this->submissions_exists($submission)) {
                redirect($CFG->wwwroot.'/mod/coursework/view.php?id='.$this->coursemodule->id, $ability->get_last_message());
            } else {
                throw new access_denied($this->coursework, $ability->get_last_message());
            }
        }

        $this->check_coursework_is_open($this->coursework);

        $submission->save();

        $filesid = file_get_submitted_draft_itemid('submission_manager');
        $submission->save_files($filesid);

        $context = \context_module::instance($this->coursemodule->id);
        // Trigger assessable_submitted event to show files are complete.
        $params = [
            'context' => $context,
            'objectid' => $submission->id,
            'other' => [
                'courseworkid' => $this->coursework->id,
            ],
        ];
        $event = assessable_submitted::create($params);
        $event->trigger();

        $submission->submit_plagiarism();

        $mailer = new mailer($this->coursework);
        if ($CFG->coursework_allsubmissionreceipt || $submission->finalised) {
            foreach ($submission->get_students() as $student) {
                $mailer->send_submission_receipt($student, $submission->finalised);
            }
        }

        if ($submission->finalised) {
            if (!$submission->get_coursework()->has_deadline()) {

                $useridcommaseparatedlist = $submission->get_coursework()->get_submission_notification_users();

                if (!empty($useridcommaseparatedlist)) {
                    $userids = explode(',', $useridcommaseparatedlist);
                    foreach ($userids as $u) {
                        $notifyuser = $DB->get_record('user', ['id' => trim($u)]);

                        if (!empty($notifyuser)) {
                            $mailer->send_submission_notification($notifyuser);
                        }
                    }
                }

            }

        }

        redirect($courseworkpageurl);
    }

    /**
     *
     */
    protected function update_submission() {

        global $USER, $CFG;

        $courseworkpageurl = $this->get_path('coursework', ['coursework' => $this->coursework]);
        if ($this->cancel_button_was_pressed()) {
            redirect($courseworkpageurl);
        }

         $validated = $this->edit_submission();

        if ($validated == true) {
            return;
        }

        $submission = submission::find($this->params['submissionid']);

        $ability = new ability($USER->id, $this->coursework);
        $this->exception_if_late($submission);
        if (!$ability->can('update', $submission)) {
            throw new access_denied($this->coursework, $ability->get_last_message());
        }

        $notifyaboutfinalisation = false;
        $incomingfinalisedsetting = $this->params['finalised'] ? 1 : 0;
        if ($incomingfinalisedsetting == 1 && $submission->finalised == 0) {
            $notifyaboutfinalisation = true;
        }
        $submission->finalised = $incomingfinalisedsetting;
        $submission->lastupdatedby = $USER->id;
        $submission->timesubmitted = time();

        $submission->save();

        $filesid = file_get_submitted_draft_itemid('submission_manager');
        $submission->save_files($filesid);

        $context = \context_module::instance($this->coursemodule->id);
        // Trigger assessable_submitted event to show files are complete.
        $params = [
            'context' => $context,
            'objectid' => $submission->id,
            'other' => [
                'courseworkid' => $this->coursework->id,
            ],
        ];
        $event = assessable_submitted::create($params);
        $event->trigger();

        $submission->submit_plagiarism();

        if ($CFG->coursework_allsubmissionreceipt || $notifyaboutfinalisation) {
            $mailer = new mailer($this->coursework);
            foreach ($submission->get_students() as $student) {
                $mailer->send_submission_receipt($student, $notifyaboutfinalisation);
            }
        }

        redirect($courseworkpageurl);

    }

    /**
     *
     */
    protected function finalise_submission() {

        global $USER;

        $courseworkpageurl = $this->get_path('coursework', ['coursework' => $this->coursework]);
        if ($this->cancel_button_was_pressed()) {
            redirect($courseworkpageurl);
        }

        $submission = submission::find($this->params['submissionid']);

        $ability = new ability($USER->id, $this->coursework);
        if (!$ability->can('finalise', $submission)) {
            throw new access_denied($this->coursework);
        }

        $submission->finalised = 1;
        $submission->save();

        // Email the user. Best to do this as an event after 2.7 so as to keep the page fast.
        $mailer = new mailer($this->coursework);
        foreach ($submission->get_students() as $student) {
            $mailer->send_submission_receipt($student, true);
        }

        redirect($courseworkpageurl, get_string('changessaved'));
    }

    protected function unfinalise_submission() {
        global $DB;

        $allocatableids = (!is_array($this->params['allocatableid'])) ? [$this->params['allocatableid']] : $this->params['allocatableid'];

        $personaldeadlinepageurl = new \moodle_url('/mod/coursework/actions/personal_deadline.php',
            ['id' => $this->coursework->get_coursemodule_id(), 'multipleuserdeadlines' => 1, 'setpersonaldeadlinespage' => 1,
                'courseworkid' => $this->params['courseworkid'], 'allocatabletype' => $this->params['allocatabletype']]);

        $changedeadlines = false;

        foreach ($allocatableids as $aid) {
            $submissiondb = $DB->get_record('coursework_submissions',
                ['courseworkid' => $this->params['courseworkid'], 'allocatableid' => $aid, 'allocatabletype' => $this->params['allocatabletype']]);
            if (!empty($submissiondb)) {
                $submission = \mod_coursework\models\submission::find($submissiondb);

                if ($submission->can_be_unfinalised()) {
                    $submission->finalised = 0;
                    $submission->save();
                    $personaldeadlinepageurl->param("allocatableid_arr[$aid]", $aid);
                    $changedeadlines = true;
                }
            }
        }

        if (!empty($changedeadlines)) {
            redirect($personaldeadlinepageurl, get_string('unfinalisedchangesubmissiondate', 'mod_coursework'));
        } else {
            $setpersonaldeadlinepageurl = new \moodle_url('/mod/coursework/actions/set_personal_deadlines.php',
                ['id' => $this->coursework->get_coursemodule_id()]);
            redirect($setpersonaldeadlinepageurl);
        }
    }

    protected function prepare_environment() {
        if (!empty($this->params['submissionid'])) {
            $this->submission = submission::find($this->params['submissionid']);
            $this->coursework = $this->submission->get_coursework();

        }

        parent::prepare_environment();
    }

    /**
     * Tells us whether the agree to terms checkbox was used.
     *
     * @return bool
     * @throws \coding_exception
     */
    private function terms_were_agreed_to() {
        return (bool)optional_param('termsagreed', 0, PARAM_INT);
    }

    /**
     * Is the coursework open?
     * @param coursework $coursework
     * @throws \coding_exception
     * @throws access_denied
     */
    protected function check_coursework_is_open($coursework) {
        if (!$coursework->start_date_has_passed()) {
            throw new \Exception(get_string('notstartedyet', 'mod_coursework', userdate($coursework->startdate)));
        }
    }

    /**
     * @throws late_submission
     */
    private function exception_if_late($submission) {
        $couldhavesubmitted = has_capability('mod/coursework:submit', $this->coursework->get_context());
        if ($this->coursework->personal_deadlines_enabled()) {
            $deadlinehaspassed = !$this->has_valid_personal_deadline($submission);
        } else {
            $deadlinehaspassed = $this->coursework->deadline_has_passed();
        }

        if ($couldhavesubmitted && $deadlinehaspassed && !$this->has_valid_extension($submission) && !$this->coursework->allow_late_submissions()) {
            throw new late_submission($this->coursework);
        }
    }

    /*
     * param submission $submission
     * return bool true if a matching record exists, else false
     */
    protected function submissions_exists($submission) {
        global $DB;

        $subexists = $DB->record_exists('coursework_submissions',
                                     ['courseworkid' => $submission->courseworkid,
                                           'allocatableid' => $submission->allocatableid,
                                           'allocatabletype' => $submission->allocatabletype]);

        return $subexists;
    }

    /**
     * @param $submission
     * @return bool
     */
    protected function has_valid_extension($submission) {
        global $DB;

        $validextension = false;
        $extension = $DB->get_record('coursework_extensions',
                                         ['courseworkid' => $submission->courseworkid,
                                               'allocatableid' => $submission->allocatableid,
                                               'allocatabletype' => $submission->allocatabletype]);

        if ($extension) {
            if ($extension->extended_deadline > time()) {
                $validextension = true;
            }
        }

        return $validextension;
    }

    /**
     * @param $submission
     * @return bool
     */
    protected function has_valid_personal_deadline($submission) {
        global $DB;

        $validpersonaldeadline = false;
        $personaldeadline = $DB->get_record('coursework_person_deadlines',
                                            ['courseworkid' => $submission->courseworkid,
                                                  'allocatableid' => $submission->allocatableid,
                                                  'allocatabletype' => $submission->allocatabletype]);
        if ($personaldeadline) {
            if ($personaldeadline->personal_deadline > time()) {
                $validpersonaldeadline = true;
            }
        } else {
            $validpersonaldeadline = !$this->coursework->deadline_has_passed();
        }
        return $validpersonaldeadline;
    }
}
