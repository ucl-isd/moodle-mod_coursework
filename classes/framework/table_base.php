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
use core\exception\coding_exception;
use core\exception\invalid_parameter_exception;
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
     * @var string|null cache area for objects by ID.
     * Child classes are expected to override this if they implement caching.
     */
    const CACHE_AREA_IDS = null;

    //todo add cache_area_by_allocatables

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
     * The database record ID.
     * Protected and readonly because we only want this to be set from within this class from DB record.
     * @var int
     */
    protected readonly int $id;

    /**
     * Makes a new instance. Can be overridden to provide a factory
     *
     * @param stdClass|int|array $dbrecord
     * @param bool $reload
     * @return bool|self
     * @throws dml_exception|invalid_parameter_exception
     */
    public static function find($dbrecord, $reload = true): self|bool {

        global $DB;

        if (empty($dbrecord)) {
            return false;
        }

        if (is_numeric($dbrecord) && $dbrecord > 0) {
            debugging("Deprecated use get_from_id() instead", DEBUG_DEVELOPER);
            return self::get_from_id($dbrecord);
        }

        $recordid = self::get_id_from_record($dbrecord);
        // Cast to array in case it's stdClass.
        $dbrecord = (array)$dbrecord;
        if (!isset($recordid)) {
            // Supplied data without record ID - treat as query params and try to get full record.
            // Filter to valid DB table columns.
            $allowedkeys = array_keys($DB->get_columns(static::$tablename));
            $filteredparams = [];
            foreach (array_keys($dbrecord) as $key) {
                if (in_array($key, $allowedkeys)) {
                    $filteredparams[$key] = $dbrecord[$key];
                }
            }
            if (empty($filteredparams)) {
                throw new coding_exception("No valid fields from table " . static::$tablename . " in params " . json_encode($dbrecord));
            }

            $dbrecords = $DB->get_records(static::get_table_name(), $filteredparams, '', '*', 0, 2);
            if (count($dbrecords) > 1) {
                throw new coding_exception("Found multiple records with supplied properties " . json_encode($filteredparams));
            }
            if (empty($dbrecords)) {
                return false;
            }

            $dbrecord = array_pop($dbrecords);
        } else if ($reload) {
            $dbrecord = $DB->get_record(static::get_table_name(), ['id' => $recordid]);

            if (!$dbrecord) {
                return false;
            }
        }
        $klass = get_called_class();
        return new $klass($dbrecord);
    }

    /**
     * Get the record ID from supplied data (which may be of various types).
     * @param stdClass|array|table_base $record
     * @return int|null
     */
    public static function get_id_from_record($record): ?int {
        if (!$record) {
            throw new coding_exception("No record");
        }
        if (is_object($record) && isset($record->id)) {
            return (int)$record->id;
        }
        if (is_array($record) && isset($record['id'])) {
            return (int)$record['id'];
        }
        if (is_object($record) && method_exists($record, 'id')) {
            return $record->id();
        }
        return null;
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
     * @throws dml_exception
     */
    public function __construct($dbrecord = false) {

        if ($dbrecord) {
            // If we do not have a DB record here, we must be building a new object (does not yet exist in DB).
            // That is allowed, but obviously we do not yet have any data in the DB for this object.
            // Allow option to supply an id e.g. if this is not being generated as part of a massive list of objects.
            if (is_numeric($dbrecord)) {
                $this->id = (int)$dbrecord;
                $this->reload();
            } else if ($id = self::get_id_from_record($dbrecord)) {
                // We have an object or array and have been provided with the ID.
                // Add all of the DB row fields to this object (if the object has a matching property).
                $this->apply_data($dbrecord);
                $this->id = $id;
                $this->dataloaded = true;
            }
        }
    }

    /**
     * @param $params
     * @return array
     * @throws dml_exception
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
     * @throws coding_exception
     * @throws dml_exception
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
                if ($columnname == 'id') {
                    // This prop is read only - set once and never from here.
                    continue;
                }
                $this->{$columnname} = $data[$columnname];
            }
        }
    }

    /**
     * Saves the record or creates a new one if needed. Allow subclasses to add bits if needed, before calling.
     *
     * @param bool $sneakily If true, do not update the timemodified stamp. Useful for cron.
     * @return void
     * @throws dml_exception
     */
    final public function save($sneakily = false) {
        global $DB;
        if (static::get_table_name() !== 'coursework' && !str_starts_with(static::get_table_name(), 'coursework_')) {
            // Some child classes e.g. user, group, modules do not use coursework_ tables but core tables.
            // Prevent accidental data modification.
            throw new coding_exception(
                "Cannot modify non-coursework data, in table '" . static::get_table_name() . "', from this class"
            );
        }

        $this->pre_save_hook();

        $savedata = $this->build_data_object_to_save($sneakily);

        // Update if there's an id, otherwise make a new one. Check first for an id?
        if ($this->persisted()) {
            $DB->update_record(static::get_table_name(), $savedata);
            static::clear_cache($this->id);
        } else {
            $this->id = $DB->insert_record(static::get_table_name(), $savedata);
        }

        // Possibly we just saved only some fields and some were created as defaults. Update with the missing ones.
        $this->reload();

        $this->post_save_hook();
    }

    /**
     * Returns the table in the DB that this data object will be written to.
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
        static::clear_cache($this->id);
    }

    /**
     * Tells us whether this record has been saved to the DB yet.
     *
     * @return bool
     * @throws dml_exception
     */
    public function persisted(): bool {
        // Previously was a check against DB here but this results in thousands of queries from grading report page via ability->can().
        // ID should only be set from a DB record anyway so that check is now removed.
        return !empty($this->id);
    }

    /**
     * Returns the most recently created record
     *
     * @return table_base
     * @throws dml_exception
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
     * @throws dml_exception
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
     * @throws dml_exception
     */
    public function reload($complainifnotfound = true) {
        global $DB;

        if (!$this->persisted()) {
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
     * @throws coding_exception
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
     * @throws coding_exception
     */
    public function update_attributes($values) {
        foreach ($values as $col => $val) {
            if ($col == 'id') {
                continue;
            }
            $this->apply_column_value_to_self($col, $val, false);
        }
        $this->save();
    }

    /**
     * Wipes out the record from the database.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function destroy() {
        global $DB;

        if (!$this->persisted()) {
            throw new coding_exception('Cannot destroy an object that has not yet been saved');
        }

        $this->before_destroy();

        $DB->delete_records(static::get_table_name(), ['id' => $this->id]);
        $this->after_destroy();
    }

    /**
     * Hook method to allow subclasses to get stuff done like destruction of dependent records.
     */
    protected function before_destroy() {
    }

    /**
     * Hook method to allow subclasses to get stuff done like destruction of dependent records.
     */
    protected function after_destroy() {
        static::clear_cache($this->id);
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
            if ($columnname == 'id' && !$this->persisted()) {
                continue;
            }
            if (isset($this->$columnname) && !is_null($this->$columnname)) {
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
     * @param array $conditions key value pairs of DB columns
     * @return bool
     * @throws coding_exception
     * @throws dml_exception
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
     * @throws coding_exception
     * @throws dml_exception
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
     * @throws dml_exception
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
     * @throws dml_exception
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
     * @return int
     * @throws coding_exception
     */
    public function id(): int {
        if (!$this->persisted()) {
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
     * @throws \core\exception\coding_exception
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
     * @throws \core\exception\coding_exception
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

    /**
     * Check that any cache key being requested is valid (i.e. exists as valid cache key in child class).
     * Otherwise, @see self::get_cached_object() will return null without complaining and no-one will notice.
     * @param string $cachekey
     * @return void
     * @throws coding_exception
     */
    protected static function validate_cache_key(string $cachekey): void {
        $validkeys = static::get_valid_cache_keys();
        if (!in_array($cachekey, $validkeys)) {
            throw new coding_exception(
                "Requested cache key '$cachekey' invalid."
                . " Must must be one of: " . implode(' | ', $validkeys)
                . " (" . self::class . "::get_cached_object())"
            );
        }
    }

    /**
     * Get the allowed/expected cache keys for this class when @see self::get_cached_object() is called.
     *
     * @return string[]
     */
    protected static function get_valid_cache_keys(): array {
        throw new coding_exception(
            "For validation, please implement get_valid_cache_keys() in child class '" . get_called_class()
            . "' where get_cached_object() is used"
        );
    }

    /**
     * Get cached object for params provided.
     * $params must use keys from child class get_valid_cache_keys()
     * @param int $courseworkid
     * @param array $params to search cache for
     * @return static|null
     * @throws \core\exception\coding_exception
     */
    public static function get_cached_object(int $courseworkid, array $params): ?static {
        if (!isset(static::$pool[$courseworkid])) {
            static::fill_pool_coursework($courseworkid);
        }
        $cachekeyone = implode('-', array_keys($params));
        static::validate_cache_key($cachekeyone);
        $cachekeytwo = implode('-', array_values($params));
        return static::$pool[$courseworkid][$cachekeyone][$cachekeytwo][0] ?? null;
    }

    /**
     * Get child object from its ID, using cache if possible.
     * @param int $id
     * @return static|null object of the child class or null.
     */
    public static function get_from_id(int $id, int $strictness = IGNORE_MISSING): ?static {
        if ($id <= 0) {
            throw new invalid_parameter_exception("Invalid ID $id");
        }
        if (static::CACHE_AREA_IDS) {
            // Child class has a cache for these.
            $cache = cache::make('mod_coursework', static::CACHE_AREA_IDS);
            $cachedrecord = $cache->get($id);
            if ($cachedrecord === false) {
                $cachedrecord = self::get_db_record_from_id($id, $strictness);
                $cache->set($id, $cachedrecord);
            }
            return new static($cachedrecord);
        } else {
            // Child class does not have a cache for these.  Get from DB.
            return new static(self::get_db_record_from_id($id, $strictness));
        }
    }

    /**
     * Get child object from its ID, from the database.
     * @param int $id
     * @return \stdClass|null
     */
    private static function get_db_record_from_id(int $id, int $strictness): ?object {
        global $DB;
        return $DB->get_record(static::$tablename, ['id' => $id], '*', $strictness) ?: null;
    }

    /**
     * Clear the cache for this object.
     * May need to be overridden in child class, if child class has additional cache areas beyond CACHE_AREA_IDS.
     * @return void
     * @throws coding_exception
     */
    public static function clear_cache(int $id) {
        if (static::CACHE_AREA_IDS) {
            $cache = cache::make('mod_coursework', static::CACHE_AREA_IDS);
            $cache->delete($id);
        }
    }
}
