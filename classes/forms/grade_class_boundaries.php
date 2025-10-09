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
 * Creates mform for grade class boundary editing.
 *
 * @package    mod_coursework
 * @copyright  2025 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\forms;

require_once("$CFG->dirroot/lib/formslib.php");

use mod_coursework\models\coursework;

use moodleform;

/**
 * Simple form providing a set of grade class boundary percentages.
 */
class grade_class_boundaries extends moodleform {
    /**
     * Makes the form elements.
     */
    public function definition() {

        $mform =& $this->_form;

        $mform->addElement('hidden', 'courseworkid', $this->_customdata['courseworkid']);
        $mform->setType('courseworkid', PARAM_INT);

        $coursework = $this->get_coursework();
        $titleelement = $coursework ? $coursework->name : get_string('systemdefault', 'coursework');
        $mform->addElement(
            'html',
            \html_writer::tag('h1', get_string('gradeboundariessettingfor', 'coursework', $titleelement))
        );

        $mform->addElement('html', \html_writer::tag('p', get_string('automaticagreementrange_form_desc', 'coursework')));

        $defaults = $this->get_default_boundaries();

        $maxbands = 6;
        $mform->addElement('html', '<hr/>');
        for ($i = 1; $i <= $maxbands; $i++) {
            $mform->addElement('float', "gradeboundarytop-$i", get_string('gradeboundarytop', 'mod_coursework', $i));
            $mform->setType("gradeboundarytop-$i", PARAM_FLOAT);
            $default = $defaults[$i - 1][1] ?? null;
            if ($default !== null) {
                $mform->setDefault("gradeboundarytop-$i", number_format($default, 2));    
            }

            $mform->addElement('float', "gradeboundarybottom-$i", get_string('gradeboundarybottom', 'mod_coursework', $i));
            $mform->setType("gradeboundarybottom-$i", PARAM_FLOAT);
            $default = $defaults[$i - 1][0] ?? null;
            if ($default !== null) {
                $mform->setDefault("gradeboundarybottom-$i", number_format($default, 2));
            }

            $mform->addElement('html', '<hr/>');
        }

        $this->add_action_buttons();

    }

    /**
     * Get the default grade boundaries.
     * @return array[]
     */
    protected function get_default_boundaries(): array {
        return [
            [70.00, 100.00],
            [60.00, 69.99],
            [50.00, 59.99],
            [40.00, 49.99],
            [1.00, 39.99],
            [0.00, 0.99],
        ];
    }

    /**
     * Get the coursework for this form.  Null means we are setting default boundaries at system level.
     * @return coursework|null
     */
    protected function get_coursework(): ?coursework {
        return  $this->_customdata['courseworkid']
            ? coursework::find($this->_customdata['courseworkid'])
            : null;
    }
}
