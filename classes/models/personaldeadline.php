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

use AllowDynamicProperties;
use context_module;
use core\exception\coding_exception;
use core\exception\invalid_parameter_exception;
use mod_coursework\allocation\allocatable;
use mod_coursework\event\personaldeadline_created;
use mod_coursework\event\personaldeadline_updated;
use mod_coursework\framework\table_base;
use mod_coursework_coursework;

/**
 * Class personaldeadline is responsible for representing one row of the personaldeadline table.

 *
 * @property mixed personaldeadline
 * @property mixed courseworkid
 * @property mixed allocatabletype
 * @property mixed allocatableid
 * @package mod_coursework\models
 */
#[AllowDynamicProperties]
class personaldeadline extends table_base {
    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @var string
     */
    protected static $tablename = 'coursework_person_deadlines';

    /**
     * @return bool|coursework
     * @throws \dml_exception
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
     * @return bool|table_base
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function extension_exists() {
        $coursework = $this->get_coursework();

        $params = ['courseworkid' => $coursework->id,
                        'allocatableid' => $this->allocatableid,
                        'allocatabletype' => $this->allocatabletype];

        return deadline_extension::find($params);
    }

    /**
     * Get any personal deadline for this student.
     * @param allocatable|user $student
     * @param coursework $coursework
     * @return bool|table_base|void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function get_personaldeadline_for_student($student, $coursework) {
        if ($coursework->is_configured_to_have_group_submissions()) {
            $allocatable = $coursework->get_coursework_group_from_user_id($student->id());
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
     * @throws \dml_exception
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
     * @return mixed
     * @throws coding_exception
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
     * @throws \coding_exception
     * @throws \moodle_exception
     * @throws invalid_parameter_exception
     */
    public function trigger_created_updated_event(string $eventtype): void {
        global $USER;
        $allocatable = $this->get_allocatable();
        $coursework = $this->get_coursework();
        $params = [
            'objectid' => $this->id,
            'userid' => $USER->id ?? 0,
            'relateduserid' => $allocatable->type() == 'user' ? $allocatable->id() : null,
            'context' => context_module::instance($coursework->get_course_module()->id),
            'anonymous' => $coursework->blindmarking_enabled() ? 1 : 0,
            'other' => [
                'allocatabletype' => $allocatable->type(),
                'courseworkid' => $coursework->id,
                'groupid' => $allocatable->type() == 'group' ? $allocatable->id() : null,
                'deadline' => $this->personaldeadline,
            ],
        ];

        switch ($eventtype) {
            case 'create':
                $event = personaldeadline_created::create($params);
                break;
            case 'update':
                $event = personaldeadline_updated::create($params);
                break;
            default:
                throw new invalid_parameter_exception("Unexpected event type '$eventtype'");
        }
        $event->trigger();
    }

    /**
     * Get all personal deadlines for a particular coursework from the database.
     * @param int $courseworkid
     * @return array
     * @throws \dml_exception
     */
    public static function get_all_for_coursework(int $courseworkid): array {
        global $DB;
        return $DB->get_records(self::$tablename, ['courseworkid' => $courseworkid]);
    }

    /**
     * Get deadline for a particular allocatable from the database.
     * @param int $courseworkid
     * @param int $allocatableid
     * @param string $allocatabletype
     * @return ?self
     * @throws \dml_exception
     */
    public static function get_for_allocatable(int $courseworkid, int $allocatableid, string $allocatabletype): ?self {
        global $DB;
        $record = $DB->get_record(
            self::$tablename,
            ['courseworkid' => $courseworkid, 'allocatableid' => $allocatableid, 'allocatabletype' => $allocatabletype]
        );
        return $record ? new self($record) : null;
    }
}
