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

/**
 * Javascript module for marking guide feedback form percentages helper.
 *
 * @module      mod_coursework/marking_guide_feedback_percent
 * @copyright   2026 UCL
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const CLASS_PERCENT_INPUT = 'input-percent';
const MAX_VALUE_PERCENT = 100;
const MAX_PERCENT_LENGTH = 5; // Including decimals.
import {getString} from 'core/str';
import Ajax from 'core/ajax';

let usingPercentGrades;

const getDecimalPlaces = () => {
    return 2;
};

const getFieldName = (criterionNumber, isPercent) => {
    return `advancedgrading-criteria-${criterionNumber}-` + (isPercent ? 'percent' : 'score');
};

const handleCheckBoxClick = async() => {
    const checkBox = event.target;
    usingPercentGrades = checkBox.checked;

    const percentFields = document.querySelectorAll('table#advancedgrading-criteria .input-percent');
    percentFields.forEach((elem) => {
        if (!usingPercentGrades) {
            elem.setAttribute('readonly', true);
        } else {
            elem.removeAttribute('readonly');
        }
    });

    const nonPercentFields = document.querySelectorAll('table#advancedgrading-criteria input:not(.input-percent)');
    nonPercentFields.forEach((elem) => {
        if (usingPercentGrades) {
            elem.setAttribute('readonly', true);
        } else {
            elem.removeAttribute('readonly');
        }
    });

    // Update user preference on server.
    const request = {
        methodname: 'core_user_update_user_preferences',
        args: {
            preferences: [
                {
                    type: "coursework_guide_enter_percent_grades",
                    value: checkBox.checked ? 1 : 0,
                }
            ]
        }
    };
    await Ajax.call([request])[0];
};

/**
 * Initialise
 * @param {bool} initialPreferenceValue
 */
export const init = (initialPreferenceValue) => {
    usingPercentGrades = initialPreferenceValue;

    // Watch the "Enter grades as %" checkbox.
    const checkBox = document.querySelector('input[data-action="coursework-set-pref-grades-as-percent"]');
    const criteriaTable = document.getElementById('advancedgrading-criteria');
    if (criteriaTable && checkBox) {
        checkBox.addEventListener('input', handleCheckBoxClick);
    }

    (async() => {
        const markPercentString = await getString('markpercent', 'coursework');
        const tableCells = document.querySelectorAll('div#guide-advancedgrading tr.criterion td.score');
        tableCells.forEach((tableCell) => {
            const scoreInputElem = tableCell.querySelector('input');
            const criterionNumber = scoreInputElem.name.match(/advancedgrading\[criteria]\[(\d+)]\[score]/)[1] ?? null;
            const newFieldName = getFieldName(criterionNumber, true);
            const scoreOutOf = parseInt(scoreInputElem.max);

            // Add score as data attr to existing (core) score input, and prevent edits if using percent instead.
            scoreInputElem.setAttribute('data-criterion-number', criterionNumber);
            if (usingPercentGrades) {
                scoreInputElem.setAttribute('readonly', 'true');
            }

            scoreInputElem.addEventListener('input', handleScoreChangeEvent);

            // New percentage <input>.
            const inp = document.createElement('input');
            inp.name = newFieldName;
            inp.classList.add("form-control");
            inp.classList.add(CLASS_PERCENT_INPUT, 'mb-2');
            inp.id = newFieldName;
            inp.setAttribute('data-criterion-number', criterionNumber);
            inp.setAttribute('type', "text");
            inp.setAttribute('inputmode', "decimal");
            inp.setAttribute('size', MAX_PERCENT_LENGTH - 1);
            inp.setAttribute('min', 0);
            inp.setAttribute('max', MAX_VALUE_PERCENT);
            inp.setAttribute('step', 1 / (Math.pow(10, getDecimalPlaces())));
            if (!usingPercentGrades) {
                inp.setAttribute('readonly', true);
            }
            tableCell.insertBefore(inp, tableCell.firstChild);
            inp.addEventListener('input', handlePercentChangeEvent);
            inp.addEventListener('keypress', preventInvalidNumber);

            // New <label> for new percentage input.
            const label = document.createElement('label');
            label.setAttribute('for', newFieldName);
            label.classList.add('text-right');
            label.textContent = markPercentString;
            tableCell.insertBefore(label, tableCell.firstChild);

            if (scoreInputElem.value !== '') {
                setPercentageFromScore(criterionNumber, Number(scoreInputElem.value), scoreOutOf);
            }
        });
    })();
};

/**
 * If the value is a whole number ignoring any tiny decimals, display it as such e.g. 10 not 10.00
 * @param {number} value
 * @returns {string}
 */
const applyRounding = (value) => {
    return Math.abs(value.toFixed(getDecimalPlaces()) - Math.floor(value)) === 0
        ? value.toFixed(0) : value.toFixed(getDecimalPlaces());
};

const setPercentageFromScore = (criterionNumber, scoreValue, scoreOutOf) => {
    const percentElem = document.getElementById(getFieldName(criterionNumber, true));
    if (scoreValue === '') {
        percentElem.value = '';
        return;
    }
    percentElem.value = applyRounding(scoreValue / scoreOutOf * MAX_VALUE_PERCENT);
};

const setScoreFromPercent = (criterionNumber, percentValue) => {
    const scoreInputElem = document.getElementById(getFieldName(criterionNumber, false));
    scoreInputElem.value = percentValue === ''
        ? ''
        : ((Number(scoreInputElem.max) * percentValue) / MAX_VALUE_PERCENT).toFixed(getDecimalPlaces());
    scoreInputElem.classList.remove('invalid-value');
};

const preventInvalidNumber = (event) => {
    // Field will allow user to enter e for scientific notation - stop that.
    if (!/[0-9.]/.test(event.key)) {
        event.preventDefault();
        return;
    }
    const maxLength = event.target.value.includes('.')
        ? MAX_PERCENT_LENGTH : MAX_PERCENT_LENGTH - 1;
    const hasSelectedText = event.target.selectionStart !== event.target.selectionEnd;
    if (
        !hasSelectedText
        && event.target.value.length >= maxLength
        && event.key !== 'Backspace'
        && event.key !== 'Delete'
    ) {
        event.preventDefault();
    }
};

const handleScoreChangeEvent = (event) => {
    const criterionNumber = Number(event.target.getAttribute('data-criterion-number'));
    const scoreOutOf = Number(event.target.max);
    if (event.target.value === '') {
        setPercentageFromScore(criterionNumber, '');
        return;
    }
    // User may enter out of range values so ensure new value is in range 0 to out of.
    const newValue = Math.max(Math.min(event.target.value, parseInt(event.target.max)), 0);
    if (newValue !== Number(event.target.value)) {
        // If user entered an out of range percentage, warn them.
        event.target.classList.add('invalid-value');
    } else {
        event.target.classList.remove('invalid-value');
    }
    setPercentageFromScore(criterionNumber, newValue, scoreOutOf);
};

const handlePercentChangeEvent = (event) => {
    const criterionNumber = Number(event.target.getAttribute('data-criterion-number'));
    if (event.target.value === '') {
        setScoreFromPercent(criterionNumber, '');
        return;
    }
    // User may enter out of range values e.g. 120% so ensure new value is in range 0 - MAX_VALUE_PERCENT.
    const newValue = Math.max(Math.min(Number(event.target.value), MAX_VALUE_PERCENT), 0);
    if (newValue !== Number(event.target.value)) {
        // If user entered an out of range percentage, warn them.
        event.target.classList.add('invalid-value');
    } else {
        event.target.classList.remove('invalid-value');
    }
    setScoreFromPercent(criterionNumber, newValue);
};
