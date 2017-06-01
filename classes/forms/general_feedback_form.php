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

namespace mod_coursework\forms;

use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * This is the non-javascript form that will pop up and allow people to add grades to
 * individual submissions.
 */
class general_feedback_form extends moodleform {

    /**
     * Defines the elements of the form.
     */
    public function definition() {
        // Add comment thing.
        $this->_form->addElement('editor', 'feedbackcomment_editor', '');
        $this->_form->setType('feedbackcomment_editor', PARAM_RAW);

        $this->_form->addElement('hidden', 'id', null);
        $this->_form->setType('id', PARAM_INT);

        $this->_form->addElement('hidden', 'cmid');
        $this->_form->setType('cmid', PARAM_INT);
        $this->_form->addElement('hidden', 'ajax', $this->_customdata->ajax);
        $this->_form->setType('ajax', PARAM_RAW);

        if (!$this->_customdata->ajax) {
            // Add action buttons.
            $this->add_action_buttons();
        }
    }

    /**
     * Updates the DB once the form has been submitted.
     *
     * @param $feedback
     */
    public function process_data($feedback) {
        global $DB;
        $coursework = new \stdClass();
        $coursework->feedbackcomment = $feedback->feedbackcomment_editor['text'];
        $coursework->feedbackcommentformat = 1;
        $coursework->generalfeedbacktimepublished = time();
        $coursework->id = $feedback->id;

        $DB->update_record('coursework', $coursework);
    }
}
