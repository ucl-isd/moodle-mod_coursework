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
 * @copyright  2025 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\traits;

/**
 * Functions for the auto agreement strategies.
 * @package mod_coursework\traits
 */
trait autoagreement_functions {
    /**
     * Combine the markers' feedback comments into a single comment for the
     * agreed feedback.
     */
    public function feedback_comments() {
        global $DB;

        $feedbacks = $DB->get_records('coursework_feedbacks', [
            'submissionid' => $this->get_allocatable()->get_submission($this->get_coursework())->id(),
            'isfinalgrade' => 0,
        ]);
        $feedbackcomment = '';
        $count = 1;

        foreach ($feedbacks as $feedback) {
            // Put all initial feedbacks together for the comment field.
            $feedbackcomment .= get_string('markercomments', 'mod_coursework', $count);
            $feedbackcomment .= $feedback->feedbackcomment;
            $feedbackcomment .= '<br>';
            $count++;
        }

        return $feedbackcomment;
    }
}
