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

namespace mod_coursework\models;

use core\exception\invalid_parameter_exception;
use  mod_coursework\framework\table_base;

/**
 * Class personal_deadline is responsible for representing one row of the personal_deadline table.

 *
 * @property mixed personal_deadline
 * @property mixed courseworkid
 * @property mixed allocatabletype
 * @property mixed allocatableid
 * @package mod_coursework\models
 */
#[\AllowDynamicProperties]
class personal_deadline extends table_base {

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @var string
     */
    protected static $tablename = 'coursework_person_deadlines';

    /**
     * @return mixed|\mod_coursework_coursework
     */
    public function get_coursework() {
        if (!isset($this->coursework)) {
            coursework::fill_pool_coursework($this->courseworkid);
            $this->coursework = coursework::get_object($this->courseworkid);
        }

        return $this->coursework;
    }

    public function get_allocatable() {
        $classname = "\\mod_coursework\\models\\{$this->allocatabletype}";
        return $classname::find($this->allocatableid);
    }

    /**
     * Function to check if extension for this personal deadline (alloctable) exists
     * @return static
     */
    public function extension_exists() {
        $coursework = $this->get_coursework();

        $params = ['courseworkid' => $coursework->id,
                        'allocatableid' => $this->allocatableid,
                        'allocatabletype' => $this->allocatabletype];

        return   deadline_extension::find($params);
    }

    /**
     * Get any personal deadline for this student.
     * @param \mod_coursework\allocation\allocatable|user $student
     * @param coursework $coursework
     * @return personal_deadline|bool
     */
    public static function get_personal_deadline_for_student($student, $coursework) {
        if ($coursework->is_configured_to_have_group_submissions()) {
            $allocatable = $coursework->get_student_group($student);
        } else {
            $allocatable = $student;
        }
        if ($allocatable) {
            return static::find(['courseworkid' => $coursework->id,
                                      'allocatableid' => $allocatable->id(),
                                      'allocatabletype' => $allocatable->type(),
            ]);
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
     * @param int $courseworkid
     * @return array
     */
    protected static function get_cache_array($courseworkid) {
        global $DB;
        $records = $DB->get_records(static::$tablename, ['courseworkid' => $courseworkid]);
        $result = [
            'allocatableid-allocatabletype' => [],
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
     * @param int $courseworkid
     * @param $key
     * @param $params
     * @return bool
     */
    public static function get_object($courseworkid, $key, $params) {
        if (!isset(self::$pool[$courseworkid])) {
            self::fill_pool_coursework($courseworkid);
        }
        $valuekey = implode('-', $params);
        return self::$pool[$courseworkid][$key][$valuekey][0] ?? false;
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

    /**
     * Trigger an event when deadline is created or updated.
     * @param string $eventtype create, or update.
     * @return void
     */
    public function trigger_deadline_created_updated_event(string $eventtype): void {
        global $USER;
        $allocatable = $this->get_allocatable();
        $coursework = $this->get_coursework();
        $params = [
            'objectid' => $this->id,
            'userid' => $USER->id ?? 0,
            'relateduserid' => $allocatable->type() == 'user' ? $allocatable->id() : null,
            'context' => \context_module::instance($coursework->get_course_module()->id),
            'other' => [
                'allocatabletype' => $allocatable->type(),
                'courseworkid' => $coursework->id,
                'groupid' => $allocatable->type() == 'group' ? $allocatable->id() : null,
                'deadline' => $this->personal_deadline,
            ],
        ];

        switch ($eventtype) {
            case 'create':
                $event = \mod_coursework\event\personal_deadline_created::create($params);
                break;
            case 'update':
                $event = \mod_coursework\event\personal_deadline_updated::create($params);
                break;
            default:
                throw new invalid_parameter_exception("Unexpected event type '$eventtype'");
        }
        $event->trigger();
    }
}
