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

use mod_coursework\framework\table_base;
use mod_coursework\allocation\allocatable;
use mod_coursework\allocation\moderatable;
use mod_coursework\traits\allocatable_functions;

/**
 * Class user
 * @package mod_coursework\models
 */
#[\AllowDynamicProperties]
class user extends table_base implements allocatable, moderatable {

    use allocatable_functions;

    /**
     * @var string
     */
    protected static $tablename = 'user';

    /**
     * @param array|object|bool $data
     */
    public function __construct($data = false) {
        $allnames = \core_user\fields::get_name_fields();
        foreach ($allnames as $namefield) {
            $this->$namefield = '';
        }
        parent::__construct($data);
    }

    /**
     * Get the user's full name.
     * @return string
     */
    public function name(): string {
        // If we already have properties to get the name without going to database, use them.
        $data = new \stdClass;
        $hasallfields = true;
        foreach (\core_user\fields::get_name_fields() as $field) {
            if ($this->$field ?? false) {
                $data->$field = $this->$field;
            } else {
                $hasallfields = false;
                break;
            }
        }
        if ($hasallfields) {
            return \core_user::get_fullname($data);
        }

        return \core_user::get_fullname($this->get_raw_record());
    }

    /**
     * @return string
     */
    public function type() {
        return 'user';
    }

    /**
     * @return string
     */
    public function profile_link() {
        return \html_writer::link(new \moodle_url('/user/view.php', ['id' => $this->id()]), $this->name(), ['data-assessorid' => $this->id()]);
    }

    /**
     * @param \stdClass $course
     * @return mixed
     */
    public function is_valid_for_course($course) {
        $coursecontext = \context_course::instance($course->id);
        return is_enrolled($coursecontext, $this->id(), 'mod/coursework:submit');
    }

    /**
     * @param coursework $coursework
     * @param $remindernumber
     * @param int $extension
     * @return bool
     * @throws \coding_exception
     */
    public function has_not_been_sent_reminder($coursework, $remindernumber, $extension=0) {
        $conditions = [
            'coursework_id' => $coursework->id,
            'userid' => $this->id(),
            'remindernumber' => $remindernumber,
            'extension' => $extension,
        ];
        return !reminder::exists($conditions);

    }

    /**
     * This is here because running an array_unique against an array of user objects was failing for not obvious
     * reason. A comparison of the objects as strings seemed to show them as
     * @return int|string
     */
    public function __toString() {
        return $this->id;
    }

    /**
     * Get user picture.
     *
     * @param int $size
     * @return string
     */
    public function get_user_picture_url(int $size = 100): string {
        global $PAGE;
        $userpicture = new \core\output\user_picture($this->get_raw_record());
        $userpicture->size = $size;
        return $userpicture->get_url($PAGE)->out(false);
    }

    /**
     * Get user picture URL as string without going to database.
     * @param int|null $usercontextid
     * @param int|null $rev
     * @return string
     */
    public static function get_picture_url_from_context_id(?int $usercontextid, ?int $rev): string {
        global $PAGE, $OUTPUT;
        // On teacher grading page, we avoid using \core\output\user_picture.
        // We don't need the extra fields and it results in additional DB queries.
        if ($usercontextid && $rev) {
            $url = \moodle_url::make_pluginfile_url(
                $usercontextid,
                'user',
                'icon',
                null,
                "/" . $PAGE->theme->name . "/",
                "f1",
                false,
                false
            );
            $url->param('rev', $rev);
            return $url->out(false);
        }
        return $OUTPUT->image_url('u/f1')->out(false);
    }

    /**
     * Get user profile url.
     *
     * @return string
     */
    public function get_user_profile_url(): string {
        $url = new \moodle_url('/user/profile.php', ['id' => $this->id()]);
        return $url->out(false);
    }

    /**
     * cache array
     *
     * @var
     */
    //public static $pool;

    /**
     *
     * @param int $courseworkid
     * @throws \dml_exception
     */
    /*
    public static function fill_pool_coursework($coursework_id) {
        if (isset(static::$pool[$coursework_id])) {
            return;
        }
        $key = static::$table_name;
        $cache = \cache::make('mod_coursework', 'courseworkdata', ['id' => $coursework_id]);

        $data = $cache->get($key);
        if ($data === false) {
            // no cache found
            $data = static::get_cache_array($coursework_id);
            $cache->set($key, $data);
        }

        static::$pool[$coursework_id] = $data;
    }
    */

    /**
     * @param int $courseworkid
     */
    /*
    public static function remove_cache($coursework_id) {
        global $SESSION;
        if (!empty($SESSION->keep_cache_data)) {
            return;
        }
        static::$pool[$coursework_id] = null;
        $cache = \cache::make('mod_coursework', 'courseworkdata', ['id' => $coursework_id]);
        $cache->delete(static::$table_name);
    }
    */

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
     * @return self
     */
    public static function get_object($id) {
        if (!isset(self::$pool['id'][$id])) {
            global $DB;
            $user = $DB->get_record(self::$tablename, ['id' => $id]);
            self::$pool['id'][$id] = new self($user);
        }
        return self::$pool['id'][$id];
    }

    /**
     * To save multiple queries to get user picture data, get relevant user context IDs for course in one hit.
     * @param int $courseid
     * @return array
     */
    public static function get_user_picture_context_ids(int $courseid): array {
        global $DB;
        $ctxids = [];
        $rs = $DB->get_recordset_sql(
        "SELECT u.id, ctx.id as ctxid
                FROM {user} u
                JOIN {context} ctx on ctx.instanceid = u.id AND ctx.contextlevel = ?
                JOIN {user_enrolments} ue ON ue.userid = u.id
                JOIN {enrol} e ON ue.enrolid = e.id AND e.courseid = ?
                WHERE u.picture != 0",
            [CONTEXT_USER, $courseid]
        );
        if ($rs->valid()) {
            foreach($rs as $r) {
                $ctxids[$r->id] = $r->ctxid;
            }
            $rs->close();
        }
        return $ctxids;
    }

}
