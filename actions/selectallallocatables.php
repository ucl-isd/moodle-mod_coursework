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

use mod_coursework\models\coursework;

require_once(dirname(__FILE__).'/../../../config.php');

global $CFG, $OUTPUT, $DB, $PAGE;

require_once($CFG->dirroot.'/mod/coursework/lib.php');

$coursemoduleid = required_param('coursemoduleid', PARAM_INT);
$stagenumber = required_param('stage', PARAM_INT);
$coursemodule = get_coursemodule_from_id('coursework', $coursemoduleid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $coursemodule->course], '*', MUST_EXIST);
$coursework = $DB->get_record('coursework', ['id' => $coursemodule->instance], '*', MUST_EXIST);
$coursework = coursework::find($coursework);
$assessorallocationstrategy = optional_param('assessorallocationstrategy', false, PARAM_TEXT);

if ($stagenumber > 0) {

    if (!isset($SESSION->allocate_page_selectentirestage[$coursework->id()]['assessor_'.$stagenumber])) {
        $SESSION->allocate_page_selectentirestage[$coursework->id()]['assessor_'.$stagenumber] = 0;

    }

    $SESSION->allocate_page_selectentirestage[$coursework->id()]['assessor_'.$stagenumber] = !$SESSION->allocate_page_selectentirestage[$coursework->id()]['assessor_'.$stagenumber];

} else {
    if (!isset($SESSION->allocate_page_selectentirestage[$coursework->id()]['moderator'])) {
        $SESSION->allocate_page_selectentirestage[$coursework->id()]['moderator'] = 0;

    }

    $SESSION->allocate_page_selectentirestage[$coursework->id()]['moderator'] = !$SESSION->allocate_page_selectentirestage[$coursework->id()]['moderator'];
}

