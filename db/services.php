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
 * Coursework web services defintions.
 *
 * @package   mod_coursework
 * @category  event
 * @copyright 2025 UCL
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_coursework_delete_extension' => [
        'classname' => 'mod_coursework\external\delete_extension',
        'description' => 'Delete an extended deadline',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => '', // Handled within the class.
    ],
    'mod_coursework_get_grading_table_row_data' => [
        'classname' => 'mod_coursework\external\get_grading_table_row_data',
        'description' => 'Get grading table row data to re-render from JS when edited via modal',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => '', // Handled within the class.
    ],
    'mod_coursework_clearannotations' => [
        'classname' => 'mod_coursework\external\clearannotations',
        'description' => 'Clear user file annotations',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => '', // Handled within the class.
    ],
    'mod_coursework_allocationpintoggle' => [
        'classname' => 'mod_coursework\external\allocationpintoggle',
        'description' => 'Toggle pinning of assessor allocation',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => '', // Handled within the class.
    ],
    'mod_coursework_allocatableinsampletoggle' => [
        'classname' => 'mod_coursework\external\allocatableinsampletoggle',
        'description' => 'Toggle alloctable inclusion in sample',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => '', // Handled within the class.
    ],
    'mod_coursework_assessorallocation' => [
        'classname' => 'mod_coursework\external\assessorallocation',
        'description' => 'Allocate assessor to assessable',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => '', // Handled within the class.
    ],
    'mod_coursework_get_turnitin_similarity_links' => [
        'classname' => 'mod_coursework\external\get_turnitin_similarity_links',
        'description' => 'Get Turnitin similarity links for grading report',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => '', // Handled within the class.
    ],
];
