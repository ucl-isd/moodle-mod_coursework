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

namespace mod_coursework\allocation\table\cell;
use coding_exception;
use html_table_cell;
use html_writer;
use mod_coursework\allocation\allocatable;
use mod_coursework\models\allocation;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\models\moderation;
use mod_coursework\models\submission;
use mod_coursework\stages\base as stage_base;

/**
 * This class and it's descendants are responsible for processing the data from the allocation form.
 * They know about the logic of what to do based on the data that the cell provides. The actions are carried
 * out by the stage class.
 *
 * @package mod_coursework\allocation\table\cell
 */
class builder {
    /**
     * @var coursework
     */
    private $coursework;

    /**
     * @var allocatable
     */
    private $allocatable;

    /**
     * @var stage_base
     */
    private $stage;

    /**
     * @param coursework $coursework
     * @param allocatable $allocatable
     * @param stage_base $stage
     * @param array $dataarray incoming data from the allocation form
     */
    public function __construct($coursework, $allocatable, $stage, $dataarray = []) {
        $this->coursework = $coursework;
        $this->allocatable = $allocatable;
        $this->stage = $stage;
    }

    /**
     * @return string
     */
    public function get_renderable_allocation_table_cell() {
        return $this->prepare_allocation_table_cell();
    }

    /**
     * @return string
     */
    public function get_renderable_moderation_table_cell() {
        return $this->prepare_moderation_table_cell();
    }

    /**
     * Makes the dropdown showing what teachers can mark this coursework.
     *
     * @return string
     * @throws coding_exception
     */
    private function get_potential_marker_dropdown() {

        if ($this->stage_does_not_use_allocation()) {
            return '';
        }
        if ($this->already_has_feedback()) {
            return '';
        }

        return $this->get_stage()->potential_marker_dropdown($this->get_allocatable());
    }

    /**
     * @return string
     */
    private function get_potential_moderators_dropdown() {

        if ($this->stage_does_not_use_allocation()) {
            return '';
        }
        if ($this->has_moderation()) {
            return '';
        }

        return $this->get_stage()->potential_moderator_dropdown($this->get_allocatable());
    }

    /**
     * @return bool
     */
    private function has_moderation() {
        if ($this->get_submission()) {
            return $this->get_stage()->has_moderation($this->get_submission());
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    private function has_feedback() {
        return $this->get_stage()->has_feedback($this->get_allocatable());
    }

    /**
     * @return bool|feedback
     */
    private function get_feedback() {
        return $this->get_stage()->get_feedback_for_allocatable($this->get_allocatable());
    }

    /**
     * @return bool|moderation
     */
    private function get_moderation() {
        if ($this->get_submission()) {
            return $this->get_stage()->get_moderation($this->get_submission());
        }
        return false;
    }

    /**
     * @return bool
     */
    private function has_allocation() {
        return $this->get_stage()->has_allocation($this->get_allocatable());
    }

    /**
     * @return allocation|bool
     */
    private function get_allocation() {
        return $this->get_stage()->get_allocation($this->get_allocatable());
    }

    /**
     */
    private function prepare_allocation_table_cell() {

        $class = $this->get_stage()->identifier();
        $contents = '';
        $assessordropdown = '';

        if ($this->coursework->sampling_enabled() && $class !== 'final_agreed_1') {
            if ($this->get_stage()->uses_sampling()) {
                if ($this->has_automatic_sampling()) {
                    $contents .= $this->get_automatically_in_sample_label();
                    $contents .= $this->sampling_hidden_checkbox();
                } else {
                    if ($this->has_feedback()) {
                        $contents .= $this->get_included_in_sample_label();
                        $contents .= $this->sampling_hidden_checkbox();
                    } else {
                        $contents .= $this->sampling_set_checkbox();
                    }
                }
            } else {
                // label for stage1 where everyone is in sample
                 $contents .= $this->get_included_in_sample_label();
            }
        }
        $contents .= '<br>';

        if ($this->coursework->allocation_enabled()) {
            $assessordropdown = $this->get_potential_marker_dropdown();
        }
        $assessorname = '';
        if ($this->has_feedback()) {
            $class .= ' has-assessor-feedback ';

            $feedback = $this->get_feedback();
            $assessor = $feedback->assessor();
            $assessorname = $assessor->profile_link();
            $assessorname .= '<br>';
            $assessorname .= 'Grade: ';
            $assessorname .= $this->get_feedback()->get_grade();
        } else if ($this->has_allocation()) {
            $assessorname .= ' ' . $this->pinned_checkbox($assessordropdown);
            $assessorname .= $this->get_stage()->get_allocated_assessor_name($this->get_allocatable());
        }

        if ($assessorname) {
            if ($this->get_stage()->uses_sampling() && !$this->get_feedback() && !$this->has_automatic_sampling()) {
                $contents .= '<br>';
            }
            $contents .= "<span class='existing-assessor'>{$assessorname}</span>";
        }

        if ($assessordropdown) {
            $contents .= $assessordropdown;
        }

        return '
            <td class="' . $class . '">
            ' . $contents . '
            </td>
        ';
    }

    /**
     * @return string
     * @throws coding_exception
     */
    private function prepare_moderation_table_cell() {

        $contents = '';
        $class = 'moderators';
        $moderatordropdown = '';

        if ($this->coursework->allocation_enabled()) {
            $moderatordropdown = $this->get_potential_moderators_dropdown();
        }

        $moderatorname = '';
        if ($this->has_moderation()) {
            $class .= ' has-moderation-agreement ';

            $moderation = $this->get_moderation();
            $moderator = $moderation->moderator();
            $moderatorname = $moderator->profile_link();
            $moderatorname .= '<br>';
            $moderatorname .= 'Agreement: ';
            $moderatorname .= get_string($this->get_moderation()->agreement, 'coursework');
        } else if ($this->has_allocation()) {
            $moderatorname = ' ' . $this->pinned_checkbox($moderatordropdown);
            $moderatorname .= $this->get_stage()->get_allocated_assessor_name($this->get_allocatable());
        }

        if ($moderatorname) {
            $contents .= '<br>';
            $contents .= "<span class='existing-moderator'>{$moderatorname}</span>";
        }

        if ($moderatordropdown) {
            $contents .= '<br>';
            $contents .= $moderatordropdown;
        }
        return '
            <td class="' . $class . '">
            ' . $contents . '
            </td>
        ';
    }

    /**
     * @return allocatable
     */
    private function get_allocatable() {
        return $this->allocatable;
    }

    /**
     * @return stage_base
     */
    private function get_stage() {
        return $this->stage;
    }

    /**
     * @throws coding_exception
     */
    private function sampling_set_checkbox() {
        $checkboxname =
            'allocatables[' . $this->get_allocatable()->id . '][' . $this->get_stage()->identifier() . '][in_set]';
        $checkboxchecked = 0;
        if ($this->get_stage()->allocatable_is_in_sample($this->get_allocatable()) || $this->get_stage()->identifier() == 'assessor_1') {
            $checkboxchecked = 1;
        }

        $checkboxchecked = $this->checkbox_checked_in_session($checkboxname, $checkboxchecked);

        $checkboxtitle = 'Included in sample';

        $attributes = ['class' => 'sampling_set_checkbox',
                            'id' => $this->get_allocatable()->type() . '_' . $this->get_allocatable()->id() . '_' . $this->get_stage()->identifier() . '_samplecheckbox',
                            'title' => $checkboxtitle];

        // if agreed grade given or grade published to students disable remaining sampling checkbox
        $submission = $this->get_submission();
        if ($this->has_final_feedback() || ($submission && $submission->firstpublished)) {
            $attributes['disabled'] = 'true';
        }

        return html_writer::checkbox(
            $checkboxname,
            1,
            $checkboxchecked,
            get_string('includedinsample', 'mod_coursework'),
            $attributes
        );
    }

    /**
     * @return string
     */
    private function sampling_hidden_checkbox() {
        $checkboxname =
            'allocatables[' . $this->get_allocatable()->id . '][' . $this->get_stage()->identifier() . '][in_set]';
        $checkboxtitle = 'Included in sample';

        return html_writer::checkbox(
            $checkboxname,
            1,
            1,
            '',
            ['class' => 'sampling_set_checkbox',
                'id' => $this->get_allocatable()->type() . '_' . $this->get_allocatable()->id() . '_' . $this->get_stage()->identifier() . '_samplecheckbox',
            'title' => $checkboxtitle,
            'hidden' => true]
        );
    }

    /**
     * returns whether the current record was automatically included in the sample set at the current stage
     *
     * @return bool
     * @throws \dml_exception
     * @throws coding_exception
     */
    private function has_automatic_sampling() {

        global $DB;

        $params = ['courseworkid' => $this->coursework->id(),
                          'allocatableid' => $this->get_allocatable()->id(),
                          'stageidentifier' => $this->get_stage()->identifier(),
                          'selectiontype' => 'automatic'];

        return $DB->record_exists('coursework_sample_set_mbrs', $params);
    }

    /**
     * @return string
     */
    private function pinned_checkbox() {

        $checkboxname =
            'allocatables[' . $this->get_allocatable()->id . '][' . $this->get_stage()->identifier() . '][pinned]';
        $checkboxchecked = 0;
        if ($this->get_stage()->has_allocation($this->get_allocatable())) {
            if ($this->get_stage()->get_allocation($this->get_allocatable())->is_pinned()) {
                $checkboxchecked = 1;
            }
        }

        $checkboxchecked = $this->checkbox_checked_in_session($checkboxname, $checkboxchecked);

        $stage = substr($this->get_stage()->identifier(), -1);
        $checkboxtitle = 'Pinned (auto allocations will not alter this)';
        return html_writer::checkbox(
            $checkboxname,
            1,
            $checkboxchecked,
            '',
            ['class' => "pinned pin_$stage",
            'title' => $checkboxtitle]
        );
    }

    private function checkbox_checked_in_session($checkboxname, $checkboxstate) {

        global  $SESSION;

        $cm = $this->coursework->get_course_module();

        if (!empty($SESSION->coursework_allocationsessions[$cm->id])) {
            if (isset($SESSION->coursework_allocationsessions[$cm->id][$checkboxname])) {
                return  $SESSION->coursework_allocationsessions[$cm->id][$checkboxname];
            }
        }

        return $checkboxstate;
    }

    /**
     * @return bool
     */
    private function already_has_feedback() {
        return $this->get_stage()->has_feedback($this->get_allocatable());
    }

    /**
     * @return bool
     */
    private function stage_does_not_use_allocation() {
        return !$this->get_stage()->uses_allocation();
    }

    /**
     * @return string
     * @throws coding_exception
     */
    private function get_included_in_sample_label() {
        return html_writer::label(get_string('includedinsample', 'mod_coursework'), null, true, ['class' => 'included_in_sample']);
    }

    /**
     * @return string
     * @throws coding_exception
     */
    private function get_automatically_in_sample_label() {
        return html_writer::label(get_string('automaticallyinsample', 'mod_coursework'), null, true, ['class' => 'included_in_sample']);
    }

    /**
     * @return bool
     * @throws \core\exception\coding_exception
     * @throws \dml_exception
     */
    private function has_final_feedback() {
        submission::fill_pool_coursework($this->coursework->id);
        feedback::fill_pool_coursework($this->coursework->id);
        $submission = submission::get_object(
            $this->coursework->id,
            'allocatableid-allocatabletype',
            [$this->allocatable->id(), $this->allocatable->type()]
        );
        if ($submission) {
            $feedbacks = isset(feedback::$pool[$this->coursework->id]['submissionid'][$submission->id]) ?
            feedback::$pool[$this->coursework->id]['submissionid'][$submission->id] : [];

            foreach ($feedbacks as $feedback) {
                if ($feedback->stageidentifier == 'final_agreed_1') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @return bool
     * @throws \core\exception\coding_exception
     */
    private function get_submission() {
        submission::fill_pool_coursework($this->coursework->id);
        return submission::get_object(
            $this->coursework->id,
            'allocatableid-allocatabletype',
            [$this->allocatable->id(), $this->allocatable->type()]
        );
    }
}
