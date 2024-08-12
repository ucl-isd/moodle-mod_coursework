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

global $CFG;

require_once($CFG->dirroot . '/mod/coursework/tests/behat/pages/page_base.php');

/**
 * Holds the functions that know about the HTML structure of the student page.
 *
 *
 */
class mod_coursework_behat_gradebook_page extends mod_coursework_behat_page_base {

    /**
     * @param $coursework
     * @param $student
     * @param $grade
     */
    public function should_have_coursework_grade_for_student($coursework, $student, $grade) {
        global $CFG;

        // This changed in 2.8, so we need a different selector
        if ((float)substr($CFG->release, 0, 5) > 2.6) { // 2.8 > 2.6
            $locator = '//th[a[contains(text(), "' . $coursework->name . '")]]/following-sibling::td[2]';
        } else {
            $locator = '//th[a[contains(text(), "' . $coursework->name . '")]]/following-sibling::td[1]';
        }
        $grade_cell = $this->getPage()->find('xpath', $locator);
        $cell_contents = $grade_cell->getText();
        assertEquals($grade, $cell_contents, "Expected the gradebook grade to be '{$grade}', but got '{$cell_contents}'");
    }
}
