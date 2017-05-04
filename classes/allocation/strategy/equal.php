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

/**
 * Allocation strategy for giving all teachers equal numbers of students to mark
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2011 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\allocation\allocatable;

defined('MOODLE_INTERNAL') || die();


/**
 * Allocates all students equally between teachers.
 */
class equal extends base {

    /**
     * This is where the core logic of the allocation strategy lives. Given a list of teachers and a student, which teacher
     * is best suited to be the next assessor for this student.
     *
     * @param array $teachers
     * @param allocatable $student
     * @return int
     */
    public function next_assessor_from_list($teachers, $student) {

        // Get a list of potential teacher ids and counts. Copying to avoid messing with the original.

        $teacherids = $this->list_of_allocatable_teachers_and_their_current_number_of_allocations($teachers, $student);

        return $this->get_teacher_with_smallest_number_of_current_allocations($teacherids);

    }

    /**
     * Flag that saves us from doing all the allocations and then getting a false response for all the
     * teacher ids.
     *
     * @return bool
     */
    public function autoallocation_enabled() {
        return true;
    }

    /**
     * Some strategies need to be configured. This function will get the HTML for the form that will configure them.
     * @param string $strategypurpose
     * @return string
     */
    public function add_form_elements($strategypurpose = 'assessor') {
        return '';
    }

    /**
     * Saves the form data associated with the instance of this strategy.
     *
     * @return mixed
     */
    public function save_allocation_strategy_options() {
        // Nothing to here as there are no form elements.
        return true;
    }

    /**
     * @param $teachers
     * @param $student
     * @return mixed
     */
    protected function list_of_allocatable_teachers_and_their_current_number_of_allocations($teachers, $student) {
        $teacherids = array();

        foreach ($teachers as $id => $teacher) {
            if ($this->teacher_already_has_an_allocation_for_this_allocatable($student, $teacher)) {
                continue;
            }

            $teacherids[$teacher->id] = $this->number_of_existing_allocations_teacher_has($teacher);
        }
        return $teacherids;
    }
}
