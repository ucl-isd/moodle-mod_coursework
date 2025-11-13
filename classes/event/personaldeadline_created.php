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
 * The mod_coursework group override created event.
 *
 * @package    mod_coursework
 * @copyright  2025 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\event;

use coding_exception;
use core\event\base;
use core\exception\moodle_exception;
use moodle_url;

/**
 * The mod_coursework personal deadline created event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - int coursework: the id of the coursework.
 * }
 *
 * @package    mod_coursework
 * @copyright  2025 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class personaldeadline_created extends base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'coursework_person_deadlines';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     * @throws coding_exception
     */
    public static function get_name() {
        return get_string('personaldeadlinecreated', 'mod_coursework');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $readabledate = userdate($this->other['deadline']);
        return "The user with ID '$this->userid' created personal deadline ID '$this->objectid' as '$readabledate'"
            . " for the coursework with course module id '$this->contextinstanceid' for the user with id '{$this->relateduserid}'.";
    }

    /**
     * Returns relevant URL.
     *
     * @return moodle_url
     * @throws moodle_exception
     */
    public function get_url() {
        return new moodle_url('/mod/coursework/view.php', ['id' => $this->contextinstanceid]);
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
        if (!isset($this->other['deadline'])) {
            throw new coding_exception('The \'deadline\' value must be set in other.');
        }
    }
}
