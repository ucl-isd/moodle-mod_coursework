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
 * Creates an mform for moderator agreement
 *
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\forms;

use core_form\dynamic_form;
use mod_coursework\ability;
use mod_coursework\exceptions\access_denied;
use mod_coursework\models\plagiarism_flag;
use mod_coursework\models\submission;
use mod_coursework\models\user;

/**
 * Simple form providing a plagiarism status and comment area that will feed straight into the coursework_plagiarism_flagging table
 */
class plagiarism_flagging_mform extends dynamic_form {
    /**
     * @var submission the submission that the flag pertains to
     */
    protected $submission;

    /**
     * @var plagiarism_flag the flag
     */
    protected $plagiarismflag;


    /**
     * Where the user has not yet picked a status, use an invalid status.
     */
    const NO_STATUS_SELECTED = -1;

    /**
     * Makes the form elements.
     */
    public function definition() {
        global $OUTPUT, $DB;

        $mform =& $this->_form;

        $formheaderdata = (object)[
            'title' => get_string(
                'plagiarismflaggingfor',
                'coursework',
                $this->get_submission()->get_allocatable_name()
            ),
            'submissionispublished' => $this->get_submission()->is_published(),
        ];

        $flag = $this->get_flag();
        if ($flag) {
            $createdbyuser = $DB->get_record('user', ['id' => $flag->createdby]);
            $formheaderdata->flag = (object)[
                'createdby' => get_string(
                'createdbynametime',
                'coursework',
                    ['name' => fullname($createdbyuser), 'time' => userdate($flag->timecreated)]
                ),
            ];
            if ($flag->lasteditedby) {
                $lastediteduser = $DB->get_record('user', ['id' => $flag->lasteditedby]);
                $formheaderdata->flag->lastedited = get_string(
                    'lastedited',
                    'coursework',
                    ['name' => fullname($lastediteduser), 'time' => userdate($flag->timemodified)]
                );
            }
        }
        $formheaderhtml = $OUTPUT->render_from_template('mod_coursework/form_header_plagiarism_flag', $formheaderdata);
        $mform->addElement('html', $formheaderhtml);

        $mform->addElement('hidden', 'submissionid', $this->get_submission()->id);
        $mform->setType('submissionid', PARAM_INT);
        $mform->addElement('hidden', 'plagiarismflagid', $flag->id ?? null);
        $mform->setType('plagiarismflagid', PARAM_INT);

        $options = [
            self::NO_STATUS_SELECTED => get_string('choose'),
            plagiarism_flag::INVESTIGATION => get_string('plagiarism_'.plagiarism_flag::INVESTIGATION, 'coursework'),
            plagiarism_flag::RELEASED => get_string('plagiarism_'.plagiarism_flag::RELEASED, 'coursework'),
            plagiarism_flag::CLEARED => get_string('plagiarism_'.plagiarism_flag::CLEARED, 'coursework'),
            plagiarism_flag::NOTCLEARED => get_string('plagiarism_'.plagiarism_flag::NOTCLEARED, 'coursework'),
        ];

        $mform->addElement('select', 'status',
                            get_string('status', 'coursework'),
                            $options,
                            ['id' => 'plagiarism_status']);

        $mform->addHelpButton('status', 'status', 'mod_coursework');

        $mform->addElement('editor', 'plagiarismcomment', get_string('comment', 'mod_coursework'), ['id' => 'plagiarism_comment']);
        $mform->setType('editor', PARAM_RAW);

        if ($flag) {
            $mform->setDefault('status', $flag->status);
            $mform->setDefault('plagiarismcomment', ['text' => $flag->comment, 'format' => $flag->comment_format]);
        }
        $mform->hideIf('plagiarismcomment', 'status', 'eq', "1");

        // Submit button.
        // Don't add these if AJAX submission as the modal has its own buttons to submit using JS.
        if (!isset($this->_ajaxformdata)) {
            $this->add_action_buttons();
        }
    }


    /**
     * Get the submission for this flag.
     * @return bool|submission|object
     */
    public function get_submission() {
        if ($this->submission === null) {
            // One of _customdata or _ajaxformdata will be set depending on whether we are called from modal or not.
            $submissionid = $this->_customdata['submissionid'] ?? ($this->_ajaxformdata['submissionid'] ?? null);
            // Old style PHP forms may not pass in submission ID so find it from flag if not.
            if (!$submissionid) {
                $submissionid = $this->get_flag()->get_submission()->id();
            }
            $this->submission = submission::find($submissionid);
        }
        return $this->submission;
    }

    /**
     * Get the flag.
     * @return bool|submission|object
     */
    public function get_flag() {
        if ($this->plagiarismflag === null) {
            // One of _customdata or _ajaxformdata should be set depending on whether we are called from modal or not.
            $flagid = ($this->_customdata['plagiarismflagid'] ?? null) ?? ($this->_ajaxformdata['plagiarismflagid'] ?? null);
            if ($flagid) {
                // Maybe no flag exists yet, but set it if it does.
                $this->plagiarismflag = plagiarism_flag::find($flagid);
            }
        }
        return $this->plagiarismflag;
    }

    /**
     * This is just to grab the data and add it to the plagiarismflag object.
     *
     * @param plagiarism_flag $plagiarismflag
     * @return plagiarism_flag
     */
    public function process_data(plagiarism_flag $plagiarismflag) {

        $formdata = $this->get_data();

        $plagiarismflag->status = $formdata->status;
        $plagiarismflag->comment = $formdata->plagiarismcomment['text'] ?? '';
        $plagiarismflag->comment_format = $formdata->plagiarismcomment['format'] ?? FORMAT_PLAIN;

        return $plagiarismflag;
    }

    /**
     * Returns context where this form is used
     *
     * This context is validated in {@see external_api::validate_context()}
     *
     * If context depends on the form data, it is available in $this->_ajaxformdata or
     * by calling $this->optional_param()
     *
     * @return \context
     **/
    protected function get_context_for_dynamic_submission(): \context {
        return $this->get_submission()->get_coursework()->get_context();
    }

    /**
     * Can the user edit this personal deadline or create one?
     * @return bool
     */
    protected function can_edit(): bool {
        global $USER;
        $plagiarismflag = new plagiarism_flag();
        $plagiarismflag->submissionid = $this->get_submission()->id();
        $plagiarismflag->courseworkid = $this->get_submission()->get_coursework()->id();

        $ability = new ability(user::find($USER), $this->get_submission()->get_coursework());
        return $ability->can('new', $plagiarismflag);
    }

    /**
     * Checks if current user has access to this form, otherwise throws exception.
     * @throws access_denied
     */
    protected function check_access_for_dynamic_submission(): void {
        if (!$this->can_edit()) {
            throw new access_denied($this->get_submission()->get_coursework(), 'No permission to edit plagiarism flag');
        }
    }

    /**
     * Process the form submission, used if form was submitted via AJAX.
     * Can return scalar values or arrays json-encoded, will be passed to the caller JS.
     * @return array
     */
    public function process_dynamic_submission() {
        global $USER;

        // By the time we reach here, $this->validation has already happened so no need to repeat.
        $data = $this->get_data();
        $errors = [];
        $warnings = [];

        if (!$this->can_edit()) {
            $errors[] = get_string('nopermissiongeneral', 'mod_coursework');
        }

        // If we reach this far with no errors, we can create/update the flag.
        if (empty($errors)) {
            $iscreating = !$this->get_flag();
            if ($iscreating) {
                // Creating.
                $data->createdby = $USER->id;
                $data->timecreated = time();
                $data->courseworkid = $this->get_submission()->get_coursework()->id();
                $this->plagiarismflag = plagiarism_flag::build($data);
            } else {
                $this->plagiarismflag->timemodified = time();
                $this->plagiarismflag->lasteditedby = $USER->id;
            }
            $this->plagiarismflag->status = $data->status;
            $this->plagiarismflag->comment = $data->plagiarismcomment['text'] ?? '';
            $this->plagiarismflag->comment_format = $data->plagiarismcomment['format'] ?? FORMAT_PLAIN;
            if ($iscreating) {
                $this->plagiarismflag->save();
            } else {
                $this->plagiarismflag->update_attributes($this->plagiarismflag);
            }
        }
        return [
            'success' => empty($errors),
            'resultcode' => empty($errors) ? 'saved' : 'error',
            'message' => get_string(
                empty($errors) ? 'plagiarismflagsavedfor' : 'plagiarismflagnotsavedfor',
                'coursework',
                $this->get_submission()->get_allocatable_name()
            ),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Set the data that the modal form needs to display.
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        // We are required to implement this, but no action required here as handled in definition.
    }

    /**
     *  Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *  This is used in the form elements sensitive to the page url, such as Atto autosave in 'editor'
     *  If the form has arguments (such as 'id' of the element being edited), the URL should
     *  also have respective argument.
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/mod/coursework/view.php', ['id' => $this->get_submission()->get_coursework()->id]);
    }

    /**
     * Validate the submitted form data.
     * @param $data
     * @param $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ($data['status'] == self::NO_STATUS_SELECTED) {
            $errors['status'] = get_string('required');
        }
        return $errors;
    }
}
