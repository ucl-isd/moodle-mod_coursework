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

use mod_coursework\grading_report;
use mod_coursework\models\coursework;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * Shows the publish button at the bottom of the grader report.
 */
class publish_form extends moodleform {

    public function definition() {

        /* @var coursework $coursework */
        $coursework = $this->_customdata['coursework'];
        $this->_form->addElement('hidden', 'id', $this->_customdata['cmid']);
        $this->_form->setType('id', PARAM_INT);

        $attributes = array();
        $explaintext = false;
        $should_release = true;

        if (!$coursework->has_stuff_to_publish()) {

            $should_release = false;
            $explaintext = get_string('nofinalgradedworkyet', 'mod_coursework');

        } else if ($coursework->blindmarking_enabled() && $coursework->has_stuff_to_publish()) {

            $allocatable = ($coursework->is_configured_to_have_group_submissions())? 'group' : 'user';
            $explaintext = '<div class ="anonymity_warning">'.get_string('anonymity_warning_'. $allocatable, 'mod_coursework').'</div>';

        } else if ($coursework->blindmarking_enabled() &&
                   $coursework->moderation_enabled() &&
                   $coursework->unmoderated_work_exists()) {

            $should_release = false;
            $explaintext = get_string('unmoderatedworkexists', 'mod_coursework');
        }

        // Confusing to show them the button with nothing to release.
        if ($coursework->has_stuff_to_publish() && $should_release) {
            $buttontext = get_string('publish', 'coursework');
            $this->_form->addElement('submit', 'publishbutton', $buttontext, $attributes);
        }

        if ($explaintext) {
            $this->_form->addElement('static', 'explainwhycantpublish', '', $explaintext);
        }

    }

    /**
     * Bypasses the bit that echos the HTML so we can join it to a string.
     *
     * @return string
     */
    public function display() {
        return $this->_form->toHtml();
    }
}
