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
 * Class plagiarism flag is responsible for representing one row of the plagiarism flags table.

 *
 * @property mixed personaldeadline
 * @property mixed courseworkid
 * @property mixed allocatabletype
 * @property mixed allocatableid
 * @package mod_coursework\models
 */
#[AllowDynamicProperties]
class plagiarism_flag extends table_base {
    /**
     * Cache area where objects by ID are stored.
     * @var string
     */
    const CACHE_AREA_IDS = 'plagiriasmflagids';


    /**
     * Cache area where object IDs of this class are stored by submission ID.
     * @var string
     */
    const CACHE_AREA_BY_SUBMISSION = 'plagiarismbysubmissionid';

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @var string
     */
    protected static $tablename = 'coursework_plagiarism_flags';

    /**
     * Constants with Statuses for Plagiarism flagging
     */
    const INVESTIGATION = 0;
    const RELEASED = 1;
    const CLEARED = 2;
    const NOTCLEARED = 3;

    /**
     * @return bool|coursework
     * @throws \dml_exception
     */
    public function get_coursework() {
        if (!isset($this->coursework)) {
            $this->coursework = coursework::get_from_id($this->courseworkid);
        }

        return $this->coursework;
    }

    /**
     * Memoized getter
     *
     * @return bool|submission
     * @throws coding_exception
     */
    public function get_submission() {
        if (!isset($this->submission) && !empty($this->submissionid)) {
            $this->submission = submission::get_from_id($this->submissionid);
        }
        return $this->submission;
    }

    /**
     * @return bool
     */
    public function can_release_grades() {

        switch ($this->status) {
            case self::INVESTIGATION:
            case self::NOTCLEARED:
                return false;
            case self::RELEASED:
            case self::CLEARED:
                return true;
        }
    }

    /**
     * Remove all plagiarism flags by a submission
     *
     * @param int $submissionid
     */
    public static function remove_plagiarisms_by_submission(int $submissionid) {
        global $DB;
        $ids = $DB->get_fieldset(
            'id',
            'coursework_plagiarism_flags',
            ['submissionid' => $submissionid]
        );
        foreach ($ids as $id) {
            $flag = self::get_from_id($id);
            $flag->destroy();
        }
    }


    /**
     * Clear caches used by this object.
     */
    public function clear_cache() {
        // For this class we implement a submission ID cache so clear that.
        $cachetoclear = cache::make('mod_coursework', self::CACHE_AREA_BY_SUBMISSION);
        $cachetoclear->delete($this->submissionid);

        parent::clear_cache();
    }

    /**
     * Get all objects for a submission.
     * @param int $submissionid
     * @return self|null
     */
    public static function get_for_submission(int $submissionid): ?self {
        global $DB;
        if ($submissionid <= 0) {
            throw new invalid_parameter_exception("Invalid ID $submissionid");
        }
        $cache = cache::make('mod_coursework', static::CACHE_AREA_BY_SUBMISSION);
        $cachedid = $cache->get($submissionid);
        if ($cachedid === false) {
            $cachedid = $DB->get_field(
                self::$tablename,
                'id',
                ['submissionid' => $submissionid]
            );
            $cache->set($submissionid, $cachedid);
        }
        return $cachedid ? self::get_from_id($cachedid) : null;
    }

}
