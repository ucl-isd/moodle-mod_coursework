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
 * Creates an mform for final grade
 *
 * @package    mod_coursework
 * @copyright  2012 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\forms;

global $CFG;

use coding_exception;
use gradingform_rubric_instance;
use mod_coursework\models\feedback;
use mod_coursework\utils\cs_editor;
use moodleform;
use stdClass;

require_once($CFG->libdir.'/formslib.php');

/**
 * Simple form providing a grade and comment area that will feed straight into the feedback table so
 * that the final comment for the gradebook can be added.
 */
class assessor_feedback_mform extends moodleform {

    /**
     * @var int the id of the submission that the grade pertains to
     */
    public $submissionid;

    /**
     * @var int
     */
    public $assessorid;

    private $_grading_controller;

    private $_grading_instance;

    /**
     * Makes the form elements.
     */
    public function definition() {

        $mform =& $this->_form;

        /**
         * @var $feedback feedback
         */
        $feedback = $this->_customdata['feedback'];
        $coursework = $feedback->get_coursework();

        $mform->addElement('hidden', 'submissionid', $feedback->submissionid ?? 0);
        $mform->setType('submissionid', PARAM_INT);

        $mform->addElement('hidden', 'isfinalgrade', $feedback->isfinalgrade ?? 0);
        $mform->setType('isfinalgrade', PARAM_INT);

        $mform->addElement('hidden', 'ismoderation', $feedback->ismoderation ?? 0);
        $mform->setType('ismoderation', PARAM_INT);

        $mform->addElement('hidden', 'assessorid', $feedback->assessorid ?? 0);
        $mform->setType('assessorid', PARAM_INT);

        $mform->addElement('hidden', 'feedbackid', $feedback->id ?? 0);
        $mform->setType('feedbackid', PARAM_INT);

        $mform->addElement('hidden', 'stage_identifier', $feedback->stage_identifier ?? '');
        $mform->setType('stage_identifier', PARAM_ALPHANUMEXT);

        $grademenu = make_grades_menu($coursework->grade);

        if (($coursework->is_using_advanced_grading() && $coursework->finalstagegrading == 0 ) || ($coursework->is_using_advanced_grading() && $coursework->finalstagegrading == 1 &&  $feedback->stage_identifier != 'final_agreed_1')) {
            $this->_grading_controller = $coursework->get_advanced_grading_active_controller();
            $this->_grading_instance = $this->_grading_controller->get_or_create_instance(0, $feedback->assessorid, $feedback->id);
            $mform->addElement('grading', 'advancedgrading', get_string('grade', 'mod_coursework'), ['gradinginstance' => $this->_grading_instance]);
        } else {
            $mform->addElement('select',
                               'grade',
                               get_string('grade', 'mod_coursework'),
                               $grademenu,
                               ['id' => 'feedback_grade']);
        }

        // Useful to keep the overall comments even if we have a rubric or something. There may be a place
        // in the rubric for comments, but not necessarily an overall comment.
        $mform->addElement('editor', 'feedbackcomment', get_string('comment', 'mod_coursework'));
        $mform->setType('editor', PARAM_RAW);

        $filemanageroptions = [
            'subdirs' => false,
            'accepted_types' => '*',
            'return_types' => FILE_INTERNAL,
        ];

        $uploadfilestring = get_string('uploadafile');
        $this->_form->addElement('filemanager',
                                 'feedback_manager',
                                 $uploadfilestring,
                                 null,
                                 $filemanageroptions);

        $this->add_submit_buttons($coursework->draft_feedback_enabled(), $feedback->id);

    }
    /**
     *
     * @return mixed
     */
    public function get_grading_controller() {
        return $this->_grading_controller;
    }

    /**
     * @param $draftenabled
     * @param $feedbackid
     * @throws coding_exception
     */
    public function add_submit_buttons($draftenabled,  $feedbackid) {

        $buttonarray = [];

        if ($draftenabled) {
            $buttonarray[] = $this->_form->createElement('submit', 'submitfeedbackbutton', get_string('saveasdraft', 'coursework'));
        }

            $buttonarray[] =
                $this->_form->createElement('submit', 'submitbutton', get_string('saveandfinalise', 'coursework'));

        $feedback = $this->_customdata['feedback'];

        $ispublished = $feedback->get_submission()->is_published();

        if ($feedbackid &&  !$ispublished) {
            $buttonarray[] = $this->_form->createElement('submit', 'removefeedbackbutton', get_string('removefeedback', 'coursework'));
        }
        $buttonarray[] = $this->_form->createElement('cancel');
        $this->_form->addGroup($buttonarray, 'buttonar', '', [' '], false);
        $this->_form->closeHeaderBefore('buttonar');

    }

    /**
     *
     * @param $data
     * @return bool
     */
    public function validate_grade($data) {
        $result = true;
        if (!empty($this->_grading_instance) && property_exists($data, 'advancedgrading')) {
            $result = $this->_grading_instance->validate_grading_element($data->advancedgrading);
        }
        return $result;
    }

    /**
     * This is just to grab the data and add it to the feedback object.
     *
     * @param feedback $feedback
     * @return feedback
     */
    public function process_data(feedback $feedback) {

        $formdata = $this->get_data();
        $coursework = $feedback->get_coursework();

        if (($coursework->is_using_advanced_grading() && $coursework->finalstagegrading == 0 ) || ($coursework->is_using_advanced_grading() && $coursework->finalstagegrading == 1 &&  $feedback->stage_identifier != 'final_agreed_1')) {
            $controller = $coursework->get_advanced_grading_active_controller();
            $gradinginstance = $controller->get_or_create_instance(0, $feedback->assessorid, $feedback->id);
            /**
             * @var gradingform_rubric_instance $grade
             */
            $feedback->grade = $gradinginstance->submit_and_get_grade($formdata->advancedgrading, $feedback->id);
        } else {
            $feedback->grade = $formdata->grade;
        }

        $feedback->feedbackcomment = $formdata->feedbackcomment['text'];
        $feedback->feedbackcommentformat = $formdata->feedbackcomment['format'];

        return $feedback;
    }

    /**
     * Saves any of the files that may have been attached. Needs a feedback that has an id.
     *
     * @param feedback $feedback
     * @throws coding_exception
     * @return bool|void
     */
    public function save_feedback_files(feedback $feedback) {

        if (!$feedback->persisted()) {
            throw new coding_exception('Must supply a feedback that has been persisted so we have an itemid to use');
        }

        $formdata = $this->get_data();

        file_save_draft_area_files($formdata->feedback_manager,
                                   $feedback->get_coursework()->get_context()->id,
                                   'mod_coursework',
                                   'feedback',
                                   $feedback->id);
    }

    /**
     *
     * @return stdClass|null
     */
    public function get_file_options() {
        global $PAGE, $CFG;
        require_once("$CFG->dirroot/lib/form/filemanager.php");
        $options = null;
        $filemanager = $this->_form->getElement('feedback_manager');
        if ($filemanager) {
            $params = (object) [
                'maxfiles' => $filemanager->getMaxfiles(),
                'subdirs' => $filemanager->getSubdirs(),
                'areamaxbytes' => $filemanager->getAreamaxbytes(),
                'target' => 'id_' . $filemanager->getName(),
                'context' => $PAGE->context,
                'itemid' => $filemanager->getValue(),
            ];
            $fm = new \form_filemanager($params);
            $options = $fm->options;
        }
        return $options;
    }

    /**
     *
     * @return stdClass|null
     */
    public function get_editor_options() {
        $editor = new cs_editor();
        $options = $editor->get_options();
        return $options;
    }
}

