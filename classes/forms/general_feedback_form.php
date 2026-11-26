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

namespace mod_coursework\forms;

use context_module;
use mod_coursework\event\coursework_deadline_changed;
use moodleform;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * General feedback form.
 */
class general_feedback_form extends moodleform {
    /**
     * Defines the elements of the form.
     */
    public function definition(): void {

        // Pre-fill the picker from the admin setting, but leave it off until a date is chosen.
        $defaultweeks = (int)get_config('mod_coursework', 'generalfeedback');
        $this->_form->addElement(
            'date_time_selector',
            'generalfeedback',
            get_string('generalfeedbackreleasedate', 'coursework'),
            ['optional' => true, 'defaulttime' => strtotime("+{$defaultweeks} weeks")],
        );
        $this->_form->addHelpButton('generalfeedback', 'generalfeedback', 'mod_coursework');

        $this->_form->addElement('editor', 'feedbackcomment_editor', get_string('generalfeedback', 'coursework'));
        $this->_form->setType('feedbackcomment_editor', PARAM_RAW);

        $this->_form->addElement('hidden', 'id', null);
        $this->_form->setType('id', PARAM_INT);

        $this->_form->addElement('hidden', 'cmid');
        $this->_form->setType('cmid', PARAM_INT);

        $this->add_action_buttons();
    }

    /**
     * Validates the submitted data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $deadline = $this->_customdata->deadline ?? 0;
        if (!empty($data['generalfeedback']) && !empty($deadline) && $data['generalfeedback'] < $deadline) {
            $errors['generalfeedback'] = get_string('must_be_after_deadline', 'mod_coursework');
        }

        return $errors;
    }

    /**
     * Updates the DB once the form has been submitted.
     *
     * @param stdClass $feedback
     * @throws \dml_exception
     */
    public function process_data(stdClass $feedback): void {
        global $DB, $USER;

        // Use the IDs the page validated, not the submitted hidden fields.
        $old = $DB->get_record('coursework', ['id' => $this->_customdata->id], '*', MUST_EXIST);

        $record = new stdClass();
        $record->id = $old->id;
        $record->feedbackcomment = $feedback->feedbackcomment_editor['text'];
        $record->feedbackcommentformat = FORMAT_HTML;
        $record->generalfeedback = (int)($feedback->generalfeedback ?? 0);
        $record->generalfeedbacktimepublished = time();

        $DB->update_record('coursework', $record);

        if ($record->generalfeedback == $old->generalfeedback) {
            return;
        }

        $event = coursework_deadline_changed::create([
            'context' => context_module::instance($this->_customdata->cmid),
            'courseid' => $old->course,
            'objectid' => $old->id,
            'other' => [
                'courseworkid' => $old->id,
                'newsubmissionsdeadline' => $old->deadline,
                'oldsubmissiondeadline' => $old->deadline,
                'newgeneraldeadline' => $record->generalfeedback,
                'oldgeneralfeedback' => $old->generalfeedback,
                'newindividualdeadline' => $old->individualfeedback,
                'oldindividualfeedback' => $old->individualfeedback,
                'userfrom' => $USER->id,
            ],
        ]);
        $event->trigger();
    }
}
