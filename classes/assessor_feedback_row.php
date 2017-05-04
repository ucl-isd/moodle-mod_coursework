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

namespace mod_coursework;

use mod_coursework\allocation\allocatable;
use mod_coursework\models\coursework;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use mod_coursework\stages\base as stage_base;

defined('MOODLE_INTERNAL') || die();


/**
 * Class that exists to tell the renderer to use a different function. This one will make the
 * feedback into a row in the assessor feedbacks table of the grading report.
 */
class assessor_feedback_row {

    /**
     * @var int So we can have a row with no feedback, but still see who is allocated to mark it.
     */
    private $assessorid;

    /**
     * @var models\submission|null
     */
    private $submission;

    /**
     * @var stage_base
     */
    private $stage;

    /**
     * @var allocatable
     */
    private $allocatable;

    /**
     * @var coursework
     */
    private $coursework;

    /**
     * @param stage_base $stage
     * @param allocatable $allocatable
     * @param coursework $coursework
     */
    public function __construct($stage, $allocatable, $coursework) {
        $this->stage = $stage;
        $this->allocatable = $allocatable;
        $this->coursework = $coursework;
    }

    /**
     * Lets us know if the current user was the author of this bit of feedback.
     *
     * @return bool
     */
    public function user_is_assessor() {

        global $USER;

        return $this->get_assessor_id() == $USER->id;
    }

    /**
     * Chained getter for loose coupling.
     *
     * @return string
     */
    public function get_assessor_username() {
        return $this->get_stage()->get_allocated_assessor_name($this->get_allocatable());
    }

    /**
     * Gets the assessor id from the feedback.
     *
     * @return mixed
     */
    public function get_assessor_id() {
        return $this->assessorid;
    }

    /**
     * Gets the assessor from the feedback.
     *
     * @return user
     */
    public function get_assessor() {
        if (!$this->get_coursework()->allocation_enabled() && $this->has_feedback()) {
            return $this->get_feedback()->assessor();
        }
        return $this->get_stage()->get_allocated_assessor($this->allocatable);
    }

    /**
     * Gets the assessor id from the feedback
     *
     * @return user
     */
    public function get_graded_by(){
        return $this->get_feedback()->assessor();
    }

    /**
     * Gets the grader's name and link to the profile
     * @return string
     */
    public function get_graders_name(){
      return  $this->get_graded_by()->profile_link();
    }

    /**
     * @return models\coursework
     */
    public function get_coursework() {
        return $this->coursework;
    }

    /**
     * Chained getter for loose coupling.
     *
     * @return int
     */
    public function get_grade() {
        if (!$this->has_feedback()) {
            return null;
        }
        return $this->get_feedback()->get_grade();
    }

    /**
     * Returns the maximum grade that this coursework will allow for a feedback.
     */
    public function get_max_grade() {
        return $this->get_coursework()->get_max_grade();
    }

    /**
     * When was this feedback last altered?
     *
     * @return mixed
     */
    public function get_time_modified() {
        return $this->get_feedback()->timemodified;
    }

    /**
     * Admins may see the feedback placeholder rows before there is anything to display.
     *
     * @return bool
     */
    public function has_feedback() {
        return !!$this->get_feedback();
    }

    /**
     * Getter
     *
     * @return models\feedback|null
     */
    public function get_feedback() {
        return $this->stage->get_feedback_for_allocatable($this->allocatable);
    }

    /**
     * Getter
     *
     * @return models\submission|null
     */
    public function get_submission() {

        if (isset($this->submission)) {
            return $this->submission;
        }
        $params = array(
            'courseworkid' => $this->get_coursework()->id,
            'allocatableid' => $this->get_allocatable()->id(),
            'allocatabletype' => $this->get_allocatable()->type(),
        );
        $this->submission = submission::find($params);
        return $this->submission;
    }

    /**
     * @return bool
     */
    private function has_submission() {
        return !!$this->get_submission();
    }

    /**
     * @return stage_base
     */
    public function get_stage() {
        return $this->stage;
    }

    /**
     * @return allocatable
     */
    public function get_allocatable() {
        return $this->allocatable;
    }

    /**
     * @return bool|models\allocation
     */
    private function get_allocation() {
        return $this->stage->get_allocation($this->get_allocatable());
    }

    /**
     * @return bool
     */
    private function has_allocation() {
        return !!$this->get_allocation();
    }
}

