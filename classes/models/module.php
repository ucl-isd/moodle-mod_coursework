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
use mod_coursework\framework\table_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Represents a row in the modules table.
 */
#[AllowDynamicProperties]
class module extends table_base {

    /**
     * @var string
     */
    protected static $tablename = 'modules';

    /**
     * @var int
     */
    public $id;

    /**
     * cache array
     *
     * @var
     */
    public static $pool;

    /**
     * Fill pool to cache for later use
     *
     * @param $array
     */
    public static function fill_pool($array) {
        self::$pool = [
            'id' => [],
            'name' => [],
        ];
        foreach ($array as $record) {
            $object = new self($record);
            self::$pool['id'][$record->id] = $object;
            self::$pool['name'][$record->name] = $object;
        }
    }
}
