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

import {add as addToast} from 'core/toast';
import {getString} from 'core/str';

/**
 * Add listeners for clicks on the "Copy to agreed" feedback links which copy this feedback to the agreed form field.
 */
const addCopyToAgreedListeners = () => {
    document.querySelectorAll('[data-action="mod-coursework-copy-feedback"]').forEach((button) => {
        button.addEventListener("click", async (e) => {
            e.preventDefault();
            const textArea = document.getElementById(button.dataset.copyTo);
            const copySource = document.getElementById(button.dataset.copySource);
            if (copySource && !textArea.value.includes(copySource.textContent)) {
                // If text area is not empty, add 2 blanks lines before appending new text.
                if (textArea.value.length !== 0) {
                    textArea.value += "\n\n";
                }
                textArea.value += copySource.textContent;
                addToast(getString('copied', 'coursework'), {type: 'success'});
            }
            button.setAttribute('disabled', 'disabled');
        });
    });
};

/**
 * Add listeners for clicks on the "Calculate average mark" links which populate mark with an average of all markers' marks.
 */
const addCalculateAverageGradeListeners = () => {
    document.querySelectorAll('[data-action="mod-coursework-calculate-average-grade"]').forEach((button) => {
        button.addEventListener("click", async (e) => {
            e.preventDefault();
            const btn = e.target.classList.contains('btn') ? e.target : e.target.closest('.btn');
            const marksDiv = btn.closest('.review-marks');
            const marks = Array.from(marksDiv.querySelectorAll('.total-score-text')).map(
                el => { return parseInt(el.textContent); }
            );
            if (marks.length >= 0) {
                const averageMark = ((marks.reduce((acc, n) => acc + n, 0)) / marks.length).toFixed(2);
                const markInput = document.getElementById(btn.dataset.target);
                markInput.value = averageMark;
                // Ensure observers elsewhere (e.g. total mark box) are aware.
                markInput.dispatchEvent(new Event('input', { bubbles: true }));
                addToast(getString('averagemarkadded', 'coursework'), {type: 'success'});
            }
        });
    });
};

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

    addCopyToAgreedListeners();
    addCalculateAverageGradeListeners();
};