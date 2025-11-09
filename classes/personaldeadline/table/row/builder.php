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

namespace mod_coursework\personaldeadline\table\row;

/**
 * Class file for the renderable object that makes a single row in the marker personal deadline table.
 *
 * @package    mod_coursework
 * @copyright  2011 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\allocation\allocatable;
use mod_coursework\models\coursework;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use mod_coursework\personaldeadline\table\builder as table_builder;
use mod_coursework\render_helpers\grading_report\cells\allocatable_cell;
use mod_coursework\user_row;

/**
 * Renderable row class.
 */
class builder implements user_row {
    /**
     * @var table_builder
     */
    private $personaldeadlinetable;

    /**
     * @var allocatable user or group
     */
    private $allocatable;

    /**
     * Constructor makes new instance.
     *
     * @param table_builder $personaldeadlinetable
     * @param allocatable $allocatable
     */
    public function __construct($personaldeadlinetable, $allocatable) {
        $this->personaldeadlinetable = $personaldeadlinetable;
        $this->allocatable = $allocatable;
    }

    /**
     * @return allocatable
     */
    public function get_allocatable() {
        return $this->allocatable;
    }

    /**
     * @return int
     */
    public function get_allocatable_id() {
        return $this->allocatable->id;
    }

    /**
     * @return string
     */
    public function get_user_name() {
        return $this->allocatable->name();
    }

    /**
     * Assume that if someone can see the coursework personal deadline table then they can see the full user names.
     *
     * @return bool
     */
    public function can_view_username() {
        return true;
    }

    /**
     * @return allocatable_cell
     */
    public function get_allocatable_cell() {
        return $this->personaldeadlinetable->get_allocatable_cell();
    }

    /**
     * @return coursework
     */
    public function get_coursework() {
        return $this->personaldeadlinetable->get_coursework();
    }

    /**
     * @return \mod_coursework\render_helpers\grading_report\cells\personaldeadline_cell
     */
    public function get_personaldeadline_cell() {
        return $this->personaldeadlinetable->get_personaldeadline_cell();
    }

    /**
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_student_firstname() {
        $allocatable = $this->get_allocatable();
        if (empty($allocatable->firstname)) {
            $this->allocatable = user::find($allocatable);
        }

        return $this->get_allocatable()->firstname;
    }

    /**
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_student_lastname() {
        $allocatable = $this->get_allocatable();
        if (empty($allocatable->lastname)) {
            $this->allocatable = user::find($allocatable);
        }

        return $this->get_allocatable()->lastname;
    }

    /**
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_idnumber() {
        $allocatable = $this->get_allocatable();
        if (empty($allocatable->idnumber)) {
            $this->allocatable = user::find($allocatable);
        }

        return $this->get_allocatable()->idnumber;
    }

    /**
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_email() {
        $allocatable = $this->get_allocatable();
        if (empty($allocatable->email)) {
            $this->allocatable = user::find($allocatable);
        }

        return $this->get_allocatable()->email;
    }

    /**
     * Getter for personal deadline time
     *
     * @return int|mixed|string
     * @throws \dml_exception
     */
    public function get_personaldeadlines() {
        global $DB;

        $allocatable = $this->get_allocatable();

        if (!$allocatable) {
            return '';
        }

        $personaldeadline = $DB->get_record(
            'coursework_person_deadlines',
            ['courseworkid' => $this->get_coursework()->id,
                  'allocatableid' => $this->allocatable->id(),
            'allocatabletype' => $this->allocatable->type()]
        );
        if ($personaldeadline) {
            $personaldeadline = $personaldeadline->personaldeadline;
        } else {
            $personaldeadline = $this->get_coursework()->deadline;
        }

        return  $personaldeadline;
    }

    public function get_submission_status() {
        global  $DB;

        $submissiondb = $DB->get_record(
            'coursework_submissions',
            ['courseworkid' => $this->get_coursework()->id,
                'allocatableid' => $this->allocatable->id(),
            'allocatabletype' => $this->allocatable->type()]
        );

        $submission = submission::find($submissiondb);

        $statustext = get_string('statusnotsubmitted', 'mod_coursework');

        if (!empty($submission) && $submission->get_state() == $submission::FINALISED) {
            $statustext = get_string('finalisedsubmission', 'mod_coursework');
        } else if (!empty($submission)) {
            $statustext = $submission->get_status_text();
        }

        return  $statustext;
    }
}
