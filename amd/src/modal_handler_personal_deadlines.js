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
 * Javascript module for handling personal extension modal.
 *
 * @module      mod_coursework/modal_handler_personal_deadlines
 * @copyright   2025 UCL
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';
import {getString} from 'core/str';
import Log from 'core/log';
import {replaceRow} from 'mod_coursework/modal_grading_table_ui';

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
    const actionSelector = '[data-action="mod-coursework-launch-modal-personal-deadline"]';
    triggerElement.addEventListener('click', event => {
        const actionMenu = event.srcElement.matches(actionSelector)
            ? event.srcElement
            : event.srcElement.closest(actionSelector);
        const rowElement = event.srcElement.closest('tr');
        if (actionMenu) {
            event.preventDefault();
        } else {
            // Was not an action menu click.
            return;
        }
        const dataSet = actionMenu.dataset;
        if (!dataSet.courseworkId ?? null) {
            // For some reason we do not have the data we need for dynamic form.
            Log.error('Insufficient data to process personal deadline request.');
            return;
        }
        const args = {
            courseworkid: courseworkId,
            allocatableid: dataSet.allocatableId,
            allocatabletype: dataSet.allocatableType,
            deadlineid: dataSet.deadlineId
        };
        const modalForm = new ModalForm({
            modalConfig: {
                title: getString('extended_deadline', 'mod_coursework'),
            },
            formClass: 'mod_coursework\\forms\\personal_deadline_form',
            saveButtonText: getString('save', 'core'),
            returnFocus: triggerElement,
            args: args
        });

        // Show a toast notification when the form is submitted.
        modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, event => {
            if (event.detail.success) {
                // Successful submission.
                if (event.detail.resultcode === 'saved') {
                    try {
                        replaceRow(rowElement, dataSet, event.detail.message);
                    } catch (e) {
                        Notification.addNotification({type: 'error', message: e.message});
                    }
                }
            } else if (event.detail.errors) {
                Notification.addNotification({
                    type: 'error',
                    message: event.detail.message
                });
            } else if (event.detail.warnings) {
                const warningMessages = event.detail.warnings.map(warning => warning.message);
                Notification.addNotification({
                    type: 'error',
                    message: warningMessages.join('<br>')
                });
            }
        });

        modalForm.show();
    });
};
