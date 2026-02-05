/**
 * Rubric labeling logic for mod_coursework.
 *
 * @module     mod_coursework/rubric
 * @copyright  2026 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';

/**
 * Initialize the rubric feedback labels.
 */
export const init = async() => {
    // Target the standard Moodle rubric container.
    const rubric_container = document.querySelector('.gradingform_rubric');
    if (!rubric_container) {
        return;
    }

    try {
        const feedback_prefix = await getString('feedbackfor', 'mod_coursework');

        // In a rubric, comments are usually in cells with the 'remark' class.
        const remark_cells = rubric_container.querySelectorAll('td.remark');

        remark_cells.forEach(cell => {
            const text_area = cell.querySelector('textarea');
            // In rubrics, the description is usually in the same row (tr).
            const parent_row = cell.closest('tr');
            const description_cell = parent_row ? parent_row.querySelector('td.description') : null;

            if (text_area && description_cell) {
                // Clone the description to strip out extra UI elements.
                const temp_description = description_cell.cloneNode(true);

                const criterion_name = temp_description.textContent.trim();

                // Create the label.
                const feedback_label = document.createElement('label');
                feedback_label.textContent = `${feedback_prefix} ${criterion_name}`;

                if (text_area.id) {
                    feedback_label.setAttribute('for', text_area.id);
                }

                // Insert it before the textarea in the remark cell.
                cell.insertBefore(feedback_label, text_area);
            }
        });
    } catch (error) {
        // console.error("Rubric Labels: Could not load string.", error);
    }
};