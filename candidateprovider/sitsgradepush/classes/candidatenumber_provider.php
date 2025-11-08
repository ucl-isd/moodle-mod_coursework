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

namespace courseworkcandidateprovider_sitsgradepush;

use core_plugin_manager;
use Exception;
use local_sitsgradepush\scnmanager;
use mod_coursework\candidateprovider;

/**
 * Candidate number provider.
 *
 * @package    courseworkcandidateprovider_sitsgradepush
 * @copyright  2025 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
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
        // Check if SITS Grade Push is available.
        if (!$this->is_available()) {
            return null;
        }

        try {
            // Use SITS Grade Push SCN manager to get candidate number.
            return scnmanager::get_instance()->get_candidate_number_by_course_student($courseid, $userid);
        } catch (Exception $e) {
            debugging('SITS Grade Push provider failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Check if this provider is available and properly configured.
     *
     * @return bool True if provider is available
     */
    public function is_available(): bool {
        return $this->is_sitsgradepush_available() && $this->is_fetch_enabled();
    }

    /**
     * Get the human-readable name of this provider.
     *
     * @return string Provider name
     */
    public function get_provider_name(): string {
        return get_string('pluginname', 'courseworkcandidateprovider_sitsgradepush');
    }

    /**
     * Check if SITS Grade Push plugin is installed and enabled.
     *
     * @return bool True if available
     */
    private function is_sitsgradepush_available(): bool {
        // Check if the sitsgradepush plugin is installed.
        $plugininfo = core_plugin_manager::instance()->get_plugin_info('local_sitsgradepush');
        if (!$plugininfo) {
            return false;
        }

        // Check if the SCN manager class exists.
        return class_exists('\\local_sitsgradepush\\scnmanager');
    }

    /**
     * Check if candidate number fetching is enabled in SITS Grade Push.
     *
     * @return bool True if enabled
     */
    private function is_fetch_enabled(): bool {
        if (!$this->is_sitsgradepush_available()) {
            return false;
        }

        try {
            return scnmanager::get_instance()->is_fetch_candidate_numbers_enabled();
        } catch (Exception $e) {
            return false;
        }
    }
}
