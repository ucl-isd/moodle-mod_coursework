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
 * @copyright  2017 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This class allows us to add functionality to the users, despite the fact that Moodle has no
 * core user class. Initially, it is using the active record approach, but this may need to change to
 * a decorator if Moodle implements such a class in future.
 */

namespace mod_coursework\models;

use mod_coursework\framework\table_base;
use mod_coursework\allocation\allocatable;
use mod_coursework\allocation\moderatable;

/**
 * @property int courseworkid
 * @property int studentid
 * @package mod_coursework\models
 *
 */
class assessment_set_membership extends table_base implements moderatable {

    /**
     * cache array
     *
     * @var
     */
    public static $pool;

    /**
     * @var string
     */
    protected static $table_name = 'coursework_sample_set_mbrs';

    /**
     *
     * @param $coursework_id
     * @return array
     */
    protected static function get_cache_array($coursework_id) {
        global $DB;
        $records = $DB->get_records(self::$table_name, ['courseworkid' => $coursework_id]);
        $result = [
            'allocatableid-allocatabletype' => [],
            'allocatableid-allocatabletype-stage_identifier' => [],
            'allocatableid-stage_identifier-selectiontype' => []
        ];
        if ($records) {
            foreach ($records as $record) {
                $object = new self($record);
                $result['allocatableid-allocatabletype'][$record->allocatableid . '-' . $record->allocatabletype][] = $object;
                $result['allocatableid-allocatabletype-stage_identifier'][$record->allocatableid . '-' . $record->allocatabletype . '-' . $record->stage_identifier][] = $object;
                $result['allocatableid-stage_identifier-selectiontype'][$record->allocatableid . '-' . $record->stage_identifier . '-' . $record->selectiontype][] = $object;
            }
        }
        return $result;
    }

    /**
     *
     * @param $coursework_id
     * @param $key
     * @param $params
     * @return bool
     */
    public static function get_object($coursework_id, $key, $params) {
        if (!isset(self::$pool[$coursework_id])) {
            self::fill_pool_coursework($coursework_id);
        }
        $value_key = implode('-', $params);
        return self::$pool[$coursework_id][$key][$value_key][0] ?? false;
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
