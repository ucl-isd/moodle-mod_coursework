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

use AllowDynamicProperties;
use core\exception\coding_exception;
use mod_coursework\allocation\moderatable;
use mod_coursework\framework\table_base;
use core_cache\cache;
use mod_coursework\traits\table_with_allocatable;

/**
 * @property int courseworkid
 * @property int studentid
 * @package mod_coursework\models
 *
 */
#[AllowDynamicProperties]
class assessment_set_membership extends table_base implements moderatable {
    use table_with_allocatable;

    /**
     * Cache area where objects by ID are stored.
     * @var string
     */
    const CACHE_AREA_IDS = 'assessmentsetids';

    /**
     * Cache area where objects of this class by allocatable (user or group) ID are stored.
     * @var string
     */
    const CACHE_AREA_BY_ALLOCATABLE = 'assessmentsetmbrshpbyallocatable';

    /**
     * @var string
     */
    protected static $tablename = 'coursework_sample_set_mbrs';

    const CACHE_AREA_MEMBER_COUNT = 'samplesetmembershipcount';

    /**
     * Allocatable ID.
     * @var int
     */
    protected int $allocatableid;


    /**
     * Allocatable type
     * @var string
     */
    protected string $allocatabletype;

    /**
     * Stgae identifier
     * @var string
     */
    protected string $stageidentifier;

    /**
     * Allows subclasses to alter data before it hits the DB.
     * @return void
     * @throws \dml_exception
     */
    protected function pre_save_hook() {
        $this->membership_count_clear_cache_for_coursework();
        parent::pre_save_hook();
    }

    /**
     * How many times does an allocatable appear in coursework_sample_set_mbrs table for a coursework?
     * We expect them to appear once for each stage (with a different stageidentifier field each time).
     * @param int $courseworkid
     * @param string $allocatabletype
     * @param int $allocatableid
     * @return int
     * @throws coding_exception
     * @throws \dml_exception
     */
    public static function membership_count(int $courseworkid, string $allocatabletype, int $allocatableid): int {
        global $DB;
        // This is called over and over during rendering of the grading page, so cache the result.
        $cachekey = self::membership_count_cache_key($courseworkid, $allocatabletype, $allocatableid);

        $cache = cache::make('mod_coursework', self::CACHE_AREA_MEMBER_COUNT);
        $cachedvalue = $cache->get($cachekey);
        if ($cachedvalue === false) {
            $sql = "SELECT COUNT(id)
                    FROM {coursework_sample_set_mbrs}
                    WHERE courseworkid = :courseworkid
                    AND allocatableid = :allocatableid
                    AND allocatabletype = :allocatabletype";

            $cachedvalue = $DB->count_records_sql(
                $sql,
                [
                    'courseworkid' => $courseworkid,
                    'allocatableid' => $allocatableid,
                    'allocatabletype' => $allocatabletype,
                ]
            );
            $cache->set($cachekey, $cachedvalue);
        }
        return $cachedvalue;
    }

    /**
     * Clear membership count cache for all membership records we have for this coursework.
     * @return void
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function membership_count_clear_cache_for_coursework() {
        global $DB;
        $concat = $DB->sql_concat('courseworkid', "'_'", 'allocatabletype', "'_'", 'allocatableid');
        $cachekeys = $DB->get_fieldset_sql(
            "SELECT $concat FROM {coursework_sample_set_mbrs} WHERE courseworkid = ?
                GROUP BY courseworkid, allocatabletype, allocatableid",
            [$this->courseworkid]
        );
        if (!empty($cachekeys)) {
            $cache = cache::make('mod_coursework', self::CACHE_AREA_MEMBER_COUNT);
            $cache->delete_many($cachekeys);
        }
    }

    /**
     * Get the cache key used for membership count cache.
     * @param int $courseworkid
     * @param string $allocatabletype
     * @param int $allocatableid
     * @return string
     */
    private static function membership_count_cache_key(int $courseworkid, string $allocatabletype, int $allocatableid): string {
        return $courseworkid . "_" . $allocatabletype . "_" . $allocatableid;
    }

    /**
     * Makes a new instance and saves it.
     *
     * @param \stdClass|array $params
     * @return table_base
     */
    public static function create($params) {
        $cache = cache::make('mod_coursework', self::CACHE_AREA_MEMBER_COUNT);
        $params = (array)$params;
        $cache->delete(self::membership_count_cache_key($params['courseworkid'], $params['allocatabletype'], $params['allocatableid']));
        return parent::create($params);
    }

    /**
     * Hook method to allow subclasses to get stuff done like destruction of dependent records.
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function before_destroy(): void {
        $this->membership_count_clear_cache_for_coursework();
        parent::before_destroy();
    }

    /**
     * @param int $courseworkid
     * @param int $stagenumber
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public static function get_manually_selected_allocatables_for_stage(int $courseworkid, int $stagenumber): array {
        global  $DB;
        return $DB->get_records(
            self::$tablename,
            ['courseworkid' => $courseworkid, 'stageidentifier' => "assessor_$stagenumber", 'selectiontype' => 'manual'],
            'id',
            'allocatableid, courseworkid, allocatabletype, stageidentifier, selectiontype'
        );
    }

    /**
     * Get automatic allocatables with feedback.
     * @param int $courseworkid
     * @param int $stagenumber
     * @return array
     * @throws \dml_exception
     */
    public static function get_automatic_with_feedback(int $courseworkid, int $stagenumber) {
        global $DB;
        return $DB->get_records_sql(
            "SELECT s.allocatableid, f.*
            FROM {coursework_submissions} s,
                {coursework_feedbacks} f,
                {coursework_sample_set_mbrs} m
            WHERE s.id = f.submissionid
                AND s.courseworkid = :courseworkid
                AND f.stageidentifier = :stageidentifier
                AND s.courseworkid = m.courseworkid
                AND s.allocatableid = m.allocatableid
                AND s.allocatabletype = m.allocatabletype
                AND f.stageidentifier = m.stageidentifier",
            ['courseworkid' => $courseworkid, 'stageidentifier' => "assessor_{$stagenumber}"]
        );
    }

    /**
     * Remove unmarked automatic allocatables.
     * @param int $courseworkid
     * @param int $stagenumber
     * @return bool|void
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function remove_unmarked_automatic_allocatables(int $courseworkid, int $stagenumber) {
        global $DB;
        $sql = "FROM {coursework_sample_set_mbrs}
            WHERE selectiontype = 'automatic'
            AND stageidentifier = :stage
            AND courseworkid = :courseworkid
            AND allocatableid NOT IN (
                SELECT s.allocatableid
                FROM {coursework_submissions} s,
                     {coursework_feedbacks} f
                WHERE s.id = f.submissionid
                 AND s.courseworkid = :cwid
                 AND f.stageidentifier = :stg
            )";

        $params = [
            'stage' => "assessor_$stagenumber",
            'stg' => "assessor_$stagenumber",
            'courseworkid' => $courseworkid,
            'cwid' => $courseworkid,
        ];

        $concatcachekeys = $DB->sql_concat('courseworkid', "'_'", 'allocatabletype', "'_'", 'allocatableid');
        $cachekeys = $DB->get_fieldset_sql(
            "SELECT $concatcachekeys $sql GROUP BY courseworkid, allocatableid, allocatabletype",
            $params
        );
        if (!empty($cachekeys)) {
            $cache = cache::make('mod_coursework', self::CACHE_AREA_MEMBER_COUNT);
            $cache->delete_many($cachekeys);
            return $DB->execute("DELETE $sql", $params);
        }
    }

    /**
     * Get membership for an allocatable at a given stage in a coursework.
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
        $memberships = self::get_set_for_allocatable($courseworkid, $allocatableid, $alloctabletype);
        $filtered = array_filter($memberships, fn($m) => $m->stageidentifier == $stageidentifier);
        return array_pop($filtered);
    }
}
