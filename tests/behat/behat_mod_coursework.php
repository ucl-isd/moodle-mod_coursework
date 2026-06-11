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
 * Custom behat steps for mod_coursework
 * Please use sparingly!
 *
 * @package    mod_coursework
 * @copyright  2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Andrew Hancox <andrewdchancox@googlemail.com>
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Moodle\BehatExtension\Exception\SkippedException;

class behat_mod_coursework extends behat_base {
    /**
     * Turnitin has been configured for behat
     *
     * @Given Turnitin has been configured for behat
     * @throws \Behat\Mink\Exception\ElementNotFoundException
     */
    public function turnitin_has_been_configured_for_behat() {
        if (
            empty(getenv('TII_APIBASEURL'))
            || empty(getenv('TII_ACCOUNT'))
            || empty(getenv('TII_SECRET'))
        ) {
            throw new SkippedException("TII not configured for behat.
                Eeasiest approach is to add the following to config.php:
                putenv('TII_ACCOUNT=XXXXXXXXXX');
                putenv('TII_SECRET=XXXXXXXXXX');
                putenv('TII_APIBASEURL=https://api.turnitinuk.com');
            ");
        }
    }
}
