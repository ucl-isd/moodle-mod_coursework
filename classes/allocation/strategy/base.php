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

namespace mod_coursework\allocation\strategy;

use mod_coursework\allocation\allocatable;
use mod_coursework\models\allocation;
use mod_coursework\models\coursework;
use mod_coursework\models\user;
use mod_coursework\stages\base as stage_base;


defined('MOODLE_INTERNAL') || die();


/**
 * This base class is extended to make specific allocation strategies, which when run, will
 * allocated teachers to mark student work in specific ways.
 */
abstract class base {

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $courseworkid;

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @var array the columns in the DB
     */
    protected $fields = array(
        'id',
        'courseworkid',
    );

    /**
     * Holds the config settings to avoid repeated DB calls.
     *
     * @var array
     */
    private $settings = array();

    /**
     * @var stage_base
     */
    private $stage;

    /**
     * Makes a new instance of this allocator
     * @param coursework $coursework
     * @param stage_base $stage
     */
    public function __construct($coursework, $stage = null) {
        $this->coursework = $coursework;
        $this->stage = $stage;
    }

    /**
     * This is where the core logic of the allocation strategy lives. Given a list of teachers and a student, which teacher
     * is best suited to be the next assessor for this student.
     *
     * @param array $teachers
     * @param allocatable $student
     * @return int
     */
    public function next_assessor_from_list($teachers, $student) {
        $teacherids = $this->list_of_allocatable_teachers_and_their_current_number_of_allocations($teachers, $student);
        return $this->get_teacher_with_smallest_number_of_current_allocations($teacherids);
    }

    /**
     * Flag that saves us from doing all the allocations and then getting a flase response for all the
     * teacher ids.
     *
     * @abstract
     * @return bool
     */
    abstract public function autoallocation_enabled();

    /**
     * Some strategies need to be configured. This function will get the HTML for the form that will configure them.
     *
     * @param string $strategypurpose
     * @return string html
     */
    abstract public function add_form_elements($strategypurpose = 'assessor');

    /**
     * Return the name of this strategy based on the class name with the prefix removed.
     *
     * @return string
     */
    protected function get_name() {
        $name = get_class($this);
        $bits = explode('\\', $name);
        $name = end($bits);
        return $name;
    }

    /**
     * Saves the form data associated with the instance of this strategy.
     *
     * @abstract
     * @return mixed
     */
    abstract public function save_allocation_strategy_options();

    /**
     * Fetches any data from the DB associated with this coursework and class.
     *
     * @param $type
     * @param bool $reset we cache this stuff, so reset = true will wipe the cache
     * @return \stdClass[]
     */
    protected final function get_existing_config_data($type = 'assessor', $reset = false) {

        global $DB;

        if (!isset($this->settings[$type]) || $reset) {
            $params = array(
                'courseworkid' => $this->coursework->id,
                'allocationstrategy' => $this->get_name(),
                'purpose' => $type

            );
            $this->settings[$type] = $DB->get_records('coursework_allocation_config', $params);
        }

        return $this->settings[$type];
    }

    /**
     * @return string
     */
    protected function get_type() {
        $exploded_class_name = explode('\\', get_class($this->stage));
        return array_pop($exploded_class_name);
    }

    /**
     * @param allocatable $student
     * @param $teacher
     * @return bool
     */
    protected function teacher_already_has_an_allocation_for_this_allocatable($student, $teacher) {
        $params = array(
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $student->id(),
            'allocatabletype' => $student->type(),
            'assessorid' => $teacher->id,
        );
        return allocation::exists($params);
    }

    /**
     * @param $teacher
     * @return int
     */
    protected function number_of_existing_allocations_teacher_has($teacher) {
        $params = array(
            'courseworkid' => $this->coursework->id,
            'assessorid' => $teacher->id,
        );
        return allocation::count($params);
    }

    /**
     * @param array $teacher_counts teacherid => number_of_allocations_so_far
     * @return user|bool
     */
    protected function get_teacher_with_smallest_number_of_current_allocations($teacher_counts) {
        // What if there aren't any e.g. only one teacher, but two are needed?
        if (empty($teacher_counts)) {
            return false;
        }

        // Which is the best one? Whichever has the fewest. Might be several with the same number, so we
        // get the allocations count value that's lowest (may represent multiple teachers), then get the first array
        // key (teacher id) that has that number of allocations.
        $smallestcount = min($teacher_counts);
        return user::find(array_search($smallestcount, $teacher_counts));
    }

    /**
     * @param $teachers
     * @param $student
     * @return mixed
     */
    abstract protected function list_of_allocatable_teachers_and_their_current_number_of_allocations($teachers, $student);
}
