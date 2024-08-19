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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/coursework/backup/moodle2/restore_coursework_stepslib.php');

class restore_coursework_activity_task extends restore_activity_task {

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder.
     *
     * @return array of restore_decode_rule
     */
    static public function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('COURSEWORKBYID',
                                           '/mod/coursework/view.php?id=$1',
                                           'course_module');
        $rules[] = new restore_decode_rule('CORSEWORKINDEX',
                                           '/mod/corsework/index.php?id=$1',
                                           'course_module');

        return $rules;

    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder.
     *
     * @return array
     */
    static public function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content('coursework', array('intro'), 'assign');

        return $contents;
    }

    /**
     * Define (add) particular settings this activity can have.
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have.
     */
    protected function define_my_steps() {
        // Only has one structure step.
        $this->add_step(new restore_coursework_activity_structure_step('coursework_structure', 'coursework.xml'));
    }

}
