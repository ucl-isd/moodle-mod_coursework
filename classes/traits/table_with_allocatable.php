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

namespace mod_coursework\traits;

use core\exception\coding_exception;
use core\exception\invalid_parameter_exception;
use core_cache\cache;
use mod_coursework\allocation\allocatable;
use mod_coursework\framework\table_base;
use mod_coursework\models\assessment_set_membership;
use mod_coursework\models\deadline_extension;
use mod_coursework\models\group;
use mod_coursework\models\personaldeadline;
use mod_coursework\models\plagiarism_flag;
use mod_coursework\models\user;

/**
 * Trait to support the many child classes of table_base which have an allocatable (user or group).
 * @see deadline_extension, personaldeadline, assessment_set_membership, submission, plagiarism_flag
 * @package    mod_coursework
 * @copyright  2026 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait table_with_allocatable {
    /**
     * Get the set of objects of the child class in a coursework which relate to this allocatable.
     * E.g. all the @see assessment_set_membership objects for the different marking stages.
     * @param int $courseworkid
     * @param int $allocatableid
     * @param string $allocatabletype
     * @return static[]
     * @throws \core\exception\invalid_parameter_exception
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function get_set_for_allocatable(int $courseworkid, int $allocatableid, string $allocatabletype): array {
        if ($allocatableid <= 0 || !in_array($allocatabletype, ['user', 'group'])) {
            throw new invalid_parameter_exception("Invalid ID $allocatableid or type $allocatabletype");
        }
        $cache = cache::make('mod_coursework', static::class::CACHE_AREA_BY_ALLOCATABLE);
        $cachekey = self::get_allocatable_cache_key($courseworkid, $allocatableid, $allocatabletype);
        $ids = $cache->get($cachekey);
        if ($ids === false) {
            $ids = self::get_db_ids_from_allocatable($courseworkid, $allocatableid, $allocatabletype);
            $cache->set($cachekey, $ids);
        }
        $result = [];
        if (!empty($ids)) {
            foreach ($ids as $id) {
                $result[] = static::get_from_id($id);
            }
        }
        return $result;
    }

    /**
     * Get a single object of the class using this trait from its allocatable.
     * @param int $courseworkid
     * @param int $allocatableid
     * @param string $allocatabletype
     * @return self|null
     * @throws \dml_exception
     * @throws coding_exception
     * @throws invalid_parameter_exception
     */
    public static function get_for_allocatable(int $courseworkid, int $allocatableid, string $allocatabletype): ?self {
        $set = self::get_set_for_allocatable($courseworkid, $allocatableid, $allocatabletype);
        if (count($set) > 1) {
            throw new coding_exception(
                "This allocatable relates to multiple object of the class " . static::class . " in coursework $courseworkid. "
                    . " To get the full set of objects, use get_set_for_allocatable() instead."
            );
        }
        return array_pop($set);
    }

    /**
     * Get the membership IDs from the DB for all memberships of this allocatable in this coursework.
     * @param int $courseworkid
     * @param int $allocatableid
     * @param string $allocatabletype
     * @return int[]
     * @throws \dml_exception
     */
    private static function get_db_ids_from_allocatable(int $courseworkid, int $allocatableid, string $allocatabletype): array {
        global $DB;
        return $DB->get_fieldset(
            static::class::$tablename,
            'id',
            ['courseworkid' => $courseworkid, 'allocatableid' => $allocatableid, 'allocatabletype' => $allocatabletype]
        );
    }

    /**
     * Get the cache key used for @see static::CACHE_AREA_BY_ALLOCATABLE
     * @param int $courseworkid
     * @param int $allocatableid
     * @param string $allocatabletype
     * @return string
     */
    public static function get_allocatable_cache_key(int $courseworkid, int $allocatableid, string $allocatabletype): string {
        $typeprefix = substr($allocatabletype, 0, 1);
        // E.g. c3u10 for coursework ID 3, user ID 10 or c3g10 for coursework ID 3, group ID 10.
        return "c$courseworkid$typeprefix$allocatableid";
    }


    /**
     * Clear the caches for this object.
     * @return void
     */
    public function clear_cache() {
        if (static::CACHE_AREA_BY_ALLOCATABLE) {
            // For this class we implement user/group ID caches so clear them.
            // E.g. submission, deadline and extension all use this.
            $allocatable = $this->get_allocatable();
            if ($allocatable && $allocatable->persisted()) {
                $cachetoclear = cache::make('mod_coursework', static::CACHE_AREA_BY_ALLOCATABLE);
                $cachetoclear->delete(self::get_allocatable_cache_key($this->courseworkid, $allocatable->id(), $allocatable->type()));
            }
        }
        parent::clear_cache();
    }

    /**
     * Get the allocatable relating to the object using this trait
     * @return user|group
     */
    public function get_allocatable(): user|group {
        if (!$this->allocatableid) {
            throw new coding_exception(
                "Object of class " . static::class . "  must have an allocatable (e.g. user)"
            );
        }
        if ($this->allocatabletype == 'user') {
            return user::get_from_id($this->allocatableid);
        } else if ($this->allocatabletype == 'group') {
            return group::get_from_id($this->allocatableid);
        } else {
            throw new \core\exception\coding_exception("Invalid type '" . $this->allocatabletype . "'");
        }
    }

    /**
     * Get the allocatable ID relating to the object using this trait without getting the full object
     * @return int|null
     */
    public function get_allocatable_id(): ?int {
        return $this->allocatableid ?? null;
    }

    /**
     * Get the allocatable type relating to the object using this trait without getting the full object
     * @return string|null
     */
    public function get_allocatable_type(): ?string {
        return $this->allocatabletype ?? null;
    }
}
