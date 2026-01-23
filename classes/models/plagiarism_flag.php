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
use mod_coursework_coursework;

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
            coursework::fill_pool_coursework($this->courseworkid);
            $this->coursework = coursework::get_cached_object_from_id($this->courseworkid);
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
            submission::fill_pool_coursework($this->courseworkid);
            $this->submission = isset(submission::$pool[$this->courseworkid]['id'][$this->submissionid]) ?
                submission::$pool[$this->courseworkid]['id'][$this->submissionid] : null;
        }

        return $this->submission;
    }

    /**
     * @param $submission
     * @return ?static
     * @throws coding_exception
     */
    public static function get_plagiarism_flag($submission) {
        self::fill_pool_coursework($submission->courseworkid);
        return self::get_cached_object(
            $submission->courseworkid,
            ['submissionid' => $submission->id]
        );
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
        $records = $DB->get_records(self::$tablename, ['courseworkid' => $courseworkid]);
        $result = array_fill_keys(self::get_valid_cache_keys(), []);
        if ($records) {
            foreach ($records as $record) {
                $object = new self($record);
                $result['submissionid'][$record->submissionid][] = $object;
            }
        }
        return $result;
    }

    /**
     * Get the allowed/expected cache keys for this class when @see self::get_cached_object() is called.
     * @return string[]
     */
    protected static function get_valid_cache_keys(): array {
        return ['submissionid'];
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
}
