// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Stop the mouse wheel from changing the value of a focused number input.
 *
 * When a number input has focus, some browsers change its value on mouse-wheel scroll instead of
 * scrolling the page.
 *
 * @module     mod_coursework/number_input_scroll
 * @author     Vlad Kidanov <vlad.kidanov@catalyst-eu.net>
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

let initialised = false;

const handleWheel = () => {
    const active = document.activeElement;
    if (active && active.matches('input[type="number"]')) {
        active.blur();
    }
};

export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;
    document.addEventListener('wheel', handleWheel, {capture: true, passive: true});
};
