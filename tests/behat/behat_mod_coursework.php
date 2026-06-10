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
 * @package    mod_coursework
 * @copyright  2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Andrew Hancox <andrewdchancox@googlemail.com>
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Moodle\BehatExtension\Exception\SkippedException;

/**
 * Custom behat steps for mod_coursework
 * Please use sparingly!
 */
class behat_mod_coursework extends behat_base {
    /**
     * Turnitin has been configured for behat
     *
     * @Given Turnitin has been configured for behat
     * @throws \Moodle\BehatExtension\Exception\SkippedException
     */
    public function turnitin_has_been_configured_for_behat() {
        if (
            empty(getenv('TII_APIBASEURL'))
            || empty(getenv('TII_ACCOUNT'))
            || empty(getenv('TII_SECRET'))
        ) {
            throw new SkippedException("TII not configured for behat.
                Easiest approach is to add the following to config.php:
                putenv('TII_ACCOUNT=XXXXXXXXXX');
                putenv('TII_SECRET=XXXXXXXXXX');
                putenv('TII_APIBASEURL=https://api.turnitinuk.com');
            ");
        }
    }

    /**
     * Convert page names to URLs for steps like 'When I am on the "[identifier]" "[page type]" page'.
     *
     * Recognised page names are:
     * | pagetype             | name meaning    | description                                |
     * | Download final marks | Coursework name | The export final marks CSV link (view.php) |
     *
     * @param string $type identifies which type of page this is, for example, "Download final marks".
     * @param string $identifier identifies the Coursework instance, for example, the instance's name.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        switch (strtolower($type)) {
            case 'download final marks':
                return new moodle_url('/mod/coursework/view.php', [
                    'id' => $this->get_cm_by_coursework_name($identifier)->id,
                    'export' => '1',
                ]);

            default:
                throw new Exception("Unrecognised coursework page type '$type'.");
        }
    }

    /**
     * Get a coursework cmid from the coursework name.
     *
     * @param string $name coursework name.
     * @return stdClass cm from get_coursemodule_from_instance.
     */
    protected function get_cm_by_coursework_name(string $name): stdClass {
        $coursework = $this->get_coursework_by_name($name);
        return get_coursemodule_from_instance('coursework', $coursework->id, $coursework->course);
    }

    /**
     * Get a coursework by name.
     *
     * @param string $name coursework name.
     * @return stdClass the corresponding DB row.
     */
    protected function get_coursework_by_name(string $name): stdClass {
        global $DB;
        return $DB->get_record('coursework', ['name' => $name], '*', MUST_EXIST);
    }
}
