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

var controller = {
    allocationpintoggle: async function (allocatableid, stageidentifier, togglestate) {
        Ajax.call([{
            methodname: 'mod_coursework_allocationpintoggle',
            args: {
                allocatableid: allocatableid,
                stageidentifier: stageidentifier,
                togglestate: togglestate
            },
        }])[0]
            .catch((error) => {
                addToast(error, {type: 'error'});
            });
    },
    allocatableinsampletoggle: async function (courseworkid, allocatableid, stageidentifier, togglestate) {
        Ajax.call([{
            methodname: 'mod_coursework_allocatableinsampletoggle',
            args: {
                courseworkid: courseworkid,
                allocatableid: allocatableid,
                stageidentifier: stageidentifier,
                togglestate: togglestate
            },
        }])[0]
            .catch((error) => {
                addToast(error, {type: 'error'});
            });
    },

};

export const init = async () => {
    document.querySelectorAll('[data-action="mod_coursework_allocationpintoggle"]').forEach((node) => {
            node.addEventListener("click", async (event) => {
                await controller.allocationpintoggle(
                    event.target.dataset.allocatableid,
                    event.target.dataset.stageidentifier,
                    event.target.checked
                ).promise;
            });
        }
    );

    document.querySelectorAll('[data-action="mod_coursework_allocatableinsampletoggle"]').forEach((node) => {
            node.addEventListener("click", async (event) => {
                await controller.allocatableinsampletoggle(
                    event.target.dataset.courseworkid,
                    event.target.dataset.allocatableid,
                    event.target.dataset.stageidentifier,
                    event.target.checked
                ).promise;
            });
        }
    );
};
