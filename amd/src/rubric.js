/**
 * Rubric labeling logic for mod_coursework.
 *
 * @module     mod_coursework/rubric
 * @copyright  2026 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';
import log from 'core/log';

/**
 * Initialize the rubric feedback labels.
 */
export const init = async() => {
    // Target the standard Moodle rubric container.
    const rubricContainer = document.querySelector('.gradingform_rubric');
    if (!rubricContainer) {
        return;
    }

    try {
        const feedbackPrefix = await getString('feedbackfor', 'mod_coursework');

        // In a rubric, comments are usually in cells with the 'remark' class.
        const remarkCells = rubricContainer.querySelectorAll('td.remark');

        remarkCells.forEach(cell => {
            const textarea = cell.querySelector('textarea');
            // In rubrics, the description is usually in the same row (tr).
            const parentRow = cell.closest('tr');
            const descriptionCell = parentRow ? parentRow.querySelector('td.description') : null;

            if (textarea && descriptionCell) {
                // Clone the description to strip out extra UI elements.
                const tempDescription = descriptionCell.cloneNode(true);

                const criterionName = tempDescription.textContent.trim();

                // Create the label.
                const feedbackLabel = document.createElement('label');
                feedbackLabel.textContent = `${feedbackPrefix} ${criterionName}`;

                if (textarea.id) {
                    feedbackLabel.setAttribute('for', textarea.id);
                }

                // Insert it before the textarea in the remark cell.
                cell.insertBefore(feedbackLabel, textarea);
            }
        });
    } catch (error) {
        log.info("Catch from rubric.js: ", error);
    }
};