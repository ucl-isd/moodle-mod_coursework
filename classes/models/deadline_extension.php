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
use mod_coursework\event\extension_created;
use mod_coursework\event\extension_deleted;
use mod_coursework\event\extension_updated;
use mod_coursework\framework\table_base;
use mod_coursework_coursework;

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
#[AllowDynamicProperties]
class deadline_extension extends table_base {
    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @var string
     */
    protected static $tablename = 'coursework_extensions';

    /**
     * @param allocatable $allocatable
     * @param coursework $coursework
     * @return bool
     * @throws coding_exception
     */
    public static function allocatable_extension_allows_submission($allocatable, $coursework) {
        self::fill_pool_coursework($coursework->id);
        $extension = self::get_object($coursework->id, 'allocatableid-allocatabletype', [$allocatable->id(), $allocatable->type()]);

        return !empty($extension) && $extension->extended_deadline > time();
    }

    /**
     * Get extension for student.
     * @param allocatable $student
     * @param coursework $coursework
     * @return deadline_extension|bool
     * @throws \coding_exception
     * @throws coding_exception
     */
    public static function get_extension_for_student($student, $coursework) {
        if ($coursework->is_configured_to_have_group_submissions()) {
            $allocatable = $coursework->get_coursework_group_from_user_id($student->id());
        } else {
            $allocatable = $student;
        }
        if ($allocatable) {
            self::fill_pool_coursework($coursework->id);
            return self::get_object($coursework->id, 'allocatableid-allocatabletype', [$allocatable->id(), $allocatable->type()]);
        }
    }

    /**
     * @return bool|coursework
     */
    public function get_coursework() {
        if (!isset($this->coursework)) {
            $this->coursework = coursework::get_object($this->courseworkid);
        }

        return $this->coursework;
    }

    public function get_allocatable() {
        $classname = "\\mod_coursework\\models\\{$this->allocatabletype}";
        return $classname::find($this->allocatableid);
    }

    /**
     * Get the user name who is granted/holds the extension.
     * @return mixed
     */
    public function get_grantee_user_name() {
        $allocatable = self::get_allocatable();
        return $allocatable->name();
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
     * Check if the current extension is already in use - if yes, block deletion.
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function can_be_deleted(): bool {
        global $DB;
        if (!$this->get_coursework()->deadline_has_passed()) {
            // User is not yet using the extension.
            return true;
        }
        $allocatable = $this->get_allocatable();
        $params = [
            'allocatableid' => $allocatable->id(),
            'allocatabletype' => $allocatable->type(),
            'courseworkid' => $this->coursework->id(),
        ];
        $personaldeadline = personaldeadline::find($DB->get_record('coursework_person_deadlines', $params)) ?? null;
        if ($personaldeadline) {
            return $personaldeadline->personaldeadline > time();
        }
        return false;
    }

    /**
     * Delete an extension.
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function delete() {
        global $DB;
        $record = $DB->get_record(self::$tablename, ['id' => $this->id]);
        if ($record) {
            $cm = get_coursemodule_from_instance(
                'coursework',
                $this->coursework->id,
                0,
                false,
                MUST_EXIST
            );
            $DB->delete_records(self::$tablename, ['id' => $this->id]);
            self::after_destroy();
            $personaldeadline = personaldeadline::get_personaldeadline_for_student($this->get_allocatable(), $this->coursework);
            // Delete the calendar/timeline event, or set it to the existing personal deadline date if present.
            $allocatable = $this->get_allocatable();
            $this->coursework->update_user_calendar_event(
                $allocatable->id(),
                $allocatable->type(),
                $personaldeadline->personaldeadline ?? 0
            );

            // Keep a record of what's deleted in the log table for audit purposes.
            $event = extension_deleted::create([
                'objectid' => $this->id,
                'context' => context_module::instance($cm->id),
                'other' => ['record' => json_encode($record)],
            ]);

            $event->trigger();
        }
    }

    /**
     * Trigger an event when extension is created or updated.
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
                'deadline' => $this->extended_deadline,
            ],
        ];

        switch ($eventtype) {
            case 'create':
                $event = extension_created::create($params);
                break;
            case 'update':
                $event = extension_updated::create($params);
                break;
            default:
                throw new invalid_parameter_exception("Unexpected event type '$eventtype'");
        }
        $event->trigger();
    }

    /**
     * Get all extensions for a particular coursework from the database.
     * @param int $courseworkid
     * @return array
     * @throws \dml_exception
     */
    public static function get_all_for_coursework(int $courseworkid): array {
        global $DB;
        return $DB->get_records(self::$tablename, ['courseworkid' => $courseworkid]);
    }

    /**
     * Get extension for a particular allocatable from the database.
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
