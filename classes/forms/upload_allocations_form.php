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
 * @copyright  2016 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class upload_allocations_form extends moodleform {
    private $cmid;

    public function __construct($cmid) {
        $this->cmid = $cmid;

        parent::__construct();
    }

    public function definition() {
        global $CFG;
        $mform =& $this->_form;

        // The identifier used could be email or username depending on plugin settings.
        $useridentifierfield = $CFG->coursework_allocation_identifier;
        $mform->addElement(
            'html',
            get_string('uploadallocationsintro', 'coursework')
            . "<pre class=\"m-3 p-3 bg-light\">user_$useridentifierfield,assessor_{$useridentifierfield}_1,assessor_{$useridentifierfield}_2"
            . "\nstudent1@example.com,assessor1@example.com,assessor2@example.com"
            . "\nstudent2@example.com,assessor1@example.com,assessor2@example.com"
            . "</pre>"
        );

        $mform->addElement('filepicker', 'allocationsdata', get_string('allocationsfile', 'coursework'), null, [ 'accepted_types' => '*.csv']);
        $mform->addRule('allocationsdata', null, 'required');

        $mform->addElement('hidden', 'cmid', $this->cmid);
        $mform->setType('cmid', PARAM_RAW);

        $choices = csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'delimiter_name', get_string('csvdelimiter', 'tool_uploaduser'), $choices);
        if (array_key_exists('cfg', $choices)) {
            $mform->setDefault('delimiter_name', 'cfg');
        } else if (get_string('listsep', 'langconfig') == ';') {
            $mform->setDefault('delimiter_name', 'semicolon');
        } else {
            $mform->setDefault('delimiter_name', 'comma');
        }

        $choices = core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'tool_uploaduser'), $choices);
        $mform->setDefault('encoding', 'UTF-8');

        $this->add_action_buttons(true, get_string('uploadallocations', 'coursework'));
    }

    public function display() {
        return $this->_form->toHtml();
    }
}
