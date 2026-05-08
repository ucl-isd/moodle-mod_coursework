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

namespace courseworkcandidateprovider_idnumber;

use core_plugin_manager;
use core_user;
use Exception;
use local_idnumber\scnmanager;
use mod_coursework\candidateprovider;

/**
 * Candidate number provider.
 *
 * @package    courseworkcandidateprovider_idnumber
 * @copyright  2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Andrew Hancox <andrewdchancox@googlemail.com>
 */
class candidatenumber_provider extends candidateprovider {
    /**
     * Get candidate number for a user in a specific course.
     *
     * @param int $courseid Course ID
     * @param int $userid User ID
     * @return string|null Candidate number or null if not found
     */
    public function get_candidate_number(int $courseid, int $userid): ?string {
        return core_user::get_user($userid)->idnumber;
    }

    /**
     * Check if this provider is available and properly configured.
     *
     * @return bool True if provider is available
     */
    public function is_available(): bool {
        return true;
    }

    /**
     * Get the human-readable name of this provider.
     *
     * @return string Provider name
     */
    public function get_provider_name(): string {
        return get_string('pluginname', 'courseworkcandidateprovider_idnumber');
    }
}
