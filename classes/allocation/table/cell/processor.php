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
     * @param data $celldata
     */
    public function process($celldata) {
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
     * returns whether the current record was automatically included in the sample set at the current stage
     *
     * @return bool
     * @throws \dml_exception
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
