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
 * @copyright  2017 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\renderers;

/**
 * Class deadline_extension_renderer is responsible for rendering pages and objects to do with
 * the deadline_extension model.
 *
 * @package mod_coursework\renderers
 */
class personal_deadline_renderer {

    /**
     * @param array $vars
     * @throws \coding_exception
     */
    public function new_page($vars) {
        global $DB, $PAGE, $SITE, $OUTPUT;

        $PAGE->set_pagelayout('standard');
        $PAGE->navbar->add('Personal deadline');
        $PAGE->set_title($SITE->fullname);
        $PAGE->set_heading($SITE->fullname);

        $html = '';

        //if page has been accessed via the set personal deadline page then we dont want to say who set the last personal
        //deadline
        if (empty($vars['params']['multipleuserdeadlines'])) {
            $allocatable = $vars['personal_deadline']->get_allocatable();
            $createdby = $DB->get_record('user', array('id' => $vars['personal_deadline']->createdbyid));
            $lasteditedby = $DB->get_record('user', array('id' => $vars['personal_deadline']->lastmodifiedbyid));

            $html = '<h1> Edit personal deadline for ' . $allocatable->name() . '</h1>';

            if ($createdby) {
                $html .= '<table class = "personal-deadline-details">';
                $html .= '<tr><th>' . get_string('createdby', 'coursework') . '</th><td>' . fullname($createdby) . '</td></tr>';
                if ($lasteditedby) {
                    $html .= '<tr><th>' . get_string('lasteditedby', 'coursework') . '</th><td>' . fullname($lasteditedby) . ' on ' .
                        userdate($vars['personal_deadline']->timemodified) . '</td></tr>';
                }
                $html .= '</table>';
            }
        } else {
            $html = '<h1> Edit personal deadline for ' . get_string('multipleusers', 'mod_coursework') . '</h1>';
        }

        echo $OUTPUT->header();
        echo $html;
        $vars['form']->display();
        echo $OUTPUT->footer();

    }
}
