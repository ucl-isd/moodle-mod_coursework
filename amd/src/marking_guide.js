/**
 * Marking guide enhancements for mod_coursework.
 *
 * @module     mod_coursework/marking_guide
 * @copyright  2026 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';
import log from 'core/log';

/**
 * Initialize the marking guide score field enhancements.
 */
export const init = async() => {
    const rootElement = document.getElementById('coursework-markingform');
    if (!rootElement) {
        return;
    }

    try {
        // Fetch the "Mark" string from lang file.
        const markLabelText = await getString('mark', 'mod_coursework');

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
        });
    } catch (error) {
        log.info("Catch from marking_guide.js: ", error);
    }
};