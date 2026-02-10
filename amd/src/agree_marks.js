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
 * Agree Marks module for mod_coursework.
 *
 * @module     mod_coursework/agree_marks
 * @copyright  2026 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initialize the mark syncing logic.
 */
export const init = () => {
    const sourceBlocks = document.querySelectorAll('#review-source-wrapper .review-marks');

    if (sourceBlocks.length === 0) {
        return;
    }

    const targetCells = document.querySelectorAll(
        '.gradingform_rubric .description, #advancedgrading-criteria .descriptionreadonly'
    );

    sourceBlocks.forEach((block, index) => {
        if (targetCells[index]) {
            targetCells[index].appendChild(block);
        }
    });
};