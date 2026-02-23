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

namespace mod_coursework\plagiarism_helpers;

use coding_exception;
use dml_exception;
use Exception;
use moodle_exception;

/**
 * Class turnitin
 * @package mod_coursework\plagiarism_helpers
 */
class turnitin extends base {
    /**
     * @return string
     */
    public function file_submission_instructions() {
        return 'Turnitin allows only one file to be submitted, and restricts the file types to those it can process.';
    }

    /**
     * @return bool
     * @throws Exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function enabled() {
        global $DB, $CFG;

        if ($CFG->enableplagiarism) {
            $plagiarismsettings = (array)get_config('plagiarism_turnitin');
            if (!empty($plagiarismsettings['enabled'])) {
                $params = [
                    'cm' => $this->get_coursework()->get_course_module()->id,
                    'name' => 'use_turnitin',
                    'value' => 1,
                ];
                if ($DB->record_exists('plagiarism_turnitin_config', $params)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return string
     * @throws coding_exception
     */
    public function human_readable_name() {
        return get_string('turnitin', 'plagiarism_turnitin');
    }

    /**
     * From the Turnitin plagiarism plugin (if installed) load the page components.
     * Important to call this if displaying Turnitin links on page by AJAX as they require this JS to launch.
     * @return void
     */
    public static function load_page_components() {
        global $CFG;

        $tiilib = "$CFG->dirroot/plagiarism/turnitin/lib.php";
        if (file_exists($tiilib)) {
            require_once($tiilib);
            if (method_exists('plagiarism_plugin_turnitin', 'load_page_components')) {
                // On the grading overview page, if the marker clicks a TII link, TII JS is required for it to launch.
                $plugin = new \plagiarism_plugin_turnitin();
                $plugin->load_page_components();
            } else {
                debugging("Could not initialise Turnitin plagiarism plugin page components");
            }
        } else {
            debugging("Could not find Turnitin plagiarism plugin lib.php");
        }
    }
}
