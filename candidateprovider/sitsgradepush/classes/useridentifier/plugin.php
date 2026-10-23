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

namespace courseworkcandidateprovider_sitsgradepush\useridentifier;

use mod_coursework\candidateprovider_manager;

/**
 * User identifier plugin for courseworkcandidateprovider_sitsgradepush.
 *
 * @package   courseworkcandidateprovider_sitsgradepush
 * @author    Conn Warwicker <conn.warwicker@catalyst-eu.net>
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin implements \core_user\identifier\base {
    #[\Override]
    public function get_user_identifier(int $userid, string $key, ?array $data = []): ?string {
        return candidateprovider_manager::instance()->get_candidate_number($data['activity']->get_course()->id, $userid);
    }
}
