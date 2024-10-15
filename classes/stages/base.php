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

namespace mod_coursework\stages;

use mod_coursework\allocation\allocatable;
use mod_coursework\allocation\table\cell\builder;
use mod_coursework\allocation\table\cell\data;
use mod_coursework\allocation\strategy\base as strategy_base;
use mod_coursework\allocation\table\cell\processor;
use mod_coursework\models\allocation;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\models\moderation;
use mod_coursework\models\assessment_set_membership;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use mod_coursework\models\null_user;

/**
 * Class base
 * @package mod_coursework\stages
 */
abstract class base {

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @var string
     */
    protected $stageidentifier;

    /**
     * @var array|null
     */
    protected $allocatableswithfeedback;

    /**
     * @var array|null
     */
    protected $allocatableswithmoderation;

    /**
     * @var array
     */
    private static $selfcache = [
        'user_is_assessor' => [],
    ];

    /**
     * @param coursework $coursework
     * @param int $stageidentifier
     */
    public function __construct($coursework, $stageidentifier) {
        $this->coursework = $coursework;
        $this->stageidentifier = $stageidentifier;
    }

    /**
     * @return strategy_base
     */
    private function get_allocation_strategy() {
        $strategyname = $this->strategy_name();
        $classname = "\\mod_coursework\\allocation\\strategy\\{$strategyname}";
        return new $classname($this->get_coursework(), $this);
    }

    /**
     * @param allocatable $allocatable
     * @return mixed|void
     */
    public function make_auto_allocation_if_necessary($allocatable) {
        if ($this->already_allocated($allocatable)) {
            return;
        }

        if ($this->get_coursework()->assessorallocationstrategy == 'group_assessor' &&  $this->identifier() == 'assessor_1' ) {
            // get teacher form the group
            $teacher = $this->get_assessor_from_moodle_course_group($allocatable);
            // $teacher = user::find(12);
        } else {
            $teacher = $this->get_next_teacher($allocatable);
        }

        if ($teacher) {
            $this->make_auto_allocation($allocatable, $teacher);
        }
    }

    /**
     * @return \mod_coursework\models\coursework
     */
    protected function get_coursework() {
        return $this->coursework;
    }

    /**
     * @param allocatable $allocatable
     * @return bool
     */
    private function already_allocated($allocatable) {
        $courseworkid = $this->get_coursework_id();
        allocation::fill_pool_coursework($courseworkid);
        $record = allocation::get_object(
            $courseworkid,
            'allocatableid-allocatabletype-stage_identifier',
            [$allocatable->id(), $allocatable->type(), $this->identifier()]
        );
        return !empty($record);
    }

    /**
     * @param allocatable $allocatable
     * @param $assessor
     * @return bool
     */
    public function assessor_already_allocated_for_this_submission($allocatable, $assessor) {

        if (!empty($assessor)) {
            $courseworkid = $this->get_coursework_id();
            allocation::fill_pool_coursework($courseworkid);
            $record = allocation::get_object(
                $courseworkid,
                'allocatableid-allocatabletype-assessorid',
                [$allocatable->id(), $allocatable->type(), $assessor->id]
            );
            return !empty($record);
        } else {
            return false;
        }
    }

    /**
     * @throws \coding_exception
     * @return string
     */
    abstract protected function strategy_name(): string;

    /**
     * @return string 'assessor_1'
     */
    public function identifier() {
        return $this->stageidentifier;
    }

    /**
     * @return int
     */
    private function get_coursework_id() {
        return $this->coursework->id;
    }

    /**
     * @param $allocatable
     * @param $teacher
     *
     * @return mixed|void
     */
    public function make_manual_allocation($allocatable, $teacher) {
        $allocation = $this->prepare_allocation_to_save($allocatable, $teacher);
        $allocation->ismanual = 1;
        $allocation->save();

        allocation::fill_pool_coursework($this->get_coursework()->id());
        return $allocation;
    }

    /**
     * @param $allocatable
     * @return \html_table_cell
     */
    public function get_allocation_table_cell($allocatable) {
        $cellhelper = $this->get_cell_helper($allocatable);

        return $cellhelper->get_renderable_allocation_table_cell();
    }

    /**
     * @param $allocatable
     * @return \html_table_cell
     */
    public function get_moderation_table_cell($allocatable) {
        $cellhelper = $this->get_cell_helper($allocatable);

        return $cellhelper->get_renderable_moderation_table_cell();
    }

    /**
     * @param $allocatable
     * @param $teacher
     *
     * @return mixed|void
     */
    private function make_auto_allocation($allocatable, $teacher) {
        $allocation = $this->prepare_allocation_to_save($allocatable, $teacher);
        $allocation->save();
    }

    /**
     * @return int
     */
    protected function is_moderator() {
        return 0;
    }

    /**
     * @param allocatable $allocatable
     * @return mixed|void
     */
    private function get_next_teacher($allocatable) {

        // for percentage allocation use only those teachers that have percentage allocated
        if ($this->coursework->assessorallocationstrategy == 'percentages') {
            $teachers = $this->get_percentage_allocated_teachers();
        } else {
            $teachers = $this->get_teachers();
        }

        return $this->get_allocation_strategy()->next_assessor_from_list($teachers, $allocatable);
    }

    /**
     * Get ids of teachers who have percentage allocated to them
     * @return array
     */
    private function get_percentage_allocated_teachers() {
        global $DB;

        return $DB->get_records('coursework_allocation_config', ['courseworkid' => $this->get_coursework_id()], '', 'assessorid as id');
    }

    /**
     * @param allocatable $allocatable
     * @param $teacher
     * @return allocation
     */
    private function prepare_allocation_to_save($allocatable, $teacher) {
        $allocation = new allocation();
        $allocation->courseworkid = $this->coursework->id;
        $allocation->assessorid = $teacher->id;
        $allocation->stage_identifier = $this->identifier();
        $allocation->moderator = $this->is_moderator();
        $allocation->allocatableid = $allocatable->id();
        $allocation->allocatabletype = $allocatable->type();
        return $allocation;
    }

    /**
     * @param allocatable $allocatable
     * @return bool
     */
    public function allocation_is_manual($allocatable) {

        $courseworkid = $this->get_coursework_id();
        allocation::fill_pool_coursework($courseworkid);
        $record = allocation::get_object(
            $courseworkid,
            'allocatableid-allocatabletype-stage_identifier',
            [$allocatable->id(), $allocatable->type(), $this->identifier()]
        );
        if ($record && $record->ismanual == 1) {
            return true;
        }
        return false;
    }

    /**
     * @return user[]
     */
    public function get_teachers() {
        $cache = \cache::make('mod_coursework', 'courseworkdata');

        $serialisedteachers = $cache->get($this->coursework->id()."_teachers");

        // There is a chance that when the teachers were initially cached the dataset was empty
        // So check again
        if (empty($serialisedteachers) || empty(unserialize($serialisedteachers))) {
            $users = get_enrolled_users($this->coursework->get_context());
            $teacherusers = [];
            $modcontext = $this->coursework->get_context();
            foreach ($users as $user) {
                if (has_capability($this->assessor_capability(), $modcontext, $user)) {
                    $teacherusers[] = user::build($user);
                }
            }
            $cache->set($this->coursework->id()."_teachers", serialize($teacherusers));
        } else {
            $teacherusers = unserialize($serialisedteachers);
        }
        return $teacherusers;
    }

    /**
     * @return string
     */
    abstract protected function assessor_capability();

    /**
     * This is expensive when called lots of times, so we cache the results all in one go.
     *
     * @param allocatable $allocatable
     * @return bool
     */
    public function has_feedback($allocatable) {
        $feedback = null;
        $courseworkid = $this->get_coursework_id();
        submission::fill_pool_coursework($courseworkid);
        $submission = submission::get_object($courseworkid, 'allocatableid', [$allocatable->id]);
        if ($submission) {
            feedback::fill_pool_coursework($courseworkid);
            $feedback = feedback::get_object($courseworkid, 'submissionid-stage_identifier', [$submission->id, $this->identifier()]);
        }
        return !empty($feedback);
    }

    /**
     * @param $submission
     * @return bool
     */
    public function has_moderation($submission) {

        global $DB;
        $feedback = $this->get_single_feedback($submission);
        if ($feedback) {

            $sql = "SELECT *
                    FROM {coursework_mod_agreements}
                    WHERE feedbackid = ?";
            return $DB->record_exists_sql($sql, [$feedback->id]);
        } else {
            return false;
        }
    }

    /**
     * @param $submission
     * @return bool|moderation
     */
    public function get_moderation($submission) {
        $feedback = $this->get_single_feedback($submission);
        if ($feedback) {
            $moderationparams = ['feedbackid' => $feedback->id];
            return moderation::find($moderationparams);
        } else {
            return false;
        }
    }

    /**
     * @param allocatable $allocatable
     * @return bool|feedback
     */
    public function get_feedback_for_allocatable($allocatable) {
        $params = [$allocatable->id(), $allocatable->type()];
        $submission = submission::get_object($this->get_coursework()->id, 'allocatableid-allocatabletype', $params);

        if ($submission) {
            return $this->get_feedback_for_submission($submission);
        }
        return false;
    }

    /**
     * @param $submission
     * @return feedback|bool
     */
    public function get_single_feedback($submission) {
        feedback::fill_pool_coursework($submission->courseworkid);
        $result = feedback::get_object($submission->courseworkid, 'submissionid-stage_identifier', [$submission->id, 'assessor_1']);

        return $result;
    }

    /**
     * @param allocatable $allocatable
     * @return bool
     */
    public function has_allocation($allocatable) {
        if (!isset($this->allocatables_with_allocations)) {
            $courseworkid = $this->get_coursework()->id;
            if (!isset(allocation::$pool[$courseworkid]['stage_identifier'])) {
                allocation::fill_pool_coursework($courseworkid);
            }
            $this->allocatables_with_allocations = array_column(allocation::$pool[$courseworkid]['stage_identifier'][$this->stageidentifier] ?? [], 'allocatableid');
        }

        return in_array($allocatable->id, $this->allocatables_with_allocations);
    }

    /**
     * Check if current marking stage has any allocation
     *
     * @return bool
     */
    public function stage_has_allocation() {
        $courseworkid = $this->get_coursework_id();
        allocation::fill_pool_coursework($courseworkid);
        $record = allocation::get_object($courseworkid, 'stage_identifier', [$this->stageidentifier]);

        return !empty($record);
    }

    /**
     * @param $allocatable
     * @throws \coding_exception
     */
    public function destroy_allocation($allocatable) {
        $this->get_allocation($allocatable)->destroy();
    }

    /**
     * @param allocatable $allocatable
     * @return allocation|bool
     */
    public function get_allocation($allocatable) {
        $courseworkid = $this->coursework->id;
        $params = [$allocatable->id(), $allocatable->type(), $this->identifier()];
        $allocation = allocation::get_object($courseworkid, 'allocatableid-allocatabletype-stage_identifier', $params);
        return $allocation;
    }

    /**
     * @param allocatable $allocatable
     * @return user
     */
    public function allocated_teacher_for($allocatable) {
        $courseworkid = $this->get_coursework_id();
        allocation::fill_pool_coursework($courseworkid);
        $allocation = allocation::get_object(
            $courseworkid,
            'allocatableid-allocatabletype-stage_identifier',
            [$allocatable->id(), $allocatable->type(), $this->identifier()]
        );

        if ($allocation) {
            return $allocation->assessor();
        }

        return false;
    }

    /**
     * @param allocatable $allocatable
     * @param array $rowdata
     * @return void
     */
    public function process_allocation_form_row_data($allocatable, $rowdata) {
        $cellhelper = $this->get_cell_processor($allocatable);
        $celldata = $this->get_cell_data($rowdata);
        $cellhelper->process($celldata);
    }

    /**
     * @param array $rowdata
     * @return mixed
     */
    private function get_cell_data($rowdata) {
        if (array_key_exists($this->identifier(), $rowdata)) {
            return new data($this, $rowdata[$this->identifier()]);
        }
        return new data($this);
    }

    /**
     * @param allocatable $allocatable
     * @return builder
     */
    private function get_cell_helper($allocatable) {
        return new builder($this->coursework, $allocatable, $this);
    }

    /**
     * @param allocatable $allocatable
     * @return processor
     */
    private function get_cell_processor($allocatable) {
        return new processor($this->coursework, $allocatable, $this);
    }

    /**
     * @param allocatable $allocatable
     * @return bool
     */
    public function allocatable_is_in_sample($allocatable) {
        if (!$this->uses_sampling()) {
            return true;
        }

        if ($this->stageidentifier == 'final_agreed_1') {
            return true;
        }

        assessment_set_membership::fill_pool_coursework($this->coursework->id);
        $record = assessment_set_membership::get_object(
            $this->coursework->id,
            'allocatableid-allocatabletype-stage_identifier',
            [$allocatable->id(), $allocatable->type(), $this->stageidentifier]);
        return !empty($record);
    }

    /**
     * @param allocatable $allocatable
     * @return bool
     */
    public function allocatable_is_not_in_sampling($allocatable) {
        return !$this->allocatable_is_in_sample($allocatable);
    }

    /**
     * @param allocatable $allocatable
     */
    public function add_allocatable_to_sampling($allocatable) {
        $moderationsetmembership = new assessment_set_membership();
        $moderationsetmembership->courseworkid = $this->coursework->id;
        $moderationsetmembership->allocatableid = $allocatable->id();
        $moderationsetmembership->allocatabletype = $allocatable->type();
        $moderationsetmembership->stage_identifier = $this->stageidentifier;
        $moderationsetmembership->save();
    }

    /**
     * @param allocatable $allocatable
     */
    public function remove_allocatable_from_sampling($allocatable) {
        global $DB;

        $params = [
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $allocatable->id(),
            'allocatabletype' => $allocatable->type(),
            'stage_identifier' => $this->stageidentifier,
        ];
        $DB->delete_records('coursework_sample_set_mbrs', $params);
    }

    /**
     * @param $assessor
     * @return bool
     */
    public function user_is_assessor($assessor) {
        if (!isset(self::$selfcache['user_is_assessor'][$this->coursework->id][$assessor->id])) {
            $enrolled = is_enrolled($this->coursework->get_course_context(), $assessor);
            $hasmoduleassessorcapability =
                ($enrolled && has_capability($this->assessor_capability(), $this->coursework->get_context(), $assessor))
                || is_primary_admin($assessor->id);
            self::$selfcache['user_is_assessor'][$this->coursework->id][$assessor->id] = $hasmoduleassessorcapability;
        }
        return self::$selfcache['user_is_assessor'][$this->coursework->id][$assessor->id];
    }

    /**
     * @param $moderator
     * @return bool
     */
    public function user_is_moderator($moderator) {
        $enrolled = is_enrolled($this->coursework->get_course_context(), $moderator, 'mod/coursework:moderate');
        return $enrolled || is_primary_admin($moderator->id);
    }

    /**
     * Check if a user has any allocation in this stage
     * @param allocatable $allocatable
     * @return bool
     */
    public function assessor_has_allocation($allocatable) {
        global $USER;
        allocation::fill_pool_coursework($this->coursework->id);
        $allocation = allocation::get_object(
            $this->coursework->id,
            'allocatableid-allocatabletype-stage_identifier',
            [$allocatable->id(), $allocatable->type(), $this->stageidentifier]
        );
        $result = ($allocation && $allocation->assessorid == $USER->id);
        return $result;
    }

    /**
     * @return bool
     */
    public function uses_sampling() {
        return $this->coursework->sampling_enabled();
    }

    /**
     * Tells us whether the allocation table needs to deal with this one.
     *
     * @return bool
     */
    public function uses_allocation() {
        return true;
    }

    /**
     * @param allocatable $allocatable
     * @return string
     */
    public function get_allocated_assessor_name($allocatable) {
        if ($this->has_allocation($allocatable)) {
            return $this->get_allocation($allocatable)->assessor_name();
        }
        return '';
    }

    /**
     * @param allocatable $allocatable
     * @return string
     */
    public function get_allocated_assessor($allocatable) {
        if ($this->has_allocation($allocatable)) {
            return $this->get_allocation($allocatable)->assessor();
        }
        return new null_user();
    }

    abstract public function allocation_table_header();

    /**
     * @param allocatable $allocatable
     * @return bool
     */
    public function prerequisite_stages_have_feedback($allocatable) {
        $allstages = $this->get_coursework()->marking_stages();

        // Some stages are parallel, so we ignore them being partially complete.
        $previousstageok = true;
        $currentstage = false;
        $currentstageok = true;
        $courseworkid = $this->get_coursework_id();
        submission::fill_pool_coursework($courseworkid);

        foreach ($allstages as $stage) {

            // if coursework has sampling enabled, each stage must be checked if it uses sampling
            if ($this->get_coursework()->sampling_enabled()) {

                $submission = submission::get_object($courseworkid, 'allocatableid-allocatabletype', [$allocatable->id(), $allocatable->type()]);

                if (count($submission->get_assessor_feedbacks()) >= $submission->max_number_of_feedbacks()
                    && $submission->sampled_feedback_exists()) {
                    break;
                }
            }

            if ($stage == $this) {
                break;
            }
            $class = get_class($stage);
            if ($class != $currentstage) { // New stage type
                $currentstage = $class;
                $previousstageok = $currentstageok;
                $currentstageok = $stage->has_feedback($allocatable) && !$stage->in_editable_period($allocatable);
            } else { // Same stage (parallel)
                $currentstageok = $currentstageok && $stage->has_feedback($allocatable) && !$stage->in_editable_period($allocatable);
            }
        }

        return $this->is_parallell() ? $previousstageok : $currentstageok;
    }

    /**
     * @return bool
     */
    protected function is_parallell() {
        return false;
    }

    /**
     * @return bool
     * @throws \coding_exception
     */
    public function auto_allocation_enabled() {
        return $this->strategy_name() !== 'none';
    }
    /**
     * @return bool
     * @throws \coding_exception
     */
    public function group_assessor_enabled() {
        return $this->strategy_name() == 'group_assessor';
    }

    /**
     * @param submission $submission
     * @return feedback|bool
     */
    public function get_feedback_for_submission($submission) {
        $stageidentifier = $this->identifier();
        $feedback = feedback::get_object($submission->courseworkid, 'submissionid-stage_identifier', [$submission->id, $stageidentifier]);
        return $feedback;
    }

    /**
     * @param $feedback
     * @return moderation|bool
     */
    public function get_moderation_for_feedback($feedback) {
        $moderationparams = [
            'feedbackid' => $feedback->id,
        ];
        return moderation::find($moderationparams);
    }

    /**
     * return bool
     */
    public function assessment_set_is_not_empty() {
        return assessment_set_membership::exists(['courseworkid' => $this->coursework->id]);
    }

    /**
     * @param user $assessor
     * @param submission $submission
     * @return bool
     */
    public function other_parallel_stage_has_feedback_from_this_assessor($assessor, $submission) {
        return false;
    }

    /**
     * @return string
     */
    public function type() {
        return substr($this->stageidentifier, 0, -2);
    }

    /**
     * @return bool
     */
    public function is_initial_assesor_stage() {
        return false;
    }

    /**
     * @param allocatable $allocatable
     * @return string
     * @throws \coding_exception
     */
    public function potential_marker_dropdown($allocatable) {

        // This gets called a lot on the allocations page, but does not change.

        if (!isset($this->assessor_dropdown_options)) {
            $this->assessor_dropdown_options = $this->potential_markers_as_options_array();
            $this->remove_currently_allocated_assessor_from_options_array($this->assessor_dropdown_options, $allocatable);
        }

        if (empty($this->assessor_dropdown_options)) {
            return '<br>' . get_string('nomarkers', 'mod_coursework');
        }

        $htmlattributes = [
            'id' => $this->assessor_dropdown_id($allocatable),
            'class' => 'assessor_id_dropdown',
        ];

        if ($this->identifier() != 'assessor_1' && !$this->currently_allocated_assessor($allocatable)
            && $this->coursework->sampling_enabled() && !$this->allocatable_is_in_sample($allocatable)
        ) {
            $htmlattributes['disabled'] = 'disabled';
        }
        $grader = substr($this->identifier(), 0, -2);

        if (!$this->has_allocation($allocatable)) {
            $identifier = 'choose' . $grader;
        } else {
            $identifier = 'change' . $grader;
        }

        $optionfornothingchosenyet = ['' => get_string($identifier, 'mod_coursework')];

        $dropdownname = $this->assessor_dropdown_name($allocatable);

        $selected = $this->selected_allocation_in_session($dropdownname);

        $assessordropdown = \html_writer::select($this->assessor_dropdown_options,
                                                 $dropdownname,
                                                 $selected,
                                                  $optionfornothingchosenyet,
                                                  $htmlattributes
        );

        return $assessordropdown;
    }

    /**
     * @param $allocatable
     * @return string
     */
    public function potential_moderator_dropdown($allocatable) {

        $optionfornothingchosenyet = ['' => 'Choose Moderator'];
        $htmlattributes = [
            'id' => $this->moderator_dropdown_id($allocatable),
            'class' => 'moderator_id_dropdown',
        ];

        $dropdownname = $this->assessor_dropdown_name($allocatable);

        $selected = $this->selected_allocation_in_session($dropdownname);

        return  $moderatordropdown = \html_writer::select($this->potential_moderators_as_options_array(),
            'allocatables[' . $allocatable->id . '][moderator][assessor_id]',
            $selected,
            $optionfornothingchosenyet,
            $htmlattributes);
    }

    /**
     * @return array
     */
    private function potential_markers_as_options_array() {
        $potentialmarkers = $this->get_teachers();
        $options = [];
        foreach ($potentialmarkers as $marker) {
            $options[$marker->id] = $marker->name();
        }

        return $options;
    }

    /**
     * @return array
     */
    private function potential_moderators_as_options_array() {
        $potentialmoderators = get_enrolled_users($this->coursework->get_course_context(), 'mod/coursework:moderate');
        $options = [];
        foreach ($potentialmoderators as $moderator) {
            $options[$moderator->id] = fullname($moderator);
        }
        return $options;
    }

    /**
     * @param array $options
     * @param allocatable $allocatable
     */
    private function remove_currently_allocated_assessor_from_options_array($options, $allocatable) {
        if ($this->has_allocation($allocatable)) {
            $assessor = $this->allocated_teacher_for($allocatable);
            unset($options[$assessor->id()]);
        }
    }

    /**
     * @param allocatable $allocatable
     * @return string user_2_assessor_1
     */
    private function assessor_dropdown_id($allocatable) {
        return $allocatable->type() . '_' . $allocatable->id() . '_' . $this->identifier();
    }

    /**
     * @param allocatable $allocatable
     * @return string user_2_assessor_1
     */
    private function moderator_dropdown_id($allocatable) {
        return $allocatable->type() . '_' . $allocatable->id() . '_moderator';
    }

    /**
     * @param allocatable $allocatable
     * @return string
     */
    private function assessor_dropdown_name($allocatable) {
        $inputname =
            'allocatables[' . $allocatable->id . '][' . $this->identifier() . '][assessor_id]';
        return $inputname;
    }

    /**
     * @param allocatable $allocatable
     * @return bool|user
     */
    private function currently_allocated_assessor($allocatable) {
        if ($this->has_allocation($allocatable)) {
            return $this->get_allocation($allocatable)->assessor();
        }
        return false;
    }

    /**
     * @param $allocatable
     * @return bool
     * @throws \dml_exception
     */
    private function in_editable_period($allocatable) {

        $result = $this->get_coursework()->get_grade_editing_time();

        // The feedback is not in the editable period if the editable setting is disabled
        if (empty($this->get_coursework()->get_grade_editing_time())) {
            return false;
        }

        $courseworkid = $this->get_coursework_id();
        submission::fill_pool_coursework($courseworkid);
        $submission = submission::get_object($courseworkid, 'allocatableid-allocatabletype', [$allocatable->id(), $allocatable->type()]);

        $feedback = $this->get_feedback_for_submission($submission);
        if ($feedback) {
            $result += $feedback->timecreated;
        }

        return $result > time();

    }

    private function selected_allocation_in_session($dropdownname) {
        global  $SESSION;

        $cm = $this->coursework->get_course_module();

        if (!empty($SESSION->coursework_allocationsessions[$cm->id])) {

            if (!empty($SESSION->coursework_allocationsessions[$cm->id][$dropdownname])) {
                return  $SESSION->coursework_allocationsessions[$cm->id][$dropdownname];
            }

        }

        return  '';

    }

    public function get_assessor_from_moodle_course_group($allocatable) {

        $assessor = '';
        // get allocatables group
        if ($this->coursework->is_configured_to_have_group_submissions()) {
            $groupid = $allocatable->id;
        } else {
            $user = user::get_object($allocatable->id);
            $group = $this->coursework->get_student_group($user);
            $groupid = ($group) ? $group->id : 0;
        }

        if ($groupid) {
            // find 1st assessor in the group
            $modcontext = $this->coursework->get_context();
            $users = get_enrolled_users($modcontext, '', $groupid, 'u.*', 'id ASC');

            foreach ($users as $user) {
                if (has_capability($this->assessor_capability(), $modcontext, $user)) {
                    $assessor = array_column($user, 'id');
                    if ($assessor) {
                        $assessorid = $assessor[0];
                        $assessor = user::get_object($assessorid);
                        break;
                    }
                }
            }
        }

        return $assessor;
    }
}
