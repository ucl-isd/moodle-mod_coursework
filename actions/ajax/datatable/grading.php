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

ob_start();
require_once(dirname(__FILE__) . '/../../../../../config.php');

global $CFG, $USER, $DB, $SESSION, $PAGE;

$PAGE->set_context(context_system::instance());

$group = required_param('group', PARAM_INT);
$perpage = required_param('perpage', PARAM_INT);
$sortby = required_param('sortby', PARAM_ALPHA);
$sorthow = required_param('sorthow', PARAM_ALPHA);
$courseworkid = required_param('courseworkid', PARAM_INT);
$unallocated = optional_param('unallocated', false, PARAM_BOOL);

// Grading report display options.
$reportoptions = [];
if ($unallocated) {
    $reportoptions['unallocated'] = true;
}

$reportoptions['group'] = $group;
$reportoptions['perpage'] = $perpage;
$reportoptions['sortby'] = $sortby;
$reportoptions['sorthow'] = $sorthow;
$reportoptions['showsubmissiongrade'] = false;
$reportoptions['showgradinggrade'] = false;
$reportoptions['courseworkid'] = $courseworkid;
$reportoptions['mode'] = \mod_coursework\grading_report::MODE_GET_REMAIN_RECORDS;

//$controller = new mod_coursework\controllers\grading_controller(['courseworkid' => $report_options, 'allocatableid' => $USER->id, 'allocatabletype' => $USER->id]);
$controller = new mod_coursework\controllers\grading_controller([]);
sleep(10);
$tablehtml = $controller->get_remain_rows_grading_table($reportoptions);
if (ob_get_contents()) {
    ob_end_clean();
}

echo $tablehtml;
