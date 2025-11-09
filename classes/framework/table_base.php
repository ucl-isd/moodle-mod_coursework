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

namespace mod_coursework\framework;

use AllowDynamicProperties;
use cache;
use coding_exception;
use dml_exception;
use dml_missing_record_exception;
use dml_multiple_records_exception;
use stdClass;

/**
 * This forms the base class for other classes that represent database table objects using the Active Record pattern.
 *
 * @property mixed fields
 */
#[AllowDynamicProperties] // Allow dynamic properties for table_base to avoid interferences elsewhere.
abstract class table_base {
    /**
     * @var string
     */
    protected static $tablename;

    /**
     * Cache for the column names.
     *
     * @var array tablename => array(of column names)
     */
    protected static $columnnames;

    /**
     * Tells us whether the data has been loaded at least once since instantiation.
     *
     * @var bool
     */
    private $dataloaded = false;

    /**
     * @var int
     */
    public $id;

    /**
     * Makes a new instance. Can be overridden to provide a factory
     *
     * @param stdClass|int|array $dbrecord
     * @param bool $reload
     * @return bool|self
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function find($dbrecord, $reload = true) {

        global $DB;

        if (empty($dbrecord)) {
            return false;
        }

        $klass = get_called_class();

        if (is_numeric($dbrecord) && $dbrecord > 0) {
            $data = $DB->get_record(self::get_table_name(), ['id' => $dbrecord]);
            if (!$data) {
                return false;
            }
            return new $klass($data);
        }

        $dbrecord = (array)$dbrecord;

        // Supplied a partial DB stdClass record
        if (!array_key_exists('id', $dbrecord)) {
            $dbrecord = $DB->get_record(static::get_table_name(), $dbrecord);
            if (!$dbrecord) {
                return false;
            }
            return new $klass($dbrecord);
        }

        if ($dbrecord) {
            $record = new $klass($dbrecord);
            if ($reload) {
                $record->reload();
            }
            return $record;
        }

        return false;
    }

    /**
     * @param array $params
     * @return array
     * @throws coding_exception
     */
    public static function find_all($params = []) {

        if (!is_array($params)) {
            throw new coding_exception('::all() require an array of parameters');
        }

        self::remove_non_existant_columns($params);

        return self::instantiate_objects($params);
    }

    /**
     * Makes a new object ready to save
     *
     * @param stdClass|array $data
     * @return table_base
     */
    public static function build($data) {
        $klass = get_called_class();
        /**
         * @var table_base $item
         */
        $item = new $klass();
        $item->apply_data($data);

        return $item;
    }

    /**
     * Makes a new instance and saves it.
     *
     * @param stdClass|array $params
     * @return table_base
     */
    public static function create($params) {
        $item = static::build($params);
        $item->save();
        return $item;
    }

    /**
     * Takes the supplied DB record (one row of the table) and applies it to this object. If it's a
     * number, change it to a DB row and then do it.
     *
     * @param object|bool $dbrecord
     */
    public function __construct($dbrecord = false) {

        // Allow the option to supply an id if this is not being generated as part of a massive list
        // of courseworks. If the id isn't there, throw an error. Weirdly, everything comes through
        // as a string here.
        if (!empty($dbrecord) && is_numeric($dbrecord)) {
            $this->id = $dbrecord;
            $this->reload();
        } else if (is_object($dbrecord) || is_array($dbrecord)) {
            // Add all of the DB row fields to this object (if the object has a matching property).
            $this->apply_data($dbrecord);
            $this->dataloaded = true;
        }
    }

    /**
     * @param $params
     * @return array
     */
    protected static function instantiate_objects($params) {
        global $DB;

        $rawrecords = $DB->get_records(static::get_table_name(), $params);
        $objects = [];
        $klass = get_called_class();
        foreach ($rawrecords as $rawrecord) {
            $objects[$rawrecord->id] = new $klass($rawrecord);
        }
        return $objects;
    }

    /**
     * @param $params
     */
    protected static function remove_non_existant_columns($params) {
        foreach ($params as $columnname => $value) {
            if (!static::column_exists($columnname)) {
                unset($params[$columnname]);
            }
        }
    }

    /**
     * This is a convenience method so we can do things like new and edit calls to ability
     * without having to juggle build and find elsewhere.
     *
     * @param array $params
     * @return object
     */
    public static function find_or_build($params) {
        $object = self::find($params);
        if ($object) {
            return $object;
        } else {
            return self::build($params);
        }
    }

    /**
     * @param $colname
     * @throws coding_exception
     */
    private static function ensure_column_exists($colname) {
        if (!static::column_exists($colname)) {
            throw new coding_exception('Column ' . $colname . ' does not exist in class ' . static::get_table_name());
        }
    }

    /**
     * Magic method to get data from the DB table columns dynamically.
     *
     * @param string $requestedpropertyname
     * @throws coding_exception
     * @return mixed
     */
    public function __get($requestedpropertyname) {
        static::ensure_column_exists($requestedpropertyname);

        if (!$this->dataloaded) {
            $this->reload(); // Will not set the variable if we have not saved the object yet
        }

        return empty($this->$requestedpropertyname) ? null : $this->$requestedpropertyname;
    }

    /**
     * Takes an object representing a DB row and applies it to this instance
     *
     * @param array|stdClass $dataobject
     * @return void
     */
    protected function apply_data($dataobject) {
        $data = (array)$dataobject;
        foreach (static::get_column_names() as $columnname) {
            if (isset($data[$columnname])) {
                $this->{$columnname} = $data[$columnname];
            }
        }
    }

    /**
     * Saves the record or creates a new one if needed. Allow subclasses to add bits if needed, before calling.
     *
     * @param bool $sneakily If true, do not update the timemodified stamp. Useful for cron.
     * @return void
     */
    final public function save($sneakily = false) {

        global $DB;

        $this->pre_save_hook();

        $savedata = $this->build_data_object_to_save($sneakily);

        // Update if there's an id, otherwise make a new one. Check first for an id?
        if ($this->persisted()) {
            $DB->update_record(static::get_table_name(), $savedata);
        } else {
            $this->id = $DB->insert_record(static::get_table_name(), $savedata);
        }

        // Possibly we just saved only some fields and some were created as defaults. Update with the missing ones.
        $this->reload();

        $this->post_save_hook();
    }

    /**
     * Returns the table in the DB that this data object will be written to.
     * @throws coding_exception
     * @return string
     */
    final public static function get_table_name() {

        if (empty(static::$tablename)) {
            $classname = get_called_class();
            $pieces = explode('\\', $classname);
            $tablename = end($pieces);
            $tablename .= 's';
        } else {
            $tablename = static::$tablename;
        }

        return $tablename;
    }

    /**
     * Allows subclasses to alter data before it hits the DB.
     */
    protected function pre_save_hook() {
    }

    /**
     * Allows subclasses to do other stuff after after the DB save.
     */
    protected function post_save_hook() {
    }

    /**
     * Tells us whether this record has been saved to the DB yet.
     *
     * @return bool
     */
    public function persisted() {
        global $DB;

        return !empty($this->id) && $DB->record_exists(static::$tablename, ['id' => $this->id]);
    }

    /**
     * Returns the most recently created record
     *
     * @return mixed
     */
    final public static function last() {
        global $DB;

        $tablename = static::get_table_name();

        $sql = "SELECT *
                  FROM {{$tablename}}
              ORDER BY id DESC
                 LIMIT 1";
        return new static($DB->get_record_sql($sql));
    }

    /**
     * Returns the most recently created record
     *
     * @return mixed
     */
    final public static function first() {
        global $DB;

        $tablename = static::get_table_name();

        $sql = "SELECT *
                  FROM {{$tablename}}
              ORDER BY id ASC
                 LIMIT 1";
        return $DB->get_record_sql($sql);
    }

    /**
     * Reads the columns from the database
     */
    protected static function get_column_names() {
        global $DB;

        $tablename = static::get_table_name();

        if (isset(static::$columnnames[$tablename])) {
            return static::$columnnames[$tablename];
        }

        $columns = $DB->get_columns($tablename);

        static::$columnnames[$tablename] = array_keys($columns);

        return static::$columnnames[$tablename];
    }

    /**
     * Tells us if the column is present in the Moodle database table.
     *
     * @param string $requestedpropertyname
     * @return bool
     */
    private static function column_exists($requestedpropertyname) {
        return in_array($requestedpropertyname, static::get_column_names());
    }

    /**
     * Reloads the data from the DB columns.
     * @param bool $complainifnotfound
     * @return $this
     * @throws coding_exception
     */
    public function reload($complainifnotfound = true) {
        global $DB;

        if (empty($this->id)) {
            return $this;
        }

        $strictness = $complainifnotfound ? MUST_EXIST : IGNORE_MISSING;
        $dbrecord = $DB->get_record(static::get_table_name(), ['id' => $this->id], '*', $strictness);

        if ($dbrecord) {
            $this->apply_data($dbrecord);
            $this->dataloaded = true;
        }

        return $this;
    }

    /**
     * Updates a single attribute and saves the model.
     *
     * @param string $name
     * @param mixed $value
     * @param bool $sneakily If true, do not update the timemodified stamp. Useful for cron.
     * @return void
     */
    public function update_attribute($name, $value, $sneakily = false): void {
        $this->apply_column_value_to_self($name, $value);
        $this->save($sneakily);
    }

    /**
     * Updates a single attribute and saves the model.
     *
     * @param mixed $values key-value pairs
     * @return void
     */
    public function update_attributes($values) {
        foreach ($values as $col => $val) {
            $this->apply_column_value_to_self($col, $val, false);
        }
        $this->save();
    }

    /**
     * Wipes out the record from the database.
     *
     * @throws coding_exception
     */
    public function destroy() {
        global $DB;

        if (empty($this->id)) {
            throw new coding_exception('Cannot destroy an object that has not yet been saved');
        }

        $this->before_destroy();

        $DB->delete_records(static::get_table_name(), ['id' => $this->id]);
    }

    /**
     * Hook method to allow subclasses to get stuff done like destruction of dependent records.
     */
    protected function before_destroy() {
    }

    /**
     * @param $col
     * @param $val
     * @param bool $witherrorsformissingcolumns
     * @throws coding_exception
     */
    private function apply_column_value_to_self($col, $val, $witherrorsformissingcolumns = true) {
        if ($witherrorsformissingcolumns) {
            static::ensure_column_exists($col);
        }
        if ($this->column_exists($col)) {
            $this->$col = $val;
        }
    }

    /**
     * @param bool $sneakily If true, do not update the timemodified stamp. Useful for cron.
     * @return stdClass
     */
    final public function build_data_object_to_save($sneakily = false): stdClass {
        // Don't just use $this as it will try to save any missing value as null. We may only want.
        // to update  some fields e.g. leaving timecreated alone.
        $savedata = new stdClass();

        // Only save the non-null fields.
        foreach (static::get_column_names() as $columnname) {
            if (!is_null($this->$columnname)) {
                $savedata->$columnname = $this->$columnname;
            }
        }

        if (static::column_exists('timecreated') && !$this->persisted()) {
            $savedata->timecreated = time();
        }

        if (!$sneakily && static::column_exists('timemodified')) {
            $savedata->timemodified = time();
        }

        return $savedata;
    }

    /**
     * @param array|table_base $conditions key value pairs of DB columns
     * @return bool
     */
    public static function exists($conditions = []) {
        global $DB;

        if (is_number($conditions)) {
            $conditions = ['id' => $conditions];
        }
        if (is_object($conditions) && method_exists($conditions, 'to_array')) {
            $conditions = $conditions->to_array();
        }

        foreach ($conditions as $colname => $value) {
            static::ensure_column_exists($colname);
        }
        return $DB->record_exists(static::get_table_name(), $conditions);
    }

    /**
     * @param array $conditions
     * @return int
     */
    public static function count($conditions = []) {
        global $DB;

        foreach ($conditions as $colname => $value) {
            static::ensure_column_exists($colname);
        }
        return $DB->count_records(static::get_table_name(), $conditions);
    }

    /**
     * @return stdClass|bool
     * @throws coding_exception
     */
    public function get_raw_record() {
        global $DB;
        return $DB->get_record(static::get_table_name(), ['id' => $this->id]);
    }

    /**
     * @param string $field
     * @param mixed $value
     */
    protected function apply_value_if_column_exists($field, $value) {
        if (static::column_exists($field)) {
            $this->{$field} = $value;
        }
    }

    /**
     * @param string $sql The bit after WHERE
     * @param array $params
     * @return array
     * @throws coding_exception
     * @throws dml_missing_record_exception
     * @throws dml_multiple_records_exception
     */
    public static function find_by_sql($sql, $params) {
        global $DB;

        $sql = 'SELECT * FROM {' . static::get_table_name() . '} WHERE ' . $sql;
        $records = $DB->get_record_sql($sql, $params);
        $klass = get_called_class();
        foreach ($records as &$record) {
            $record = new $klass($record);
        }
        return $records;
    }

    /**
     * @return string
     * @throws coding_exception
     */
    public function __toString() {
        $string = $this->get_table_name() . ' ' . $this->id . ' ';
        foreach ($this->get_column_names() as $column) {
            $string .= $column . ' ' . $this->$column . ' ';
        }
        return $string;
    }

    /**
     * @return array
     */
    public function to_array() {
        $data = [];

        // Only save the non-null fields.
        foreach (static::get_column_names() as $columnname) {
            if (!is_null($this->$columnname)) {
                $data[$columnname] = $this->$columnname;
            }
        }
        return $data;
    }

    /**
     * @return int|string
     * @throws coding_exception
     */
    public function id() {
        if (empty($this->id)) {
            throw new coding_exception('Asking for the id of an unsaved object');
        }
        return $this->id;
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
     * @throws dml_exception
     */
    public static function fill_pool_coursework($courseworkid) {
        if (isset(static::$pool[$courseworkid])) {
            return;
        }
        $key = static::$tablename;
        $cache = cache::make('mod_coursework', 'courseworkdata', ['id' => $courseworkid]);

        $data = $cache->get($key);
        if ($data === false) {
            // no cache found
            $data = static::get_cache_array($courseworkid);
            $cache->set($key, $data);
        }

        static::$pool[$courseworkid] = $data;
    }

    /**
     * @param int $courseworkid
     */
    public static function remove_cache($courseworkid) {
        global $SESSION;
        if (!empty($SESSION->keep_cache_data)) {
            return;
        }
        static::$pool[$courseworkid] = null;
        $cache = cache::make('mod_coursework', 'courseworkdata', ['id' => $courseworkid]);
        $cache->delete(static::$tablename);
    }
}
