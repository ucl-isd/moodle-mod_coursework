/**
 * Total mark calculation module for mod_coursework.
 *
 * @module     mod_coursework/total_mark
 * @copyright  2026 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Update the UI elements.
 *
 * @param {Number} totalScore The current total score.
 * @param {Number} totalMax The maximum possible score.
 */
const updateUi = (totalScore, totalMax) => {
    const score = +totalScore.toFixed(2);
    const max = +totalMax.toFixed(2);
    const percent = max > 0 ? (score / max) * 100 : 0;
    const displayPercent = +Math.max(0, Math.min(percent, 100)).toFixed(2);

    document.querySelectorAll('[data-region="total-mark-display"]').forEach(region => {
        // Update the text spans.
        region.querySelector('.total-score-text').textContent = score.toString();
        region.querySelector('.total-max-text').textContent = max.toString();
        region.querySelector('.total-percent').textContent = displayPercent.toString();

        // Update the progress bar.
        const bar = region.querySelector('.total-progress-bar');
        if (bar) {
            bar.style.width = displayPercent + '%';
            bar.setAttribute('aria-valuenow', score.toString());
            bar.setAttribute('aria-valuemax', max.toString());
        }
    });
};

/**
 * Calculate totals for Rubric.
 */
const calculateRubric = () => {
    const rubric = document.querySelector('.gradingform_rubric');
    if (!rubric) {
        return;
    }

    let runningTotal = 0;
    let runningMax = 0;

    rubric.querySelectorAll('.criterion').forEach(row => {
        const selected = row.querySelector('.level.checked .scorevalue');
        if (selected) {
            runningTotal += parseFloat(selected.textContent) || 0;
        }

        let rowMax = 0;
        row.querySelectorAll('.scorevalue').forEach(span => {
            const val = parseFloat(span.textContent) || 0;
            if (val > rowMax) {
                rowMax = val;
            }
        });
        runningMax += rowMax;
    });

    updateUi(runningTotal, runningMax);
};

/**
 * Calculate totals for Marking Guide.
 */
const calculateGuide = () => {
    const guide = document.querySelector('.gradingform_guide');
    if (!guide) {
        return;
    }

    let runningTotal = 0;
    let runningMax = 0;

    guide.querySelectorAll('.score input[type="number"]').forEach(input => {
        const val = parseFloat(input.value);
        const max = parseFloat(input.getAttribute('max'));
        if (!isNaN(val)) {
            runningTotal += val;
        }
        if (!isNaN(max)) {
            runningMax += max;
        }
    });

    updateUi(runningTotal, runningMax);
};

/**
 * Initialize the grading tracker listeners.
 */
const initGradingTracker = () => {
    const rubric = document.querySelector('.gradingform_rubric');
    const guide = document.querySelector('.gradingform_guide');

    if (rubric) {
        const observer = new MutationObserver(calculateRubric);
        observer.observe(rubric, {
            attributes: true,
            subtree: true,
            attributeFilter: ['class']
        });
        calculateRubric();
    }

    if (guide) {
        document.addEventListener('input', (e) => {
            if (e.target.closest('.gradingform_guide .score')) {
                calculateGuide();
            }
        });
        calculateGuide();
    }
};

/**
 * Init.
 */
export const init = () => {
    const run = () => {
        // A tiny 50ms delay to let the DOM settle.
        setTimeout(() => {
            initGradingTracker();
        }, 50);
    };

    if (document.readyState === 'complete') {
        run();
    } else {
        window.addEventListener('load', run);
    }
};