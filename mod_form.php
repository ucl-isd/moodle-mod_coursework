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
 * The main coursework module configuration form. Presented to the user when they make a new
 * instance of this module
 *
 * @package    mod_coursework
 * @copyright  2011 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\candidateprovider_manager;
use mod_coursework\models\coursework;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/coursework/lib.php');

/**
 * Mod form that allows a new coursework to ber created, or for the settings of an existing one to be altered.
 */
class mod_coursework_mod_form extends moodleform_mod {

    private function form() {
        return $this->_form;
    }

    /**
     */
    public function definition() {
        global $PAGE;

        // Don't include jQuery when not on this module's settings page, for
        // example, if on the default activity completion page.  See MDL-78528.
        if ($PAGE->pagetype === 'mod-coursework-mod') {
            $PAGE->requires->jquery();

            $module = [
                'name' => 'mod_coursework',
                'fullpath' => '/mod/coursework/mod_form.js',
                'requires' => [
                    'node',
                    'ajax',
                ]];

            $PAGE->requires->js_init_call('M.mod_coursework.init', [], true, $module);
        }

        $this->set_form_attributes();

        $this->add_general_header();

        $this->add_name_field();
        $this->standard_intro_elements(get_string('description', 'coursework'));

        $this->add_availability_header();

        $this->add_start_date_field();
        $this->add_submission_deadline_field();
        $this->add_personaldeadline_field();

        $this->add_marking_deadline_field();
        $this->add_initial_marking_deadline_field();
        $this->add_agreed_grade_marking_deadline_field();
        $this->add_relative_initial_marking_deadline_field();
        $this->add_relative_agreed_grade_marking_deadline_field();

        $this->add_allow_early_finalisation_field();
        $this->add_allow_late_submissions_field();

        if (coursework_is_ulcc_digest_coursework_plugin_installed()) {
            $this->add_digest_header();
            $this->add_marking_reminder_warning();
            $this->add_marking_reminder_field();

        }

        $this->add_submissions_header();

        $this->add_turnitin_files_settings_waring();
        $this->add_file_types_field();
        $this->add_max_file_size_field();
        $this->add_number_of_files_field();
        $this->add_rename_file_field();
        $this->add_submission_notification_field();
        $this->add_enable_plagiarism_flag_field();

        $this->add_marking_workflow_header();

        $this->add_number_of_initial_assessors_field();
        $this->add_enable_moderation_agreement_field();

        $this->add_enable_allocation_field();
        $this->add_assessor_allocation_strategy_field();
        $this->add_enable_sampling_checkbox();
        $this->add_automatic_agreement_enabled();
        $this->add_view_initial_assessors_grade();
        $this->add_auto_populate_agreed_feedback_comments();

        $this->add_blind_marking_header();

        $this->add_enable_blind_marking_field();

        $this->add_assessor_anonymity_header();

        $this->add_enable_assessor_anonymity_field();

        $this->add_feedback_header();

        $this->add_general_feedback_release_date_field();
        $this->add_individual_feedback_release_date_field();
        $this->add_email_individual_feedback_notification_field();
        $this->add_all_feedbacks_field();

        $this->add_extensions_header();

        $this->add_enable_extensions_field();

        $this->add_group_submission_header();

        $this->add_usegroups_field();
        $this->add_grouping_field();

        $this->standard_grading_coursemodule_elements();
        $this->add_tweaks_to_standard_grading_form_elements();

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();

    }

    /**
     * Adds all default data to the form elements.
     *
     * @param $defaultvalues
     * @return void
     */
    public function set_data($defaultvalues) {
        $defaultvalues = (array)$defaultvalues;

        foreach ($this->_form->_elements as $element) {

            // Some form elements are replaced with static HTML e.g. if there is a submission now
            // And they should not be editable.
            //
            // For any elements that won't have any default (static thingys we are using for
            // non-editing display), add the data from its corresponding real element, which is
            // now hidden. This is the only way to get around the issue of Moodle requiring all
            // the form's required data to be resubmitted if you want to edit any part of it.
            // Using a rule to disable an element means it doesn't get submitted, which breaks
            // stuff. Just having a static element pre-fills with defaults, but won't get
            // resubmitted, so we have to use a hidden value, then another static one with
            // 'html' suffixed (arbitrarily) which we add the same default data to here.
            if (isset($element->_attributes['name']) && substr($element->_attributes['name'], -6) == 'static') {
                // TODO this is using private attributes directly. Need to switch to proper
                // Getters and setters.
                if (isset($defaultvalues[substr($element->_attributes['name'], 0, -6)])) {
                    $default = $defaultvalues[substr($element->_attributes['name'], 0, -6)];
                    $defaultvalues[$element->_attributes['name']] = $default;
                }
            }
        }
        parent::set_data($defaultvalues);
    }

    /**
     * We can't do this with $mform->addRule() because the compare function works with the raw form values, which is
     * an array of date components. Here, Moodle's internals have processed those values into a Unix timestamp, so the
     * comparison works.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {

        $errors = [];

        if ($data['startdate'] != 0 && !empty($data['deadline']) && $data['startdate'] > $data['deadline']) {
            $errors['startdate'] = get_string('must_be_before_dealdine', 'mod_coursework');
        }

        if ($data['individualfeedback'] != 0 && !empty($data['deadline']) && $data['individualfeedback'] < $data['deadline']) {
            $errors['individualfeedback'] = get_string('must_be_after_dealdine', 'mod_coursework');
        }

        if ($data['generalfeedback'] != 0 && !empty($data['deadline']) && $data['generalfeedback'] < $data['deadline']) {
            $errors['generalfeedback'] = get_string('must_be_after_dealdine', 'mod_coursework');
        }

        if (isset($data['initialmarkingdeadline']) && $data['initialmarkingdeadline'] != 0 && !empty($data['deadline']) && $data['initialmarkingdeadline'] < $data['deadline']) {
            $errors['initialmarkingdeadline'] = get_string('must_be_after_dealdine', 'mod_coursework');
        }

        if (isset($data['agreedgrademarkingdeadline']) && $data['agreedgrademarkingdeadline'] != 0 && !empty($data['deadline']) && $data['agreedgrademarkingdeadline'] < $data['deadline']) {
            $errors['agreedgrademarkingdeadline'] = get_string('must_be_after_dealdine', 'mod_coursework');
        }

        if (isset($data['agreedgrademarkingdeadline']) && $data['agreedgrademarkingdeadline'] != 0 &&  $data['agreedgrademarkingdeadline'] < $data['initialmarkingdeadline'] ) {
            $errors['agreedgrademarkingdeadline'] = get_string('must_be_after_initial_grade_dealdine', 'mod_coursework');
        }

        if (isset($data['initialmarkingdeadline']) && $data['initialmarkingdeadline'] != 0 && !empty($data['deadline']) && $data['deadline'] && $data['initialmarkingdeadline'] < $data['deadline']) {
            $errors['initialmarkingdeadline'] = get_string('must_be_after_dealdine', 'mod_coursework');
        }

        if (isset($data['agreedgrademarkingdeadline']) && $data['agreedgrademarkingdeadline'] != 0 && !empty($data['deadline']) && $data['agreedgrademarkingdeadline'] < $data['deadline']) {
            $errors['agreedgrademarkingdeadline'] = get_string('must_be_after_dealdine', 'mod_coursework');
        }

        if (isset($data['agreedgrademarkingdeadline']) && $data['agreedgrademarkingdeadline'] != 0 &&  $data['agreedgrademarkingdeadline'] < $data['initialmarkingdeadline'] ) {
            $errors['agreedgrademarkingdeadline'] = get_string('must_be_after_initial_grade_dealdine', 'mod_coursework');
        }

        if (isset($data['relativeagreedmarkingdeadline']) && $data['relativeagreedmarkingdeadline'] != 0 && $data['relativeagreedmarkingdeadline'] < $data['relativeinitialmarkingdeadline'] ) {
            $errors['relativeagreedmarkingdeadline'] = get_string('must_be_after_or_equal_to_relative_initial_grade_dealdine', 'mod_coursework');

        }

        $courseworkid = $this->get_courseworkid();
        if ($courseworkid) {
            $coursework = mod_coursework\models\coursework::find($courseworkid);
            if ($coursework->has_samples() && isset($data['samplingenabled']) && $data['samplingenabled'] == 0) {
                $errors['samplingenabled'] = get_string('sampling_cant_be_disabled', 'mod_coursework');
            }
        }

        if ( isset($data['numberofmarkers']) && $data['numberofmarkers'] == 1 && isset($data['samplingenabled']) && $data['samplingenabled'] == 1) {
            $errors['numberofmarkers'] = get_string('not_enough_assessors_for_sampling', 'mod_coursework');
        }

        // Validate candidate number setting changes.
        if (!empty($this->_customdata['courseworkid'])) {
            $coursework = coursework::find($this->_customdata['courseworkid']);

            if ($coursework && !$coursework->can_change_candidate_number_setting()) {
                $currentvalue = $coursework->usecandidate ?? 0;

                // Check if trying to change the setting.
                if ((!empty($data['usecandidate']) && !$currentvalue) ||
                    (empty($data['usecandidate']) && $currentvalue)) {
                    $errors['usecandidate'] = get_string('cannot_change_candidate', 'mod_coursework');
                }
            }
        }

        $parenterrors = parent::validation($data, $files);
        return array_merge($errors, $parenterrors);

    }

    /**
     * Get data from the form and manipulate it
     * @return bool|object
     */
    public function get_data() {
        global $CFG;
        $data = parent::get_data();

        if (!$data) {
            return false;
        }

        if ($this->forceblindmarking() == 1) {
            $data->blindmarking = $CFG->coursework_blindmarking;
        }

        if ($data->numberofmarkers > 1) {
            $data->moderationagreementenabled = 0;
        }

        return $data;
    }

    /**
     * @throws coding_exception
     */
    protected function add_submissions_header() {
        $moodleform =& $this->_form;

        $moodleform->addElement('header', 'submissions', get_string('submissions', 'mod_coursework'));
        // We want it expanded by default
        $moodleform->setExpanded('submissions');
    }

    /**
     * @throws coding_exception
     */
    protected function add_availability_header() {
        $moodleform =& $this->_form;

        $moodleform->addElement('header', 'availability', get_string('availability', 'mod_coursework'));
        // We want it expanded by default
        $moodleform->setExpanded('availability');
    }

    /**
     * @throws coding_exception
     */
    protected function add_name_field() {
        $moodleform =& $this->_form;

        $moodleform->addElement('text',
                                 'name',
                                 get_string('courseworkname', 'coursework'),
                                 ['size' => '64']);
        $moodleform->addRule('name', null, 'required', null, 'client');
        $moodleform->addRule('name',
                              get_string('maximumchars', '', 255),
                              'maxlength',
                              255,
                              'client');
        $moodleform->setType('name', PARAM_TEXT);
    }

    /**
     * @throws coding_exception
     */
    protected function add_submission_deadline_field() {
        global $CFG;

        $moodleform =& $this->_form;

        $defaulttimestamp = strtotime('+2 weeks');
        $disabled = true;

        if (!empty($CFG->coursework_submission_deadline)) {
            $disabled = false;

            $defaulttimestamp = strtotime('today');
            if ($CFG->coursework_submission_deadline == 7 ) {
                $defaulttimestamp = strtotime('+1 weeks');
            } else if ($CFG->coursework_submission_deadline == 14 ) {
                $defaulttimestamp = strtotime('+2 weeks');
            } else if ($CFG->coursework_submission_deadline == 31 ) {
                $defaulttimestamp = strtotime('+1 month');
            }
        }

        $optional = true;
        $courseworkid = $this->get_courseworkid();
        if ($courseworkid) {
            $coursework = mod_coursework\models\coursework::find($courseworkid);
            if ($coursework->extension_exists()) {
                $optional = false;
            }
        }

        $moodleform->addElement('date_time_selector',
                                 'deadline',
                                 get_string('deadline', 'coursework'),
                                 ['optional' => $optional, 'disabled' => $disabled]);

         $moodleform->addElement('html', '<div class ="submission_deadline_info alert">');
         $moodleform->addElement('html', get_string('submissionsdeadlineinfo', 'mod_coursework'));
         $moodleform->addElement('html', '</div>');

        if (!empty($CFG->coursework_submission_deadline)) {
            $moodleform->setDefault('deadline', $defaulttimestamp);
        }
        $moodleform->addHelpButton('deadline', 'deadline', 'mod_coursework');
    }

    /**
     * @throws coding_exception
     */
    protected function add_personaldeadline_field() {

        $moodleform =& $this->_form;
        $options = [0 => get_string('no'), 1 => get_string('yes')];

        $courseworkid = $this->get_courseworkid();
        $disabled = [];
        if (coursework_personaldeadline_passed($courseworkid)) {
            $moodleform->hideif('personaldeadlineenabled', 'deadline[enabled]', 'notchecked');
            $disabled = ['disabled' => true];
        }
        $moodleform->addElement('select',
                                 'personaldeadlineenabled',
                                  get_string('usepersonaldeadline', 'mod_coursework'), $options, $disabled);
        $moodleform->setType('personaldeadlineenabled', PARAM_INT);
        $moodleform->addHelpButton('personaldeadlineenabled', 'usepersonaldeadline', 'mod_coursework');

        $moodleform->setDefault('personaldeadlineenabled', 0);
    }

    /**
     * @throws coding_exception
     */
    protected function add_start_date_field() {
        global $CFG;

        $moodleform =& $this->_form;

        $defaulttimestamp = strtotime('+2 weeks');
        $disabled = true;

        if (!empty($CFG->coursework_start_date)) {
            $disabled = false;
            $defaulttimestamp = strtotime('today');
        }

        $moodleform->addElement('date_time_selector',
                                 'startdate',
                                 get_string('startdate', 'coursework'),
                                 ['optional' => true, 'disabled' => $disabled]
        );

        if (!empty($CFG->coursework_start_date)) {
            $moodleform->setDefault('startdate', $defaulttimestamp);
        }
        $moodleform->addHelpButton('startdate', 'startdate', 'mod_coursework');
    }

    private function add_marking_deadline_field() {
        global $CFG;
        $moodleform =& $this->_form;
        $options = [0 => get_string('no'), 1 => get_string('yes')];
        $moodleform->addElement('select',
            'markingdeadlineenabled',
            get_string('usemarkingdeadline', 'mod_coursework'), $options);
        $moodleform->setType('markingdeadlineenabled', PARAM_INT);

        $settingdefault = (empty($CFG->coursework_marking_deadline) && empty($CFG->coursework_agreed_marking_deadline)) ? 0 : 1;
        $moodleform->setDefault('markingdeadlineenabled', $settingdefault);
    }

    /**
     * @throws coding_exception
     */
    protected function add_initial_marking_deadline_field() {
        global $CFG;

        $moodleform =& $this->_form;

        $defaulttimestamp = strtotime('today');
        $disabled = true;

        $submissiondeadlinetimestamp = strtotime('today');

        if (!empty($CFG->coursework_submission_deadline)) {
            if ($CFG->coursework_submission_deadline == 7 ) {
                $submissiondeadlinetimestamp = strtotime('+1 weeks');
            } else if ($CFG->coursework_submission_deadline == 14 ) {
                $submissiondeadlinetimestamp = strtotime('+2 weeks');
            } else if ($CFG->coursework_submission_deadline == 31 ) {
                $submissiondeadlinetimestamp = strtotime('+1 month');
            }
        }

        if (!empty($CFG->coursework_marking_deadline)) {

            $disabled = false;

            if ($CFG->coursework_marking_deadline == 7 ) {
                $defaulttimestamp = strtotime('+1 weeks', $submissiondeadlinetimestamp);
            } else if ($CFG->coursework_marking_deadline == 14 ) {
                $defaulttimestamp = strtotime('+2 weeks', $submissiondeadlinetimestamp);
            } else if ($CFG->coursework_marking_deadline == 21 ) {
                $defaulttimestamp = strtotime('+3 weeks', $submissiondeadlinetimestamp);
            } else if ($CFG->coursework_marking_deadline == 28 ) {
                $defaulttimestamp = strtotime('+4 weeks', $submissiondeadlinetimestamp);
            } else if ($CFG->coursework_marking_deadline == 35 ) {
                $defaulttimestamp = strtotime('+5 weeks', $submissiondeadlinetimestamp);
            } else if ($CFG->coursework_marking_deadline == 42 ) {
                $defaulttimestamp = strtotime('+6 weeks', $submissiondeadlinetimestamp);
            }
        }

        $moodleform->addElement('date_time_selector',
            'initialmarkingdeadline',
            get_string('initialmarkingdeadline', 'coursework'),
            ['optional' => true, 'disabled' => $disabled]
        );

        if (!empty($CFG->coursework_marking_deadline)) {
            $moodleform->setDefault('initialmarkingdeadline', $defaulttimestamp);
        }

        $moodleform->addHelpButton('initialmarkingdeadline', 'initialmarkingdeadline', 'mod_coursework');
    }

    /**
     * @throws coding_exception
     */
    protected function add_agreed_grade_marking_deadline_field() {
        global $CFG;

        $moodleform =& $this->_form;

        $defaulttimestamp = strtotime('today');
        $disabled = true;

        $submissiondeadlinetimestamp = strtotime('today');

        if (!empty($CFG->coursework_submission_deadline)) {
            if ($CFG->coursework_submission_deadline == 7 ) {
                $submissiondeadlinetimestamp = strtotime('+1 weeks');
            } else if ($CFG->coursework_submission_deadline == 14 ) {
                $submissiondeadlinetimestamp = strtotime('+2 weeks');
            } else if ($CFG->coursework_submission_deadline == 31 ) {
                $submissiondeadlinetimestamp = strtotime('+1 month');
            }
        }

        if (!empty($CFG->coursework_agreed_marking_deadline)) {
            $disabled = false;
            if ($CFG->coursework_agreed_marking_deadline == 7 ) {
                $defaulttimestamp = strtotime('+1 weeks', $submissiondeadlinetimestamp);
            } else if ($CFG->coursework_agreed_marking_deadline == 14 ) {
                $defaulttimestamp = strtotime('+2 weeks', $submissiondeadlinetimestamp);
            } else if ($CFG->coursework_agreed_marking_deadline == 21 ) {
                $defaulttimestamp = strtotime('+3 weeks', $submissiondeadlinetimestamp);
            } else if ($CFG->coursework_agreed_marking_deadline == 28 ) {
                $defaulttimestamp = strtotime('+4 weeks', $submissiondeadlinetimestamp);
            } else if ($CFG->coursework_agreed_marking_deadline == 35 ) {
                $defaulttimestamp = strtotime('+5 weeks', $submissiondeadlinetimestamp);
            } else if ($CFG->coursework_agreed_marking_deadline == 42 ) {
                $defaulttimestamp = strtotime('+6 weeks', $submissiondeadlinetimestamp);
            }
        }

        $moodleform->addElement('date_time_selector',
            'agreedgrademarkingdeadline',
            get_string('agreedgrademarkingdeadline', 'coursework'),
            ['optional' => true, 'disabled' => $disabled]
        );

        if (!empty($CFG->coursework_agreed_marking_deadline)) {
            $moodleform->setDefault('agreedgrademarkingdeadline', $defaulttimestamp);
        }
        $moodleform->addHelpButton('agreedgrademarkingdeadline', 'agreedgrademarkingdeadline', 'mod_coursework');
    }

    /********
     *  Adds the relative initial marking deadline fields to the settings
     */
    protected function add_relative_initial_marking_deadline_field() {
        global $CFG;

        $moodleform =&  $this->_form;

        $options = ['0' => get_string('disabled', 'mod_coursework')];
        $options['7'] = get_string('oneweekoption', 'mod_coursework');
        $options['14'] = get_string('twoweeksoption', 'mod_coursework');
        $options['21'] = get_string('threeweeksoption', 'mod_coursework');
        $options['28'] = get_string('fourweeksoption', 'mod_coursework');
        $options['35'] = get_string('fiveweeksoption', 'mod_coursework');
        $options['42'] = get_string('sixweeksoption', 'mod_coursework');

        $moodleform->addElement('select',
            'relativeinitialmarkingdeadline',
            get_string('relativeinitialmarkingdeadline', 'mod_coursework'), $options);

        if (!empty($CFG->coursework_marking_deadline)) {
            $moodleform->setDefault('relativeinitialmarkingdeadline', $CFG->coursework_marking_deadline);
        }
        $moodleform->addHelpButton('relativeinitialmarkingdeadline', 'relativeinitialmarkingdeadline', 'mod_coursework');

    }

    /********
     *  Adds the relative agreed grade marking deadline fields to the settings
     */
    protected function add_relative_agreed_grade_marking_deadline_field() {
        global $CFG;

        $moodleform =&  $this->_form;

        $options = ['0' => get_string('disabled', 'mod_coursework')];
        $options['7'] = get_string('oneweekoption', 'mod_coursework');
        $options['14'] = get_string('twoweeksoption', 'mod_coursework');
        $options['21'] = get_string('threeweeksoption', 'mod_coursework');
        $options['28'] = get_string('fourweeksoption', 'mod_coursework');
        $options['35'] = get_string('fiveweeksoption', 'mod_coursework');
        $options['42'] = get_string('sixweeksoption', 'mod_coursework');

        $moodleform->addElement('select',
            'relativeagreedmarkingdeadline',
            get_string('relativeagreedmarkingdeadline', 'mod_coursework'), $options);

        if (!empty($CFG->coursework_agreed_marking_deadline)) {
            $moodleform->setDefault('relativeagreedmarkingdeadline', $CFG->coursework_agreed_marking_deadline);
        }
        $moodleform->addHelpButton('relativeagreedmarkingdeadline', 'relativeagreedmarkingdeadline', 'mod_coursework');

    }

    /**
     * @throws coding_exception
     */
    protected function add_digest_header() {
        $moodleform =& $this->_form;

        $moodleform->addElement('header', 'digest', get_string('digest', 'mod_coursework'));
        // We want it expanded by default
        $moodleform->setExpanded('digest');
    }

    private function add_marking_reminder_field() {
        global $CFG;
        $moodleform =& $this->_form;
        $options = [0 => get_string('no'), 1 => get_string('yes')];
        $moodleform->addElement('select',
            'markingreminderenabled',
            get_string('sendmarkingreminder', 'mod_coursework'), $options);
        $moodleform->setType('markingreminderenabled', PARAM_INT);

        $settingdefault = (empty($CFG->coursework_marking_deadline)) ? 0 : 1;
        $moodleform->setDefault('markingreminderenabled', $settingdefault);

    }

    protected function add_marking_reminder_warning() {
        $this->_form->addElement('html', '<div class ="notification_tii">');
        $this->_form->addElement('html',
            get_string('relativedeadlinesreminder', 'mod_coursework'));
        $this->_form->addElement('html', '</div>');
    }

    /**
     * @throws coding_exception
     */
    protected function add_allow_early_finalisation_field() {
        $moodleform =& $this->_form;
        $options = [ 0 => get_string('no'), 1 => get_string('yes')];
        $moodleform->addElement('select',
                                 'allowearlyfinalisation',
                                 get_string('allowearlyfinalisation', 'mod_coursework'), $options);
        $moodleform->setType('allowearlyfinalisation', PARAM_INT);
        $moodleform->hideif('allowearlyfinalisation', 'deadline[enabled]', 'notchecked');
    }

    /**
     * @throws coding_exception
     */
    protected function add_group_submission_header() {

        $moodleform =& $this->_form;

        $moodleform->addElement('header', 'group_submission', get_string('groupsubmissionsettings', 'mod_coursework'));
        // We want it expanded by default
        $moodleform->setExpanded('group_submission');

    }

    /**
     * @throws coding_exception
     */
    protected function add_usegroups_field() {
        $moodleform =& $this->_form;

        $options = [ 0 => get_string('no'), 1 => get_string('yes')];
        $moodleform->addElement('select', 'usegroups', get_string('usegroups', 'mod_coursework'), $options);
        $moodleform->addHelpButton('usegroups', 'usegroups', 'mod_coursework');
    }

    /**
     * @throws coding_exception
     */
    protected function add_grouping_field() {
        global $COURSE, $DB;

        $moodleform =& $this->_form;

        $groupsoptionsresult = $DB->get_records('groupings', ['courseid' => $COURSE->id], 'name', 'id, name');
        $groupsoptions = [];
        if ($groupsoptionsresult !== false) {
            foreach ($groupsoptionsresult as $result) {
                $groupsoptions[$result->id] = $result->name;
            }
        }

        // Not calling it groupingid as this conflicts with the groupingid field in the common module
        // settings.
        $defaultgroupsoptions = [0 => 'Use all groups'];
        $groupsoptions = $defaultgroupsoptions + $groupsoptions;
        $moodleform->addElement('select',
                                 'grouping_id',
                                 get_string('grouping_id', 'mod_coursework'),
                                 $groupsoptions);
        $moodleform->addHelpButton('grouping_id', 'grouping_id', 'mod_coursework');
        $moodleform->hideif('grouping_id', 'usegroups', 'eq', 0);
    }

    /**
     * @throws coding_exception
     */
    protected function add_general_header() {
        $moodleform =& $this->_form;

        $moodleform->addElement('header', 'generalstuff', get_string('general', 'form'));
    }

    /**
     * @throws coding_exception
     */
    protected function add_file_types_field() {
        $moodleform =& $this->_form;

        $moodleform->addElement('text',
                                 'filetypes',
                                 get_string('filetypes', 'coursework'),
                                 ['placeholder' => 'e.g. doc, docx, txt, rtf']);
        $moodleform->addHelpButton('filetypes', 'filetypes', 'mod_coursework');
        $moodleform->setType('filetypes', PARAM_TEXT);
        $moodleform->hideif('filetypes', 'use_turnitin', 'eq', '1');
    }

    /**
     * @throws coding_exception
     */
    protected function add_max_file_size_field() {
        global $CFG, $COURSE;

        $moodleform =& $this->_form;

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes);
        $choices[0] = get_string('maximumupload'). ' set in course';
        $moodleform->addElement('select',
                                 'maxbytes',
                                 get_string('maximumsize', 'coursework'),
                                 $choices);
        $moodleform->setDefault('maxbytes', $CFG->coursework_maxbytes);
        $moodleform->addHelpButton('maxbytes', 'maximumsize', 'mod_coursework');
        $moodleform->hideif('maxbytes', 'use_turnitin', 'eq', '1');
    }

    /**
     * @throws coding_exception
     */
    protected function add_number_of_files_field() {

        $moodleform =& $this->_form;

        // Maximum number of files:
        $choices = [1 => 1,
                         2 => 2,
                         3 => 3,
                         4 => 4,
                         5 => 5,
                         6 => 6,
                         7 => 7,
                         8 => 8,
                         9 => 9,
                         10 => 10];
        $moodleform->addElement('select',
                                 'maxfiles',
                                 get_string('maxfiles', 'coursework'),
                                 $choices);
        $moodleform->setDefault('maxfiles', 1);
        $moodleform->setType('maxfiles', PARAM_INT);
        $moodleform->addHelpButton('maxfiles', 'maxfiles', 'mod_coursework');

    }

    protected function add_rename_file_field() {

        global  $DB, $PAGE;

        $moodleform =& $this->_form;

        $choices = ['0' => get_string('no'), '1' => get_string('yes')];
        $courseworkid = $this->get_courseworkid();
        $courseworkhassubmissions = !empty($courseworkid)
            && $DB->record_exists('coursework_submissions', ['courseworkid' => $courseworkid]);

        if (empty($courseworkid) || empty($courseworkhassubmissions)) {

            $moodleform->addElement('select', 'renamefiles',
                get_string('renamefiles', 'mod_coursework'), $choices);

            $moodleform->addHelpButton('renamefiles', 'renamefiles', 'mod_coursework');

            $moodleform->hideif('renamefiles', 'blindmarking', 'eq', '1');

            $PAGE->requires->js_amd_inline("
            require(['jquery'], function() {
                       $('#id_blindmarking').change(function() {
                            console.log($(this).val());

                            if ($(this).val()== 1) {
                                $('#id_renamefiles').val(1);
                            }

                        });
                });
            ");

        } else {

            $sql = "SELECT     *
                         FROM       {coursework}
                         WHERE      id = :courseworkid
                         AND        renamefiles = 1";

            $settingvalue = ($DB->get_records_sql($sql, ['courseworkid' => $courseworkid])) ? get_string('yesrenamefile', 'mod_coursework') : get_string('norenamefile', 'mod_coursework');

            $moodleform->addElement('static', 'renamefilesdescription', get_string('renamefiles', 'mod_coursework'),
                $settingvalue);
        }
    }

    /**
     * @throws coding_exception
     */
    protected function add_marking_workflow_header() {
        $moodleform =& $this->_form;

        $moodleform->addElement('header', 'markingworkflow', get_string('markingworkflow', 'mod_coursework'));
    }

    /**
     * @throws coding_exception
     */
    protected function add_number_of_initial_assessors_field() {

        $moodleform =& $this->_form;
        $courseworkid = $this->get_courseworkid();

        $multioptions = [
            // Don't want to give the option for 0!
            1 => 1,
            2 => 2,
            3 => 3,
        ];
        // Remove all options lower than the current maximum number of feedbacks that any student has.
        $currentmaxfeedbacks = coursework_get_current_max_feedbacks($courseworkid);
        if ($currentmaxfeedbacks) {
            foreach ($multioptions as $key => $option) {
                if ($key < $currentmaxfeedbacks) {
                    unset($multioptions[$key]);
                }
            }
        }
        $moodleform->addElement('select',
                                 'numberofmarkers',
                                 get_string('numberofmarkers', 'mod_coursework'),
                                 $multioptions);
        $moodleform->addHelpButton('numberofmarkers', 'numberofmarkers', 'mod_coursework');
        $moodleform->setDefault('numberofmarkers', 1);
    }

    /**
     * @throws coding_exception
     */
    protected function add_enable_moderation_agreement_field() {
        $moodleform =& $this->_form;

        $options = [0 => get_string('no'), 1 => get_string('yes')];
        $moodleform->addElement('select', 'moderationagreementenabled', get_string('moderationagreementenabled', 'mod_coursework'), $options);
        $moodleform->addHelpButton('moderationagreementenabled', 'moderationagreementenabled', 'mod_coursework');
        $moodleform->setDefault('moderationagreementenabled', 0);
        $moodleform->hideif('moderationagreementenabled', 'numberofmarkers', 'neq', 1);
    }

    /**
     * @return int
     * @throws coding_exception
     */
    protected function get_courseworkid() {
        $upcmid = optional_param('update', -1, PARAM_INT);
        $cm = get_coursemodule_from_id('coursework', $upcmid);
        $courseworkid = 0;
        if ($cm) {
            $courseworkid = $cm->instance;
            return $courseworkid;
        }
        return $courseworkid;
    }

    /**
     * @throws coding_exception
     */
    protected function add_enable_allocation_field() {
        $moodleform =& $this->_form;

        $options = [ 0 => get_string('no'), 1 => get_string('yes')];
        $moodleform->addElement('select', 'allocationenabled', get_string('allocationenabled', 'mod_coursework'), $options);
        $moodleform->addHelpButton('allocationenabled', 'allocationenabled', 'mod_coursework');
    }

    /**
     * @throws coding_exception
     */
    protected function add_assessor_allocation_strategy_field_rdb() {
        $moodleform =& $this->_form;

        $options = mod_coursework\allocation\manager::get_allocation_classnames();

        $radioarray = [];
        $keys = array_keys($options);

        foreach ($keys as $key) {
            $radioarray[] =& $moodleform->createElement('radio', 'assessorallocationstrategy', '', $options[$key], $key, '');
        }
        $moodleform->addGroup($radioarray, 'radioarray',  get_string('assessorallocationstrategy', 'mod_coursework'), [' '], false);
        $moodleform->addHelpButton('radioarray', 'assessorallocationstrategy', 'mod_coursework');
        $moodleform->hideif('radioarray', 'allocationenabled', 'eq', 0);
    }

    protected function add_assessor_allocation_strategy_field() {
        $moodleform =& $this->_form;

        $options = mod_coursework\allocation\manager::get_allocation_classnames();

        $moodleform->addElement('select', 'assessorallocationstrategy', get_string('assessorallocationstrategy', 'mod_coursework'), $options);
        $moodleform->addHelpButton('assessorallocationstrategy', 'assessorallocationstrategy', 'mod_coursework');
        $moodleform->hideif('assessorallocationstrategy', 'allocationenabled', 'eq', 0);
    }

    /**
     * @throws coding_exception
     */
    protected function add_blind_marking_header() {

        $moodleform =& $this->_form;

        $moodleform->addElement('header', 'anonymity', get_string('blindmarking', 'mod_coursework'));
        $moodleform->addElement('html', '<div class ="blind_marking_info">');
        $moodleform->addElement('html',
            get_string('anonymitydescription', 'mod_coursework'));
        $moodleform->addElement('html', '</div>');

    }

    /**
     * @throws coding_exception
     */
    protected function add_assessor_anonymity_header() {
        $moodleform =& $this->_form;

        $moodleform->addElement('header', 'assessoranonymityheader', get_string('assessoranonymity', 'mod_coursework'));
        $moodleform->addElement('html', '<div class ="assessor_anonymity_info">');
        $moodleform->addElement('html',
            get_string('assessoranonymity_desc', 'mod_coursework'));
        $moodleform->addElement('html', '</div>');
    }

    /**
     * @throws coding_exception
     */
    protected function add_enable_blind_marking_field() {
        global $CFG;

        $moodleform =& $this->_form;

        $options = [ 0 => get_string('no'), 1 => get_string('yes')];
        $moodleform->addElement('select', 'blindmarking', get_string('blindmarking', 'mod_coursework'), $options);
        $moodleform->addHelpButton('blindmarking', 'blindmarking', 'mod_coursework');
        $moodleform->setDefault('blindmarking', $CFG->coursework_blindmarking);

        $submissionexists = 0;
        // disable the setting if at least one submission exists
        $courseworkid = $this->get_courseworkid();
        if ($courseworkid && mod_coursework\models\coursework::find($courseworkid)->has_any_submission()) {
            $submissionexists = 1;
        }

        $moodleform->addElement('hidden', 'submission_exists', $submissionexists);
        $moodleform->setType('submission_exists', PARAM_INT);
        $moodleform->hideif('blindmarking', 'submission_exists', 'eq', 1);

        // Disable blindmarking if forceblindmarking is enabled, process data for DB in get_data()
        if ($this->forceblindmarking() == 1) {
            $moodleform->addElement('hidden', 'forceblindmarking', $this->forceblindmarking());
            $moodleform->setType('forceblindmarking', PARAM_INT);
            $moodleform->hideif('blindmarking', 'forceblindmarking', 'eq', 1);
            $moodleform->addElement('static', 'forceblindmarking_explanation', '', get_string('forcedglobalsetting', 'mod_coursework'));
        }

        // Add candidate number file naming setting.
        $this->add_candidate_number_setting();
    }

    /**
     * @throws coding_exception
     */
    protected function add_enable_assessor_anonymity_field() {
        global $CFG;

        $moodleform =& $this->_form;
        $options = [0 => get_string('no'), 1 => get_string('yes')];
        $moodleform->addElement('select', 'assessoranonymity', get_string('assessoranonymity', 'mod_coursework'), $options);
        $moodleform->addHelpButton('assessoranonymity', 'assessoranonymity', 'mod_coursework');
        $moodleform->setDefault('assessoranonymity', $CFG->coursework_assessoranonymity);

    }

    /**
     * @throws coding_exception
     */
    protected function add_feedback_header() {
        $moodleform =& $this->_form;

        $moodleform->addElement('header', 'feedbacktypes', get_string('feedbacktypes', 'mod_coursework'));
    }

    /**
     * @throws coding_exception
     */
    protected function add_individual_feedback_release_date_field() {

        global $CFG;

        $moodleform =& $this->_form;

        $timestamp = strtotime('+' . $CFG->coursework_individualfeedback . ' weeks');

        $default = [
            'day' => date('j', $timestamp),
            'month' => date('n', $timestamp),
            'year' => date('Y', $timestamp),
            'hour' => date('G', $timestamp),
            'minute' => date('i', $timestamp),
        ];
        $options = ['optional' => true];
        if ($CFG->coursework_auto_release_individual_feedback == 0) {
            $options['disabled'] = true;

        } else {
            $default['enabled'] = 1;
        }

        if ($CFG->coursework_forceauto_release_individual_feedback == 1) {
            $options['optional'] = false;
        }

        $moodleform->addElement('date_time_selector',
                                 'individualfeedback',
                                 get_string('individualfeedback', 'coursework'),
                                 $options);
        $moodleform->setDefault('individualfeedback', $default);
        $moodleform->addHelpButton('individualfeedback', 'individualfeedback', 'mod_coursework');

        if ($this->forceautorelease() == 1 && $CFG->coursework_auto_release_individual_feedback == 0) {
            $moodleform->addElement('hidden', 'forceautorelease', $this->forceautorelease());
            $moodleform->setType('forceautorelease', PARAM_INT);
            $moodleform->hideif('individualfeedback', 'forceautorelease', 'eq', 1);
        }
        $moodleform->hideif('individualfeedback', 'deadline[enabled]', 'notchecked');
    }

    /**
     * @throws coding_exception
     */
    protected function add_email_individual_feedback_notification_field() {
        global $CFG;

        $moodleform =& $this->_form;
        $options = [0 => get_string('no'), 1 => get_string('yes')];
        $moodleform->addElement('select', 'feedbackreleaseemail', get_string('feedbackreleaseemail', 'mod_coursework'), $options);
        $moodleform->addHelpButton('feedbackreleaseemail', 'feedbackreleaseemail', 'mod_coursework');
        $moodleform->setDefault('feedbackreleaseemail', $CFG->coursework_feedbackreleaseemail);
    }

    /**
     * @throws coding_exception
     */
    protected function add_general_feedback_release_date_field() {
        global $CFG;

        $moodleform =& $this->_form;

        $moodleform->addElement('date_time_selector',
                                 'generalfeedback',
                                 get_string('generalfeedbackreleasedate', 'coursework'),
                                 ['optional' => true, 'disabled' => true]);
        // We have a field which is sometimes disabled. Disabled fields are not sent back to the
        // server, so the default is used.
        $timestamp = strtotime('+' . $CFG->coursework_generalfeedback . ' weeks');

        $default = [
            'day' => date('j', $timestamp),
            'month' => date('n', $timestamp),
            'year' => date('Y', $timestamp),
            'hour' => date('G', $timestamp),
            'minute' => date('i', $timestamp),
        ];
        $moodleform->setDefault('generalfeedback', $default);
        $moodleform->addHelpButton('generalfeedback', 'generalfeedback', 'mod_coursework');
        $moodleform->hideif('generalfeedback', 'deadline[enabled]', 'notchecked');
    }

    /**
     */
    protected function add_tweaks_to_standard_grading_form_elements() {
        $moodleform =& $this->_form;

        $moodleform->addHelpButton('grade', 'grade', 'mod_coursework');
        $moodleform->setExpanded('modstandardgrade');

        $options = [0 => get_string('sameforallstages', 'mod_coursework'),
                         1 => get_string('simpledirectgrading', 'mod_coursework')];

        $moodleform->addElement('select', 'finalstagegrading', get_string('finalstagegrading', 'mod_coursework'), $options);
        $moodleform->addHelpButton('finalstagegrading', 'finalstagegrading', 'mod_coursework');
        $moodleform->setDefault('finalstagegrading', 0);
        $moodleform->hideif('finalstagegrading', 'numberofmarkers', 'eq', 1);
        $moodleform->hideif('finalstagegrading', 'advancedgradingmethod_submissions', 'eq', "");

        $feedbackexists = 0;
        // disable the setting if at least one feedback exists
        $courseworkid = $this->get_courseworkid();
        if ($courseworkid && mod_coursework\models\coursework::find($courseworkid)->has_any_final_feedback()) {
            $feedbackexists = 1;
        }

        $moodleform->addElement('hidden', 'feedbackexists', $feedbackexists);
        $moodleform->setType('feedbackexists', PARAM_INT);
        $moodleform->disabledIf('finalstagegrading', 'feedbackexists', 'eq', 1);
    }

    /**
     */
    protected function set_form_attributes() {
        $moodleform =& $this->_form;

        $moodleform->_attributes['name'] = 'ocm_update_form';
    }

    protected function add_turnitin_files_settings_waring() {
        $this->_form->addElement('html', '<div class ="notification_tii">');
        $this->_form->addElement('html',
                                 get_string('turnitinfilesettingswarning', 'mod_coursework'));
        $this->_form->addElement('html', '</div>');
    }

    private function add_allow_late_submissions_field() {
        global $CFG;
        $moodleform =& $this->_form;
        $options = [ 0 => get_string('no'), 1 => get_string('yes')];
        $moodleform->addElement('select',
                                 'allowlatesubmissions',
                                 get_string('allowlatesubmissions', 'mod_coursework'), $options);
        $moodleform->setType('allowlatesubmissions', PARAM_INT);
        $moodleform->setDefault('allowlatesubmissions', $CFG->coursework_allowlatesubmissions);
        $moodleform->hideif('allowlatesubmissions', 'deadline[enabled]', 'notchecked');

    }

    protected function add_all_feedbacks_field() {

        $moodleform =& $this->_form;

        $options = [ 0 => get_string('no'), 1 => get_string('yes')];
        $moodleform->addElement('select',
                                 'showallfeedbacks',
                                 get_string('showallfeedbacks', 'mod_coursework'), $options);
        $moodleform->setDefault('showallfeedbacks', 0);
        $moodleform->hideif('showallfeedbacks', 'numberofmarkers', 'eq', 1);
        $moodleform->addHelpButton('showallfeedbacks', 'showallfeedbacks', 'mod_coursework');
    }

    /**
     * @throws coding_exception
     */
    private function add_enable_sampling_checkbox() {
        $moodleform =& $this->_form;

        $moodleform->addElement('selectyesno', 'samplingenabled', get_string('samplingenabled', 'mod_coursework'));
        $moodleform->addHelpButton('samplingenabled', 'samplingenabled', 'mod_coursework');

        $courseworkid = $this->get_courseworkid();
        if (!$courseworkid ||  ($courseworkid && !mod_coursework\models\coursework::find($courseworkid)->has_samples()) ) {
            $moodleform->hideif('samplingenabled', 'numberofmarkers', 'eq', 1);

        }
    }

    /**
     * @throws coding_exception
     */
    private function add_view_initial_assessors_grade() {

        $moodleform =& $this->_form;

        $moodleform->addElement('selectyesno', 'viewinitialgradeenabled', get_string('viewinitialgradeenabled', 'mod_coursework'));
        $moodleform->addHelpButton('viewinitialgradeenabled', 'viewinitialgradeenabled', 'mod_coursework');

        $moodleform->hideif('viewinitialgradeenabled', 'numberofmarkers', 'eq', 1);
    }

    /**
     * @throws coding_exception
     */
    private function add_auto_populate_agreed_feedback_comments() {
        $moodleform =& $this->_form;
        $moodleform->addElement('selectyesno', 'autopopulatefeedbackcomment', get_string('autopopulatefeedbackcomment', 'mod_coursework'));
        $moodleform->addHelpButton('autopopulatefeedbackcomment', 'autopopulatefeedbackcomment', 'mod_coursework');
        $moodleform->hideif('autopopulatefeedbackcomment', 'numberofmarkers', 'eq', 1);
    }

    private function forceblindmarking() {
        global $CFG;
        return $CFG->coursework_forceblindmarking;

    }

    private function forceautorelease() {
        global $CFG;
        return $CFG->coursework_forceauto_release_individual_feedback;

    }

    private function add_extensions_header() {
        $moodleform =& $this->_form;

        $moodleform->addElement('header', 'extensions', get_string('extensions', 'mod_coursework'));
    }

    private function add_enable_extensions_field() {
        global $CFG;
        $moodleform =& $this->_form;

        $options = [ 0 => get_string('no'), 1 => get_string('yes')];
        $moodleform->addElement('select', 'extensionsenabled', get_string('individual_extension', 'mod_coursework'), $options);
        $moodleform->addHelpButton('extensionsenabled', 'individual_extension', 'mod_coursework');
        $moodleform->setDefault('extensionsenabled', $CFG->coursework_individual_extension);
    }

    private function add_submission_notification_field() {

        global  $COURSE;

        $moodleform =& $this->_form;

        $selectableusers = [];

        // capability for user allowed to receive submission notifications
        $enrolledusers = get_enrolled_users(context_course::instance($COURSE->id), 'mod/coursework:receivesubmissionnotifications');
        if ($enrolledusers) {
            foreach ($enrolledusers as $u) {
                $selectableusers[$u->id] = fullname($u);
            }
        }

        $select = $moodleform->addElement('select', 'submissionnotification', get_string('submissionnotification', 'mod_coursework'), $selectableusers);
        $select->setMultiple(true);
        $moodleform->hideif('submissionnotification', 'deadline[enabled]', 'checked');

    }

    private function add_automatic_agreement_enabled() {
        $options = ['none' => 'none',
                         'percentage_distance' => 'percentage distance',
                         'average_grade' => 'average grade'];
        $this->form()->addelement('select',
                                  'automaticagreementstrategy',
                                  get_string('automaticagreementofgrades', 'mod_coursework'),
                                  $options);
        $this->form()->settype('automaticagreementstrategy', PARAM_ALPHAEXT);
        $this->form()->addhelpbutton('automaticagreementstrategy', 'automaticagreement', 'mod_coursework');

        $this->form()->hideif('automaticagreementstrategy', 'numberofmarkers', 'eq', 1);
        $this->form()->hideif('automaticagreementrange', 'automaticagreementstrategy', 'neq', 'percentage_distance');

        // If guide or rubric grading in use, none of the existing auto agreement options will work correctly, so hide for now.
        $this->form()->hideif('automaticagreementstrategy', 'advancedgradingmethod_submissions', 'neq', "");

        $this->form()->addElement('select',
                                  'automaticagreementrange',
                                  get_string('automaticagreementrange', 'mod_coursework'),
                                  range(0, 100));
        $this->form()->setType('automaticagreementrange', PARAM_INT);
        $this->form()->setDefault('automaticagreementrange', 10);

        // rounding of the average grade
        $roundingoptions = ['mid' => get_string('roundmid', 'mod_coursework'),
                                 'up' => get_string('roundup', 'mod_coursework'),
                                 'down' => get_string('rounddown', 'mod_coursework')];

        $this->form()->addElement('select',
                                   'roundingrule',
                                    get_string('roundingrule', 'mod_coursework'),
                                    $roundingoptions);
        $this->form()->addhelpbutton('roundingrule', 'roundingrule', 'mod_coursework');

        $this->form()->setType('roundingrule', PARAM_ALPHAEXT);
        $this->form()->setDefault('roundingrule', 'mid');
        $this->form()->hideif('roundingrule', 'automaticagreementstrategy', 'neq', 'average_grade');

    }

    private function add_enable_plagiarism_flag_field() {
        global $CFG;
        $moodleform =& $this->_form;

        $options = [ 0 => get_string('no'), 1 => get_string('yes')];
        $moodleform->addElement('select', 'plagiarismflagenabled', get_string('plagiarism_flag_enable', 'mod_coursework'), $options);
        $moodleform->addHelpButton('plagiarismflagenabled', 'plagiarism_flag_enable', 'mod_coursework');
        $moodleform->setDefault('plagiarismflagenabled', $CFG->coursework_plagiarismflag);
    }

    /**
     * Add candidate number file naming setting.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function add_candidate_number_setting(): void {
        $moodleform =& $this->_form;
        $coursework = $this->get_courseworkid() ? coursework::find($this->get_courseworkid()) : null;

        // If new coursework, or can change setting, show as editable.
        if (!$coursework || $coursework->can_change_candidate_number_setting()) {
            // Only show if candidate number provider is available.
            if (candidateprovider_manager::instance()->is_provider_available()) {
                $moodleform->addElement('select', 'usecandidate', get_string('use_candidate', 'mod_coursework'),
                    [0 => get_string('no'), 1 => get_string('yes')]);
                $moodleform->setDefault('usecandidate', $coursework->usecandidate ?? 0);
                $moodleform->addHelpButton('usecandidate', 'use_candidate', 'mod_coursework');
            }
        } else {
            // Show as immutable.
            $currentvalue = $coursework->usecandidate ?? 0;
            $statustext = $currentvalue ?
                get_string('use_candidate_enabled', 'mod_coursework') :
                get_string('use_candidate_disabled', 'mod_coursework');

            $moodleform->addElement('static', 'usecandidatestatus',
                get_string('use_candidate', 'mod_coursework'),
                $statustext . '<br>' . get_string('use_candidate_immutable', 'mod_coursework'));
            $moodleform->addElement('hidden', 'usecandidate', $currentvalue);
            $moodleform->setType('usecandidate', PARAM_INT);
        }
    }
}
