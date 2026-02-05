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
    const targetCells = document.querySelectorAll(
        '.gradingform_rubric .description, #advancedgrading-criteria .descriptionreadonly'
    );

    if (sourceBlocks.length === 0) {
        return;
    }

    sourceBlocks.forEach((block, index) => {
        if (targetCells[index]) {
            targetCells[index].appendChild(block);
        }
    });
};