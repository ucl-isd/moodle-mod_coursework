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
     * Cache area where objects by ID are stored.
     * @var string
     */
    const CACHE_AREA_IDS = 'allocationids';

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
            $this->coursework = coursework::get_from_id($this->courseworkid);
        }

        return $this->coursework;
    }

    /**
     * @return user
     */
    public function assessor() {
        return user::get_from_id($this->assessorid);
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

    public function togglepin(bool $state) {
        if ($state !== $this->is_pinned()) {
            $this->update_attribute('ismanual', $state);
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
        $result = array_fill_keys(self::get_valid_cache_keys(), []);
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
     * Get the allowed/expected cache keys for this class when @see self::get_cached_object() is called.
     * @return string[]
     */
    protected static function get_valid_cache_keys(): array {
        return [
            'id',
            'stageidentifier',
            'allocatableid-allocatabletype-stageidentifier',
            'allocatableid-allocatabletype-assessorid',
            'assessorid-allocatabletype',
        ];
    }

    /**
     *
     * @param int $courseworkid
     * @param $key
     * @param $params
     * @return self|bool
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
        self::clear_cache($this->id);
        self::remove_cache($this->courseworkid);
    }

    /**
     * Destroy all allocations for a coursework.
     * @param int $courseworkid
     * @return void
     */
    public static function destroy_all(int $courseworkid) {
        global $DB;
        $ids = $DB->get_fieldset(static::$tablename, 'id', ['courseworkid' => $courseworkid]);
        foreach ($ids as $id) {
            self::clear_cache($id);
        }
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
        return (bool)self::get_cached_object(
            $courseworkid,
            [
                'allocatableid' => $allocatableid,
                'allocatabletype' => $allocatabletype,
                'assessorid' => $assessorid ?? $USER->id,
            ]
        );
    }

    /**
     * Get allocations for an assessor/alloctable pair.
     * @param int $courseworkid
     * @param int $allocatableid
     * @return allocation[]
     * @throws \core\exception\invalid_parameter_exception
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function get_for_allocatable(int $courseworkid, int $allocatableid, string $alloctabletype): ?static {
        throw new coding_exception("TODO Use parent for this");
        global $DB;
        $allocationids = $DB->get_fieldset(
            'coursework_allocation_pairs',
            'id',
            ['courseworkid' => $courseworkid, 'allocatableid' => $allocatableid]
        );
        $result = [];
        foreach ($allocationids as $allocationid) {
            $result[] = self::get_from_id($allocationid);
        }
        return $result;
    }
}
