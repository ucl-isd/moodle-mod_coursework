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
use cache;
use core\exception\coding_exception;
use core\exception\invalid_parameter_exception;
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
     * Cache area where objects of this class by allocatable (user or group) ID are stored.
     * @var string
     */
    const CACHE_AREA_BY_ALLOCATABLE = 'allocationsbyallocatable';


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
     * Destroy all allocations for a coursework.
     * @param int $courseworkid
     * @return void
     */
    public static function destroy_all(int $courseworkid) {
        global $DB;
        $ids = $DB->get_fieldset(static::$tablename, 'id', ['courseworkid' => $courseworkid]);
        foreach ($ids as $id) {
            $a = self::get_from_id($id);
            $a->destroy();
        }
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
        $allocations = self::get_set_for_allocatable(
            $courseworkid,
            $allocatableid,
            $allocatabletype
        );
        $filtered = array_filter($allocations, fn($a) => $a->assessorid == $assessorid ?? $USER->id);
        return !empty($filtered);
    }

    /**
     * Get allocations for an allocatable in a coursework.
     * Each allocatable may have more than one in the set (from different marking stages).
     * @param int $courseworkid
     * @param int $allocatableid
     * @param string $alloctabletype
     * @return allocation[]
     * @throws \core\exception\invalid_parameter_exception
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function get_set_for_allocatable(int $courseworkid, int $allocatableid, string $allocatabletype): array {
        if ($allocatableid <= 0 || !in_array($allocatabletype, ['user', 'group'])) {
            throw new invalid_parameter_exception("Invalid ID $allocatableid or type $allocatabletype");
        }
        $cache = cache::make('mod_coursework', self::CACHE_AREA_BY_ALLOCATABLE);
        $cachekey = self::get_allocatable_cache_key($courseworkid, $allocatableid, $allocatabletype);
        $ids = $cache->get($cachekey);
        if ($ids === false) {
            $ids = self::get_db_ids_from_allocatable($courseworkid, $allocatableid, $allocatabletype);
            $cache->set($cachekey, $ids);
        }
        $result = [];
        foreach ($ids as $id) {
            $result[] = self::get_from_id($id);
        }
        return $result;
    }

    /**
     * Get the allocation IDs from the DB for all allocations of this allocatable in this coursework.
     * @param $courseworkid
     * @param $allocatableid
     * @param $allocatabletype
     * @return array
     * @throws \dml_exception
     */
    private static function get_db_ids_from_allocatable($courseworkid, $allocatableid, $allocatabletype): array {
        global $DB;
        return $DB->get_fieldset(
            'coursework_allocation_pairs',
            'id',
            ['courseworkid' => $courseworkid, 'allocatableid' => $allocatableid, 'allocatabletype' => $allocatabletype]
        );
    }

    /**
     * Get allocation for an allocatable at a given stage in a coursework.
     * @param int $courseworkid
     * @param int $allocatableid
     * @param string $alloctabletype
     * @param string $stageidentifier stage identifier filter e.g. assessor::STAGE_ASSESSOR_1
     * @return self|null
     * @throws \core\exception\invalid_parameter_exception
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function get_for_allocatable_at_stage(int $courseworkid, int $allocatableid, string $alloctabletype, string $stageidentifier): ?self {
        $allocations = self::get_set_for_allocatable($courseworkid, $allocatableid, $alloctabletype);
        $allocation = array_pop($allocations);
        return $allocation && $allocation->stageidentifier == $stageidentifier ? $allocation : null;
    }

    /**
     * Get the allocatable for this allocation.
     * @return user|group
     * @throws coding_exception
     * @throws invalid_parameter_exception
     */
    public function get_allocatable(): user|group {
        if ($this->allocatabletype == 'user') {
            return user::get_from_id($this->allocatableid);
        } else if ($this->allocatabletype == 'group') {
            return group::get_from_id($this->allocatableid);
        } else {
            throw new coding_exception("Invalid type");
        }
    }
}
