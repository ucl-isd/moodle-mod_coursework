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

namespace mod_coursework\controllers;

use mod_coursework\models\coursework;

/**
 * Class grading_controller
 * @package mod_coursework\controllers
 */
class grading_controller extends controller_base {

    public function get_remain_rows_grading_table($options) {

        global $DB, $PAGE;
        $courseworkrecord = $DB->get_record('coursework', ['id' => $options['courseworkid']], '*', MUST_EXIST);
        // $coursework = mod_coursework\models\coursework::find($coursework_record);
        $coursework = coursework::find($courseworkrecord, false);

        $coursework->coursemodule = get_coursemodule_from_instance('coursework', $coursework->id, $coursework->course, false, MUST_EXIST);
        require_login($coursework->course, false, $coursework->coursemodule);
        $gradingreport = $coursework->renderable_grading_report_factory($options);

        $tablerows = $gradingreport->get_table_rows_for_page();
        $cellhelpers = $gradingreport->get_cells_helpers();
        $subrowhelper = $gradingreport->get_sub_row_helper();

        $gradingreportrenderer = $PAGE->get_renderer('mod_coursework', 'grading_report');
        $tablehtml = $gradingreportrenderer->make_rows($tablerows, $cellhelpers, $subrowhelper, $coursework->has_multiple_markers());
        return $tablehtml;
    }

}
