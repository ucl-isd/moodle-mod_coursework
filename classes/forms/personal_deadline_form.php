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
 * @copyright  2017 University of London Computer Centre {@link http://ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\forms;
use mod_coursework\models\coursework;

/**
 * Class personal_deadline_form is responsible for new and edit actions related to the
 * personal_deadlines.
 *
 */
class personal_deadline_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {

        $this->_form->addElement('hidden', 'allocatabletype');
        $this->_form->settype('allocatabletype', PARAM_ALPHANUMEXT);
        $this->_form->addElement('hidden', 'allocatableid');
        $this->_form->settype('allocatableid', PARAM_RAW);
        $this->_form->addElement('hidden', 'courseworkid');
        $this->_form->settype('courseworkid', PARAM_INT);
        $this->_form->addElement('hidden', 'id');
        $this->_form->settype('id', PARAM_INT);
        $this->_form->addElement('hidden', 'setpersonaldeadlinespage');
        $this->_form->settype('setpersonaldeadlinespage', PARAM_INT);
        $this->_form->addElement('hidden', 'multipleuserdeadlines');
        $this->_form->settype('multipleuserdeadlines', PARAM_INT);

        // Current deadline for comparison
        $this->_form->addElement('html', '<div class="alert">Default deadline: '. userdate($this->get_coursework()->deadline).'</div>');

        // Date and time picker
        $this->_form->addElement('date_time_selector', 'personal_deadline', get_string('personal_deadline', 'mod_coursework'));

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
        $errors = [];
        if ($data['personal_deadline'] <= time()) {
            $errors['personal_deadline'] = 'The new deadline you chose has already passed. Please select appropriate deadline';
        }

        return $errors;
    }

}
