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
 * Output class to assemble data needed to display grade class boundaries page.
 *
 * @package    mod_coursework
 * @copyright  2025 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\output;


/**
 * Output class to assemble data needed to display grade class boundaries page.
 *
 * @package    mod_coursework
 * @copyright  2025 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_class_boundaries implements \renderable, \templatable {
    /**
     * Export data for template.
     * @return void
     */
    public function export_for_template(\renderer_base $output): object {
        global $DB;
        $selectedtemplateid = optional_param('templateid', 0, PARAM_INT);
        $templates = $DB->get_records('coursework_class_boundary_templates', null, 'id');
        if ($selectedtemplateid && isset($templates[$selectedtemplateid])) {
            $templates[$selectedtemplateid]->selected = true;
        }
        $data = (object)[
            'templates' => array_values($templates),
            'hastemplates' => !empty($templates),
            'templateid' => $selectedtemplateid,
        ];
        return $data;
    }
}
