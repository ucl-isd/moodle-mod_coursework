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

use mod_coursework\allocation\allocatable;
use mod_coursework\models\deadline_extension;

/**
 * Class deadline_extension_renderer is responsible for rendering pages and objects to do with
 * the deadline_extension model.
 *
 * @package mod_coursework\renderers
 */
class deadline_extension_renderer {

    /**
     * @param array $vars
     * @throws \coding_exception
     */
    public function show_page($vars) {
        global $PAGE, $SITE, $OUTPUT;

        /**
         * @var allocatable $allocatable
         */
        $allocatable = $vars['deadline_extension']->get_allocatable();

        $PAGE->set_pagelayout('standard');
        $heading = 'Deadline extension for ' . $allocatable->name();
        $PAGE->navbar->add($heading);
        $PAGE->set_title($SITE->fullname);
        $PAGE->set_heading($heading);

        $html = '';

        echo $OUTPUT->header();
        echo $html;
        echo $OUTPUT->footer();
    }

    /**
     * @param array $vars
     * @throws \coding_exception
     */
    public function new_page($vars) {
        global $PAGE, $SITE, $OUTPUT;

        $PAGE->set_pagelayout('standard');
        $PAGE->navbar->add('New deadline extension');
        $PAGE->set_title($SITE->fullname);
        $PAGE->set_heading($SITE->fullname);

        $allocatable = $vars['deadline_extension']->get_allocatable();

        $html = '<h1>Adding a new extension to the deadline for '.$allocatable->name().'</h1>';

        echo $OUTPUT->header();
        echo $html;
        $vars['form']->display();
        echo $OUTPUT->footer();

    }

    /**
     * @param array $vars
     * @throws \coding_exception
     */
    public function edit_page($vars) {
        global $PAGE, $SITE, $OUTPUT;

        $allocatable = $vars['deadline_extension']->get_allocatable();

        $PAGE->set_pagelayout('standard');
        $PAGE->navbar->add('Edit deadline extension for '.$allocatable->name());
        $PAGE->set_title($SITE->fullname);
        $PAGE->set_heading($SITE->fullname);

        $html = '<h1>Editing the extension to the deadline for ' . $allocatable->name() . '</h1>';

        echo $OUTPUT->header();
        echo $html;
        $vars['form']->display();
        echo $OUTPUT->footer();
    }

}
