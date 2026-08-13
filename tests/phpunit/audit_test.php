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

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/audit/audit_testable.php');

/**
 * Unit tests for audit calculations.
 *
 * @copyright  2026 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class audit_test extends \advanced_testcase {
    /**
     * @return audit_testable
     */
    private function get_testable_audit(): audit_testable {
        $reflection = new \ReflectionClass(audit_testable::class);
        /** @var audit_testable $audit */
        $audit = $reflection->newInstanceWithoutConstructor();
        return $audit;
    }

    /**
     * @covers \mod_coursework\audit::calculate_grade_statistics
     */
    public function test_calculate_statistics_returns_zeroes_for_empty_grades(): void {
        $audit = $this->get_testable_audit();
        $stats = $audit->calculate_statistics_public([]);

        $this->assertSame(0, $stats['mean']);
        $this->assertSame(0, $stats['median']);
        $this->assertSame(0, $stats['max']);
        $this->assertSame(0, $stats['min']);
        $this->assertSame(0, $stats['sd']);
        $this->assertSame(0, $stats['flagged']);
    }

    /**
     * @covers \mod_coursework\audit::calculate_grade_statistics
     */
    public function test_calculate_statistics_for_odd_grade_count(): void {
        $audit = $this->get_testable_audit();
        $audit->flaggedcount = 2;

        $stats = $audit->calculate_statistics_public([10.0, 20.0, 30.0]);

        $this->assertEquals(20.0, $stats['mean']);
        $this->assertEquals(20.0, $stats['median']);
        $this->assertEquals(30.0, $stats['max']);
        $this->assertEquals(10.0, $stats['min']);
        $this->assertEquals(8.16, $stats['sd']);
        $this->assertSame(2, $stats['flagged']);
    }

    /**
     * @covers \mod_coursework\audit::calculate_grade_statistics
     */
    public function test_calculate_statistics_for_even_grade_count(): void {
        $audit = $this->get_testable_audit();

        $stats = $audit->calculate_statistics_public([10.0, 20.0, 30.0, 40.0]);

        $this->assertEquals(25.0, $stats['mean']);
        $this->assertEquals(25.0, $stats['median']);
        $this->assertEquals(40.0, $stats['max']);
        $this->assertEquals(10.0, $stats['min']);
        $this->assertEquals(11.18, $stats['sd']);
    }

    /**
     * @covers \mod_coursework\audit::count_grades_within_boundary
     */
    public function test_count_grades_within_boundary_is_inclusive(): void {
        $audit = $this->get_testable_audit();
        $count = $audit->count_grades_within_boundary_public([0.0, 9.99, 10.0, 15, 20.0, 20.1], [10.0, 20.0]);
        $this->assertSame(3, $count);
    }
}
