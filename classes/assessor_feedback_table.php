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

defined('MOODLE_INTERNAL') || die();


/**
 * Renderable class to represent a table containing assessor feedbacks for a single student submission.
 * Used in the grading report for multiple assessor courseworks.
 */
class assessor_feedback_table {

    /**
     * @var allocatable
     */
    protected $allocatable;

    /**
     * @var models\coursework
     */
    private $coursework;

    /**
     * @var models\submission
     */
    private $submission;

    /**
     * @var int what colspan should the containing table cell have?
     */
    private $numberofcolumns;

    /**
     * Constructor sets variables only.
     *
     * @param coursework $coursework
     * @param allocatable $allocatable
     * @param models\submission $submission
     */
    public function __construct($coursework, $allocatable, $submission = null) {
        $this->coursework = $coursework;
        $this->submission = $submission;
        $this->allocatable = $allocatable;
    }

    /**
     * The renderer will need row objects to render. This provides them. We may have allocations which
     * have not yet been turned into feedbacks, so we want to show these as empty rows in order to let
     * managers know what is going on.
     *
     * @return \mod_coursework_assessor_feedback_row[]
     */
    public function get_renderable_feedback_rows() {

        // Makes a new result set every time, so we can modify this array without worrying about
        // messing up the cache.

        $feedbackobjects = array();
        foreach ($this->coursework->get_assessor_marking_stages() as $stage) {
            $renderable_row = new assessor_feedback_row($stage, $this->get_allocatable(), $this->coursework);
            $feedbackobjects[] = $renderable_row;
        }

        return $feedbackobjects;
    }

    /**
     * Has the current user already submitted feedback for this submission? If so, we know not to add
     * an option for them to do it again.
     *
     * @return bool
     */
    public function user_has_submitted_feedback() {
        if (!isset($this->submission)) {
            return false;
        }
        return $this->submission->user_has_submitted_feedback();
    }

    /**
     * @return int
     */
    public function get_allocatable_id() {
        return $this->allocatable->id();
    }

    /**
     * Sets the width of the containing table cell relative to the containing grading table row.
     *
     * @param $numberofcolumns
     */
    public function set_column_width($numberofcolumns) {
        $this->numberofcolumns = $numberofcolumns;
    }

    /**
     * How wide should the containing cell be?
     *
     * @return int
     */
    public function get_column_width() {
        return $this->numberofcolumns;
    }

    /**
     * @return allocatable
     */
    public function get_allocatable() {
        return $this->allocatable;
    }

    /**
     * @return coursework
     */
    public function get_coursework() {
        return $this->coursework;
    }

    /**
     * @return models\submission|null
     */
    public function get_submission() {
        return $this->submission;
    }
}
