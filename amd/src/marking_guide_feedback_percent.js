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
const MAX_DECIMAL_PLACES = 1;

const getDecimalPlaces = () => {
    return Math.max(Math.min(Number(document.getElementById('decimal-places').value), MAX_DECIMAL_PLACES), 0);
};

const getFieldName = (criterionNumber, isPercent) => {
    return `advancedgrading-criteria-${criterionNumber}-` + (isPercent ? 'percent' : 'score');
};

const getExistingScoreDiv = (parentElement) => {
    const regex = /\/\d+/g;
    const divs = parentElement.querySelectorAll('div');
    return Array.from(divs).filter(div => regex.test(div.textContent)).pop() ?? null;
};

const addPercentageFields = () => {
    const tableCells = document.querySelectorAll('div#guide-advancedgrading tr.criterion td.score');
    tableCells.forEach((tableCell) => {
        const scoreInputElem = tableCell.querySelector('input');
        const criterionNumber = scoreInputElem.name.match(/advancedgrading\[criteria]\[(\d+)]\[score]/)[1] ?? null;
        const newFieldName = getFieldName(criterionNumber, true);
        const scoreDiv = getExistingScoreDiv(tableCell);
        const scoreOutOf = parseInt(scoreDiv.textContent.replace('/', ''));

        // New <td> form column for percentage.
        const newTableCell = document.createElement('td');
        newTableCell.classList.add("score-percent");

        // Add score as data attr to existing score input.
        scoreInputElem.setAttribute('data-out-of', scoreOutOf);
        scoreInputElem.setAttribute('data-criterion-number', criterionNumber);

        // Labels for existing (score) <input>.
        const coreFieldName = getFieldName(criterionNumber, false);
        const labelForScore = document.createElement('label');
        labelForScore.setAttribute('for', coreFieldName);
        labelForScore.textContent = scoreDiv.textContent;
        if (scoreDiv) {
            scoreDiv.remove();
        }
        tableCell.insertBefore(labelForScore, scoreInputElem);


        // New <label> for new percentage input.
        const label = document.createElement('label');
        label.setAttribute('for', newFieldName);
        label.classList.add('text-right');
        label.textContent = '%';
        newTableCell.appendChild(label);

        // New percentage <input>.
        const inp = document.createElement('input');
        inp.name = newFieldName;
        inp.classList.add("form-control");
        inp.classList.add(CLASS_PERCENT_INPUT);
        inp.id = newFieldName;
        inp.setAttribute('data-criterion-number', criterionNumber);
        inp.setAttribute('type', "number");
        inp.setAttribute('min', 0);
        inp.setAttribute('max', MAX_VALUE_PERCENT);
        inp.setAttribute('step', 0.1);
        newTableCell.appendChild(inp);
        tableCell.insertAdjacentElement('afterend', newTableCell);
        if (scoreInputElem.value !== '') {
            setPercentageFromScore(criterionNumber, Number(scoreInputElem.value), scoreOutOf);
        }

    });
};

const addListeners = () => {
    const percentInputElems = document.querySelectorAll(`div#guide-advancedgrading input.${CLASS_PERCENT_INPUT}`);
    percentInputElems.forEach(elem => {
        elem.addEventListener('input', handlePercentChangeEvent);
    });

    const scoreInputElems = document.querySelectorAll(`div#guide-advancedgrading input:not(.${CLASS_PERCENT_INPUT})`);
    scoreInputElems.forEach(elem => {
        elem.addEventListener('input', handleScoreChangeEvent);
    });

    document.getElementById('decimal-places').addEventListener(
        'input',
        (event) => {
            const newValue = getDecimalPlaces();
            if (event.target.value !== newValue) {
                event.target.value = newValue;
            }
        }
    );
};

const setPercentageFromScore = (criterionNumber, scoreValue, scoreOutOf) => {
    document.getElementById(getFieldName(criterionNumber, true)).value =
        scoreValue === ''
            ? ''
            : (scoreValue / scoreOutOf * MAX_VALUE_PERCENT).toFixed(getDecimalPlaces());
};

const setScoreFromPercent = (criterionNumber, percentValue) => {
    const scoreElem = document.getElementById(getFieldName(criterionNumber, false));
    scoreElem.value =
        percentValue === ''
            ? ''
            : (Number(scoreElem.getAttribute('data-out-of')) * percentValue / MAX_VALUE_PERCENT).toFixed(getDecimalPlaces());
};

const handleScoreChangeEvent = (event) => {
    // User may enter out of range values e.g. -10 so ensure new value is in range 0 - outOf.
    const outOf = Number(event.target.getAttribute('data-out-of'));
    setPercentageFromScore(
        Number(event.target.getAttribute('data-criterion-number')),
        event.target.value === '' ? '' : Math.max(Math.min(Number(event.target.value), outOf), 0),
        outOf
    );
};

const handlePercentChangeEvent = (event) => {
    // User may enter out of range values e.g. 120% so ensure new value is in range 0 - MAX_VALUE_PERCENT.
    const newValue = event.target.value === '' ? '' : Math.max(Math.min(Number(event.target.value), MAX_VALUE_PERCENT), 0);
    if (event.target.value !== newValue) {
        event.target.value = newValue;
    }
    setScoreFromPercent(
        Number(event.target.getAttribute('data-criterion-number')),
        newValue
    );
};

/**
 * Initialise module.
 */
export const init = () => {
    addPercentageFields();
    addListeners();
};
