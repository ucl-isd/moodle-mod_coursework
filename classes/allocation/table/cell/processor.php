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

namespace mod_coursework\allocation\table\cell;
use coding_exception;
use mod_coursework\allocation\allocatable;
use mod_coursework\models\allocation;
use mod_coursework\models\coursework;
use mod_coursework\stages\base as stage_base;

/**
 * This class and it's descendants are responsible for processing the data from the allocation form.
 * They know about the logic of what to do based on the data that the cell provides. The actions are carried
 * out by the stage class.
 *
 * @package mod_coursework\allocation\table\cell
 */
class processor {
    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @var allocatable
     */
    protected $allocatable;

    /**
     * @var stage_base
     */
    protected $stage;

    /**
     * @param coursework $coursework
     * @param allocatable $allocatable
     * @param stage_base $stage
     * @param array $dataarray incoming data from the allocation form
     */
    public function __construct($coursework, $allocatable, $stage, $dataarray = []) {
        $this->coursework = $coursework;
        $this->allocatable = $allocatable;
        $this->stage = $stage;
    }

    /**
     * @param data $celldata
     */
    public function process($celldata) {

        if ($this->get_stage()->uses_sampling()) {
            $this->process_sampling_membership($celldata);
        } else if ($this->get_stage()->has_allocation($this->get_allocatable())) {
            $this->process_pin($celldata);
        }

        if ($celldata->has_assessor() && $this->get_stage()->allocatable_is_in_sample($this->get_allocatable())) {
            $this->save_assessor_allocation($celldata);
        }
    }

    /**
     * @param data $data
     */
    private function save_assessor_allocation($data) {

        // Do not save if this assessor is already allocated to another stage.
        if ($this->get_stage()->assessor_already_allocated_for_this_submission($this->get_allocatable(), $data->get_assessor())) {
            return;
        }

        $allocation = $this->get_allocation();

        if ($allocation) {
            $allocation->set_assessor($data->get_assessor());
            $allocation->pin();
        } else {
            $this->make_allocation($data->get_assessor(), $this->get_allocatable());
        }
    }

    /**
     * @param $teacher
     * @param allocatable $student
     * @return mixed|void
     */
    private function make_allocation($teacher, $student) {
        return $this->get_stage()->make_manual_allocation($student, $teacher);
    }

    /**
     * @return allocation|bool
     */
    private function get_allocation() {
        return $this->get_stage()->get_allocation($this->get_allocatable());
    }

    /**
     * @param data $data
     */
    private function process_sampling_membership($data) {
        if ($data->allocatable_should_be_in_sampling()) {
            if ($this->get_stage()->allocatable_is_not_in_sampling($this->get_allocatable())) {
                $this->get_stage()->add_allocatable_to_sampling($this->get_allocatable());
            }
            if ($this->get_stage()->has_allocation($this->get_allocatable())) {
                $this->process_pin($data);
            }
        } else {
            if ($this->get_stage()->allocatable_is_in_sample($this->get_allocatable()) && !$this->has_automatic_sampling()) {
                $this->get_stage()->remove_allocatable_from_sampling($this->get_allocatable());
            }

            if ($this->get_stage()->has_allocation($this->get_allocatable())) {
                $this->get_stage()->destroy_allocation($this->get_allocatable());
            }
        }
    }

    /**
     * @return allocatable
     */
    private function get_allocatable() {
        return $this->allocatable;
    }

    /**
     * @return stage_base
     */
    private function get_stage() {
        return $this->stage;
    }

    /**
     * @param data $celldata
     */
    private function process_pin($celldata) {
        $allocation = $this->get_allocation();

        if ($celldata->is_pinned()) {
            $allocation->pin();
        } else {
            $allocation->unpin();
        }
    }

    /**
     * returns whether the current record was automatically included in the sample set at the current stage
     *
     * @return bool
     * @throws coding_exception
     */
    private function has_automatic_sampling() {

        global $DB;

        $params = ['courseworkid' => $this->coursework->id(),
            'allocatableid' => $this->get_allocatable()->id(),
            'stageidentifier' => $this->get_stage()->identifier(),
            'selectiontype' => 'automatic'];

        return $DB->record_exists('coursework_sample_set_mbrs', $params);
    }
}
