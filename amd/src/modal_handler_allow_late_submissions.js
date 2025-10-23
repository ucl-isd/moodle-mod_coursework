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
 * Javascript module for handling allow late submissions modal.
 *
 * @module      mod_coursework/modal_handler_extensions
 * @copyright   2025 UCL
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {getStrings} from 'core/str';
import {add as addToast} from 'core/toast';
import Ajax from 'core/ajax';
import {replaceRow} from 'mod_coursework/modal_grading_table_ui';
import Log from 'core/log';

let courseworkId;

/**
 * Initialize module.
 * @param {number} courseworkid the coursework ID.
 */
export const init = (courseworkid) => {
    courseworkId = courseworkid;
    // Using table not rows as rows will be re-rendered.
    const tableSelector = `#mod-coursework-submissions-table-${courseworkId}`;
    const triggerElement = document.querySelector(tableSelector);
    const actionSelector = '[data-action="mod-coursework-launch-modal-allowlatesubmissionsuser"]';
    triggerElement.addEventListener('click', event => {
        const actionMenu = event.target.matches(actionSelector)
            ? event.target
            : event.target.closest(actionSelector);
        const rowElement = event.target.closest('tr');
        if (actionMenu) {
            event.preventDefault();
        } else {
            // Was not an action menu click.
            return;
        }
        const dataSet = actionMenu.dataset;
        if (!dataSet.courseworkId ?? null) {
            // For some reason we do not have the data we need for dynamic form.
            Log.error('Insufficient data to process request.');
            return;
        }
        const strRequests = [
            {key: 'yes', component: 'core'},
            {key: 'no', component: 'core'},
            {key: 'allowlatesubmissions', component: 'mod_coursework'},
            {key: 'allowlatesubmissionssure', component: 'mod_coursework'},
            {key: 'allowlatesubmissionssurerevoke', component: 'mod_coursework'},
        ];
        getStrings(strRequests).then((strings) => {
            Notification.confirm(
                strings[2],
                dataSet.lateSubsAllowed === "1" ? strings[4] : strings[3],
                strings[0],
                strings[1],
                async () => {
                    try {
                        const wsResult = await Ajax.call([{
                            methodname: 'mod_coursework_allow_late_submissions',
                            args: {
                                courseworkid: parseInt(dataSet.courseworkId),
                                allocatableid: parseInt(dataSet.allocatableId),
                                allocatabletype: dataSet.allocatableType,
                                status: dataSet.lateSubsAllowed === "1" ? 0 : 1,
                            }
                        }])[0];
                        if (wsResult.success) {
                            replaceRow(rowElement, dataSet, wsResult.message);
                        } else if (wsResult.exception ?? null) {
                            addToast(wsResult.exception.message, {type: 'warning'});
                        } else if (typeof(wsResult.error ?? null) === 'string') {
                            addToast(wsResult.error, {type: 'warning'});
                        }
                    } catch (e) {
                        addToast(e.message ?? e.error, {type: 'danger'});
                    }
                }
            );
        });
    });
};
