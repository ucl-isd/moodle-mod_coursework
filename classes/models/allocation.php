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
use mod_coursework\traits\table_with_allocatable;

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
    use table_with_allocatable;

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
        $filtered = array_filter($allocations, fn($m) => $m->stageidentifier == $stageidentifier);
        return !empty($filtered) ? array_pop($filtered) : null;
    }
}
