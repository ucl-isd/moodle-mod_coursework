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

namespace mod_coursework\allocation\strategy;

    /**
 * lass file for the default allocation strategy.
 *
 * @package    mod_coursework
 * @copyright  2012 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use html_writer;;
/**
 * Class that acts as a default for auto allocation when we don't actually want to allocate at all.
 * Will always return false when asked for the next appropriate teacher.
 */
class none extends base {

    /**
     * Flag that saves us from doing all the allocations and then getting a false response for all the
     * teacher ids.
     *
     * @return bool
     */
    public function autoallocation_enabled() {
        return false;
    }

    /**
     * Some strategies need to be configured. This function will get the HTML for the form that will configure them.
     *
     * @param string $strategypurpose
     * @return string
     */
    public function add_form_elements($strategypurpose = 'assessor') {
        global $PAGE, $CFG;

        $url = "$CFG->wwwroot/mod/coursework/actions/upload_allocations.php?cmid={$PAGE->cm->id}";
        $html = html_writer::link($url, get_string('uploadallocations', 'mod_coursework'));

        return $html;
    }

    /**
     * Saves the form data associated with the instance of this strategy.
     *
     * @return mixed
     */
    public function save_allocation_strategy_options() {
        // Nothing to do here as there are no form elements.
    }

    /**
     * @param $teachers
     * @param $student
     * @return mixed
     */
    protected function list_of_allocatable_teachers_and_their_current_number_of_allocations($teachers, $student) {
        return [];
    }
}
