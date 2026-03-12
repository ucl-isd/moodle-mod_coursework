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
 * Javascript module for marking guide feedback form percentages helper.
 *
 * @module      mod_coursework/marking_guide_feedback_percent
 * @copyright   2026 UCL
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Templates from 'core/templates';
import {getString} from 'core/str';

const CLASS_PERCENT_CONTAINER = 'percent-input-container';
const MAX_VALUE_PERCENT = 100;
const MAX_INPUT_LENGTH = 7;

let usingPercentGrades;

/**
 * Return the number of decimal places allowed.
 *
 * @returns {number}
 */
const getDecimalPlaces = () => 2;

/**
 * Generate field names for the criterion.
 *
 * @param {number} criterionNumber
 * @param {boolean} isPercent
 * @returns {string}
 */
const getFieldName = (criterionNumber, isPercent) => {
    return `advancedgrading-criteria-${criterionNumber}-` + (isPercent ? 'percent' : 'score');
};

/**
 * Handle checkbox change to update preferences and UI state.
 *
 * @param {Event} e The change event.
 */
const handleCheckBoxClick = (e) => {
    usingPercentGrades = e.target.checked;

    // Toggle visibility of percent containers.
    document.querySelectorAll(`.${CLASS_PERCENT_CONTAINER}`).forEach((container) => {
        container.classList.toggle('d-none', !usingPercentGrades);
    });

    // Toggle readonly on the core Moodle score fields.
    document.querySelectorAll('table#advancedgrading-criteria input[id$="-score"]').forEach((elem) => {
        if (usingPercentGrades) {
            elem.setAttribute('readonly', true);
        } else {
            elem.removeAttribute('readonly');
        }
    });

    Ajax.call([{
        methodname: 'core_user_update_user_preferences',
        args: {
            preferences: [{
                type: "coursework_guide_enter_percent_grades",
                value: usingPercentGrades ? 1 : 0,
            }]
        }
    }]);
};

/**
 * Initialise the module.
 *
 * @param {boolean} initialPreferenceValue
 */
export const init = async(initialPreferenceValue) => {
    usingPercentGrades = initialPreferenceValue;

    const markPercentString = await getString('markpercent', 'mod_coursework');
    const checkBox = document.getElementById('switch-entergradesaspercent');
    if (checkBox) {
        checkBox.addEventListener('change', handleCheckBoxClick);
    }

    const tableCells = document.querySelectorAll('div#guide-advancedgrading tr.criterion td.score');
    tableCells.forEach((tableCell) => {
        const scoreInputElem = tableCell.querySelector('input');
        if (!scoreInputElem) {
            return;
        }

        const match = scoreInputElem.name.match(/advancedgrading\[criteria]\[(\d+)]\[score]/);
        const criterionNumber = match ? match[1] : null;
        const newFieldName = getFieldName(criterionNumber, true);
        const scoreOutOf = parseFloat(scoreInputElem.max) || 0;

        scoreInputElem.setAttribute('data-criterion-number', criterionNumber);

        if (usingPercentGrades) {
            scoreInputElem.setAttribute('readonly', 'true');
        }
        scoreInputElem.addEventListener('input', handleScoreChangeEvent);

        const context = {
            "name": newFieldName,
            "id": newFieldName,
            "min": "0",
            "max": MAX_VALUE_PERCENT,
            "step": "any",
            "required": true
        };

        (async() => {
            const {html, js} = await Templates.renderForPromise('mod_coursework/form_number', context);
            const container = document.createElement('div');
            container.classList.add(CLASS_PERCENT_CONTAINER);

            if (!usingPercentGrades) {
                container.classList.add('d-none');
            }

            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            const templateWrapper = tempDiv.firstElementChild;
            const inp = templateWrapper.querySelector('input');

            inp.setAttribute('data-criterion-number', criterionNumber);

            const label = document.createElement('label');
            label.setAttribute('for', newFieldName);
            label.textContent = markPercentString;
            label.classList.add('d-block');

            container.appendChild(label);
            container.appendChild(templateWrapper);
            tableCell.insertBefore(container, tableCell.firstChild);

            Templates.runTemplateJS(js);

            inp.addEventListener('input', handlePercentChangeEvent);
            inp.addEventListener('keypress', preventInvalidNumber);

            if (scoreInputElem.value !== '') {
                setPercentageFromScore(criterionNumber, parseFloat(scoreInputElem.value), scoreOutOf);
            }
        })();
    });
};

/**
 * Round values for display.
 *
 * @param {number} value
 * @returns {string}
 */
const applyRounding = (value) => {
    const dp = getDecimalPlaces();
    return Math.abs(value.toFixed(dp) - Math.floor(value)) === 0
        ? value.toFixed(0) : value.toFixed(dp);
};

/**
 * Sync percent from score.
 *
 * @param {number} criterionNumber
 * @param {number} scoreValue
 * @param {number} scoreOutOf
 */
const setPercentageFromScore = (criterionNumber, scoreValue, scoreOutOf) => {
    const percentElem = document.getElementById(getFieldName(criterionNumber, true));
    if (!percentElem || isNaN(scoreValue) || scoreOutOf === 0) {
        if (percentElem) {
            percentElem.value = '';
        }
        return;
    }
    percentElem.value = applyRounding((scoreValue / scoreOutOf) * MAX_VALUE_PERCENT);
};

/**
 * Sync score from percent.
 *
 * @param {number} criterionNumber
 * @param {number} percentValue
 */
const setScoreFromPercent = (criterionNumber, percentValue) => {
    const scoreInputElem = document.getElementById(getFieldName(criterionNumber, false));
    if (!scoreInputElem) {
        return;
    }
    scoreInputElem.value = (percentValue === '' || isNaN(percentValue))
        ? ''
        : ((parseFloat(scoreInputElem.max) * percentValue) / MAX_VALUE_PERCENT).toFixed(getDecimalPlaces());
};


/**
 * Prevent invalid entries into the grade inputs.
 * @param {event} event
 */
export const preventInvalidNumber = (event) => {
    // Field will allow user to enter e for scientific notation - stop that.
    if (!/[0-9.]/.test(event.key)) {
        event.preventDefault();
        return;
    }
    if (
        event.target.value.length >= MAX_INPUT_LENGTH
        && !['Backspace', 'Delete', 'Tab'].includes(event.key)
    ) {
        event.preventDefault();
    }
};

/**
 * Handle score change.
 *
 * @param {Event} event
 */
const handleScoreChangeEvent = (event) => {
    const criterionNumber = event.target.getAttribute('data-criterion-number');
    const scoreOutOf = parseFloat(event.target.max);
    const val = event.target.value;

    if (val === '') {
        setPercentageFromScore(criterionNumber, NaN, scoreOutOf);
        return;
    }

    const numVal = parseFloat(val);
    setPercentageFromScore(criterionNumber, numVal, scoreOutOf);
};

/**
 * Handle percent change.
 *
 * @param {Event} event
 */
const handlePercentChangeEvent = (event) => {
    const criterionNumber = event.target.getAttribute('data-criterion-number');
    const val = event.target.value;

    if (val === '') {
        setScoreFromPercent(criterionNumber, '');
        return;
    }

    const numVal = parseFloat(val);
    setScoreFromPercent(criterionNumber, numVal);
};