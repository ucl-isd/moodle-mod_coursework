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

/**
 * Javascript module for handling fetching of similarity data from TII after page load.
 *
 * @module      mod_coursework/modal_handler_plagiarism
 * @copyright   2025 UCL
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const processTurnitin = async (courseworkId) => {
    const plagiarismLinks = document.querySelectorAll('.mod-coursework-plagiarism-tii-links');
    const submissionIds = Array.from(plagiarismLinks).map(elem => {
        return parseInt(elem.closest('.mod-coursework-submissions-submission-col').dataset.submissionId);
    });
    const WSResult = await Ajax.call([{
        methodname: 'mod_coursework_get_turnitin_similarity_links',
        args: {courseworkid: courseworkId, submissionids: submissionIds}
    }])[0];
    if (WSResult.success) {
        WSResult.result.forEach(submission => {
            document.getElementById('mod-coursework-plagiarism-tii-links-' + submission.submissionid).innerHTML
                = submission.files.map(file => file.linkshtml).join('');
        });
    }
};

/**
 * Initialize module.
 * @param {number} courseworkid the coursework ID.
 */
export const init = (courseworkid) => {
    processTurnitin(courseworkid);
};
