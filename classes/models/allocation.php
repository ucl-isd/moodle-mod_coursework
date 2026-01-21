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
use core\exception\coding_exception;
use mod_coursework\framework\table_base;

/**
 * Represents a row in the coursework_allocation_pairings table.
 *
 * @property string stageidentifier
 * @property int moderator
 * @property mixed allocatableid
 * @property mixed allocatabletype
 */
#[AllowDynamicProperties]
class allocation extends table_base {
    /**
     * @var string
     */
    protected static $tablename = 'coursework_allocation_pairs';

    /**
     * @var int
     */
    public $courseworkid;

    /**
     * @var coursework
     */
    public $coursework;

    /**
     * @var int
     */
    public $assessorid;

    /**
     * @var int
     */
    public $studentid;

    /**
     * @var int
     */
    public $ismanual;

    /**
     * @var int UNIX timestamp for the point at which this started to be marked. If it's within a set timeframe, we prevent
     * reallocation in case marking is in progress.
     */
    public $timelocked;

    /**
     * @var array
     */
    protected $fields = [
        'id',
        'courseworkid',
        'assessorid',
        'studentid',
        'ismanual',
        'timelocked',
    ];

    /**
     * @return bool|coursework|table_base
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_coursework() {

        if (!isset($this->coursework)) {
            $this->coursework = coursework::find($this->courseworkid);
        }

        return $this->coursework;
    }

    /**
     * @return user
     */
    public function assessor() {
        return user::get_cached_object_from_id($this->assessorid);
    }

    /**
     * @return string
     */
    public function assessor_name() {
        return $this->assessor()->profile_link();
    }

    /**
     * @return bool
     */
    public function is_pinned(): bool {
        return (bool)$this->ismanual;
    }

    /**
     * @param user $assessor
     */
    public function set_assessor($assessor) {
        $this->update_attribute('assessorid', $assessor->id);
    }

    /**
     *
     */
    public function pin() {
        if (empty($this->ismanual)) {
            $this->update_attribute('ismanual', 1);
        }
    }

    /**
     *
     */
    public function unpin() {
        if ($this->ismanual) {
            $this->update_attribute('ismanual', 0);
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
            'id' => [],
            'stageidentifier' => [],
            'allocatableid-allocatabletype-stageidentifier' => [],
            'allocatableid-allocatabletype-assessorid' => [],
            'assessorid-allocatabletype' => [],
        ];
        if ($records) {
            foreach ($records as $record) {
                $object = new self($record);
                $result['id'][$record->id] = $object;
                $result['stageidentifier'][$record->stageidentifier][] = $object;
                $result['allocatableid-allocatabletype-stageidentifier'][$record->allocatableid . '-' . $record->allocatabletype . '-' . $record->stageidentifier][] = $object;
                $result['allocatableid-allocatabletype-assessorid'][$record->allocatableid . '-' . $record->allocatabletype . '-' . $record->assessorid][] = $object;
                $result['assessorid-allocatabletype'][$record->assessorid . '-' . $record->allocatabletype][] = $object;
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
     * Destroy all allocations for a coursework.
     * @param int $courseworkid
     * @return void
     */
    public static function destroy_all(int $courseworkid) {
        global $DB;
        $DB->delete_records(static::$tablename, ['courseworkid' => $courseworkid]);
        self::remove_cache($courseworkid);
    }

    /**
     * Checks whether the current user or specific assessor is allocated to mark this student's submission.
     * @param int $courseworkid
     * @param int $allocatableid
     * @param string $allocatabletype
     * @param int|null $assessorid
     * @return bool
     */
    public static function allocatable_is_allocated_to_assessor(
        int $courseworkid,
        int $allocatableid,
        string $allocatabletype,
        ?int $assessorid = null
    ): bool {
        global $USER;
        return (bool)self::get_object(
            $courseworkid,
            'allocatableid-allocatabletype-assessorid',
            [
                $allocatableid,
                $allocatabletype,
                $assessorid ?? $USER->id,
            ]
        );
    }
}
