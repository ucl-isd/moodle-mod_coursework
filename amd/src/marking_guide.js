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
 * Marking guide enhancements for mod_coursework.
 *
 * @module     mod_coursework/marking_guide
 * @copyright  2026 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as FeedbackPercent from "mod_coursework/marking_guide_feedback_percent";

const MAX_INPUT_LENGTH = 7;

/**
 * Prevent invalid entries into the score input.
 * @param {event} event
 */
const preventInvalidNumber = (event) => {
    // Field will allow user to enter e for scientific notation - stop that.
    if (!/[0-9.]/.test(event.key)) {
        event.preventDefault();
        return;
    }
    if (
        event.target.value.length >= MAX_INPUT_LENGTH
        && event.key !== 'Backspace'
        && event.key !== 'Delete'
    ) {
        event.preventDefault();
    }
};

/**
 * Set up the marking guide score field enhancements.
 *
 * @param {String} markLabelText The localized string for "Mark" passed from Mustache.
 */
const setUpUI = (markLabelText) => {
    const rootElement = document.getElementById('coursework-markingform');
    if (!rootElement) {
        return;
    }

    const scoreCells = rootElement.querySelectorAll('td.score');

    scoreCells.forEach(cell => {
        const scoreInput = cell.querySelector('input');
        const maxScoreDiv = cell.querySelector('div');

        if (scoreInput && maxScoreDiv) {
            // Extract only numbers/decimals from the max score text.
            const maxScore = maxScoreDiv.textContent.replace(/[^0-9.]/g, '');

            // 1. Enhance the input field.
            scoreInput.setAttribute('required', 'required');
            scoreInput.setAttribute('type', 'number');
            scoreInput.setAttribute('min', '0');
            scoreInput.setAttribute('step', 'any');

            if (maxScore) {
                scoreInput.setAttribute('max', maxScore);
            }

            // 2. Create and insert the Label.
            const scoreLabel = document.createElement('label');
            scoreLabel.textContent = `${markLabelText} (0–${maxScore})`;

            if (scoreInput.id) {
                scoreLabel.setAttribute('for', scoreInput.id);
            }

            // Insert at the top of the cell.
            cell.insertBefore(scoreLabel, cell.firstChild);

            // 3. Clean up the old overhanging div.
            maxScoreDiv.remove();
        }

        scoreInput.addEventListener('keypress', preventInvalidNumber);
    });

    // CTP-5833 - when focus is on a mark input field and user presses return, avoid implicit click on frequent comments button.
    // (I.e. add type="button" because core code in grade/grading/form/guide/renderer.php doesn't do so).
    const insertFrequentCommentButtons = document.querySelectorAll('button.commentchooser.btn');
    insertFrequentCommentButtons.forEach((btn) => {
        btn.setAttribute('type', 'button');
    });
};

/**
 * Initialise the marking guide score field enhancements.
 *
 * @param {String} markLabelText The localized string for "Mark" passed from Mustache.
 * @param {boolean} enterPercentGrades whether user want to enter grades as percentages (user preference).
 * @param {boolean} percentGradesAllowed whether user has capability to enter percent grades.
 */
export const init = (markLabelText, enterPercentGrades, percentGradesAllowed) => {
    setUpUI(markLabelText);
    // Once the new fields are added, can initialise the percentage field JS (which depends on them).
    if (percentGradesAllowed) {
        FeedbackPercent.init(enterPercentGrades);
    }
};
