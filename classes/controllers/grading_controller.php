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

namespace mod_coursework\controllers;

use mod_coursework\models\coursework;

/**
 * Class grading_controller
 * @package mod_coursework\controllers
 */
class grading_controller extends controller_base {

    public function get_remain_rows_grading_table($options) {

        global $DB, $PAGE;
        $coursework_record = $DB->get_record('coursework', array('id' => $options['courseworkid']), '*', MUST_EXIST);
        //$coursework = mod_coursework\models\coursework::find($coursework_record);
        $coursework = coursework::find($coursework_record, false);
        require_login($coursework->course);

        $coursework->coursemodule = get_coursemodule_from_instance('coursework', $coursework->id, $coursework->course, false, MUST_EXIST);
        $grading_report = $coursework->renderable_grading_report_factory($options);

        $tablerows = $grading_report->get_table_rows_for_page();
        $cell_helpers = $grading_report->get_cells_helpers();
        $sub_row_helper = $grading_report->get_sub_row_helper();

        $grading_report_renderer = $PAGE->get_renderer('mod_coursework', 'grading_report');
        $table_html = $grading_report_renderer->make_rows($tablerows, $cell_helpers, $sub_row_helper, $coursework->has_multiple_markers());
        return $table_html;
    }

}
