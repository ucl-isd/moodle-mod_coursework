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
 * @copyright  2017 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\stages;
use mod_coursework\models\user;

/**
 * Class marking_stage represents a stage of the marking process. For a basic single marked coursework,
 * there will only be one. Double marking will have 3 (Two initial assessors and a final grade), and if
 * moderation is enabled, there will be one more.
 *
 * @package mod_coursework
 */
class assessor extends base {

    /**
     * @throws \coding_exception
     * @return string
     */
    protected function strategy_name() {
        if (empty($this->get_coursework()->assessorallocationstrategy)) {
            return 'none';
        }
        return $this->get_coursework()->assessorallocationstrategy;
    }

    /**
     * @return string
     */
    public function allocation_table_header() {
        return 'Assessor';
    }

    /**
     * @return string
     */
    protected function assessor_capability() {
        return 'mod/coursework:addinitialgrade';
    }

    /**
     * @return bool
     */
    protected function is_parallell() {
        return true;
    }

    /**
     * @param user $assessor
     * @param \mod_coursework\models\submission $submission
     * @return bool
     */
    public function other_parallel_stage_has_feedback_from_this_assessor($assessor, $submission) {
        global $DB;

        $sql = "
            SELECT 1
            FROM {coursework_feedbacks} f
            WHERE assessorid = ?
            AND submissionid = ?
            AND stage_identifier LIKE '{$this->type()}%'
        ";
        return $DB->record_exists_sql($sql, array($assessor->id(),
                                                  $submission->id));
    }

    /**
     * @return bool
     */
    public function auto_allocation_enabled() {
        return $this->get_coursework()->allocation_enabled() && parent::auto_allocation_enabled();
    }

    /**
     * @return bool
     */
    public function is_initial_assesor_stage() {
        return true;
    }

    /**
     * @return bool
     */
    public function uses_sampling() {
        if ($this->identifier() == 'assessor_1') {
            return false;
        } else {
            return parent::uses_sampling();
        }
    }

}
