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
    const source_blocks = document.querySelectorAll('#review-source-wrapper .review-marks');
    const target_cells = document.querySelectorAll(
        '.gradingform_rubric .description, #advancedgrading-criteria .descriptionreadonly'
    );

    if (source_blocks.length === 0) {
        return;
    }

    source_blocks.forEach((block, index) => {
        if (target_cells[index]) {
            target_cells[index].appendChild(block);
        }
    });
};