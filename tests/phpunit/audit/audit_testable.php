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
 * Unit tests for mod/coursework/classes/audit.php.
 *
 * @package    mod_coursework
 * @copyright  2026 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Conn Warwicker <conn.warwicker@catalyst-eu.net> (Co-Authored by Co-Pilot)
 */

namespace mod_coursework;

/**
 * Test-only subclass for exposing protected audit methods.
 */
class audit_testable extends audit {
    /**
     * @var int
     */
    public int $flaggedcount = 0;

    /**
     * Expose calculate_statistics for testing.
     *
     * @param array $grades
     * @return array
     */
    public function calculate_statistics_public(array $grades): array {
        return $this->calculate_grade_statistics($grades);
    }

    /**
     * Expose count_grades_within_boundary for testing.
     *
     * @param array $grades
     * @param array $boundary
     * @return int
     */
    public function count_grades_within_boundary_public(array $grades, array $boundary): int {
        return $this->count_grades_within_boundary($grades, $boundary);
    }

    /**
     * Override flag count so the tests are DB-independent.
     *
     * @return int
     */
    protected function count_flagged_submissions(): int {
        return $this->flaggedcount;
    }
}
