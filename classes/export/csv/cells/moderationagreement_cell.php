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

namespace mod_coursework\export\csv\cells;
use mod_coursework\models\submission;
use mod_coursework\models\moderation;

/**
 * Class moderationagreement_cell
 */
class moderationagreement_cell extends cell_base {

    /**
     * @param submission $submission
     * @param $student
     * @param $stage_identifier
     * @return array
     */
    public function get_cell($submission, $student, $stage_identifier) {
        global $DB;

        $data = [];
        $moderation_agreement = '';
        $moderation = '';

        if ($this->coursework->allocation_enabled()) {
            $allocation = $submission->get_assessor_allocation_by_stage('moderator');
            if ($allocation) {
                $data[] = $this->get_assessor_name($allocation->assessorid);
                $data[] = $this->get_assessor_username($allocation->assessorid);
            } else {
                $data[] = get_string('moderatornotallocated', 'mod_coursework');
                $data[] = get_string('moderatornotallocated', 'mod_coursework');
            }
        }
        $feedback = $submission->get_assessor_feedback_by_stage('assessor_1');
        if ($feedback) $moderation = moderation::find(array('feedbackid' => $feedback->id));

        if ($moderation) $moderation_agreement = $moderation->get_moderator_agreement($feedback);

        if ($moderation_agreement) {
                $data[] = $moderation_agreement->agreement;
                $data[] = $this->get_assessor_name($moderation_agreement->moderatorid);
                $data[] = $this->get_assessor_username($moderation_agreement->moderatorid);
                $data[] = userdate($moderation_agreement->timemodified, $this->dateformat);
            } else {
                $data[] = '';
                $data[] = '';
                $data[] = '';
                $data[] = '';
            }
        return $data;
    }

    /**
     * @param $stage
     * @return array
     * @throws \coding_exception
     */
    public function get_header($stage) {

        $fields = [];
        if ($this->coursework->allocation_enabled()) {
            $fields['allocatedmoderatorname'] = 'Allocated moderator name';
            $fields['allocatedmoderatorusername'] = 'Allocated moderator username';
        }

        $fields['moderatoragreement'] = 'Moderator agreement';
        $fields['moderatorname'] = 'Moderator name';
        $fields['moderatorusername'] = 'Moderator username';
        $fields['moderatedon'] = 'Moderated on';

        return $fields;
    }

}
