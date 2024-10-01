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

/**
 * This class is here to take the data from the table on the allocation page and process each
 * of the rows. It's job is to know which other classes need to be called to process each rom
 */

namespace mod_coursework\allocation\table;
use mod_coursework\allocation\allocatable;
use mod_coursework\models\coursework;
use mod_coursework\models\group;
use mod_coursework\models\user;

/**
 * Class form_table_processor
 * @package mod_coursework\allocation
 */
class processor {

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @param coursework $coursework
     */
    public function __construct($coursework) {
        $this->coursework = $coursework;
    }

    /**
     * @param array $tabledata
     */
    public function process_data($tabledata  = []) {
        $cleandata = $this->clean_data($tabledata);
        $allocatables = $this->coursework->get_allocatables();

        foreach ($allocatables as $allocatable) {
            if (array_key_exists($allocatable->id(), $cleandata)) {
                $rowdata = $cleandata[$allocatable->id()];
            } else {
                $rowdata = [];
            }

            $allocatable = $this->get_allocatable_from_id($allocatable->id());
            $rowobject = $this->get_row($allocatable);
            $rowobject->process($rowdata);
        }
    }

    /**
     * @param allocatable $allocatable
     * @return row\processor
     */
    private function get_row($allocatable) {
        return new row\processor($this->coursework, $allocatable);
    }

    /**
     * Sanitises the data, mostly making sure that we have ony valid student ids and valid stage identifiers.
     * The stages will deal with sanitising the data for each cell.
     *
     * @param array $rawdata
     * @return array
     */
    private function clean_data($rawdata) {

        // Data looks like this:
        // $example_data = array(
        // 4543 => array( // Student id
        // 'assessor_1' => array(
        // 'allocation_id' => 43,
        // 'assessor_id' => 232,
        // ),
        // 'moderator_1' => array(
        // 'allocation_id' => 46,
        // 'assessor_id' => 235,
        // 'in_set' => 1,
        // )
        // )
        // );

        $cleandata = [];
        foreach ($rawdata as $allocatableid => $datarrays) {

            if (!$this->allocatable_id_is_valid($allocatableid)) { // Should be the id of a student.
                continue;
            }

            $cleandata[$allocatableid] = [];

            foreach ($this->coursework->marking_stages() as $stage) {

                if (array_key_exists($stage->identifier(), $datarrays)) {
                    $stagedata = $datarrays[$stage->identifier()];
                    $cleandata[$allocatableid][$stage->identifier()] = $stagedata;
                }
            }
            /* if (array_key_exists('moderator', $datarrays)) {
                $moderator_data = $datarrays['moderator'];
                $clean_data[$allocatable_id]['moderator'] = $moderator_data;
            }*/
        }
        return $cleandata;
    }

    /**
     * @param int $studentid
     * @return bool
     */
    private function allocatable_id_is_valid($studentid) {
        $allocatable = $this->get_allocatable_from_id($studentid);
        return $allocatable && $allocatable->is_valid_for_course($this->coursework->get_course());
    }

    /**
     * @param int $allocatableid
     * @return allocatable
     */
    private function get_allocatable_from_id($allocatableid) {
        if ($this->coursework->is_configured_to_have_group_submissions()) {
            return group::find($allocatableid);
        } else {
            return user::find($allocatableid);
        }
    }
}

