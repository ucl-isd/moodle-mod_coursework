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
 * @copyright  2025 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\forms;

use moodleform;

/**
 * Class personaldeadline_form_bulk is responsible for new and edit actions related to the
 * personaldeadlines where the user is submitting a bulk deadline change.
 *
 */
class personaldeadline_form_bulk extends moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        global $OUTPUT;

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

        $mustachedata = $this->get_header_mustache_data(
            json_decode($this->_customdata['allocatableid']),
            $this->_customdata['allocatabletype']
        );
        $this->_form->addElement(
            'html',
            $OUTPUT->render_from_template('coursework/form_header_personal_deadline', $mustachedata)
        );

        // Date and time picker.
        $maxextensionmonths = $CFG->coursework_max_extension_deadline ?? 0;
        $maxyear = (int)date("Y") + max(ceil($maxextensionmonths / 12), 2);
        $this->_form->addElement(
            'date_time_selector',
            'personaldeadline',
            get_string('personaldeadline', 'mod_coursework'),
            ['startyear' => (int)date("Y"), 'stopyear'  => $maxyear]
        );
        $this->_form->setDefault('personaldeadline', time());

        // Submit button.
        $this->add_action_buttons();
    }

    private function get_coursework() {
        return $this->_customdata['coursework'];
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     * @throws \coding_exception
     */
    public function validation($data, $files) {
        $errors = [];
        if ($data['personaldeadline'] <= time()) {
            $errors['personaldeadline'] = get_string('alert_validate_deadline', 'coursework');
        }

        return $errors;
    }

    /**
     * Add mustache data for form header template.
     * @param array $allocatableids [] the user IDs or group IDs.
     * @param string $allocatabletype whethe user or group.
     * @return object
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function get_header_mustache_data(array $allocatableids, string $allocatabletype): object {
        global $DB;
        $data = (object)[
            'isbulkform' => true,
            'groupmode' => $allocatabletype == 'group',
            'deadlines' => [
                // Default deadline.
                (object)[
                    'label' => get_string('default_deadline_short', 'mod_coursework'),
                    'value' => userdate($this->get_coursework()->deadline, get_string('strftimerecentfull', 'langconfig')),
                    'notes' => [],
                    'class' => '',
                    'hasnotes' => false,
                ],
            ],
        ];
        if (!empty($allocatableids)) {
            [$insql, $params] = $DB->get_in_or_equal($allocatableids, SQL_PARAMS_NAMED);
            if ($allocatabletype == 'user') {
                $users = $DB->get_records_sql("SELECT * FROM {user} WHERE id $insql", $params);
                $data->bulkallocatables = array_values(
                    array_map(
                        function ($user) {
                            return (object)['id' => $user->id, 'allocatablename' => fullname($user)];
                        },
                        $users
                    )
                );
            } else {
                $groups = $DB->get_records_sql("SELECT id, name as allocatablename FROM {groups} WHERE id $insql", $params);
                $data->bulkallocatables = array_values($groups);
            }
        }

        $data->title = get_string(
            $allocatabletype == 'user' ? 'new_personaldeadline_for_bulk_users' : 'new_personaldeadline_for_bulk_groups',
            'coursework'
        );

        return $data;
    }
}
