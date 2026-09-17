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
 * Agree Marks module for mod_coursework.
 *
 * @module     mod_coursework/feedbacktabcontroller
 * @copyright  2026 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const setactivetab = (annotatedfeedbackid) => {
    document.querySelectorAll('.feedbackannotator_tabbutton').forEach(elem => {
        elem.setAttribute('aria-selected', false);
        elem.classList.remove("active");
    });
    document.querySelectorAll('.tab-pane').forEach(elem => {
        elem.classList.remove("active");
    });

    var elem = document.querySelector(
        ".feedbackannotator_tabbutton[data-feedbackid='" + annotatedfeedbackid + "']"
    );
    elem.setAttribute('aria-selected', true);
    elem.classList.add("active");
    document.getElementById("feedbackannotator_" + annotatedfeedbackid).classList.add("active");
};
