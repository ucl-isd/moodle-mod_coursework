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
//
/**
 * Rubric scripts for coursework.
 *
 * @author    Conn Warwicker <conn.warwicker@catalyst-eu.net>
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const init = () => {
    // Move the points to the right. The input must stay a direct child of the cell, as the ranged rubric's own JS
    // finds the levels from the input's parent, so align the cell instead of wrapping the input.
    const cells = document.querySelectorAll('.gradingform_rubric_ranges table td.points');
    for (const cell of cells) {
        if (!cell.querySelector('input.rangegrade')) {
            continue;
        }
        cell.classList.add('text-end');
        // Keep the remark box aligned as normal.
        cell.querySelector('.remark')?.classList.add('text-start');
    }
};
