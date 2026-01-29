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

import Ajax from "../../../../lib/amd/src/ajax";
import {getString} from 'core/str';
import Log from 'core/log';

let courseworkId;

/**
 * Javascript module for handling fetching of similarity data from TII after page load.
 *
 * @module      mod_coursework/turnitin_similarity_fetcher
 * @copyright   2025 UCL
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Find all submission files currently visible in the UI where TII links are not yet loaded, get the links and add to UI.
 * Designed to be called on each scroll event to check and load any unloaded submissions presently in view.
 * (I.e. avoid loading hundreds of submissions at the outset where some may never be viewed).
 * @type {(function(...[*]): void)|*}
 */
const processTurnitin = debounce(
    async() => {
        const plagiarismLinks = [...document.querySelectorAll('.mod-coursework-plagiarism-tii-links')];
        const visiblePlagiarismLinks = plagiarismLinks
            .filter(el => {
                return el.dataset.tiiLoaded === "false";
            })
            .filter(el => { // Only if currently in view
                const {top, bottom, left, right} = el.getBoundingClientRect();
                return top >= 0 && bottom <= window.innerHeight && left >= 0 && right <= window.innerWidth;
            });
        const submissionIds = visiblePlagiarismLinks.map(elem => {
            return parseInt(elem.closest('.mod-coursework-submissions-submission-col').dataset.submissionId);
        });

        if (submissionIds.length) {
            const WSResult = await Ajax.call([{
                methodname: 'mod_coursework_get_turnitin_similarity_links',
                args: {courseworkid: courseworkId, submissionids: submissionIds}
            }])[0];
            const tiiEmptyString = await getString('turnitinnoreport', 'coursework');
            if (WSResult.success) {
                WSResult.result.forEach(submission => {
                    submission.files.forEach(file => {
                        const el = document.getElementById('mod-coursework-plagiarism-tii-links-' + file.fileid);
                        if (el) {
                            el.innerHTML = file.linkshtml;
                            if (el.innerHTML === '') {
                                el.innerHTML = `<small>${tiiEmptyString}</small>`;
                            }
                            el.dataset.tiiLoaded = "true";
                        } else {
                            Log.debug("Element not found #mod-coursework-plagiarism-tii-links-" + file.fileid);
                        }
                    });
                });
            } else {
                Log.debug(
                    "Error getting Turnitin links for submissions " + submissionIds.join(',')
                    + ": " + WSResult.errorcode + " | " + WSResult.message
                );
                submissionIds.forEach(id => {
                    const elems = document.querySelectorAll(
                        `.mod-coursework-submissions-submission-col[data-submission-id="${id}"]`
                            + `.mod-coursework-plagiarism-tii-links`
                    );
                    elems.forEach(el => {
                        el.innerHTML = `<small>${tiiEmptyString}</small>`;
                        el.dataset.tiiLoaded = "true";
                    });
                });
            }
        }
    },
    100
);

/**
 * To improve performance avoid repeatedly running the provided fn on every scroll event.
 * @param {function} fn
 * @param {number} delay
 * @returns {(function(...[*]): void)|*}
 */
function debounce(fn, delay = 100) {
    let timerId;
    return function(...args) {
        clearTimeout(timerId);
        timerId = setTimeout(() => fn.apply(this, args), delay);
    };
}

/**
 * Initialize module.
 * @param {number} courseworkid the coursework ID.
 */
export const init = (courseworkid) => {
    courseworkId = courseworkid;
    window.addEventListener("scrollend", processTurnitin, {passive: true});
    processTurnitin();
};
