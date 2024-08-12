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

namespace mod_coursework\models;

use mod_coursework\framework\table_base;
use mod_coursework\allocation\allocatable;

/**
 * Class deadline_extension is responsible for representing one row of the deadline_extensions table.
 * Each extension is awarded to a user or group so that they are allowed to submit after the deadline
 * due to extenuating circumstances.
 *
 * @property mixed extended_deadline
 * @property mixed courseworkid
 * @property mixed allocatabletype
 * @property mixed allocatableid
 * @package mod_coursework\models
 */
class deadline_extension extends table_base {

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @var string
     */
    protected static $table_name = 'coursework_extensions';

    /**
     * @param allocatable $allocatable
     * @param coursework $coursework
     * @return bool
     */
    public static function allocatable_extension_allows_submission($allocatable, $coursework) {
        self::fill_pool_coursework($coursework->id);
        $extension = self::get_object($coursework->id, 'allocatableid-allocatabletype', [$allocatable->id(), $allocatable->type()]);

        return !empty($extension) && $extension->extended_deadline > time();
    }

    /**
     * @param user $student
     * @param coursework $coursework
     * @return deadline_extension|bool
     */
    public static function get_extension_for_student($student, $coursework) {
        if ($coursework->is_configured_to_have_group_submissions()) {
            $allocatable = $coursework->get_student_group($student);
        } else {
            $allocatable = $student;
        }
        if ($allocatable) {
            self::fill_pool_coursework($coursework->id);
            $extension = self::get_object($coursework->id, 'allocatableid-allocatabletype', [$allocatable->id(), $allocatable->type()]);
            return $extension;
        }
    }

    /**
     * @return mixed|\mod_coursework_coursework
     */
    public function get_coursework() {
        if (!isset($this->coursework)) {
            $this->coursework = coursework::get_object($this->courseworkid);
        }

        return $this->coursework;
    }

    public function get_allocatable() {
        $class_name = "\\mod_coursework\\models\\{$this->allocatabletype}";
        return $class_name::find($this->allocatableid);
    }

    protected function pre_save_hook() {
        global $USER;

        if (!$this->persisted()) {
            $this->createdbyid = $USER->id;
        }
    }

    /**
     * cache array
     *
     * @var
     */
    public static $pool;

    /**
     *
     * @param $coursework_id
     * @return array
     */
    protected static function get_cache_array($coursework_id) {
        global $DB;
        $records = $DB->get_records(static::$table_name, ['courseworkid' => $coursework_id]);
        $result = [
            'allocatableid-allocatabletype' => []
        ];
        if ($records) {
            foreach ($records as $record) {
                $object = new self($record);
                $result['allocatableid-allocatabletype'][$record->allocatableid . '-' . $record->allocatabletype][] = $object;
            }
        }
        return $result;
    }

    /**
     *
     * @param $coursework_id
     * @param $key
     * @param $params
     * @return bool
     */
    public static function get_object($coursework_id, $key, $params) {
        if (!isset(self::$pool[$coursework_id])) {
            self::fill_pool_coursework($coursework_id);
        }
        $value_key = implode('-', $params);
        return self::$pool[$coursework_id][$key][$value_key][0] ?? false;
    }

    /**
     *
     */
    protected function post_save_hook() {
        self::remove_cache($this->courseworkid);
    }

    /**
     *
     */
    protected function after_destroy() {
        self::remove_cache($this->courseworkid);
    }

}
