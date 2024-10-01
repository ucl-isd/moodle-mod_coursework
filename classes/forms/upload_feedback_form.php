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

require_once($CFG->libdir.'/formslib.php');

class upload_feedback_form extends moodleform {

    private $cmid;
    private $coursework;

    public function __construct($coursework, $cmid) {
        $this->cmid = $cmid;
        $this->coursework = $coursework;

        parent::__construct();
    }

    public function definition() {
        $mform =& $this->_form;

        $mform->addElement('filepicker', 'feedbackzip', get_string('feedbackzipfile', 'coursework'), null, [ 'accepted_types' => '*.zip']);
        $mform->addRule('feedbackzip', null, 'required');
        $mform->addHelpButton('feedbackzip', 'feedbackzipfile', 'coursework');

        $mform->addElement('advcheckbox', 'overwrite', '', get_string('overwritefeedback', 'coursework'), null, [0, 1]);
        $mform->addElement('hidden', 'cmid', $this->cmid);
        $mform->setType('cmid', PARAM_RAW);

        $options = [];

        if ($this->coursework->get_max_markers() > 1) {

            $capability = ['mod/coursework:addinitialgrade', 'mod/coursework:editinitialgrade'];
            if (has_any_capability($capability, $this->coursework->get_context()) && !has_capability('mod/coursework:administergrades', $this->coursework->get_context())) {
                $options['initialassessor'] = get_string('initialassessor', 'coursework');

            } else if (has_capability('mod/coursework:administergrades', $this->coursework->get_context())) {
                $options['assessor_1'] = get_string('assessorupload', 'coursework', '1');
                if ($this->coursework->get_max_markers() >= 2) {
                    $options['assessor_2'] = get_string('assessorupload', 'coursework', '2');
                }
                if ($this->coursework->get_max_markers() >= 3) {
                    $options['assessor_3'] = get_string('assessorupload', 'coursework', '3');
                }
            }

            $capability = ['mod/coursework:addagreedgrade', 'mod/coursework:editagreedgrade', 'mod/coursework:administergrades'];
            if (has_any_capability($capability, $this->coursework->get_context())) {
                $options['final_agreed_1'] = get_string('finalagreed', 'coursework');
            }

            $mform->addElement('select', 'feedbackstage', get_string('feedbackstage', 'coursework'), $options);
        } else {
            $mform->addElement('hidden', 'feedbackstage', 'assessor_1');
            $mform->setType('feedbackstage', PARAM_RAW);
        }

        // Disable overwrite current feedback files checkbox if user doesn't have edit capability
        if (!has_capability('mod/coursework:editinitialgrade', $this->coursework->get_context())) {
            $mform->disabledIf('overwrite', 'feedbackstage', 'eq', 'initialassessor');
        }

        if (!has_capability('mod/coursework:editagreedgrade', $this->coursework->get_context()) && !has_capability('mod/coursework:administergrades', $this->coursework->get_context()) ) {
            $mform->disabledIf('overwrite', 'feedbackstage', 'eq', 'final_agreed_1');
        }

        $this->add_action_buttons(true, get_string('uploadfeedbackzip', 'coursework'));
    }

    public function display() {
        return $this->_form->toHtml();
    }

}
