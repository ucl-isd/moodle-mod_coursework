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
 * Allocation strategy for giving each teacher a different percentage of work to mark.
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2011 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use html_table;
use html_table_cell;
use html_table_row;
use html_writer;
use mod_coursework\allocation\allocatable;
use mod_coursework\models\user;
use stdClass;

defined('MOODLE_INTERNAL') || die();


/**
 * Allocates all students equally between teachers.
 */
class percentages extends base {

    /**
     * This is where the core logic of the allocation strategy lives. Given a list of teachers and a student, which teacher
     * is best suited to be the next assessor for this student.
     *
     * @param array $teachers
     * @param allocatable $student
     * @return user|bool false if no match
     */
    public function next_assessor_from_list($teachers, $student) {

        // Get a list of potential teacher ids counts, and target percentages. Copying to avoid messing with the original.
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
     *
     * @param string $strategypurpose moderator or assessor
     * @return string
     */
    public function add_form_elements($strategypurpose = 'assessor') {

        $html = '';

        // Get all teachers.
        $teachers = get_enrolled_users($this->coursework->get_context(), 'mod/coursework:addinitialgrade');

        if (empty($teachers)) {
            $html .= html_writer::start_tag('div');
            $html .= get_string('nomoderators', 'mod_coursework');
            $html .= html_writer::end_tag('div');
        } else {

            // Get existing settings.
            $settings = $this->get_existing_config_data($strategypurpose);

            // Loop to make inputs.
            $htmltable = new html_table();
            foreach ($teachers as $teacher) {

                $row = new html_table_row();

                $currentsetting = false;
                foreach ($settings as $setting) {
                    if ($setting->assessorid == $teacher->id) {
                        $currentsetting = $setting;
                        break;
                    }
                }

                $attributes = array(
                    'name' => 'assessorstrategy'.$this->get_name().'['.$teacher->id.']',
                    'size' => 3
                );
                if ($currentsetting) {
                    $attributes['value'] = $currentsetting->value;
                }
                $cell = new html_table_cell();
                $cell->text .= fullname($teacher);
                $row->cells[] = $cell;

                $cell = new html_table_cell();
                $cell->text .= html_writer::empty_tag('input', $attributes);
                $cell->text .= '%';
                $row->cells[] = $cell;

                $htmltable->data[] = $row;
            }

            $html .= html_writer::table($htmltable);
        }

        return $html;

    }

    /**
     * Saves the form data associated with the instance of this strategy.
     *
     * @return mixed
     */
    public function save_allocation_strategy_options() {

        global $DB;

        // Get the data from the form.
        $name = $this->get_name();
        $data = optional_param_array('assessorstrategy'.$name, array(), PARAM_RAW); // Array[teacherid] => value.

        if (!is_array($data)) {
            return true;
        }

        $existingrecords = $this->get_existing_config_data();

        foreach ($data as $teacherid => $value) {
            // Validate the data.
            if (!is_numeric($teacherid)) {
                continue;
            }

            if (!is_numeric($value)) {
                // Empty or duff - make sure we delete any existing record.
                $params = array(
                    'courseworkid' => $this->coursework->id,
                    'allocationstrategy' => $this->get_name(),
                    'assessorid' => $teacherid,
                    'purpose' => 'assessor'
                );
                $DB->delete_records('coursework_allocation_config', $params);
                continue;
            }

            // Make sure we have only whole numbers between 0 and 100.
            $value = round($value);
            $value = min($value, 100);
            $value = max($value, 0);

            // TODO use domain object.
            $tosave = new stdClass();
            $tosave->courseworkid = $this->coursework->id;
            $tosave->allocationstrategy = $this->get_name();
            $tosave->assessorid = $teacherid;
            $tosave->value = $value;
            $tosave->purpose = 'assessor';

            // Update if we can.
            reset($existingrecords);
            foreach ($existingrecords as $dbid => $existingrecord) {
                if ($existingrecord->assessorid == $teacherid) {
                    $tosave->id = $dbid;
                    $DB->update_record('coursework_allocation_config', $tosave);
                    continue 2;
                }
            }
            $DB->insert_record('coursework_allocation_config', $tosave);
        }

        return true;

    }

    /**
     * @param $teacher
     * @return int|mixed
     */
    private function percentage_for_teacher($teacher){

        global $DB;

        $params = array(
            'courseworkid' => $this->coursework->id,
            'allocationstrategy' => $this->get_name(),
            'purpose' => $this->get_type(),
            'assessorid' => $teacher->id,

        );
        $setting = $DB->get_field('coursework_allocation_config', 'value', $params);

        return $setting ? $setting : 0; // Default to 0 percent.

    }

    /**
     * @param $teacher
     * @return float
     */
    private function number_of_total_allocations_this_teacher_should_have($teacher) {
        $maxmarkers = $this->coursework->get_max_markers();
        $percentage = $this->percentage_for_teacher($teacher);
        $numberofstudents = $this->coursework->number_of_allocatables();

        return round($numberofstudents * $maxmarkers * ($percentage / 100));
    }

    /**
     * @param $teacher
     * @return bool
     */
    private function teacher_already_has_maximum_allocations($teacher) {
        $targetnumber = $this->number_of_total_allocations_this_teacher_should_have($teacher);
        $number_of_exisiting_allocations = $this->number_of_existing_allocations_teacher_has($teacher);

        return $number_of_exisiting_allocations >= $targetnumber;
    }

    /**
     * @param allocatable[] $teachers
     * @param allocatable $student
     * @return array
     */
    protected function list_of_allocatable_teachers_and_their_current_number_of_allocations($teachers, $student) {
        $teacherids = array();
        foreach ($teachers as $teacher) {

            if ($this->teacher_already_has_an_allocation_for_this_allocatable($student, $teacher)) {
                continue;
            }

            if ($this->teacher_already_has_maximum_allocations($teacher)) {
                continue;
            }

            $teacherids[$teacher->id] = $this->number_of_existing_allocations_teacher_has($teacher);
        }
        return $teacherids;
    }
}
