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

use mod_coursework\ability;
use mod_coursework\allocation\manager;
use mod_coursework\grade_judge;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\models\moderation;
use mod_coursework\models\user;
use mod_coursework\models\moderation_set_rule;
use mod_coursework\models\submission;
use mod_coursework\router;
use mod_coursework\warnings;
use mod_coursework\models\personal_deadline;
use mod_coursework\models\deadline_extension;
use mod_coursework\render_helpers\grading_report\cells;

global $CFG;

require_once($CFG->dirroot . '/lib/plagiarismlib.php');

/**
 * This deals with the specific objects that are part of the pages. The other renderer deals with the pages themselves.
 */
class mod_coursework_object_renderer extends plugin_renderer_base {

    /**
     * Renders a coursework feedback as a row in a table.
     * This is for the grading report when we have multiple markers and we want an AJAX pop up *
     * with details of the feedback. Also for the student view.
     *
     * @param feedback $feedback
     * @return string
     */
    public function render_feedback(feedback $feedback) {
        // WIP - feedback view.

        global $USER;

        $template = new stdClass();

        $submission = $feedback->get_submission();
        $coursework = $feedback->get_coursework();
        $studentname = $submission->get_allocatable_name();

        // Determine the feedback title.
        if ($feedback->is_agreed_grade()) {
            $template->title = get_string('finalfeedback', 'mod_coursework', $studentname);
        } elseif ($feedback->is_moderation()) {
            $template->title = get_string('moderatorfeedback', 'mod_coursework', $studentname);
        } else {
            $stage = $feedback->get_assessor_stage_no();
            $template->title = get_string('componentfeedback', 'mod_coursework', ['stage' => $stage, 'student' => $studentname]);
        }

        $gradejudge = new grade_judge($coursework);
        $template->mark = $gradejudge->grade_to_display($feedback->get_grade());

        // Marker name.
        // TODO - this feels like a lot! varlidate this logic - is it all needed?
        $issamplingenabled = $submission->get_coursework()->sampling_enabled();
        $sampledfeedbackexists = $submission->sampled_feedback_exists();
        $assessoriszero = ($feedback->assessorid == 0);
        $timeequal = ($feedback->timecreated == $feedback->timemodified);
        $isautomaticagreement = ((!$issamplingenabled || $sampledfeedbackexists) && $assessoriszero && $timeequal);

        if (!$isautomaticagreement && $feedback->assessorid != 0) {
            $template->markername = $feedback->display_assessor_name();
            $template->date = $feedback->timemodified;

            // Marker image.
            if ($feedback->assessor) {
                $template->markerimg = $feedback->get_assessor_user_picture()->get_url($this->page)->out(false);
            }
        }

        // Feedback comment.
        $template->feedbackcomment = $feedback->feedbackcomment;

        // Feedback files.
        if ($files = $feedback->get_feedback_files()) {
            $template->feedbackfileshtml = $this->render_feedback_files(new mod_coursework_feedback_files($files));
        }

        // Rubric/Advanced grading stuff if it's there.
        if (feedback::is_stage_using_advanced_grading($coursework, $feedback)) {
            $controller = $coursework->get_advanced_grading_active_controller();
            $template->advancedgradinghtml = $controller->render_grade($this->page, $feedback->id, null, '', false);
        }

        if ($template->feedbackcomment || isset($template->feedbackfileshtml)) {
            $template->separator = true;
        }

        // Return html from template.
        return $this->render_from_template('mod_coursework/feedback', $template);
    }


    /**
     * Renders a coursework moderation as a row in a table.
     *
     * @param moderation $moderation
     * @return string
     */
    public function render_moderation(moderation $moderation) {

        $title =
            get_string('moderationfor', 'coursework', $moderation->get_submission()->get_allocatable_name());

        $out = '';
        $moderatedby = \core_user::get_fullname(\core_user::get_user($moderation->moderatorid));
        $lasteditedby = \core_user::get_fullname(\core_user::get_user($moderation->lasteditedby));

        $table = new html_table();
        $table->attributes['class'] = 'moderation';
        $table->id = 'moderation'. $moderation->id;
        $header = new html_table_cell();
        $header->colspan = 2;
        $header->text = $title;
        $table->head[] = $header;

        // Moderated by
        $tablerow = new html_table_row();
        $leftcell = new html_table_cell();
        $rightcell = new html_table_cell();
        $leftcell->text = get_string('moderatedby', 'coursework' );
        $rightcell->text = $moderatedby;
        $rightcell->id = 'moderation_moderatedby';

        $tablerow->cells['left'] = $leftcell;
        $tablerow->cells['right'] = $rightcell;
        $table->data[] = $tablerow;

        // Last edited by
        $tablerow = new html_table_row();
        $leftcell = new html_table_cell();
        $rightcell = new html_table_cell();
        $leftcell->text = get_string('lasteditedby', 'coursework');
        $rightcell->text = $lasteditedby . ' on ' .
                            userdate($moderation->timemodified, '%a, %d %b %Y, %H:%M');
        $rightcell->id = 'moderation_lasteditedby';

        $tablerow->cells['left'] = $leftcell;
        $tablerow->cells['right'] = $rightcell;
        $table->data[] = $tablerow;

        // Moderation agreement
        $tablerow = new html_table_row();
        $leftcell = new html_table_cell();
        $rightcell = new html_table_cell();
        $leftcell->text = get_string('moderationagreement', 'coursework');
        $rightcell->text = get_string($moderation->agreement, 'coursework');
        $rightcell->id = 'moderation_agreement';

        $tablerow->cells['left'] = $leftcell;
        $tablerow->cells['right'] = $rightcell;
        $table->data[] = $tablerow;

        // Moderation comment
        $tablerow = new html_table_row();
        $leftcell = new html_table_cell();
        $rightcell = new html_table_cell();
        $leftcell->text = get_string('comment', 'mod_coursework');
        $rightcell->text = $moderation->modcomment;
        $rightcell->id = 'moderation_comment';

        $tablerow->cells['left'] = $leftcell;
        $tablerow->cells['right'] = $rightcell;
        $table->data[] = $tablerow;

        $out .= html_writer::table($table);

        return $out;
    }

    /**
     * Outputs the files as a HTML list.
     *
     * @param mod_coursework_submission_files $files
     * @return string
     */
    public function render_submission_files(mod_coursework_submission_files $files) {

        $submissionfiles = $files->get_files();
        $filesarray = [];

        foreach ($submissionfiles as $file) {
            $filesarray[] = $this->make_file_link($files, $file);
        }

        $br = html_writer::empty_tag('br');
        $out = implode($br, $filesarray);

        return $out;
    }

    /**
     * @param mod_coursework_feedback_files $files
     * @return string
     */

    private function render_feedback_files(mod_coursework_feedback_files $files) {

        $filesarray = [];
        $feedbackfiles = $files->get_files();
        foreach ($feedbackfiles as $file) {
            $filesarray[] = $this->make_file_link($files, $file, 'feedbackfile');
        }

        $br = html_writer::empty_tag('br');
        $out = implode($br, $filesarray);

        return $out;
    }

    /**
     * Outputs the files as a HTML list.
     *
     * @param mod_coursework_submission_files $files
     * @param bool $withresubmitbutton
     * @return string
     */
    public function render_submission_files_with_plagiarism_links(mod_coursework_submission_files $files, $withresubmitbutton = true) {

        global $USER;

        $ability = new ability(user::find($USER), $files->get_coursework());

        $coursework = $files->get_coursework();
        $submissionfiles = $files->get_files();
        $submission = $files->get_submission();
        $filesarray = [];

        foreach ($submissionfiles as $file) {

            $link = $this->make_file_link($files, $file);

            if ($ability->can('view_plagiarism', $submission)) {
                // With no stuff to show, $plagiarismlinks comes back as '<br />'.
                $link .= '<div class ="percent">'. $this->render_file_plagiarism_information($file, $coursework, $submission).'</div>';
            }

            if ($withresubmitbutton) {
                $link .= '<div class ="subbutton">'. $this->render_resubmit_to_plagiarism_button($coursework, $submission).'</div>';
            }

            $filesarray[] = $link;
        }

        $br = html_writer::empty_tag('br');
        $out = implode($br, $filesarray);

        return $out;
    }

    /**
     * Outputs the files as a HTML list.
     *
     * @param mod_coursework_submission_files $files
     * @return string
     */
    public function render_plagiarism_links($files) {

        global $USER;

        $ability = new ability(user::find($USER), $files->get_coursework());

        $coursework = $files->get_coursework();
        $submissionfiles = $files->get_files();
        $submission = $files->get_submission();
        $filesarray = [];

        foreach ($submissionfiles as $file) {

            $link = '';

            if ($ability->can('view_plagiarism', $submission)) {
                // With no stuff to show, $plagiarismlinks comes back as '<br />'.
                $link = $this->render_file_plagiarism_information($file, $coursework, $submission);
            }

            $filesarray[] = $link;
        }

        $br = html_writer::empty_tag('br');
        $out = implode($br, $filesarray);

        return $out;
    }

    /**
     * Displays a coursework so that we can see the intro, deadlines etc at the top of view.php
     *
     * @param mod_coursework_coursework $coursework
     * @return string html
     */
    protected function render_mod_coursework_coursework(mod_coursework_coursework $coursework): string {
        global $USER;
        $student = user::find($USER);
        $template = new stdClass();

        // Capability checks
        $canallocate = has_capability('mod/coursework:allocate', $coursework->get_context());
        $cangrade = has_capability('mod/coursework:addinitialgrade', $this->page->context);
        $canpublish = has_capability('mod/coursework:publish', $this->page->context);
        $canaddgeneralfeedback = has_capability('mod/coursework:addgeneralfeedback', $this->page->context);
        $cansubmit = has_capability('mod/coursework:submit', $this->page->context);

        // Warnings.
        if ($canallocate) {
            $warnings = new warnings($coursework);
            $template->notenoughassessors = $warnings->not_enough_assessors();
        }

        // Teacher summary col.
        if ($cangrade || $canpublish) {
            $template->markingsummary = $this->coursework_marking_summary($coursework);
        }

        // Student summary col.
        if ($cansubmit && !$cangrade) {
            $pagerenderer = $this->page->get_renderer('mod_coursework', 'page');
            $template->studentview = $pagerenderer->student_view_page($coursework, $student);
        }

        $submission = $coursework->get_user_submission($student);

        // Feedback or intro.
        if (!$cangrade && $submission && $submission->is_published()) {
            $template->feedbackfromteachers = $this->existing_feedback_from_teachers($submission);
        } else {
            $template->introdates = $this->add_intro_dates($coursework, $template);
            $template->description = format_module_intro('coursework', $coursework, $coursework->get_coursemodule_id());

            // Marking guide - from advanced grading.
            $template->markingguideurl = self::get_marking_guide_url($coursework);
        }

        if ($cangrade || $canpublish || $canaddgeneralfeedback || $coursework->is_general_feedback_released()) {
            $feedback = new stdClass();
            $feedback->feedback = $coursework->feedbackcomment;
            if ($cangrade || $canpublish || $canaddgeneralfeedback) {
                $feedback->duedate = $coursework->generalfeedback;
            }
            if ($canaddgeneralfeedback) {
                $feedback->button = new stdClass();
                $feedback->button->url = new moodle_url('/mod/coursework/actions/general_feedback.php', ['cmid' => $coursework->get_coursemodule_id()]);
                $feedback->button->label = get_string($coursework->feedbackcomment ? 'editgeneralfeedback' : 'addgeneralfeedback', 'coursework');
            }
            $template->generalfeedback = $feedback;
        }

        return $this->render_from_template('mod_coursework/intro', $template);
    }

    /**
     * Makes the HTML table for allocating markers to students and returns it.
     *
     * @param mod_coursework_allocation_table $allocationtable
     * @return string
     */
    protected function render_mod_coursework_allocation_table(mod_coursework_allocation_table $allocationtable) {

        global $SESSION;

        $tablehtml = $allocationtable->get_hidden_elements();
        $all = count($allocationtable->get_coursework()->get_allocatables());
        $options = $allocationtable->get_options();

        $tablehtml .= '<div class="table-and-jumptos">';

        // Pagination controls have been removed.
        $tablehtml .= \html_writer::start_tag('form', ['method' => 'post']);

        $tablehtml .= '

            <table class="allocations display">
                <thead>
                <tr>

        ';

        // Get the hidden elements used for assessors and moderators selected on other pages;

        $allocatablecellhelper = $allocationtable->get_allocatable_cell();
        $tablehtml .= '<th>';
        $tablehtml .= $allocatablecellhelper->get_table_header($allocationtable->get_options());
        $tablehtml .= '</th>';

        $no = 0;
        foreach ($allocationtable->marking_stages() as $stage) {
            if ($stage->uses_allocation()) {
                $tablehtml .= '<th>';
                // pin all checkbox
                $checkboxtitle = get_string('selectalltopin', 'coursework');
                if ($stage->allocation_table_header() == 'Assessor') {
                    $no++;
                    if ($stage->stage_has_allocation() ) {// has any pins
                        $tablehtml .= '<input type="checkbox" name="" id="selectall_' . $no . '" title = "' . $checkboxtitle . '">';
                    }
                    $tablehtml .= $stage->allocation_table_header() . ' ' . $no;
                } else if ($allocationtable->get_coursework()->moderation_agreement_enabled()) {
                    // Moderator header
                    if ($stage->stage_has_allocation() ) {// has any pins
                        $tablehtml .= '<input type="checkbox" name="" id="selectall_mod" title = "' . $checkboxtitle . '">';
                    }
                    $tablehtml .= get_string('moderator', 'coursework');

                } else {
                    $tablehtml .= $stage->allocation_table_header();
                }
                $tablehtml .= '</th>';
            }
        }

        $tablehtml .= '
                </tr>
                </thead>
                <tbody>
        ';

        $rowdata = $allocationtable->get_rows();
        foreach ($rowdata as $row) {
            $tablehtml .= $this->render_allocation_table_row($row);
        }

        $tablehtml .= '
                </tbody>
            </table>
        ';
        // Form save button.

        $attributes = ['name' => 'save',
            'type' => 'submit',
            'id' => 'save_manual_allocations_1',
            'value' => get_string('save', 'mod_coursework')];
        $tablehtml .= html_writer::empty_tag('input', $attributes);
        $tablehtml .= html_writer::end_tag('form');
        $tablehtml .= '</div>';

        return $tablehtml;
    }

    /**
     * Makes a single row for the HTML table and returns it. The row contains form elements, but if we try
     * to use the mforms library, we can't use the html_table library as we would have to hand code the
     * start and end of each table row in the form.
     *
     * @param mod_coursework_allocation_table_row $allocationrow
     * @return \html_table_row
     */
    protected function render_mod_coursework_allocation_table_row(mod_coursework_allocation_table_row $allocationrow) {

        $row = new html_table_row();
        $row->id = $allocationrow->get_allocatable()->type().'_'.$allocationrow->get_allocatable()->id();

        $allocatablecellhelper = $allocationrow->get_allocatable_cell();

        $allocatablecell = $allocatablecellhelper->get_table_cell($allocationrow);
        $row->cells['allocatable'] = $allocatablecell;

        $stages = $allocationrow->marking_stages();

        foreach ($stages as $stage) {
            $row->cells[$stage->identifier()] = $stage->get_allocation_table_cell($allocationrow->get_allocatable());
        }

        return $row;
    }

    /**
     * Output allocation mechanism.
     *
     * @param mod_coursework_allocation_widget $allocationwidget
     * @return string
     */
    public function render_mod_coursework_allocation_widget(mod_coursework_allocation_widget $allocationwidget): string {
        $template = new stdClass();

        // 1. Strings for display.
        $template->headerstring = ($allocationwidget->get_coursework()->moderation_agreement_enabled()) ?
            get_string('allocatemarkersandmoderators', 'mod_coursework') :
            get_string('allocatemarkers', 'mod_coursework');

        $template->strategyheader = get_string('assessorallocationstrategy', 'mod_coursework');

        // 2. Data for the select element.
        $options = manager::get_allocation_classnames();
        $currentstrategy = $allocationwidget->get_assessor_allocation_strategy();
        $strategyselectoptions = [];

        // Loop through options to create the structure Mustache expects for a select loop.
        foreach ($options as $key => $value) {
            $option = new stdClass();
            $option->value = $key;
            $option->string = $value;
            $option->selected = ($key === $currentstrategy); // Boolean for conditional rendering.
            $strategyselectoptions[] = $option;
        }
        $template->strategy = $strategyselectoptions;

        // 3. Other HTML to render (which can't easily be converted to a simple key/value).
        // The inner configuration is still generated by a PHP function.
        $template->strategyoptionshtml = $this->get_allocation_strategy_form_elements(
            $allocationwidget->get_coursework()
        );

        return $this->render_from_template('coursework/allocate/strategy', $template);
    }

    /**
     * @return router
     */
    protected function get_router() {
        return router::instance();
    }

    public function render_mod_coursework_sampling_set_widget(mod_coursework_sampling_set_widget $samplingwidget) {

        global $DB;

        $html = html_writer::tag('h2', get_string('sampling', 'mod_coursework'));

        $html .= html_writer::start_tag('div', ['class' => 'assessor-sampling-wrapper accordion']);

        $html .= html_writer::start_tag('h3', ['id' => 'sampling_strategy_settings_header']);
        $html .= get_string('samplingstrategy', 'mod_coursework');
        $html .= html_writer::end_tag('h3');

        $html .= html_writer::start_tag('div', ['class' => 'sampling-rules']);

        // We want to allow the allocation strategy to add configuration options.

        $html .= html_writer::start_tag('div', ['class' => 'sampling-select']);

        $script = "
            var samplingValidateHdl = [];
        ";

        $html  .= html_writer::script($script);

        $table = new html_table();
        $table->attributes['class'] = 'sampling';
        $table->head = [''];

        $assessorheaders = [];

        for ($i = 0; $i < $samplingwidget->get_coursework()->get_max_markers(); $i++) {
            $assessorheaders[] = get_string('assessorheading', 'mod_coursework', $i + 1);
        }

        $scale = "";

        if ($samplingwidget->get_coursework()->grade > 0) {

            $comma = "";

            for ($i = 0; $i <= $samplingwidget->get_coursework()->grade; $i++) {
                $scale .= $comma.$i;
                $comma = ",";
            }
        } else {
            $gradescale = \grade_scale::fetch(['id' => abs($samplingwidget->get_coursework()->grade)]);
            $scale = $gradescale->scale;
        }

        $html  .= "<input id='scale_values' type='hidden' value='".$scale."' />";

        $table->head = $assessorheaders;

        $assessor1cell = html_writer::start_tag('div', ['class' => 'samples_strategy']);
        $assessor1cell  .= get_string('assessoronedefault', 'mod_coursework');
        $assessor1cell  .= html_writer::end_tag('div');

        $columndata = [new html_table_cell($assessor1cell)];

        $percentageoptions = [];

        for ($i = 0; $i < 110; $i = $i + 10) {
            $percentageoptions[$i] = "{$i}%";
        }

        $javascript = false;

        for ($i = 2; $i <= $samplingwidget->get_coursework()->get_max_markers(); $i++) {

            // Create the secon

            $samplingstrategies = ['0' => get_string('sampling_manual', 'mod_coursework'),
                                              '1' => get_string('sampling_automatic', 'mod_coursework')];

            // Check whether any rules have been saved for this stage
            $selected = ($samplingwidget->get_coursework()->has_automatic_sampling_at_stage('assessor_'.$i)) ? '1' : false;

            $samplingcell = html_writer::start_tag('div', ['class' => 'samples_strategy']);
            $samplingcell .= html_writer::label(get_string('sampletype', 'mod_coursework'), "assessor_{$i}_samplingstrategy");

            $samplingcell .= html_writer::select($samplingstrategies,
                "assessor_{$i}_samplingstrategy",
                $selected,
                false,
                ['id' => "assessor_{$i}_samplingstrategy", 'class' => "assessor_sampling_strategy sampling_strategy_detail"]);

            $samplingcell .= html_writer::end_tag('div');

            if ($i == $samplingwidget->get_coursework()->get_max_markers()) {
                $javascript = true;
            }

            $graderules =

            $graderules = html_writer::start_tag('h4');
            $graderules .= get_string('graderules', 'mod_coursework');
            $graderules .= html_writer::end_tag('h4');

            $graderules .= $this->get_sampling_strategy_form_elements($samplingwidget->get_coursework(), $i, $javascript);

            $samplingcell .= html_writer::div($graderules, '', ['id' => "assessor_{$i}_automatic_rules"]);

            $columndata[] = new html_table_cell($samplingcell);
        }

        $table->data[] = $columndata;

          //= array($asessoronecell, $asessortwocell);

        $html  .= html_writer::table($table);

        // End the form with save button.
        $attributes = ['name' => 'save_sampling',
            'type' => 'submit',
            'id' => 'save_manual_sampling',
            'value' => get_string('save', 'mod_coursework')];
        $html  .= html_writer::empty_tag('input', $attributes);

        /**
         *  Ok this is either some really clever or really hacky code depending on where you stand, time of day and your mood :)
         *  The following script creates a global Array var (samplingValidateHdl)that holds the names of the validation functions for each plugin
         *  (as  I write none but....) It also creates an event handler for the submit button. The event handler calls each of the
         *  functions defined in samplingValidateHdl  allowing the plugins to validate their own sections (function names to be called plugin_name_validation)
         *  returning 0 or 1 depending on whether and error was found. (Was that verbose...yeah...oh well) - ND
         */

        $script = "

            $('#save_manual_sampling').on('click', function (e) {

                validationresults = [];

                $.each(samplingValidateHdl, function(i,functionname) {
                     validationresults.push(eval(functionname+'()'));
                })

                if (validationresults.lastIndexOf(1) != -1) e.preventDefault();
            })

        ";

        $html  .= html_writer::script($script);

        $html .= html_writer::end_tag('div');

        $html .= html_writer::end_tag('div');

        $html .= html_writer::end_tag('div');

        return $html;
    }

    private function sampling_strategy_column($samplingwidget, $suffix = '') {

        $percentageoptions = [];

        for ($i = 0; $i < 110; $i = $i + 10) {
            $percentageoptions[$i] = "{$i}%";
        }

        // Hidden input containing scale values
        $scale = [];
        $samplingcolumn = "<input id='scale_values' type='hidden' value='".implode(',', $scale)."' />";

        $samplingcolumn  .= html_writer::tag('br', '');
        $samplingcolumn  .= html_writer::tag('strong', get_string('selectrules', 'mod_coursework'));
        $samplingcolumn  .= html_writer::tag('br', '');

        $samplingcolumn  .= html_writer::start_tag('div');

        for ($i = 0; $i < 1; $i++) {
            $samplingcolumn .= html_writer::start_tag('span', ['class' => "assessor_{$suffix}_grade_rules", 'id' => "assessor_{$suffix}_grade_rules"]);

            $samplingcolumn .= html_writer::checkbox("assessor_{$suffix}_samplerules[]", 1, false, get_string('grade', 'mod_coursework'),
                ['id' => "assessor_{$suffix}_samplerules_{$i}", 'class' => "assessor_{$suffix} sampling_strategy_detail"]);

            $options = ['0' => get_string('percentagesign', 'mod_coursework'),
                '1' => get_string('gradescale', 'mod_coursework')];

            $samplingcolumn .= html_writer::select($options,
                "assessor_{$suffix}_sampletype[]",
                $samplingwidget->get_sampling_strategy(),
                false,
                ['id' => "assessor_{$suffix}_sampletype_{$i}", 'class' => "grade_type assessor_{$suffix} sampling_strategy_detail"]);

            $samplingcolumn .= html_writer::label(get_string('from', 'mod_coursework'), 'assessortwo_samplefrom[0]');

            $ruleoptions = $percentageoptions;

            $samplingcolumn .= html_writer::select($ruleoptions,
                "assessor_{$suffix}_samplefrom[]",
                $samplingwidget->get_sampling_strategy(),
                false,
                ['id' => "assessor_{$suffix}_samplefrom_{$i}", 'class' => "assessor_{$suffix} sampling_strategy_detail"]);

            $samplingcolumn .= html_writer::label(get_string('to', 'mod_coursework'), "assessor_{$suffix}_sampleto[0]");

            $samplingcolumn .= html_writer::select($ruleoptions,
                "assessor_{$suffix}_sampleto[]",
                $samplingwidget->get_sampling_strategy(),
                false,
                ['id' => "assessor_{$suffix}_sampleto_{$i}", 'class' => "assessor_{$suffix} sampling_strategy_detail"]);

            $samplingcolumn .= html_writer::end_tag('span', '');
        }

        $samplingcolumn  .= html_writer::end_tag('div');

        $samplingcolumn  .= html_writer::link('#', get_string('addgraderule', 'mod_coursework'), ['id' => "assessor_{$suffix}_addgradderule", 'class' => 'addgradderule sampling_strategy_detail']);
        $samplingcolumn  .= html_writer::link('#', get_string('removegraderule', 'mod_coursework'), ['id' => "assessor_{$suffix}_removegradderule", 'class' => 'removegradderule sampling_strategy_detail']);

        $samplingcolumn  .= html_writer::checkbox("assessor_{$suffix}_samplertopup", 1, false, get_string('topupto', 'mod_coursework'),
            ['id' => "assessor_{$suffix}_samplerules[]", 'class' => "assessor_{$suffix} sampling_strategy_detail"]);

        $samplingcolumn .= html_writer::select($percentageoptions,
            "assessor_{$suffix}_sampletopup",
            $samplingwidget->get_sampling_strategy(),
            false,
            ['id' => "assessor_{$suffix}_sampletopup", 'class' => "assessor_{$suffix} sampling_strategy_detail"]);
        $samplingcolumn  .= html_writer::label(get_string('ofallstudents', 'mod_coursework'), 'assessortwo_sampleto[]');

        return $samplingcolumn;

    }

    /**
     * Deals with grades which may be unset as yet, or which may be scales.
     * @param $grade
     * @param $maxgrade
     * @throws coding_exception
     * @return float|string
     */
    private function output_grade_as_string($grade, $maxgrade) {

        global $DB;

        // String.
        $out = '';

        if ($maxgrade < -1) { // Coursework is graded with a scale.
            // TODO cache these.
            $scalegrade = -$maxgrade;
            $scale = $DB->get_record('scale', ['id' => ($scalegrade)]);

            if ($scale) {
                $items = explode(',', $scale->scale);
                $out = $items[$grade - 1]; // Scales always start fom 1.
            }
        } else {
            if ($grade == -1 || $grade === false || is_null($grade)) {
                $out = get_string('nograde');
            } else {
                // Grade has been set, although it may be zero.
                $out = round($grade, 2);
            }
        }

        return $out;
    }

    /**
     * Outputs a rule object on screen so we can see what it does.
     *
     * @param moderation_set_rule $rule
     * @throws coding_exception
     * @return \html_table_row
     */
    protected function make_moderation_set_rule_row(moderation_set_rule $rule) {

        $row = new html_table_row();

        $rulecell = new html_table_cell();

        $numbers = new stdClass();
        $numbers->upperlimit = $rule->upperlimit;
        $numbers->lowerlimit = $rule->lowerlimit;
        $numbers->minimum = $rule->minimum;
        $rulecell->text .= get_string($rule->get_name() . 'desc', 'mod_coursework', $numbers);

        $row->cells[] = $rulecell;

        $controlscell = new html_table_cell();
        // Add a delete button. Ideally, we submit the whole form in case people have changed any bit of it.
        // Can intercept with AJAX later if needs be.
        $linktitle = get_string('delete');

        $attributes = [
            'type' => 'submit',
            'name' => 'delete-mod-set-rule[' . $rule->id . ']',
            'value' => $linktitle,
        ];
        $controlscell->text .= html_writer::empty_tag('input', $attributes);
        $row->cells[] = $controlscell;

        return $row;
    }

    /**
     * Gives us the form elements that allow us to configure the allocation strategies.
     *
     * @param coursework $coursework
     * @return string HTML form elements
     */
    protected function get_allocation_strategy_form_elements(coursework $coursework): string {
        global $CFG;

        $html = '';
        $strategydir = $CFG->dirroot . '/mod/coursework/classes/allocation/strategy';
        $strategyfilepaths = glob($strategydir . '/*.php');
        $currentstrategyname = $coursework->assessorallocationstrategy;

        foreach ($strategyfilepaths as $filepath) {
            $shortname = pathinfo($filepath, PATHINFO_FILENAME);

            // Skip the base class file.
            if ($shortname === 'base') {
                continue;
            }

            $fullclassname = "\\mod_coursework\\allocation\\strategy\\{$shortname}";

            // Ensure the class exists before trying to instantiate it.
            if (!class_exists($fullclassname)) {
                continue;
            }

            /** @var \mod_coursework\allocation\strategy\base $strategy */
            $strategy = new $fullclassname($coursework);

            $attributes = [
                'class' => 'assessor-strategy-options',
                'id' => 'assessor-strategy-' . $shortname,
            ];

            // Hide this if it's not currently selected.
            if ($shortname !== $currentstrategyname) {
                $attributes['style'] = 'display:none';
            }

            $html .= html_writer::start_tag('div', $attributes);
            $html .= $strategy->add_form_elements('assessor');
            $html .= html_writer::end_tag('div');
        }

        return $html;
    }

    protected function get_sampling_strategy_form_elements($coursework, $assessornumber, $loadjavascript=false) {

        global $CFG, $DB;

        $html = '';
        $javascript = '';
        $classdir = $CFG->dirroot . '/mod/coursework/classes/sample_set_rule/';

        $sampleplugins = $DB->get_records('coursework_sample_set_plugin', null, 'pluginorder');

        // $fullclasspaths = glob($classdir . '/*.php');
        foreach ($sampleplugins as $plugin) {
            /*    if (strpos($fullclassname, 'base') !== false) {
                continue;
            }*/
            preg_match('/([^\/]+).php/', $classdir."/".$plugin->rulename.".php", $matches);
            $classname = $matches[1];
            $fullclassname = '\mod_coursework\sample_set_rule\\' . $classname;

            $samplingrule = new $fullclassname($coursework);

            $html .= $samplingrule->add_form_elements($assessornumber);

            if ($loadjavascript) {
                $javascript .= $samplingrule->add_form_elements_js($assessornumber);
            }

        }

        return $html." ".$javascript;

    }

    /**
     * @param coursework $coursework
     * @param submission $submission
     * @throws coding_exception
     * @return string
     */
    protected function resubmit_to_plagiarism_button($coursework, $submission) {
        $html = '';
        $html .= html_writer::start_tag('form',
                                                  ['action' => $this->page->url,
                                                        'method' => 'POST']);
        $html .= html_writer::empty_tag('input',
                                                  ['type' => 'hidden',
                                                        'name' => 'submissionid',
                                                        'value' => $submission->id]);
        $html .= html_writer::empty_tag('input',
                                                  ['type' => 'hidden',
                                                        'name' => 'id',
                                                        'value' => $coursework->get_coursemodule_id()]);
        $plagiarismpluginnames = [];
        foreach ($coursework->get_plagiarism_helpers() as $helper) {
            $plagiarismpluginnames[] = $helper->human_readable_name();
        }
        $plagiarismpluginnames = implode(' ', $plagiarismpluginnames);

        $resubmit = get_string('resubmit', 'coursework', $plagiarismpluginnames);
        $html .= html_writer::empty_tag('input',
                                                  ['type' => 'submit',
                                                        'value' => $resubmit,
                                                        'name' => 'resubmit']);
        $html .= html_writer::end_tag('form');
        return $html;
    }

    /**
     * @param stored_file $file
     * @param coursework $coursework
     * @return string
     */
    protected function render_file_plagiarism_information($file, $coursework) {

        $plagiarismlinksparams = [
            'userid' => $file->get_userid(),
            'file' => $file,
            'cmid' => $coursework->get_coursemodule_id(),
            'course' => $coursework->get_course(),
            'coursework' => $coursework->id,
            'modname' => 'coursework',
        ];
        $plagiarsmlinks = plagiarism_get_links($plagiarismlinksparams);

        return $plagiarsmlinks;
    }

    /**
     * @param mod_coursework_submission_files $files
     * @param stored_file $file
     * @param string $classname
     * @return string
     */
    protected function make_file_link($files, $file, $classname = 'submissionfile') {
        global $CFG;

        $url = "{$CFG->wwwroot}/pluginfile.php/{$file->get_contextid()}" .
            "/mod_coursework/{$files->get_file_area_name()}";
        $filename = $file->get_filename();

        $image = $this->output->pix_icon(file_file_icon($file),
                                   $filename,
                        'moodle',
                               ['class' => 'submissionfileicon']);

        $fileurl = $url . $file->get_filepath() . $file->get_itemid() . '/' . rawurlencode($filename);
        return html_writer::link($fileurl, $image.$filename, ['class' => $classname]);
    }

    /**
     * @param coursework $coursework
     * @param submission $submission
     * @return string
     */
    protected function render_resubmit_to_plagiarism_button($coursework, $submission) {
        global $USER;

        $ability = new ability(user::find($USER), $coursework);
        $html = '';
        if ($coursework->plagiarism_enbled() && $ability->can('resubmit_to_plagiarism', $submission)) {
            // Show the resubmit to plagiarism button if the user is allowed to do this.
            $html .= $this->resubmit_to_plagiarism_button($coursework, $submission);
        }
        return $html;
    }

    /**
     * Get marking guide URL when advanced marking is in use.
     *
     * @param mod_coursework_coursework $coursework
     * @return moodle_url|null Null if there's no advanced grading form set up.
     */
    public static function get_marking_guide_url(mod_coursework_coursework|mod_coursework\models\coursework $coursework): ?moodle_url {

        if (!$coursework->is_using_advanced_grading()) {
            return null;
        }

        $controller = $coursework->get_advanced_grading_active_controller();
        if ($controller->is_form_defined() && ($options = $controller->get_options()) && !empty($options['alwaysshowdefinition'])) {
            // Extract method name using reflection for protected method access.
            $reflectionclass = new ReflectionClass($controller);
            $getmethodname = $reflectionclass->getMethod('get_method_name');
            $getmethodname->setAccessible(true);
            $methodname = $getmethodname->invoke($controller);

            return new moodle_url('/grade/grading/form/' . $methodname . '/preview.php',
                    ['areaid' => $controller->get_areaid()]);
        }

        return null;
    }

    /**
     * @param mod_coursework_coursework $coursework
     * @return stdClass
     */
    private function add_intro_dates(mod_coursework_coursework $coursework) {
        global $USER;

        $template = new stdClass();

        // Fetch student and deadline information.
        $user = user::find($USER);

        // Handle coursework deadline details.
        if ($coursework->has_deadline()) {
            // Determine the effective deadline.
            if ($personaldeadline = personal_deadline::get_personal_deadline_for_student($user, $coursework)) {
                $template->duedate = $personaldeadline->personal_deadline;
            } else {
                $template->duedate = $coursework->deadline;
            }


            if ($coursework->allow_late_submissions()) {
                $template->latesubmissionsallowed = true;
            }

            if ($coursework->personal_deadlines_enabled() && (!has_capability('mod/coursework:submit', $this->page->context) || is_siteadmin($user))) {
                $template->personaldeadlines = true;
            }

            // Add extension if it exists.
            if ($deadlineextension = deadline_extension::get_extension_for_student($user, $coursework)) {
                $template->deadlineextension = $deadlineextension->extended_deadline;
            }

            // Handle individual feedback deadline.
            if ($coursework->individualfeedback) {
                $template->individualfeedbackdeadline = $coursework->get_individual_feedback_deadline();
            }
        }

        return empty((array) $template) ? null : $template;
    }


    /**
     * @param submission $submission
     * @return string
     */
    protected function existing_feedback_from_teachers($submission) {

        global $USER;

        $coursework = $submission->get_coursework();

        $html = '';

        // Start with final feedback. Use moderated grade?

        $finalfeedback = $submission->get_final_feedback();

        $ability = new ability(user::find($USER), $submission->get_coursework());

        if ($finalfeedback && $ability->can('show', $finalfeedback)) {
            $html .= $this->render_feedback($finalfeedback);
        }

        if ($submission->has_multiple_markers() && $coursework->students_can_view_all_feedbacks()) {
            $assessorfeedbacks = $submission->get_assessor_feedbacks();
            foreach ($assessorfeedbacks as $feedback) {
                if ($ability->can('show', $feedback)) {
                    $html .= $this->render_feedback($feedback);
                }
            }
        }

        return $html;
    }

    /**
     * @param \mod_coursework\allocation\table\row\builder $allocationrow
     * @return string
     */
    private function render_allocation_table_row($allocationrow) {

        $rowhtml = '
            <tr id="'. $allocationrow->get_allocatable()->type() . '_' . $allocationrow->get_allocatable()->id().'">
        ';

        $allocatablecellhelper = $allocationrow->get_allocatable_cell();
        $rowhtml .= $allocatablecellhelper->get_table_cell($allocationrow);

        foreach ($allocationrow->marking_stages() as $stage) {
            if ($stage->uses_allocation() && $stage->identifier() != 'moderator') {
                $rowhtml .= $stage->get_allocation_table_cell($allocationrow->get_allocatable());
            }
        }

        // moderator
        if ($allocationrow->get_coursework()->moderation_agreement_enabled()) {
            $rowhtml .= $stage->get_moderation_table_cell($allocationrow->get_allocatable());
        }

        $rowhtml .= '</tr>';

        return $rowhtml;
    }

    /**
     * Makes the HTML table for allocating markers to students and returns it.
     *
     * @param mod_coursework_personal_deadlines_table $personaldeadlinestable
     * @return string
     */
    protected function render_mod_coursework_personal_deadlines_table(mod_coursework_personal_deadlines_table $personaldeadlinestable) {
        $courseworkpageurl = $this->get_router()->get_path('coursework', ['coursework' => $personaldeadlinestable->get_coursework()]);
        $tablehtml = '<div class="return_to_page">'.html_writer::link($courseworkpageurl, get_string('returntocourseworkpage', 'mod_coursework')).'</div>';

        $tablehtml .= '<div class="alert">'.get_string('nopersonaldeadlineforextensionwarning', 'mod_coursework').'</div>';

        $usergroups = $personaldeadlinestable->get_coursework()->get_allocatable_type();

        $tablehtml .= '<div class="largelink">'.html_writer::link('#', get_string('setdateforselected', 'mod_coursework', $personaldeadlinestable->get_coursework()->get_allocatable_type()), ['id' => 'selected_dates']).'</div>';

        if (has_capability('mod/coursework:revertfinalised', $this->page->context)) {
            $tablehtml .= '<div class="largelink">' . html_writer::link('#', get_string('unfinaliseselected', 'mod_coursework', $personaldeadlinestable->get_coursework()->get_allocatable_type()), ['id' => 'selected_unfinalise']) . '</div>';
        }
        $tablehtml .= '<br />';
        $url = $this->get_router()->get_path('edit personal deadline', []);

        $tablehtml .= '<form  action="'.$url.'" id="coursework_personal_deadline_form" method="post">';

        $tablehtml .= '<input type="hidden" name="courseworkid" value="'.$personaldeadlinestable->get_coursework()->id().'" />';
        $tablehtml .= '<input type="hidden" name="allocatabletype" value="'.$personaldeadlinestable->get_coursework()->get_allocatable_type().'" />';
        $tablehtml .= '<input type="hidden" name="setpersonaldeadlinespage" value="1" />';
        $tablehtml .= '<input type="hidden" name="multipleuserdeadlines" value="1" />';
        $tablehtml .= '<input type="hidden" name="selectedtype" id="selectedtype" value="date" />';

        $tablehtml .= '

            <table class="personal_deadline display">
                <thead>
                <tr>

        ';

        $allocatablecellhelper = $personaldeadlinestable->get_allocatable_cell();
        $personaldeadlinescellhelper = $personaldeadlinestable->get_personal_deadline_cell();
        $tablehtml .= '<th>';
        $tablehtml .= '<input type="checkbox" name="" id="selectall">';
        $tablehtml .= '</th>';
        $tablehtml .= '<th>';
        $tablehtml .= $allocatablecellhelper->get_table_header($personaldeadlinestable->get_options());
        $tablehtml .= '</th>';
        $tablehtml .= '<th>';
        $tablehtml .= $personaldeadlinescellhelper->get_table_header($personaldeadlinestable->get_options());
        $tablehtml .= '</th>';
        $tablehtml .= '<th>';
        $tablehtml .= get_string('tableheadstatus', 'mod_coursework');
        $tablehtml .= '</th>';

        $tablehtml .= '
                </tr>
                </thead>
                <tbody>
        ';

        $rowdata = $personaldeadlinestable->get_rows();
        foreach ($rowdata as $row) {
            $tablehtml .= $this->render_personal_deadline_table_row($row);
        }

        $tablehtml .= '
                </tbody>
            </table>
        ';

        $tablehtml .= '</form>';

        return $tablehtml;

    }

    /**
     * This is used on the old bulk personal deadlines page i.e. actions/set_personal_deadlines.php.
     * @param \mod_coursework\personal_deadline\table\row\builder $personaldeadlinerow
     * @return string
     */
    private function render_personal_deadline_table_row($personaldeadlinerow) {

        global $USER;

        $coursework = $personaldeadlinerow->get_coursework();

        $personaldeadline =
            \mod_coursework\models\personal_deadline::get_personal_deadline_for_student(user::find($personaldeadlinerow->get_allocatable()->id()), $coursework);

        if (!$personaldeadline) {
            $personaldeadline = \mod_coursework\models\personal_deadline::build(
                [
                    'allocatableid' => $personaldeadlinerow->get_allocatable()->id(),
                    'allocatabletype' => $personaldeadlinerow->get_allocatable()->type(),
                    'courseworkid' => $personaldeadlinerow->get_coursework()->id,
                ]
            );
        }

        $ability = new ability(user::find($USER), $coursework);
        $disabledelement = (!$personaldeadline ||($personaldeadline && $ability->can('edit', $personaldeadline)) ) ? "" : " disabled='disabled' ";

        $rowhtml = '<tr id="'. $personaldeadlinerow->get_allocatable()->type() . '_' . $personaldeadlinerow->get_allocatable()->id().'">';
        $rowhtml .= '<td>';
        $rowhtml .= '<input type="checkbox" name="allocatableid_arr['.$personaldeadlinerow->get_allocatable()->id().']" id="date_'. $personaldeadlinerow->get_allocatable()->type() . '_' . $personaldeadlinerow->get_allocatable()->id().'" class="date_select" value="'.$personaldeadlinerow->get_allocatable()->id().'" '.$disabledelement.' >';
        $rowhtml .= '<input type="hidden" name="allocatabletype_'.$personaldeadlinerow->get_allocatable()->id().'" value="'.$personaldeadlinerow->get_allocatable()->type().'" />';
        $rowhtml .= '</td>';

        $allocatablecellhelper = $personaldeadlinerow->get_allocatable_cell();
        $personaldeadlinescellhelper = $personaldeadlinerow->get_personal_deadline_cell();
        $rowhtml .= $allocatablecellhelper->get_table_cell($personaldeadlinerow);
        $rowhtml .= $personaldeadlinescellhelper->get_table_cell($personaldeadlinerow);
        $rowhtml .= '';
        $rowhtml .= "<td>".$personaldeadlinerow->get_submission_status()."</td>";
        $rowhtml .= '</tr>';

        return $rowhtml;
    }

    /**
     * Provides a summary of the marking progress for a coursework activity.
     *
     * This function generates the HTML for a marking summary, including counts of
     * submitted, needing marking, and published submissions, as well as details
     * for assessors and dropdown menus for export and upload actions.
     *
     * @param mod_coursework_coursework $coursework The coursework activity object.
     * @return stdClass Template data for the marking summary.
     */
    private function coursework_marking_summary(mod_coursework_coursework $coursework): stdClass {
        $template = new stdClass();

        // Edge case: for a single-marked coursework with marker allocation
        // enabled managers who can only add agreed grades cannot mark anyone.
        if (has_capability('mod/coursework:addinitialgrade', $coursework->get_context())
                || (!(has_capability('mod/coursework:addagreedgrade', $coursework->get_context()) && !$coursework->has_multiple_markers() && !$coursework->allocation_enabled())
                && has_any_capability(['mod/coursework:addagreedgrade', 'mod/coursework:administergrades'], $coursework->get_context()))) {
            $template->canmark = true;

            $participants = $this->get_allocatables_count_per_assessor($coursework);
            $allsubs = $coursework->get_all_submissions();
            $submitted = count($allsubs);

            $allocatedsubs = $this->get_submissions_for_assessor($coursework, $allsubs);
            $allocatedsubs = $this->remove_unfinalised_submissions($allocatedsubs);
            $allocatedsubs = $this->remove_ungradable_submissions($allocatedsubs);
            $finalgradedsubs = $this->removed_final_graded_submissions($allocatedsubs);
            $gradedcount = count($finalgradedsubs);
            $needsmarking = 0;
            $allocatedsubsforgrading = $allocatedsubs;
            $template->assessor = []; // Initialize the assessor array.
            $template->dropdown = $this->get_export_upload_links($coursework);

            // For users who can add agreed grades or administer grades (or a combination).
            if (has_any_capability(['mod/coursework:addagreedgrade', 'mod/coursework:administergrades'], $coursework->get_context())
                    || has_all_capabilities(['mod/coursework:addinitialgrade', 'mod/coursework:addallocatedagreedgrade'], $coursework->get_context())) {
                $numberofassessable = count($allocatedsubsforgrading);
                $allocatedsubsforgrading = $this->remove_final_gradable_submissions($allocatedsubsforgrading);
                $needsmarking = $numberofassessable - count($allocatedsubsforgrading);
            }

            // For users who can add initial grades or administer grades.
            if (has_any_capability(['mod/coursework:addinitialgrade', 'mod/coursework:administergrades'], $coursework->get_context())) {
                $allocatedsubsforinitial = $allocatedsubs; // Use the original set
                $allocatedsubsforinitial = $this->remove_final_gradable_submissions($allocatedsubsforinitial);
                $needsmarking += count($this->get_assessor_initial_graded_submissions($allocatedsubsforinitial));
            }

            $publishedsubs = $coursework->get_published_submissions();
            $published = count($this->get_submissions_for_assessor($coursework, $publishedsubs));

            $template->participants = $participants;
            $template->submitted = $submitted;
            $template->needsmarking = $needsmarking;
            $template->published = $published;

            // Assessor data.
            if ($coursework->has_multiple_markers() && has_capability('mod/coursework:administergrades', $coursework->get_context())) {
                // Agreed Mark count.
                $agreedstage = 'final_agreed_1';
                $agreedsubs = $coursework->get_graded_submissions_by_stage($agreedstage);
                $agreedmarkcount = count($this->get_submissions_for_assessor($coursework, $agreedsubs));
                $template->assessor[] = [
                    'border' => true,
                    'name' => get_string('markedagreemark', 'mod_coursework'),
                    'count' => $agreedmarkcount,
                ];

                $stages = $coursework->marking_stages();
                foreach ($stages as $stage => $s) {
                    if ($stage != 'final_agreed_1') {
                        $initialassessorno = substr("$stage", -1);
                        $gradedsubs = $coursework->get_graded_submissions_by_stage($stage);
                        $count = count($this->get_submissions_for_assessor($coursework, $gradedsubs));
                        $template->assessor[] = [
                            'name' => get_string('initialassessorno', 'mod_coursework', $initialassessorno),
                            'count' => $count,
                        ];
                    }
                }
            } else {
                // If no multiple markers, the 'graded' count essentially represents the 'marked' count.
                $gradedsubs = $this->get_submissions_with_final_grade($this->get_submissions_for_assessor($coursework, $allsubs));
                $template->assessor[] = [
                    'border' => true,
                    'name' => get_string('marked', 'mod_coursework'),
                    'count' => count($gradedsubs),
                ];
            }
        }

        return $template;
    }

    /**
     * Generates the dropdown data for export and upload links.
     *
     * @param mod_coursework_coursework $coursework The coursework activity object.
     * @return array An array containing the structured dropdown data.
     */
    private function get_export_upload_links(mod_coursework_coursework $coursework): array {
        $cmid = $this->page->cm->id;
        $viewurl = '/mod/coursework/view.php';
        $submissions = $coursework->get_all_submissions();
        $hasfinalised = $coursework->get_finalised_submissions();
        $finalised = submission::$pool[$coursework->id]['finalised'][1] ?? [];
        $can = fn(string $cap) => has_capability($cap, $this->page->context);
        $canmark = !empty($submissions) && $hasfinalised;

        // Export/Import options.
        $menuoptions = [
            'download' => [
                'name' => get_string('download'),
                'actions' => [
                    [
                        'url' => new moodle_url($viewurl, ['id' => $cmid, 'download' => 1]),
                        'lang' => 'download_submitted_files',
                        'cap' => ($finalised && !empty($submissions)),
                    ],
                    [
                        'url' => new moodle_url($viewurl, ['id' => $cmid, 'export' => 1]),
                        'lang' => 'downloadgrades',
                        'cap' => ($can('mod/coursework:viewallgradesatalltimes') && $can('mod/coursework:canexportfinalgrades') && $hasfinalised),
                    ],
                    [
                        'url' => new moodle_url($viewurl, ['id' => $cmid, 'export_grading_sheet' => 1]),
                        'lang' => 'downloadgradingsheets',
                        'cap' => $canmark,
                    ],
                ],
            ],
            'upload' => [
                'name' => get_string('upload'),
                'actions' => [
                    [
                        'url' => new moodle_url('/mod/coursework/actions/upload_grading_sheet.php', ['cmid' => $cmid]),
                        'lang' => 'uploadgradingsheet',
                        'cap' => $canmark,
                    ],
                    [
                        'url' => new moodle_url('/mod/coursework/actions/upload_feedback.php', ['cmid' => $cmid]),
                        'lang' => 'uploadfeedbackfiles',
                        'cap' => $canmark,
                    ],
                ],
            ],
        ];

        // Check user capability, and build download/upload dropdown menu actions.
        $dropdown = [];
        foreach ($menuoptions as $id => $option) {
            $actions = [];
            foreach ($option['actions'] as $action) {
                if ($action['cap']) {
                    $actions[] = [
                        'url' => $action['url']->out(false),
                        'title' => get_string($action['lang'], 'mod_coursework'),
                    ];
                }
            }

            if (!empty($actions)) {
                $dropdown[] = [
                    'id' => $id,
                    'name' => $option['name'],
                    'action' => $actions,
                ];
            }
        }

        return $dropdown;
    }

    /**
     * Get number of participants assessor can see on the grading page
     * @param coursework $coursework
     */
    public function get_allocatables_count_per_assessor($coursework) {
        global $USER;
        $participant = 0;
        $allocatables = $coursework->get_allocatables();

        if (!$coursework->has_multiple_markers() && has_capability('mod/coursework:addagreedgrade', $coursework->get_context()) &&
            !has_capability('mod/coursework:addinitialgrade', $coursework->get_context()) ) {

            $submissions = $coursework->get_all_submissions();

            foreach ($submissions as $sub) {
                $submission = submission::find($sub);
                if ( $submission->final_grade_agreed()) {

                    continue;
                } else if ( count($submission->get_assessor_feedbacks()) < $submission->max_number_of_feedbacks()) {
                    unset($submissions[$submission->id]);
                }
            }

            $participant = count($submissions);

        } else if (is_siteadmin($USER) || !$coursework->allocation_enabled() || has_any_capability(['mod/coursework:administergrades'], $coursework->get_context())) {
            $participant = count($allocatables);
        } else {
            foreach ($allocatables as $allocatable) {
                $submission = $allocatable->get_submission($coursework);

                if ($coursework->assessor_has_any_allocation_for_student($allocatable) || has_capability('mod/coursework:addagreedgrade', $coursework->get_context())
                    && !empty($submission) && (($submission->all_inital_graded() && !$coursework->sampling_enabled())
                        || ($coursework->sampling_enabled() && $submission->all_inital_graded() && $submission->max_number_of_feedbacks() > 1 ))) {
                    $participant  ++;
                }
            }
        }

        return $participant;
    }

    /**
     * Remove submissions that have not been finalised
     *
     * @param $submissions
     * @return mixed
     */
    public function remove_unfinalised_submissions($submissions) {

        foreach ($submissions as $sub) {

            $submission = submission::find($sub);

            if (empty($submission->finalised)) {
                unset($submissions[$sub->id]);
            }
        }

        return $submissions;
    }

    /**
     * Remove submissions that have final grade
     *
     * @param $submissions
     * @return mixed
     */
    public function removed_final_graded_submissions($submissions) {

        foreach ($submissions as $sub) {

            $submission = submission::find($sub);

            if (!empty($submission->get_final_grade() )) {
                unset($submissions[$sub->id]);
            }
        }

        return $submissions;
    }

    /**
     * Remove submissions that can't be graded
     *
     * @param $submissions
     * @return mixed
     * @throws coding_exception
     */
    public function remove_ungradable_submissions($submissions) {

        foreach ($submissions as $sub) {

            $submission = submission::find($sub);

            if (has_capability('mod/coursework:addallocatedagreedgrade', $submission->get_coursework()->get_context()) && !$submission->is_assessor_initial_grader() && $submission->all_inital_graded()) {
                unset($submissions[$sub->id]);
            }
        }

        return $submissions;
    }

    /**
     * Remove submissions that can be given final grade
     *
     * @param $submissions
     * @return mixed
     */
    public function remove_final_gradable_submissions($submissions) {

        foreach ($submissions as $sub) {

            $submission = submission::find($sub);
            if (!empty($submission->all_inital_graded()) ) {
                unset($submissions[$sub->id]);
            }
        }

        return $submissions;

    }

    /**
     * Get submission graded by assessor in initial stages
     *
     * @param $submissions
     * @return mixed
     * @throws coding_exception
     */
    public function get_assessor_initial_graded_submissions($submissions) {
        global $USER;

        foreach ($submissions as $sub) {

            $submission = submission::find($sub);

            if (count($submission->get_assessor_feedbacks()) >= $submission->max_number_of_feedbacks() || $submission->is_assessor_initial_grader()
                && (!has_capability('mod/coursework:administergrades', $submission->get_coursework()->get_context()) && !is_siteadmin($USER->id))) {

                // Is this submission assessable by this user at an inital gradig stage
                unset($submissions[$sub->id]);
            }
        }

        return $submissions;
    }

    /**
     * Get submissions that have final feedback
     *
     * @param $submissions
     * @return mixed
     * @throws exception
     */
    public function get_submissions_with_final_grade($submissions) {

        foreach ($submissions as $sub) {

            $submission = submission::find($sub);

            if (!$submission->get_final_feedback()) {
                unset($submissions[$sub->id]);

            }
        }

        return $submissions;
    }

    /**
     * Get submissions an assessor can see on the grading page or will be able to mark
     *
     * @param $coursework
     * @param $submissions
     * @return array
     * @throws coding_exception
     */
    public function get_submissions_for_assessor($coursework, $submissions) {
        global $USER;

        $gradeblesub = [];

        if (!$coursework->has_multiple_markers() && has_capability('mod/coursework:addagreedgrade', $coursework->get_context()) &&
            !has_capability('mod/coursework:addinitialgrade', $coursework->get_context()) ) {

            foreach ($submissions as $sub) {
                $submission = submission::find($sub);
                if ( $submission->final_grade_agreed()) {
                    continue;
                } else if (count($submission->get_assessor_feedbacks()) < $submission->max_number_of_feedbacks()) {
                        unset($submissions[$submission->id]);
                }
            }

            $gradeblesub = $submissions;

        } else if (is_siteadmin($USER) || !$coursework->allocation_enabled() || has_any_capability(['mod/coursework:administergrades'], $coursework->get_context())) {

            foreach ($submissions as $sub) {
                $submission = submission::find($sub);
                $gradeblesub[$submission->id] = $submission;
            }

        } else {
            foreach ($submissions as $sub) {
                $submission = submission::find($sub);
                if ($coursework->assessor_has_any_allocation_for_student($submission->reload()->get_allocatable()) || (has_capability('mod/coursework:addagreedgrade', $coursework->get_context()))
                    && !empty($submission) && (($submission->all_inital_graded() && !$submission->get_coursework()->sampling_enabled())
                        || ($submission->get_coursework()->sampling_enabled() && $submission->all_inital_graded() && $submission->max_number_of_feedbacks() > 1))) {

                    $gradeblesub[$submission->id] = $submission;
                }
            }
        }

        return $gradeblesub;
    }

}
