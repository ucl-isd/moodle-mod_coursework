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
 * @param {Number} total_score The current total score.
 * @param {Number} total_max The maximum possible score.
 */
const update_ui = (total_score, total_max) => {
    const score = +total_score.toFixed(2);
    const max = +total_max.toFixed(2);
    const percent = max > 0 ? (score / max) * 100 : 0;
    const display_percent = +Math.max(0, Math.min(percent, 100)).toFixed(2);

    document.querySelectorAll('[data-region="total-mark-display"]').forEach(region => {
        // Update the text spans.
        region.querySelector('.total-score-text').textContent = score.toString();
        region.querySelector('.total-max-text').textContent = max.toString();
        region.querySelector('.total-percent').textContent = display_percent.toString();

        // Update the progress bar.
        const bar = region.querySelector('.total-progress-bar');
        if (bar) {
            bar.style.width = display_percent + '%';
            bar.setAttribute('aria-valuenow', score.toString());
            bar.setAttribute('aria-valuemax', max.toString());
        }
    });
};

/**
 * Calculate totals for Rubric.
 */
const calculate_rubric = () => {
    const rubric = document.querySelector('.gradingform_rubric');
    if (!rubric) {
        return;
    }

    let running_total = 0;
    let running_max = 0;

    rubric.querySelectorAll('.criterion').forEach(row => {
        const selected = row.querySelector('.level.checked .scorevalue');
        if (selected) {
            running_total += parseFloat(selected.textContent) || 0;
        }

        let row_max = 0;
        row.querySelectorAll('.scorevalue').forEach(span => {
            const val = parseFloat(span.textContent) || 0;
            if (val > row_max) {
                row_max = val;
            }
        });
        running_max += row_max;
    });

    update_ui(running_total, running_max);
};

/**
 * Calculate totals for Marking Guide.
 */
const calculate_guide = () => {
    const guide = document.querySelector('.gradingform_guide');
    if (!guide) {
        return;
    }

    let running_total = 0;
    let running_max = 0;

    guide.querySelectorAll('.score input[type="number"]').forEach(input => {
        const val = parseFloat(input.value);
        const max = parseFloat(input.getAttribute('max'));
        if (!isNaN(val)) {
            running_total += val;
        }
        if (!isNaN(max)) {
            running_max += max;
        }
    });

    update_ui(running_total, running_max);
};

/**
 * Initialize the grading tracker listeners.
 */
const init_grading_tracker = () => {
    const rubric = document.querySelector('.gradingform_rubric');
    const guide = document.querySelector('.gradingform_guide');

    if (rubric) {
        const observer = new MutationObserver(calculate_rubric);
        observer.observe(rubric, {
            attributes: true,
            subtree: true,
            attributeFilter: ['class']
        });
        calculate_rubric();
    }

    if (guide) {
        document.addEventListener('input', (e) => {
            if (e.target.closest('.gradingform_guide .score')) {
                calculate_guide();
            }
        });
        calculate_guide();
    }
};

/**
 * init.
 */
export const init = () => {
    const run = () => {
        // A tiny 50ms delay to let the DOM settle.
        setTimeout(() => {
            init_grading_tracker();
        }, 50);
    };

    if (document.readyState === 'complete') {
        run();
    } else {
        window.addEventListener('load', run);
    }
};