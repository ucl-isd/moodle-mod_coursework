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

namespace mod_coursework\allocation;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\models\submission;

/**
 * This tells us that a class (e.g. user or group) can be allocated to a teacher for marking
 * or moderation.
 *
 * @property int id
 * @package mod_coursework\allocation
 */
interface allocatable {

    /**
     * @return string
     */
    public function name();

    /**
     * @return int
     */
    public function id();

    /**
     * @return string
     */
    public function type();

    /**
     * @return string
     */
    public function picture();

    /**
     * @param bool $with_picture
     * @return string
     */
    public function profile_link($with_picture = false);

    /**
     * @param \stdClass $course
     * @return mixed
     */
    public function is_valid_for_course($course);

    /**
     * @param coursework $coursework
     * @return bool
     */
    public function has_agreed_feedback($coursework);

    /**
     * @param coursework $coursework
     * @return bool
     */
    public function get_agreed_feedback($coursework);

    /**
     * @param coursework $coursework
     * @return feedback[]
     */
    public function get_initial_feedbacks($coursework);

    /**
     * @param coursework $coursework
     * @return bool
     */
    public function has_all_initial_feedbacks($coursework);

    /**
     * @param coursework $coursework
     * @return submission
     */
    public function get_submission($coursework);
}
