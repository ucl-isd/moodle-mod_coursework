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

namespace mod_coursework;

use core_plugin_manager;
use Exception;

/**
 * Manager class for candidate number providers.
 *
 * @package    mod_coursework
 * @copyright  2025 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */
class candidateprovider_manager {
    /** @var candidateprovider_manager|null Singleton instance */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return candidateprovider_manager
     */
    public static function instance(): candidateprovider_manager {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private array $providers;
    /**
     * Get all available candidate number providers.
     *
     * @return array Array of provider name => display name
     */
    public function get_available_providers(): array {
        if (!isset($this->providers)) {
            $this->providers = [];

            foreach (core_plugin_manager::instance()->get_plugins_of_type('courseworkcandidateprovider') as $subplugin) {
                $provider = self::create_provider_instance($subplugin->name);
                if ($provider && $provider->is_available()) {
                    $this->providers[$subplugin->name] = $provider->get_provider_name();
                }
            }
        }

        return $this->providers;
    }

    /**
     * Get the currently selected provider instance.
     *
     * @return candidateprovider|null Provider instance or null if none selected
     * @throws \dml_exception
     */
    private function get_selected_provider(): ?candidateprovider {
        $selected = get_config('mod_coursework', 'candidate_provider');
        if (empty($selected)) {
            return null;
        }

        return $this->create_provider_instance($selected);
    }

    /**
     * Check if a provider is selected and available.
     *
     * @return bool True if provider is selected and available
     */
    public function is_provider_available(): bool {
        $provider = $this->get_selected_provider();

        return $provider !== null && $provider->is_available();
    }

    /**
     * Create provider instance by name.
     *
     * @param string $providername Provider name (e.g., 'sitsgradepush')
     * @return candidateprovider|null Provider instance or null if not found
     */
    private function create_provider_instance(string $providername): ?candidateprovider {
        $classname = "\\courseworkcandidateprovider_$providername\\candidatenumber_provider";

        try {
            $instance = new $classname();

            if ($instance instanceof candidateprovider) {
                return $instance;
            }

            debugging('Unexpected type returned by ' . $providername, DEBUG_DEVELOPER);
        } catch (Exception $e) {
            debugging('Failed to create provider instance for ' . $providername . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return null;
    }

    /**
     * Get candidate number using the selected provider.
     *
     * @param int $courseid Course ID
     * @param int $userid User ID
     * @return string|null Candidate number or null if not found
     */
    public function get_candidate_number(int $courseid, int $userid): ?string {
        $provider = $this->get_selected_provider();
        if (!$provider || !$provider->is_available()) {
            return null;
        }

        try {
            return $provider->get_candidate_number($courseid, $userid);
        } catch (Exception $e) {
            debugging('Provider failed to get candidate number: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }
}
