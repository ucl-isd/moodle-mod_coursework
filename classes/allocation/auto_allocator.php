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

namespace mod_coursework\allocation;

use mod_coursework\models\allocation;
use mod_coursework\models\coursework;
use mod_coursework\stages\base as stage_base;

/**
 * Class auto_allocator handles the auto allocation of students or groups to tutors.
 *
 * @package mod_coursework\allocation
 */
class auto_allocator {

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

    public function process_allocations() {
        $this->delete_all_ungraded_auto_allocations();

        foreach ($this->marking_stages() as $stage) {
            if ($stage->group_assessor_enabled() && $stage->identifier() == 'assessor_1') {
                // if allocation strategy 'group_assessor' then assign assessor from that group to stage1 and continue
                // for the rest of stages with manual allocation
                $allocatables = $this->get_allocatables();

                foreach ($allocatables as $allocatable) {
                    $stage->make_auto_allocation_if_necessary($allocatable);
                }

            } else if ($stage->auto_allocation_enabled()) {
                $this->process_marking_stage($stage);
            }
        }
        allocation::remove_cache($this->coursework->id);
        allocation::fill_pool_coursework($this->coursework->id);
    }

    /**
     * @param stage_base $stage
     */
    private function process_marking_stage($stage) {
        if (!$stage->auto_allocation_enabled()) {
            return;
        }

        $allocatables = $this->get_allocatables();

        foreach ($allocatables as $allocatable) { // Allocatable = user or group
            if ($stage->uses_sampling() && $stage->allocatable_is_not_in_sampling($allocatable)) {
                continue;
            }

            $stage->make_auto_allocation_if_necessary($allocatable);
        }
    }

    /**
     * @return stage_base[]
     */
    private function marking_stages() {
        return $this->get_coursework()->marking_stages();
    }

    /**
     * @return allocatable[]
     */
    private function get_allocatables() {
        return $this->get_coursework()->get_allocatables();
    }

    /**
     * So that we can re-do them in case stuff has changed.
     */
    private function delete_all_ungraded_auto_allocations() {
        global $DB;

        $ungradedallocations = $DB->get_records_sql('
            SELECT *
            FROM {coursework_allocation_pairs} p
            WHERE courseworkid = ?
            AND p.manual = 0
            AND NOT EXISTS (
                SELECT 1
                FROM {coursework_submissions} s
                INNER JOIN {coursework_feedbacks} f
                ON f.submissionid = s.id
                WHERE s.allocatableid = p.allocatableid
                AND s.allocatabletype = p.allocatabletype
                AND s.courseworkid = p.courseworkid
                AND f.stage_identifier = p.stage_identifier
            )
        ', ['courseworkid' => $this->get_coursework()->id]);

        foreach ($ungradedallocations as &$allocation) {
            /**
             * @var allocation $allocation_object
             */
            $allocationobject = allocation::find($allocation);
            $allocationobject->destroy();
        }
    }

    /**
     * @return coursework
     */
    private function get_coursework() {
        return $this->coursework;
    }
}
