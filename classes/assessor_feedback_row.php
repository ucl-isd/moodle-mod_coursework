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

namespace mod_coursework;

use mod_coursework\allocation\allocatable;
use mod_coursework\models\coursework;
use mod_coursework\models\submission;
use mod_coursework\models\null_user;
use mod_coursework\stages\base as stage_base;

/**
 * Class that exists to tell the renderer to use a different function. This one will make the
 * feedback into a row in the assessor feedbacks table of the grading report.
 */
class assessor_feedback_row {
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
     * Gets the assessor from the feedback.
     *
     * @return allocatable
     */
    public function get_assessor() {
        if ($this->has_feedback()) {
            return $this->get_feedback()->assessor();
        }

        if ($this->get_stage()->has_allocation($this->allocatable)) {
            return $this->get_stage()->get_allocation($this->allocatable->id(), $this->allocatable->type())->assessor();
        }

        return new null_user();
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
     * When was this feedback last altered?
     *
     * @return int
     */
    public function get_time_modified() {
        return $this->get_feedback()->timemodified;
    }

    /**
     * Admins may see the feedback placeholder rows before there is anything to display.
     *
     * @return bool
     */
    public function has_feedback(): bool {
        return (bool)$this->get_feedback();
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
     * @return submission|null
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_submission() {

        if (isset($this->submission)) {
            return $this->submission;
        }
        $this->submission = submission::get_for_allocatable(
            $this->get_coursework()->id,
            $this->get_allocatable()->id(),
            $this->get_allocatable()->type()
        );
        return $this->submission;
    }

    /**
     * @return bool
     */
    public function has_submission(): bool {
        return (bool)$this->get_submission();
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
}
