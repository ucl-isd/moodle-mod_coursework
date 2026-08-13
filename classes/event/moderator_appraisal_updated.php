<?php
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
 * Event for updating a moderator appraisal record.
 *
 * @package    mod_coursework
 * @copyright  2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\event;

use coding_exception;
use core\event\base;

class moderator_appraisal_updated extends base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'coursework_moderator_appraisals';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     * @throws coding_exception
     */
    public static function get_name() {
        return get_string('moderatorappraisalupdated', 'mod_coursework');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with ID '{$this->userid}' updated moderator appraisal ID '{$this->objectid}'"
            . " for coursework with course module id '{$this->contextinstanceid}'.";
    }

    /**
     * Custom validation.
     *
     * @return void
     * @throws coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['courseworkid'])) {
            throw new coding_exception('The \'courseworkid\' value must be set in other.');
        }
    }
}
