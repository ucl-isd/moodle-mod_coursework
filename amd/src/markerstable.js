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
 * @module      mod_coursework/markerstable
 * @copyright   2025 UCL
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {add as addToast} from 'core/toast';
import $ from 'jquery';

export const init = async() => {
    document.querySelectorAll('[data-action="mod_coursework_allocatableinsampletoggle"]').forEach((node) => {
            node.addEventListener("change", async(event) => {
                var cell = $(event.target).parents('td');

                cell.find('[data-action="mod_coursework_assessorallocation"]')
                    .toggleClass('d-none', !event.target.checked);

                cell.find('[data-action="mod_coursework_allocationpintoggle"]')
                    .parent()
                    .toggleClass('d-none', !event.target.checked);

                Ajax.call([{
                    methodname: 'mod_coursework_allocatableinsampletoggle',
                    args: {
                        courseworkid: event.target.dataset.courseworkid,
                        allocatableid: event.target.dataset.allocatableid,
                        stageidentifier: event.target.dataset.stageidentifier,
                        togglestate: event.target.checked
                    },
                }])[0]
                    .catch((error) => {
                        addToast(error, {type: 'error'});
                    });
            });
        }
    );

    document.querySelectorAll('[data-action="mod_coursework_allocationpintoggle"]').forEach((node) => {
            node.addEventListener("change", async(event) => {
                Ajax.call([{
                    methodname: 'mod_coursework_allocationpintoggle',
                    args: {
                        allocatableid: event.target.dataset.allocatableid,
                        stageidentifier: event.target.dataset.stageidentifier,
                        togglestate: event.target.checked
                    },
                }])[0]
                    .catch((error) => {
                        addToast(error, {type: 'error'});
                    });
            });
        }
    );

    document.querySelectorAll('[data-action="mod_coursework_assessorallocation"]').forEach((node) => {
            node.addEventListener("change", async(event) => {
                Ajax.call([{
                    methodname: 'mod_coursework_assessorallocation',
                    args: {
                        courseworkid: event.target.dataset.courseworkid,
                        allocatableid: event.target.dataset.allocatableid,
                        stageidentifier: event.target.dataset.stageidentifier,
                        assessorid: event.target.value
                    },
                }])[0]
                    .then((result) => {
                        if (!result.success) {
                            event.target.value = "0";
                            return addToast(result.error, {type: 'error'});
                        } else {
                            return true;
                        }
                    })
                    .catch((error) => {
                        addToast(error, {type: 'error'});
                    });
            });
        }
    );
};
