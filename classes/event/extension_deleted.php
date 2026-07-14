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
 * Class extension_deleted is responsible for listening for changes to the extensions deleted.
 * @package    mod_coursework
 * @copyright  2025 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\event;

use coding_exception;
use core\event\base;

/**
 * Class extension_deleted is responsible for listening for changes to the extensions deleted.
 *
 * @package mod_coursework\event
 */
class extension_deleted extends base {
    #[\Override]
    public static function get_name() {
        return get_string('eventextensiondeleted', 'mod_coursework');
    }

    #[\Override]
    public function get_description() {
        $record = json_decode($this->other['record'], true);
        $type = $record['allocatabletype'];
        $method = 'get_description_' . $type;
        if (method_exists($this, $method)) {
            return $this->$method();
        } else {
            throw new coding_exception("The method '{$method}' does not exist on class extension_deleted.");
        }
    }

    /**
     * Get the event description for a group extension.
     *
     * @return string
     */
    private function get_description_group(): string {
        $record = json_decode($this->other['record'], true);
        return "The user with id '{$this->userid}' deleted the extension ID '{$this->objectid}' from the coursework " .
            "activity with course module id '{$this->contextinstanceid}', for the group with id '{$record['allocatableid']}'.";
    }

    /**
     * Get the event description for a user extension.
     *
     * @return string
     */
    private function get_description_user(): string {
        $record = json_decode($this->other['record'], true);
        return "The user with id '{$this->userid}' deleted the extension ID '{$this->objectid}' from the coursework " .
            "activity with course module id '{$this->contextinstanceid}', for the user with id '{$record['allocatableid']}'.";
    }

    /**
     * Override in subclass.
     *
     * Set all required data properties:
     *  1/ crud - letter [crud]
     *  2/ edulevel - using a constant self::LEVEL_*.
     *  3/ objecttable - name of database table if objectid specified
     *
     * Optionally it can set:
     * a/ fixed system context
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'coursework_extensions';
    }
}
