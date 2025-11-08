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

namespace mod_coursework\behat\fixtures;

/**
 * Mock candidate provider for Behat testing.
 *
 * @package    mod_coursework
 * @copyright  2025 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */
class mock_candidate_provider extends \mod_coursework\candidateprovider {
    /** @var string|null The candidate number to return for all requests */
    private ?string $candidatenumber;

    /**
     * Constructor.
     *
     * @param string|null $candidatenumber The candidate number to return
     */
    public function __construct(?string $candidatenumber = 'TEST001') {
        $this->candidatenumber = $candidatenumber;
    }

    /**
     * Get candidate number for a user in a specific course.
     *
     * @param int $courseid Course ID
     * @param int $userid User ID
     * @return string|null Candidate number or null if not found
     */
    public function get_candidate_number(int $courseid, int $userid): ?string {
        return $this->candidatenumber;
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
        return 'Behat Mock Provider (' . $this->candidatenumber . ')';
    }
}
