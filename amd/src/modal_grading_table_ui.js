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
 * Javascript module for changes to grading table after modal form edits.
 *
 * @module      mod_coursework/modal_grading_table_ui
 * @copyright   2025 UCL
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {add as addToast} from 'core/toast';
import Ajax from 'core/ajax';
import Templates from 'core/templates';

/**
 * Once a row has been changed via modal form, replace it in the UI with fresh data from server.
 * @param {Element} rowElement to replace
 * @param {object} dataSet data we got from the original click
 * @param {string} successMessage
 * @returns {Promise<void>}
 */
export const replaceRow = async (rowElement, dataSet, successMessage) => {
    const templateDataResult = await Ajax.call([{
        methodname: 'get_grading_table_row_data',
        args: {
            courseworkid: dataSet.courseworkId,
            allocatableid: dataSet.allocatableId,
            allocatabletype: dataSet.allocatableType
        }
    }])[0];
    if (templateDataResult.success) {
        const rowHtml = await Templates.render(
            'mod_coursework/submissions/tr',
            JSON.parse(templateDataResult.result)
        );
        if (rowHtml) {
            const temp = document.createElement('tr');
            temp.innerHTML = rowHtml;
            rowElement.replaceWith(temp);
            addToast(successMessage, {type: 'success'});
        } else if (templateDataResult.exception ?? null) {
            addToast(templateDataResult.exception.message, {type: 'warning'});
        } else if (templateDataResult.message) {
            addToast(templateDataResult.message, {type: 'warning'});
        }
    }
};
