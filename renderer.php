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
 * Renderer for the coursework module.
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2011 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @var $OUTPUT core_renderer
 */
global $CFG, $OUTPUT;

/**
 * Moodle has some weird rule for putting renderers in subdirectories (which we are not using). This seems like
 * a cleaner approach.
 */
foreach (scandir($CFG->dirroot . '/mod/coursework/renderers') as $filename) {
    $path = $CFG->dirroot . '/mod/coursework/renderers' . '/' . $filename;
    if (is_file($path)) {
        require $path;
    }
}

// Now moving them to the classes directory.

require_once($CFG->dirroot . '/lib/filelib.php');
