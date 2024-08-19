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

namespace mod_coursework\models;

/**
 * Class null_user
 */
class null_user implements \mod_coursework\allocation\allocatable {

    /**
     * @return string
     */
    public function name() {
        return '';
    }

    /**
     * @return string
     */
    public function picture() {
        return '';
    }

    /**
     * @return int
     */
    public function id() {
        return 0;
    }

    /**
     * @return string
     */
    public function type() {
        return 'user';
    }

    /**
     * @param bool $with_picture
     * @return string
     */
    public function profile_link($with_picture = false) {
        return '';
    }

    /**
     * @param \stdClass $course
     * @return mixed
     */
    public function is_valid_for_course($course) {
        return true;
    }

    /**
     * @param coursework $coursework
     * @return bool
     */
    public function has_agreed_feedback($coursework) {
        return false;
    }

    /**
     * @param coursework $coursework
     * @return feedback[]
     */
    public function get_initial_feedbacks($coursework) {
        return [];
    }

    /**
     * @param coursework $coursework
     * @return bool
     */
    public function has_all_initial_feedbacks($coursework) {
        return false;
    }

    /**
     * @param coursework $coursework
     * @return bool
     */
    public function get_agreed_feedback($coursework) {
        return false;
    }

    /**
     * @param coursework $coursework
     * @return submission
     */
    public function get_submission($coursework) {
    }

}
