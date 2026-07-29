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
 * Rubric labels and make radio buttons work.
 *
 * @module    mod_coursework/rubric
 * @author    Conn Warwicker <conn.warwicker@catalyst-eu.net>
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Toggle visibility of automatic agreement fields based on grading method and final stage grading values.
 */
const toggleAutomaticAgreementFields = () => {
    const gradingMethod = document.getElementById('id_advancedgradingmethod_submissions');
    const finalStageGrading = document.getElementById('id_finalstagegrading');
    const strategyEl = document.getElementById('id_automaticagreementstrategy');
    const rangeEl = document.getElementById('id_automaticagreementrange');
    const roundEl = document.getElementById('id_roundingrule');

    if (!gradingMethod || !finalStageGrading || !strategyEl || !rangeEl || !roundEl) {
        return;
    }

    const shouldHide = gradingMethod.value !== '' && finalStageGrading.value === '0';

    [strategyEl, rangeEl, roundEl].forEach(el => {
        el.closest('.fitem').style.display = shouldHide ? 'none' : '';
    });
};

export const init = () => {
    const gradingMethod = document.getElementById('id_advancedgradingmethod_submissions');
    const finalStageGrading = document.getElementById('id_finalstagegrading');

    if (!gradingMethod || !finalStageGrading) {
        return;
    }

    [gradingMethod, finalStageGrading].forEach(el => {
        el.addEventListener('change', toggleAutomaticAgreementFields);
    });

    // Apply on load.
    toggleAutomaticAgreementFields();
};