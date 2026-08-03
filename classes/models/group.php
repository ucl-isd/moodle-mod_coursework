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
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This class allows us to add functionality to the users, despite the fact that Moodle has no
 * core user class. Initially, it is using the active record approach, but this may need to change to
 * a decorator if Moodle implements such a class in future.
 */

namespace mod_coursework\models;

use AllowDynamicProperties;
use cm_info;
use core_availability\info_module;
use mod_coursework\allocation\allocatable_table_base;
use mod_coursework\allocation\moderatable;
use stdClass;

/**
 * Class group
 *
 * @property string name
 * @property mixed courseid
 * @package mod_coursework\models
 */
#[AllowDynamicProperties]
class group extends allocatable_table_base implements moderatable {
    /**
     * @var string
     */
    protected static $tablename = 'groups';

    /**
     * @return string
     */
    public function name(): string {
        return $this->name;
    }

    /**
     * @return int
     */
    public function id(): int {
        return $this->id;
    }

    /**
     * @return string
     */
    public function type(): string {
        return 'group';
    }

    /**
     * @param $context
     * @param $cm
     * @return user[]
     */
    public function get_members($context, $cm) {
        $members = groups_get_members($this->id());

        $info = new info_module(cm_info::create($cm));
        $members = $info->filter_user_list($members);

        $memberobjects = [];
        foreach ($members as $member) {
            // check is member has capability to submit in this coursework (to get rid of assessors if they are placed in the group)
            if (has_capability('mod/coursework:submit', $context, $member)) {
                $memberobjects[] = user::get_from_id($member->id);
            }
        }
        return $memberobjects;
    }

    /**
     * @param bool $withpicture
     * @return void
     */
    public function profile_link($withpicture = false): string {
        debugging('Cannot call profile_link on a group', DEBUG_DEVELOPER);
    }

    /**
     * @param stdClass $course
     * @return bool
     */
    public function is_valid_for_course($course): bool {
        return $this->courseid == $course->id;
    }
}
