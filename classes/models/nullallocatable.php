<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_coursework\models;

use mod_coursework\allocation\allocatable;

/**
 * Class to prevent potential fatal run time errors where an allocatable cannot be found.
 * Hopefully we can delete this in the not-too-distant future.
 */
class nullallocatable implements allocatable {
    public $id = -1; // Due to legacy of weak typing this is needed.

    public function __construct() {
        debugging('nullallocatable should never be used, only present to manage data hygiene issues', DEBUG_DEVELOPER);
    }

    public function name(): string {
        return '';
    }

    public function id(): int {
        return -1;
    }

    public function type(): string {
        return 'user';
    }

    public function profile_link(): string {
        return '';
    }

    public function is_valid_for_course($course): bool {
        return true;
    }

    public function has_agreed_feedback($coursework): bool {
        return false;
    }

    public function get_agreed_feedback($coursework): object|bool {
        return false;
    }

    public function get_initial_feedbacks($coursework): array {
        return [];
    }

    public function has_all_initial_feedbacks($coursework): bool {
        return false;
    }

    public function get_submission($coursework): ?submission {
        return null;
    }
}
