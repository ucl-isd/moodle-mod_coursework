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

    /** @var candidateprovider|null Mock provider for testing */
    private $mockprovider = null;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        if ((defined('PHPUNIT_TEST') && PHPUNIT_TEST) || defined('BEHAT_SITE_RUNNING')) {
            // Check if Behat step has specified a mock provider filepath, class name and candidate number.
            $mockproviderfilepath = get_config('core', 'behat_mock_provider_filepath');
            $mockproviderclass = get_config('core', 'behat_mock_provider_class');
            $mockcandidatenumber = get_config('core', 'behat_mock_candidate_number') ?? null;

            if ($mockproviderfilepath && $mockproviderclass) {
                $this->mockprovider = $this->load_mock_provider_from_file(
                    $mockproviderfilepath,
                    $mockproviderclass,
                    $mockcandidatenumber
                );
            }
        }
    }

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

    /**
     * Get all available candidate number providers.
     *
     * @return array Array of provider name => display name
     */
    public function get_available_providers(): array {
        $providers = [];
        $subplugins = \core_plugin_manager::instance()->get_subplugins_of_plugin('mod_coursework');
        if (empty($subplugins)) {
            return $providers;
        }

        foreach ($subplugins as $subplugin) {
            if ($subplugin->type === 'courseworkcandidateprovider') {
                $provider = self::create_provider_instance($subplugin->name);
                if ($provider && $provider->is_available()) {
                    $providers[$subplugin->name] = $provider->get_provider_name();
                }
            }
        }

        return $providers;
    }

    /**
     * Get the currently selected provider instance.
     *
     * @return candidateprovider|null Provider instance or null if none selected
     */
    public function get_selected_provider(): ?candidateprovider {
        // Return mock provider if set (for testing).
        if ($this->mockprovider !== null) {
            return $this->mockprovider;
        }
        $selected = get_config('mod_coursework', 'candidate_provider');
        if (empty($selected) || $selected === 'none') {
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

        // Return true if mock provider is set (for testing).
        if ($this->mockprovider !== null) {
            return true;
        }

        return $provider !== null && $provider->is_available();
    }

    /**
     * Create provider instance by name.
     *
     * @param string $providername Provider name (e.g., 'sitsgradepush')
     * @return candidateprovider|null Provider instance or null if not found
     */
    private function create_provider_instance(string $providername): ?candidateprovider {
        $component = 'courseworkcandidateprovider_' . $providername;
        $classname = "\\$component\\candidatenumber_provider";

        // Check if the class exists.
        if (!class_exists($classname)) {
            return null;
        }

        // Check if plugin is installed.
        $plugininfo = \core_plugin_manager::instance()->get_plugin_info($component);
        if (!$plugininfo) {
            return null;
        }

        try {
            $instance = new $classname();
            if ($instance instanceof candidateprovider) {
                return $instance;
            }
        } catch (\Exception $e) {
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
        if (!$provider) {
            return null;
        }

        try {
            return $provider->get_candidate_number($courseid, $userid);
        } catch (\Exception $e) {
            debugging('Provider failed to get candidate number: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    /**
     * Load mock provider from specified file.
     *
     * @param string $filepath Path to mock provider file
     * @param string $classname Class name of the mock provider
     * @param string|null $candidatenumber Candidate number to use
     * @return candidateprovider|null Mock provider instance or null if failed
     */
    private function load_mock_provider_from_file(string $filepath, string $classname, ?string $candidatenumber): ?candidateprovider {
        try {
            require_once($filepath);
            if (class_exists($classname)) {
                return new $classname($candidatenumber);
            }
        } catch (\Exception $e) {
            debugging('Failed to load mock provider from file: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return null;
    }
}
