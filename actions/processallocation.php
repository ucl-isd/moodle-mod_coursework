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

// This stops the page from aborting when an ajax call disconnects
ignore_user_abort(true);

// We have to set the time limit to 0 as depending on
set_time_limit ( 0 );

use mod_coursework\models\coursework;
use mod_coursework\allocation\widget;

require_once(dirname(__FILE__).'/../../../config.php');

global $CFG, $OUTPUT, $DB, $PAGE;

require_once($CFG->dirroot.'/mod/coursework/lib.php');

$coursemoduleid = required_param('coursemoduleid', PARAM_INT);
$coursemodule = get_coursemodule_from_id('coursework', $coursemoduleid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $coursemodule->course], '*', MUST_EXIST);
$coursework = $DB->get_record('coursework', ['id' => $coursemodule->instance], '*', MUST_EXIST);
$coursework = coursework::find($coursework);
$assessorallocationstrategy = optional_param('assessorallocationstrategy', false, PARAM_TEXT);

require_login($course, true, $coursemodule);

require_capability('mod/coursework:allocate', $PAGE->context, null, true, "Can't allocate here - permission denied.");

if ($assessorallocationstrategy) {
    if ($assessorallocationstrategy != $coursework->assessorallocationstrategy) {
        $coursework->set_assessor_allocation_strategy($assessorallocationstrategy);
    }
    $coursework->save_allocation_strategy_options($assessorallocationstrategy);
}

$coursework->save();

$allocator = new \mod_coursework\allocation\auto_allocator($coursework);
$allocator->process_allocations();

 echo $coursework->name. "re-allocated";
