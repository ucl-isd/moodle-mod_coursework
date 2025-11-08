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

namespace mod_coursework\render_helpers\grading_report\cells;

use coding_exception;
use html_table_cell;
use mod_coursework\models\coursework;
use mod_coursework\router;
use mod_coursework_object_renderer;

/**
 * Class cell_base
 */
abstract class cell_base implements cell_interface {
    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @param array $items
     */
    public function __construct($items = []) {
        $this->coursework = $items['coursework'];
        $this->after_initialisation($items);
    }

    /**
     * Makes the links to go in the top of a sortable column.
     *
     * @param $displayname
     * @param string $field
     * @param $sorthow
     * @param string $sortby The current sort from the URL.
     * @param string $tablename
     * @return string
     * @throws coding_exception
     */
    protected function helper_sortable_heading($displayname, $field, $sorthow, $sortby = '', $tablename = '') {
        return $displayname;
    }

    /**
     * @return router
     */
    protected function get_router() {
        return router::instance();
    }

    /**
     * @return mod_coursework_object_renderer
     */
    protected function get_renderer() {
        global $PAGE;

        return $PAGE->get_renderer('mod_coursework', 'object');
    }

    /**
     * @return string|void
     */
    public function cell_name() {
        $namespacedclass = get_class($this);
        $bits = explode('\\', $namespacedclass);
        return end($bits);
    }

    /**
     * @param string $content
     * @return html_table_cell
     */
    protected function get_new_cell_with_class($content = '') {
        return '
        <td data-class-name="' . get_class($this) . '" class="' . $this->cell_name() . '">' . $content . '
        </td>
        ';
    }

    /**
     * @param array $data
     * @return html_table_cell
     */
    protected function get_new_cell_with_order_data($data) {
        return '<td class="' . $this->cell_name() . '" data-order="' . $data['@data-order'] . '">' . $data['display'] . '</td>';
    }

    /**
     * Override for specific constructor stuff.
     *
     * @param array $items
     * @return void
     */
    protected function after_initialisation($items) {
    }

    /**
     * Override for the header help message
     */
    public function get_table_header_help_icon() {
    }
}
