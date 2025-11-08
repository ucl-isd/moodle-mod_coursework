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

namespace mod_coursework\plugininfo;

use core\plugininfo\base;

/**
 * Plugin info class for coursework candidate number providers.
 *
 * @package    mod_coursework
 * @copyright  2025 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */
class courseworkcandidateprovider extends base {
    /**
     * Should there be a way to uninstall the plugin via the administration UI.
     *
     * @return bool
     */
    public function is_uninstall_allowed(): bool {
        return true;
    }

    /**
     * Return the directory name where these types of plugins are located.
     *
     * @return string
     */
    public function get_dir(): string {
        return 'candidateprovider';
    }

    /**
     * Defines whether there should be a way to uninstall the plugin via the administration UI.
     *
     * @return bool
     */
    public function uninstall_cleanup(): bool {
        // Clean up any configuration related to this provider
        $pluginname = $this->name;

        // Remove from coursework settings if this provider was selected
        $currentprovider = get_config('mod_coursework', 'candidate_provider');
        if ($currentprovider === $pluginname) {
            unset_config('candidate_provider', 'mod_coursework');
        }

        return true;
    }
}
