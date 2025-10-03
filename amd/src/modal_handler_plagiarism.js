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
 * Javascript module for handling plagiarism flags modal.
 *
 * @module      mod_coursework/modal_handler_plagiarism
 * @copyright   2025 UCL
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';
import {getString} from 'core/str';
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
    const actionSelector = '[data-action="mod-coursework-launch-modal-plagiarism"]';
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
            Log.error('Insufficient data to process extension request.');
            return;
        }
        const args = {
            submissionid: dataSet.submissionId,
            plagiarismflagid: dataSet.plagiarismId ?? ''
        };
        const modalForm = new ModalForm({
            modalConfig: {
                title: getString('plagiarismflagging', 'mod_coursework'),
            },
            formClass: 'mod_coursework\\forms\\plagiarism_flagging_mform',
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
                    message: event.detail.errors.join('<br>')
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
