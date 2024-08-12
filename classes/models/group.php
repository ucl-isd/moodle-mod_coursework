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
 * @copyright  2017 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This class allows us to add functionality to the users, despite the fact that Moodle has no
 * core user class. Initially, it is using the active record approach, but this may need to change to
 * a decorator if Moodle implements such a class in future.
 */

namespace mod_coursework\models;

use mod_coursework\framework\table_base;
use mod_coursework\allocation\allocatable;
use mod_coursework\allocation\moderatable;
use mod_coursework\traits\allocatable_functions;

/**
 * Class group
 *
 * @property string name
 * @property mixed courseid
 * @package mod_coursework\models
 */
class group extends table_base implements allocatable, moderatable {

    use allocatable_functions;

    /**
     * @var string
     */
    protected static $table_name = 'groups';

    /**
     * @return string
     */
    public function name() {
        return $this->name;
    }

    /**
     * @return int
     */
    public function id() {
        return $this->id;
    }

    /**
     * @return string
     */
    public function type() {
        return 'group';
    }

    /**
     * @return string
     */
    public function picture() {
        return print_group_picture($this, $this->courseid);
    }

    /**
     * @return user[]
     */
    public function get_members($context, $cm) {
        $members = groups_get_members($this->id());

        $info = new \core_availability\info_module(\cm_info::create($cm));
        $members = $info->filter_user_list($members);

        $member_objects = [];
        foreach ($members as $member) {
            // check is member has capability to submit in this coursework (to get rid of assessors if they are placed in the group)
            if (has_capability('mod/coursework:submit', $context, $member)) {
                $member_objects[] = user::find($member);
            }
        }
        return $member_objects;
    }

    /**
     * @param bool $with_picture
     * @return string
     */
    public function profile_link($with_picture = false) {
        // TODO: Implement profle_link() method.
    }

    /**
     * @param \stdClass $course
     * @return mixed
     */
    public function is_valid_for_course($course) {
        return $this->courseid == $course->id;
    }

    /**
     * cache array
     *
     * @var
     */
    public static $pool;

    /**
     * Fill pool to cache for later use
     *
     * @param $array
     */
    public static function fill_pool($array) {
        foreach ($array as $record) {
            $object = new self($record);
            self::$pool['id'][$record->id] = $object;
        }
    }

    /**
     * @param $id
     * @return mixed
     */
    public static function get_object($id) {
        if (!isset(self::$pool['id'][$id])) {
            global $DB;
            $user = $DB->get_record(self::$table_name, ['id' => $id]);
            self::$pool['id'][$id] = new self($user);
        }
        return self::$pool['id'][$id];
    }
}
