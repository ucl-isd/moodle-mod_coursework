/**
 * Marking guide enhancements for mod_coursework.
 *
 * @module     mod_coursework/marking_guide
 * @copyright  2026 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';

/**
 * Initialize the marking guide score field enhancements.
 */
export const init = async() => {
    const root_element = document.getElementById('coursework-markingform');
    if (!root_element) {
        return;
    }

    try {
        // Fetch the "Mark" string from lang file.
        const mark_label_text = await getString('mark', 'mod_coursework');

        const score_cells = root_element.querySelectorAll('td.score');

        score_cells.forEach(cell => {
            const score_input = cell.querySelector('input');
            const max_score_div = cell.querySelector('div');

            if (score_input && max_score_div) {
                // Extract only numbers/decimals from the max score text.
                const max_score = max_score_div.textContent.replace(/[^0-9.]/g, '');

                // 1. Enhance the input field.
                score_input.setAttribute('required', 'required');
                score_input.setAttribute('type', 'number');
                score_input.setAttribute('min', '0');
                score_input.setAttribute('step', 'any');

                if (max_score) {
                    score_input.setAttribute('max', max_score);
                }

                // 2. Create and insert the Label.
                const score_label = document.createElement('label');
                score_label.textContent = `${mark_label_text} (0–${max_score})`;

                if (score_input.id) {
                    score_label.setAttribute('for', score_input.id);
                }

                // Insert at the top of the cell.
                cell.insertBefore(score_label, cell.firstChild);

                // 3. Clean up the old overhanging div.
                max_score_div.remove();
            }
        });
    } catch (error) {
        // console.error("Marking Guide: Error enhancing score fields", error);
    }
};