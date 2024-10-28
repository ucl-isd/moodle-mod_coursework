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
     * Process form data received from /actions/allocate.php form.
     * @param array $dirtyformdata
     */
    public function process_data($dirtyformdata  = []) {
        $cleandata = $this->clean_data($dirtyformdata);
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
     * The stages will further deal with sanitising the data for each cell.
     *
     * @param array $dirtyformdata
     * @return array
     */
    private function clean_data(array $dirtyformdata): array {
        // Raw data looks like this - 4543 is student ID.
        // $exampledata = [
        //      4543 => [
        //          'assessor_1' => ['allocation_id' => 43, 'assessor_id' => 232],
        //          'moderator_1' => ['allocation_id' => 46, 'assessor_id' => 235, 'in_set' => 1],
        //      ],
        // ];
        $allowedrowkeys = [
            \mod_coursework\allocation\table\cell\data::ALLOCATION_ID_KEY,
            \mod_coursework\allocation\table\cell\data::ASSESSOR_ID_KEY,
            \mod_coursework\allocation\table\cell\data::MODERATION_SET_KEY,
            \mod_coursework\allocation\table\cell\data::PINNED_KEY,
        ];

        $cleandata = [];
        foreach ($dirtyformdata as $allocatableid => $dirtyrowsforuser) {

            // Should be the id of a student.
            if (!$this->allocatable_id_is_valid(clean_param($allocatableid, PARAM_INT))) {
                continue;
            }

            // Variable $rawdataforuser is expected to be an array of arrays.
            $validstageindentifiers = array_keys($this->coursework->marking_stages());
            foreach ($dirtyrowsforuser as $stageidentifier => $dirtyrowforuser) {
                if (!isset($validstageindentifiers, $stageidentifier)) {
                    throw new \invalid_parameter_exception("Invalid stage identifier $stageidentifier");
                }

                // Finally, check the keys and values in the cleaned row individually.
                $keys = array_keys($dirtyrowforuser);
                foreach ($keys as $key) {
                    if (!in_array($key, $allowedrowkeys)) {
                        throw new \invalid_parameter_exception("Invalid key $key");
                    }
                    if ($dirtyrowforuser[$key] && !filter_var($dirtyrowforuser[$key], FILTER_SANITIZE_NUMBER_INT)) {
                        throw new \invalid_parameter_exception(
                            "Invalid value type for key '$key' - expected integer"
                        );
                    }
                }
                $cleandata[$allocatableid][$stageidentifier] = clean_param_array($dirtyrowforuser, PARAM_INT);
            }
        }
        return $cleandata;
    }

    /**
     * Is this a valid allocatable ID?
     * @param int $studentid
     * @return bool
     */
    private function allocatable_id_is_valid(int $studentid): bool {
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

