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

import {getString} from 'core/str';
/**
 * Initialize the mark syncing logic.
 * @param {object} args - Additional arguments.
 */
export const init = async(args) => {
    // If have a different final grading method, we want to see the original marks always regardless of type.
    if (args.differentfinalgradingmethod) {
        document.getElementById('review-source-wrapper').classList.remove('d-none');
        // Also remove the bullet point which shows as default list style type.
        document.querySelectorAll('#review-source-wrapper ul li').forEach(li => {
            li.style.listStyleType = 'none';
        });
        return;
    }

    const sourceBlocks = document.querySelectorAll('#review-source-wrapper .review-marks');

    if (sourceBlocks.length === 0) {
        return;
    }

    const targetCells = document.querySelectorAll(
        '.gradingform_rubric .description,' +
        '#advancedgrading-criteria .descriptionreadonly,' +
        '.gradingform_rubric_ranges .description'
    );

    sourceBlocks.forEach((block, index) => {
        if (targetCells[index]) {
            targetCells[index].appendChild(block);
        }
    });

    // If we are including the final agreed mark in with the others, change the label from "marker" to "agreed mark".
    var title = await getString('agreedmark', 'mod_coursework');
    var els = document.querySelectorAll(
        '.review-marks [class*="mod_coursework-stage-final_agreed_"] div.coursework-progress-container strong'
    );
    els.forEach(el => {
        el.textContent = title + ': ';
    });

    // Append a "Calculate grade" button to the marks for each criterion.
    const table = document.querySelector('#advancedgrading-criteria');
    if (table && (args.method === 'guide' || args.method === 'rubric_ranges')) {
        let cells = [];
        if (args.method === 'guide') {
            cells = table.querySelectorAll('td.score');
        } else if (args.method === 'rubric_ranges') {
            cells = table.querySelectorAll('td.points');
        }
        const linktext = await getString('calculateavggrade', 'mod_coursework');
        cells.forEach(cell => {
            const isRanged = cell.querySelector('input.rangegrade');
            if (isRanged) {
                const div = document.createElement('div');
                const link = document.createElement('a');
                link.href = '#';
                link.dataset.action = 'calculate-avg-grade';
                const icon = document.createElement('i');
                icon.classList.add('fa', 'fa-calculator', 'me-1');
                link.append(icon, linktext);
                div.append(link);
                cell.prepend(div);
            }
        });
    }

    // "Copy to agreed" buttons copy a marker's criterion feedback; "Calculate average grade" links fill in the average mark.
    document.addEventListener('click', (e) => {
        // Use closest() so clicks on the icon inside the button/link are handled too.
        const button = e.target.closest('[data-action]');
        if (!button) {
            return;
        }

        // Copy feedback.
        if (button.dataset.action === 'copy-to-agreed') {
            e.preventDefault();
            const row = button.closest('tr.criterion');
            if (!row) {
                return;
            }

            const remarkInput = row.querySelector('td.remark textarea, td.points div.remark textarea');
            if (remarkInput) {
                // Append the feedback, but not if it's already there exactly.
                const current = remarkInput.value.trim();
                const remark = button.dataset.remark.trim();
                if (!current.includes(remark)) {
                    remarkInput.value = current === '' ? remark : `${current}\n\n${remark}`;
                    remarkInput.dispatchEvent(new Event('input', {bubbles: true}));
                    remarkInput.dispatchEvent(new Event('change', {bubbles: true}));
                }
            }
        } else if (button.dataset.action === 'calculate-avg-grade') {
            e.preventDefault();
            const row = button.closest('tr.criterion');
            if (!row) {
                return;
            }

            const elements = row.querySelectorAll('[data-action="copy-to-agreed"]');
            const scores = [];
            elements.forEach(el => {
                const score = el.dataset.score;
                if (score !== '') {
                    scores.push(score);
                }
            });
            const avg = scores.reduce((a, b) => a + parseFloat(b), 0) / scores.length;

            let input = false;

            // Marking guide.
            if (args.method === 'guide') {
                input = row.querySelector('td.score input');
            } else if (args.method === 'rubric_ranges') {
                input = row.querySelector('td.points input');
            }

            if (input) {
                input.value = avg;
                input.dispatchEvent(new Event('input', {bubbles: true}));
                input.dispatchEvent(new Event('change', {bubbles: true}));
            }
        }
    });

};
