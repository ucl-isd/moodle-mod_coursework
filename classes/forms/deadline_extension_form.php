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
use mod_coursework\models\coursework;

/**
 * Class deadline_extension_form is responsible for new and edit actions related to the
 * deadline_extensions.
 *
 */
class deadline_extension_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {

        $this->_form->addElement('hidden', 'allocatabletype');
        $this->_form->settype('allocatabletype', PARAM_ALPHANUMEXT);
        $this->_form->addElement('hidden', 'allocatableid');
        $this->_form->settype('allocatableid', PARAM_INT);
        $this->_form->addElement('hidden', 'courseworkid');
        $this->_form->settype('courseworkid', PARAM_INT);
        $this->_form->addElement('hidden', 'id');
        $this->_form->settype('id', PARAM_INT);

        if ($this->get_coursework()->personaldeadlineenabled &&  $personaldeadline = $this->personal_deadline()) {
            $this->_form->addElement('html', '<div class="alert">Personal deadline: '. userdate($personaldeadline->personal_deadline).'</div>');
        } else {
            // Current deadline for comparison
            $this->_form->addElement('html', '<div class="alert">Default deadline: ' . userdate($this->get_coursework()->deadline) . '</div>');
        }

        // Date and time picker
        $this->_form->addElement('date_time_selector', 'extended_deadline', get_string('extended_deadline',
                                                                                       'mod_coursework'));

        $extensionreasons = coursework::extension_reasons();
        if (!empty($extensionreasons)) {
            $this->_form->addElement('select',
                                     'pre_defined_reason',
                                     get_string('extension_reason',
                                                'mod_coursework'),
                $extensionreasons);
        }

        $this->_form->addElement('editor', 'extra_information', get_string('extra_information', 'mod_coursework'));
        $this->_form->setType('extra_information', PARAM_RAW);

        // Submit button
        $this->add_action_buttons();
    }

    private function get_coursework() {
        return $this->_customdata['coursework'];
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $CFG;
        $maxdeadline = $CFG->coursework_max_extension_deadline;

        if ($this->get_coursework()->personaldeadlineenabled && $personaldeadline = $this->personal_deadline()) {
            $deadline = $personaldeadline->personal_deadline;
        } else {
            $deadline = $this->get_coursework()->deadline;
        }

        $errors = [];
        if ($data['extended_deadline'] <= $deadline) {
            $errors['extended_deadline'] = 'The new deadline must be later than the current deadline';
        }
        if ($data['extended_deadline'] >= strtotime("+$maxdeadline months", $deadline)) {
            $errors['extended_deadline'] = "The new deadline must not be later than $maxdeadline months after the current deadline";
        }
        return $errors;
    }

    public function personal_deadline() {
        global $DB;

        $extensionid = optional_param('id', 0,  PARAM_INT);

        if ($extensionid != 0) {
            $ext = $DB->get_record('coursework_extensions', ['id' => $extensionid]);
            $allocatableid = $ext->allocatableid;
            $allocatabletype = $ext->allocatabletype;
            $courseworkid = $ext->courseworkid;

        } else {

            $allocatableid = required_param('allocatableid', PARAM_INT);
            $allocatabletype = required_param('allocatabletype', PARAM_ALPHANUMEXT);
            $courseworkid = required_param('courseworkid', PARAM_INT);
        }

        $params = [
            'allocatableid' => $allocatableid,
            'allocatabletype' => $allocatabletype ,
            'courseworkid' => $courseworkid,
        ];

        return  $personaldeadline = $DB->get_record('coursework_person_deadlines', $params);
    }

}
