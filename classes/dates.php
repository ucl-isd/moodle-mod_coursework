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
 * Contains the class for fetching the important dates in mod_coursework for a given module instance and a user.
 *
 * @package   mod_coursework
 * @copyright 2025 UCL
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_coursework;

use core\activity_dates;
use mod_coursework\models\coursework;
use mod_coursework\models\deadline_extension;
use mod_coursework\models\user;

/**
 * Class for fetching the important dates in mod_coursework for a given module instance and a user.
 *
 * @copyright 2025 UCL
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dates extends activity_dates {
    /**
     * Returns a list of important dates in mod_coursework
     *
     * @return array
     */
    protected function get_dates(): array {
        global $USER;

        $instance = coursework::find($this->cm->instance);
        $timeopen = $instance->startdate ?? null;
        $timedue = $instance->get_user_deadline($USER->id) ?? null;

        $user = user::find($USER->id);
        $deadlineextension = $user
            ? deadline_extension::get_extension_for_student($user, $instance) : null;
        if ($deadlineextension && ($deadlineextension->extended_deadline ?? null)) {
            $timedue = $deadlineextension->extended_deadline;
        }

        $dates = [];

        if ($timeopen) {
            $stringkey = $timeopen < time() ? 'activitydate:submissionsopened' : 'activitydate:submissionsopen';
            $dates[] = [
                'dataid' => 'opendate',
                'label' => get_string($stringkey, 'assign'),
                'timestamp' => (int)$timeopen,
            ];
        }
        if ($timedue) {
            $label = $timedue < time()
                ? get_string('activitydate:submissionsclosed', 'mod_coursework')
                : get_string('activitydate:submissionsdue', 'assign');
            $dates[] = ['dataid' => 'duedate', 'label' => $label, 'timestamp' => $timedue];
        }
        return $dates;
    }
}
