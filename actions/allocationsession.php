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

require_once("../../../config.php");

global $SESSION;


$assesorselect = required_param_array('assesorselect',PARAM_RAW);
$assesorselectvalue = required_param_array('assesorselectvalue',PARAM_RAW);
$pinnedchk = optional_param_array('pinned',array(),PARAM_RAW);
$pinnedchkval = optional_param_array('pinnedvalue',array(),PARAM_RAW);
$moderatorselect = optional_param_array('moderatorselect',array(),PARAM_RAW);
$moderatorselectvalue = optional_param_array('moderatorselectvalue',array(),PARAM_RAW);
$samplechk = optional_param_array('sample',array(),PARAM_RAW);
$samplechkvalue = optional_param_array('samplevalue',array(),PARAM_RAW);
$coursemoduleid = required_param('coursemoduleid', PARAM_INT);

if (!isset($SESSION->coursework_allocationsessions))    {
    $SESSION->coursework_allocationsessions = array();
}

if (!isset($SESSION->coursework_allocationsessions[$coursemoduleid]))   {
    $SESSION->coursework_allocationsessions[$coursemoduleid] = array();
}



for($i = 0; $i < count($assesorselect); $i++) {
    $SESSION->coursework_allocationsessions[$coursemoduleid][$assesorselect[$i]] = $assesorselectvalue[$i];
}

for($i = 0; $i < count($pinnedchk); $i++) {
    $SESSION->coursework_allocationsessions[$coursemoduleid][$pinnedchk[$i]] = $pinnedchkval[$i];
}

for($i = 0; $i < count($moderatorselect); $i++) {
    $SESSION->coursework_allocationsessions[$coursemoduleid][$moderatorselect[$i]] = $moderatorselectvalue[$i];
}

for($i = 0; $i < count($samplechk); $i++) {
    $SESSION->coursework_allocationsessions[$coursemoduleid][$samplechk[$i]] = $samplechkvalue[$i];
}

