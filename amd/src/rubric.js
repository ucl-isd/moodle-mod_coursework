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
 * Rubric labeling and interaction logic for mod_coursework.
 *
 * @module     mod_coursework/rubric
 * @copyright  2026 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';

/**
 * Initialize the rubric enhancements.
 */
export const init = async() => {
    const rubricContainer = document.querySelector('.gradingform_rubric');
    if (!rubricContainer) {
        return;
    }

    // Radio button fix.
    rubricContainer.addEventListener('click', (e) => {
        const cell = e.target.closest('.level');
        if (!cell) {
            return;
        }

        const radio = cell.querySelector('input[type="radio"]');
        if (!radio) {
            return;
        }

        // Stop Moodle core JS from interfering.
        e.stopPropagation();

        if (e.target !== radio) {
            radio.checked = true;
        }

        // Update visual states.
        const row = cell.closest('tr');
        row.querySelectorAll('.level').forEach((l) => {
            l.classList.remove('checked', 'currentchecked');
            l.setAttribute('aria-checked', 'false');
        });

        cell.classList.add('checked', 'currentchecked');
        cell.setAttribute('aria-checked', 'true');

        // Trigger change for Moodle's calculation logic.
        radio.dispatchEvent(new Event('change', {bubbles: true}));
    }, true);

    // Feedback labeling logic.
    try {
        const feedbackPrefix = await getString('feedbackfor', 'mod_coursework');
        const remarkCells = rubricContainer.querySelectorAll('td.remark');

        remarkCells.forEach(cell => {
            const textarea = cell.querySelector('textarea');
            const parentRow = cell.closest('tr');
            const descriptionCell = parentRow ? parentRow.querySelector('td.description') : null;

            if (textarea && descriptionCell) {
                const tempDescription = descriptionCell.cloneNode(true);
                const criterionName = tempDescription.textContent.trim();

                const feedbackLabel = document.createElement('label');
                feedbackLabel.textContent = `${feedbackPrefix} ${criterionName}`;

                if (textarea.id) {
                    feedbackLabel.setAttribute('for', textarea.id);
                }

                cell.insertBefore(feedbackLabel, textarea);
            }
        });
    } catch (error) {
        // eslint-disable-next-line no-console
        console.error("Catch from rubric.js:", error);
    }
};