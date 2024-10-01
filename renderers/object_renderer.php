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
use mod_coursework\render_helpers\grading_report\cells;
global $CFG;

require_once($CFG->dirroot . '/lib/plagiarismlib.php');

/**
 * This deals with the specific objects that are part of the pages. The other renderer deals with the pages themselves.
 */
class mod_coursework_object_renderer extends plugin_renderer_base {

    /**
     * Renders a coursework feedback as a row in a table. This is for the grading report when we have
     * multiple markers and we want an AJAX pop up with details of the feedback. Also for the student view.
     *
     * @param feedback $feedback
     * @return string
     */
    public function render_feedback(feedback $feedback) {

        global $USER;

        $out = '';

        $submission = $feedback->get_submission();
        $coursework = $feedback->get_coursework();

        $table = new html_table();
        $table->attributes['class'] = 'feedback';
        $table->id = 'feedback_'. $feedback->id;

        // Header should say what sort of feedback it is.
        if ($feedback->is_agreed_grade()) {
            $title = get_string('finalfeedback', 'mod_coursework');
        } else if ($feedback->is_moderation()) {
            $title = get_string('moderatorfeedback', 'mod_coursework');
        } else {
            $a = $feedback->get_assessor_stage_no();
            $title = get_string('componentfeedback', 'mod_coursework', $a);
        }
        $header = new html_table_cell();
        $header->colspan = 2;
        $header->text = $title;
        // Student view is only for the student, who doesn't need to be told their own name.
        $header->text .= has_capability('mod/coursework:submit', $coursework->get_context()) ? '' :
            ': ' . $submission->get_allocatable_name();
        $table->head[] = $header;

        // Assessor who gave this feedback.
        $tablerow = new html_table_row();
        $tablerow->cells['left'] = get_string('assessor', 'mod_coursework');

        if (!has_capability('mod/coursework:submit', $coursework->get_context()) || is_siteadmin($USER->id) ) {
            $tablerow->cells['right'] = $feedback->get_assesor_username();
        } else {

            if ((!$submission->get_coursework()->sampling_enabled() || $submission->sampled_feedback_exists()) &&  $feedback->assessorid == 0 && $feedback->timecreated == $feedback->timemodified) {
                $tablerow->cells['right'] = get_string('automaticagreement', 'mod_coursework');
            } else {
                $tablerow->cells['right'] = $feedback->display_assessor_name();
            }
        }
        $table->data[] = $tablerow;

        // Grade row.
        $tablerow = new html_table_row();

        $leftcell = new html_table_cell();
        $rightcell = new html_table_cell();

        $nameforgrade = get_string('provisionalgrade', 'mod_coursework');
        $leftcell->text = $nameforgrade;
        // For final feedback, students should see the moderated grade, not the one awarded by the final grader.

        $gradejudge = new grade_judge($coursework);
        $rightcell->text = $gradejudge->grade_to_display($feedback->get_grade());
        $rightcell->id = 'final_feedback_grade';

        $tablerow->cells['left'] = $leftcell;
        $tablerow->cells['right'] = $rightcell;
        $table->data[] = $tablerow;

        // Feedback comment.
        $comment = $feedback->feedbackcomment;

        $tablerow = new html_table_row();
        $leftcell = new html_table_cell();
        $rightcell = new html_table_cell();

        $leftcell->text = get_string('feedbackcomment', 'mod_coursework');
        $rightcell->text = $comment;
        $rightcell->id = 'final_feedback_comment';

        $tablerow->cells['left'] = $leftcell;
        $tablerow->cells['right'] = $rightcell;
        $table->data[] = $tablerow;

        $tablerow = new html_table_row();
        $leftcell = new html_table_cell();
        $rightcell = new html_table_cell();

        $files = $feedback->get_feedback_files();

        if ($files) {
            $leftcell->text = get_string('feedbackfiles', 'mod_coursework');
            $rightcell->text = $this->render_feedback_files(new mod_coursework_feedback_files($files));
            $rightcell->id = 'final_feedback_files';

            $tablerow->cells['left'] = $leftcell;
            $tablerow->cells['right'] = $rightcell;
            $table->data[] = $tablerow;
        }

        // Rubric stuff if it's there
        if ($coursework->is_using_advanced_grading() && ($coursework->finalstagegrading == 0  || ($coursework->finalstagegrading == 1 &&  $feedback->stage_identifier != 'final_agreed_1'))) {
            $tablerow = new html_table_row();
            $leftcell = new html_table_cell();
            $rightcell = new html_table_cell();

            $controller = $coursework->get_advanced_grading_active_controller();
            $leftcell->text = 'Advanced grading';
            $rightcell->text = $controller->render_grade($this->page, $feedback->id, null, '', false);

            $tablerow->cells['left'] = $leftcell;
            $tablerow->cells['right'] = $rightcell;
            $table->data[] = $tablerow;
        }

        $out .= html_writer::table($table);

        // It seems html_table doesn't support colgroup, so manually add it here
        $colgroup = '<colgroup><col class="col1" style="width: 20%;"><col class="col2" style="width: 80%;"></colgroup>';
        $toreplace = '</table>';
        $pos = strrpos($out, $toreplace);

        $out = substr_replace($out, $colgroup . $toreplace, $pos, strlen($toreplace));

        return $out;
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
        $moderatedby = fullname(user::find($moderation->moderatorid));
        $lasteditedby = fullname(user::find($moderation->lasteditedby));

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
     * Renders a feedback as a table row. We may want an empty one for the user to add their own feedback.
     *
     * @param mod_coursework_assessor_feedback_row $feedbackrow
     * @return \html_table_row
     */
    protected function render_mod_coursework_assessor_feedback_row(mod_coursework_assessor_feedback_row $feedbackrow) {

        /**
         * NOT USED!!!!!!
         */

        global $USER, $COURSE;

        $row = new html_table_row();

        // Row attributes

        if ($feedbackrow->get_assessor_id() == $USER->id) {
            $row->attributes['class'] = 'coursework_own_feedback';
        }
        // Unique identifier for testing.
        // We can't add ids to the row.
        // Also might be the same person marking many students so needs to have the student id.
        // Do not say 'student so that it's not obvious that this is a way that blind marking could be circumvented.
        $row->attributes['class'] =
            "feedback-{$feedbackrow->get_assessor_id()}-{$feedbackrow->get_allocatable()->id()} {$feedbackrow->get_stage()->identifier()}";

        $existingfeedback = $feedbackrow->get_feedback();

        // Assessor cell: name, image and edit link.

        $cell = new html_table_cell();
        $assessor = $feedbackrow->get_assessor();

        $cell->text = $assessor->picture();
        $cell->text .= ' &nbsp;';
        $profilelinkurl = new moodle_url('/user/profile.php', ['id' => $assessor->id(),
                                                                    'course' => $COURSE->id]);
        $cell->text .= html_writer::link($profilelinkurl, $assessor->name());

        $row->cells['assessor'] = $cell;

        // Comment cell (includes edit feedback link)

        $cell = new html_table_cell();
        $cell->text = 'asdadas';
        // Edit feedback link.
        $submission = $feedbackrow->get_submission();
        $newfeedback = false;
        if (empty($existingfeedback)) {
            $params = [
                'assessorid' => $assessor->id(),
                'stage_identifier' => $feedbackrow->get_stage()->identifier(),
            ];
            if ($submission) {
                $params['submissionid'] = $submission->id;
            }
            $newfeedback = feedback::build($params);
        }

        $ability = new ability(user::find($USER), $feedbackrow->get_coursework());

        if ($existingfeedback && $ability->can('edit', $existingfeedback)) {

            $linktitle = get_string('edit');
            $icon = new pix_icon('edit', $linktitle, 'coursework', ['width' => '20px']);
            $linkid = "edit_feedback_" . $feedbackrow->get_feedback()->id;
            $link = $this->get_router()->get_path('edit feedback', ['feedback' => $feedbackrow->get_feedback()]);
            $iconlink = $this->output->action_icon($link, $icon, null, ['id' => $linkid]);
            $cell->text .= $iconlink;
        } else if ($newfeedback && $ability->can('new', $newfeedback)) {

            // New
            $linktitle = "new_feedback";
            $icon = new pix_icon('edit', $linktitle, 'coursework', ['width' => '20px']);

            $newfeedbackparams = [
                'submission' => $feedbackrow->get_submission(),
                'assessor' => $feedbackrow->get_assessor(),
                'stage' => $feedbackrow->get_stage(),
            ];
            $link = $this->get_router()->get_path('new feedback', $newfeedbackparams);
            $iconlink = $this->output->action_icon($link, $icon, null, ['class' => "new_feedback"]);
            $cell->text .= $iconlink;
        } else if ($existingfeedback && $ability->can('show', $existingfeedback)) {
            // Show - for managers and others who are reviewing the grades but who should
            // not be able to change them.

            $linktitle = get_string('viewfeedback', 'mod_coursework');
            $icon = new pix_icon('show', $linktitle, 'coursework', ['width' => '20px']);
            $linkid = "show_feedback_" . $feedbackrow->get_feedback()->id;
            $link = $this->get_router()->get_path('show feedback', ['feedback' => $feedbackrow->get_feedback()]);
            $iconlink = $this->output->action_icon($link, $icon, null, ['id' => $linkid]);
            $cell->text .= $iconlink;

        }

        if (!is_null($feedbackrow->get_grade()) && $feedbackrow->has_feedback()) {
            $maxgrade = $feedbackrow->get_max_grade();
            $feedbackgrade = $feedbackrow->get_grade();
            $gradestring = $this->output_grade_as_string($feedbackgrade, $maxgrade);
            $cell->text .= '&nbsp;' . get_string('grade', 'coursework') . ": " . $gradestring;
        }

        $row->cells['feedbackcomment'] = $cell;

        // Feedback time submitted cell.

        $cell = new html_table_cell();
        if ($feedbackrow->has_feedback()) {
            $cell->text = $feedbackrow ? userdate($feedbackrow->get_time_modified(), '%a, %d %b %Y, %H:%M') : '';
        }
        $row->cells['timemodified'] = $cell;

        return $row;
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

    public function render_feedback_files(mod_coursework_feedback_files $files) {

        $filesarray = [];
        $submissionfiles = $files->get_files();
        foreach ($submissionfiles as $file) {
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
    protected function render_mod_coursework_coursework(mod_coursework_coursework $coursework) {

        global $CFG, $USER;

        $out = '';

        if ($CFG->branch < 400) {
            // Show the details of the assessment (Name and introduction.
            $out .= html_writer::tag('h2', $coursework->name);
        }

        if (has_capability('mod/coursework:allocate', $coursework->get_context())) {
            $warnings = new warnings($coursework);
            $out .= $warnings->not_enough_assessors();
        }

        if ($CFG->branch < 400) {
            // Intro has it's own <p> tags etc.
            $out .= '<div class="description">';
            $out .= format_module_intro('coursework', $coursework, $coursework->get_coursemodule_id());
            $out .= '</div>';
        }

        // Deadlines section.
        $out .= html_writer::tag('h3', get_string('deadlines', 'coursework'));
        $out .= $this->coursework_deadlines_table($coursework);

        $cangrade = has_capability('mod/coursework:addinitialgrade', $this->page->context);
        $canpublish = has_capability('mod/coursework:publish', $this->page->context);
        $ispublished = $coursework->user_grade_is_published($USER->id);
        $allowedtoaddgeneralfeedback = has_capability('mod/coursework:addgeneralfeedback', $coursework->get_context());
        $canaddgeneralfeedback = has_capability('mod/coursework:addgeneralfeedback', $this->page->context);

        $out .= html_writer::tag('h3', get_string('gradingsummary', 'coursework'));
        $out .= $this->coursework_grading_summary_table($coursework);

        // Show general feedback if it's there and the deadline has passed or general feedback's date is not enabled which means it should be displayed automatically
        if (($coursework->is_general_feedback_enabled() && $allowedtoaddgeneralfeedback && (time() > $coursework->generalfeedback || $cangrade || $canpublish || $ispublished)) || !$coursework->is_general_feedback_enabled()) {
            $out .= html_writer::tag('h3', get_string('generalfeedback', 'coursework'));
            $out .= $coursework->feedbackcomment
                ? html_writer::tag('p', $coursework->feedbackcomment)
                : html_writer::tag('p', get_string('nofeedbackyet', 'coursework'));

            // General feedback Add edit link.
            if ($canaddgeneralfeedback) {
                $title = ($coursework->feedbackcomment) ? get_string('editgeneralfeedback', 'coursework') : get_string('addgeneralfeedback', 'coursework');
                $class = ($coursework->feedbackcomment) ? 'edit-btn' : 'add-general_feedback-btn';
                $out .= html_writer::tag('p', '', ['id' => 'feedback_text']);
                $link = new moodle_url('/mod/coursework/actions/general_feedback.php',
                                       ['cmid' => $coursework->get_coursemodule_id()]);
                $out .= html_writer::link($link,
                                          $title,
                                          ['class' => $class]);
                $out .= html_writer::empty_tag('br');
                $out .= html_writer::empty_tag('br');
            }
        }

        return $out;
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

        $tablehtml .= '

            <table class="allocations display">
                <thead>
                <tr>

        ';

        $options = $allocationtable->get_options();

        $pagingbar = new paging_bar($allocationtable->get_participant_count(), $options['page'], $options['perpage'],
            $this->page->url, 'page');

        $all = count($allocationtable->get_coursework()->get_allocatables());

        $recordsperpage = [3 => 3,
            10 => 10,
            20 => 20,
            30 => 30,
            40 => 40,
            50 => 50,
            100 => 100,
            $all => get_string('all', 'mod_coursework')]; // for boost themes instead of 'all' we can put 0, however currently it is a bug

        // Commenting these out as they appear unused and are causing exception in behat test.
        // $single_select_params = compact('sortby', 'sorthow', 'page');
        // $single_select_params['page'] = '0';
        $select = new single_select($this->page->url, 'per_page', $recordsperpage, $options['perpage'], null);
        $select->label = get_string('records_per_page', 'coursework');
        $select->class = 'jumpmenu';
        $select->formid = 'sectionmenu';
        $tablehtml .= $this->output->render($select);

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

        $rowdata = $allocationtable->get_table_rows_for_page();
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

        $tablehtml .= $this->output->render($select);

        $tablehtml .= $this->page->get_renderer('mod_coursework', 'object')->render($pagingbar);

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
     * Outputs the buttons etc to choose and trigger the auto allocation mechanism. Do this as part of the main form so we
     * can choose some allocations, then click a button to auto-allocate the rest.
     * @param mod_coursework_allocation_widget $allocationwidget
     * @throws \coding_exception
     * @return string
     */
    public function render_mod_coursework_allocation_widget(mod_coursework_allocation_widget $allocationwidget) {
        $langstr = ($allocationwidget->get_coursework()->moderation_agreement_enabled()) ? 'allocateassessorsandmoderators' : 'allocateassessors';
        $html = html_writer::tag('h2', get_string($langstr, 'mod_coursework'));

        $html .= '<div class="assessor-allocation-wrapper accordion">';

        $html .= html_writer::start_tag('h3', ['id' => 'assessor_allocation_settings_header']);
        $html .= get_string('assessorallocationstrategy', 'mod_coursework');
        // $html .= $this->output->help_icon('allocationstrategy', 'mod_coursework');
        $html .= html_writer::end_tag('h3');

        $html .= '<div class="allocation-strategy"';
        // Allow allocation method to be changed.
        $html .= html_writer::label(get_string('allocationstrategy', 'mod_coursework'), 'assessorallocationstrategy');

        $options = manager::get_allocation_classnames();
        $html .= html_writer::select($options,
                                     'assessorallocationstrategy',
                                     $allocationwidget->get_assessor_allocation_strategy(),
                                     '');

        // We want to allow the allocation strategy to add configuration options.
        $html .= html_writer::start_tag('div', ['class' => 'assessor-strategy-options-configs']);
        $html .= $this->get_allocation_strategy_form_elements($allocationwidget->get_coursework());
        $html .= html_writer::end_tag('div');
        $html .= "<br>";
        $attributes = ['id' => 'coursework_input_buttons'];
        $html .= html_writer::start_tag('div', $attributes);
        // Spacer so we get the button underneath the form stuff.
        $attributes = ['class' => 'coursework_spacer'];
        $html .= html_writer::start_tag('div', $attributes);
        $html .= html_writer::end_tag('div');

        // Save button.
        $attributes = ['name' => 'save',
            'type' => 'submit',
            'id' => 'save_assessor_allocation_strategy',
            'class' => 'coursework_assessor_allocation',
            'value' => get_string('apply', 'mod_coursework')];
        $html .= html_writer::empty_tag('input', $attributes);

        $attributes = ['name' => 'saveandexit',
            'type' => 'submit',
            'id' => 'save_and_exit_assessor_allocation_strategy',
            'class' => 'coursework_assessor_allocation',
            'value' => get_string('save_and_exit', 'mod_coursework')];
        $html .= html_writer::empty_tag('input', $attributes);
        $html .= html_writer::end_tag('div');
        $html .= '</div>';
        $html .= '</div>';

        return $html;
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
    protected function get_allocation_strategy_form_elements($coursework) {

        global $CFG;

        $html = '';

        $classdir = $CFG->dirroot . '/mod/coursework/classes/allocation/strategy';
        $fullclasspaths = glob($classdir . '/*.php');
        foreach ($fullclasspaths as $fullclassname) {
            if (strpos($fullclassname, 'base') !== false) {
                continue;
            }
            preg_match('/([^\/]+).php/', $fullclassname, $matches);
            $classname = $matches[1];
            $fullclassname = '\mod_coursework\allocation\strategy\\' . $classname;
            // We want the elements from all the strategies so we can show/hide them.
            /* @var \mod_coursework\allocation\strategy\base $strategy */
            $strategy = new $fullclassname($coursework);

            $attributes = [
                'class' => 'assessor-strategy-options',
                'id' => 'assessor-strategy-' . $classname,
            ];
            // Hide this if it's not currently selected.
            $strategytype = 'assessorallocationstrategy';
            if ($classname !== $coursework->$strategytype) {
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
     * @param mod_coursework_coursework $coursework
     * @return string
     * @throws coding_exception
     */
    protected function coursework_deadlines_table(mod_coursework_coursework $coursework) {
        global $USER;

        $deadlineextension =
            \mod_coursework\models\deadline_extension::get_extension_for_student(user::find($USER), $coursework);

        $personaldeadline =
            \mod_coursework\models\personal_deadline::get_personal_deadline_for_student(user::find($USER), $coursework);

        $normaldeadline = $coursework->deadline;

        if ($personaldeadline) {
            $normaldeadline = $personaldeadline->personal_deadline;
        }
        $deadlineheadertext = get_string('deadline', 'coursework');
        if ($coursework->personal_deadlines_enabled() && (!has_capability('mod/coursework:submit', $this->page->context) || is_siteadmin($USER))) {
            $deadlineheadertext .= "<br>". get_string('default_deadline', 'coursework');
        }
        $deadlinedate = '';

        if ($deadlineextension) {
            $deadlinedate .= '<span class="crossed-out">';
            $deadlinedate .= userdate($normaldeadline, '%a, %d %b %Y, %H:%M');
            $deadlinedate .= '</span>';
        } else if ($coursework->has_deadline()) {
            $deadlinedate .= userdate($normaldeadline, '%a, %d %b %Y, %H:%M');
        } else {
            $deadlinedate .= get_string('nocourseworkdeadline', 'mod_coursework');
        }

        $deadlinemessage = '';
        if ($coursework->has_deadline()) {
            if ($coursework->allow_late_submissions()) {
                $latemessage = get_string('latesubmissionsallowed', 'mod_coursework');
                $lateclass = 'text-success';
            } else {
                $latemessage = get_string('nolatesubmissions', 'mod_coursework');
                $lateclass = $coursework->deadline_has_passed() ? 'text-error' : 'text-warning';
            }
            $latemessage .= ' ';
            $deadlinemessage = html_writer::start_tag('span', ['class' => $lateclass]);
            $deadlinemessage .= $latemessage;
            $deadlinemessage .= html_writer::end_tag('span');
        }

        // Does the user have an extension?

        $deadlineextensionmessage = '';
        if ($deadlineextension) {
            $deadlineextensionmessage .= html_writer::start_tag('div');
            $deadlineextensionmessage .= '<span class="text-success">You have an extension!</span><br> Your deadine is: '
                . userdate($deadlineextension->extended_deadline);
            $deadlineextensionmessage .= html_writer::end_tag('div');
        }

        if ($coursework->has_deadline()) {
            $deadlinemessage .= html_writer::start_tag('div', ['class' => 'autofinalise_info']);
            $deadlinemessage .= ($coursework->personal_deadlines_enabled() && (!has_capability('mod/coursework:submit', $this->page->context) || is_siteadmin($USER)))
                ? get_string('personal_deadline_warning', 'mod_coursework') : get_string('deadline_warning', 'mod_coursework');
            $deadlinemessage .= html_writer::end_tag('div');
        }

        $tablehtml = '
        <table class="deadlines display">
          <tbody>
            <tr class="r0">
              <th >'.$deadlineheadertext.'</th>
              <td >'. $deadlinedate.'<br />
                '.$deadlineextensionmessage.'
                '. $deadlinemessage.'</td>
            </tr>
        ';

        if ($coursework->is_general_feedback_enabled() && $coursework->generalfeedback) {
            $generalfeedbackheader = get_string('generalfeedbackdeadline', 'coursework') . ': ';
            $generalfeedbackdeadline = $coursework->get_general_feedback_deadline();
            $generalfeedbackdeadlinemessage = $generalfeedbackdeadline
                ? userdate($generalfeedbackdeadline, '%a, %d %b %Y, %H:%M')
                : get_string('notset', 'coursework');

            $tablehtml .= '
                <tr class="r1">
                  <th>'. $generalfeedbackheader.'</th>
                  <td class="cell c1">'. $generalfeedbackdeadlinemessage.'</td>
                </tr>

            ';
        }

        if ($coursework->individualfeedback) {

            $individualfeedbackheader = get_string('individualfeedback', 'coursework');
            $individualfeedbackdeadline = $coursework->get_individual_feedback_deadline();
            $indivisualfeedbackmessage = $individualfeedbackdeadline
                ? userdate($individualfeedbackdeadline, '%a, %d %b %Y, %H:%M')
                : get_string('notset', 'coursework');

            $tablehtml .= '
                <tr class="r1">
                  <th>'. $individualfeedbackheader.'</th>
                  <td class="cell c1">'. $indivisualfeedbackmessage.'</td>
                </tr>

            ';
        }

        $tablehtml .= '
            </tbody>
        </table>
        ';

        return $tablehtml;
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
     * @param \mod_coursework\personal_deadline\table\row\builder $personaldeadlinerow
     * @return string
     */
    private function render_personal_deadline_table_row($personaldeadlinerow) {

        global $USER;

        $coursework = $personaldeadlinerow->get_coursework();

        $newpersonaldeadlineparams = [
            'allocatableid' => $personaldeadlinerow->get_allocatable()->id(),
            'allocatabletype' => $personaldeadlinerow->get_allocatable()->type(),
            'courseworkid' => $personaldeadlinerow->get_coursework()->id,
        ];

        // $personal_deadline = \mod_coursework\models\personal_deadline::find($new_personal_deadline_params);

        $personaldeadline =
            \mod_coursework\models\personal_deadline::get_personal_deadline_for_student(user::find($personaldeadlinerow->get_allocatable()->id()), $coursework);

        if (!$personaldeadline) {
            $personaldeadline = \mod_coursework\models\personal_deadline::build($newpersonaldeadlineparams);
        }

        $ability = new ability(user::find($USER), $coursework);
        $disabledelement = (!$personaldeadline ||($personaldeadline && $ability->can('edit', $personaldeadline)) ) ? "" : " disabled='disabled' ";

        $rowhtml = '<tr id="'. $personaldeadlinerow->get_allocatable()->type() . '_' . $personaldeadlinerow->get_allocatable()->id().'">';
        $rowhtml .= '<td>';
        $rowhtml .= '<input type="checkbox" name="allocatableid_arr['.$personaldeadlinerow->get_allocatable()->id().']" id="date_'. $personaldeadlinerow->get_allocatable()->type() . '_' . $personaldeadlinerow->get_allocatable()->id().'" class="date_select" value="'.$personaldeadlinerow->get_allocatable()->id().'" '.$disabledelement.' >';
        $rowhtml .= '<input type="hidden" name="allocatabletype_'.$personaldeadlinerow->get_allocatable()->id().'" value="'.$personaldeadlinerow->get_allocatable()->type().'" />';
        $rowhtml .= '</td>';

        $newpersonaldeadlineparams = [
            'allocatableid' => $personaldeadlinerow->get_allocatable()->id(),
            'allocatabletype' => $personaldeadlinerow->get_allocatable()->type(),
            'courseworkid' => $personaldeadlinerow->get_coursework()->id,
            'setpersonaldeadlinespage' => '1',
        ];

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
     * @param mod_coursework_coursework $coursework
     * @return string
     * @throws coding_exception
     */
    protected function coursework_grading_summary_table(mod_coursework_coursework $coursework) {
        global $USER;

        $gradedheader = "";

        $warningmessage = "";
        $stagename = $coursework->has_multiple_markers() ? ' (Agreed grade)' : '';

        $participants = 0;
        $submitted = 0;
        $needsgrading = 0;
        $graded = 0;
        $finalgrade = 0;
        $published = 0;

        if (!$coursework->has_multiple_markers() && !$coursework->allocation_enabled() && !has_capability('mod/coursework:addinitialgrade', $coursework->get_context())
            && has_capability('mod/coursework:addagreedgrade', $coursework->get_context())) {

            $warningmessage = "<tr><td colspan='2'>You don't have a capability to grade anyone in this Coursework</td></tr>";

        } else {

            $participants = $this->get_allocatables_count_per_assessor($coursework);

            $allsubmissions = $coursework->get_all_submissions();
            $assessablesubmittedsubmissions = $this->get_submissions_for_assessor($coursework, $allsubmissions);
            $submitted = count($assessablesubmittedsubmissions);

            $assessablesubmittedsubmissions = $this->remove_unfinalised_submissions($assessablesubmittedsubmissions);

            $assessablesubmittedsubmissions = $this->remove_ungradable_submissions($assessablesubmittedsubmissions);

            // Remove all submission with final grade
            $assessablesubmittedsubmissions = $this->removed_final_graded_submissions($assessablesubmittedsubmissions);

            // If has addagreedgrade or administergrade or addallocatedagreedgrade+initialgrade
            if (has_any_capability(['mod/coursework:addagreedgrade', 'mod/coursework:administergrades'], $coursework->get_context())
                || (has_capability('mod/coursework:addinitialgrade', $coursework->get_context()) && has_capability('mod/coursework:addallocatedagreedgrade', $coursework->get_context()))) {

                // Count number of submissions at final grade stage
                $numberofassessable = count($assessablesubmittedsubmissions);

                $assessablesubmittedsubmissions = $this->remove_final_gradable_submissions($assessablesubmittedsubmissions);

                $needsgrading = $numberofassessable - count($assessablesubmittedsubmissions);
            }

            // If has initialgrade
            if (has_any_capability(['mod/coursework:addinitialgrade', 'mod/coursework:administergrades'], $coursework->get_context())) {

                $assessablesubmittedsubmissions = $this->remove_final_gradable_submissions($assessablesubmittedsubmissions);
                $needsgrading += count($this->get_assessor_initial_graded_submissions($assessablesubmittedsubmissions));
            }

            $gradedsubmissions = $this->get_submissions_with_final_grade($this->get_submissions_for_assessor($coursework, $allsubmissions));
            $graded = count($gradedsubmissions);

            $finalgrade = $graded;
            // display breakdown of marks for initial stages
            if ($coursework->has_multiple_markers() && has_capability('mod/coursework:administergrades', $coursework->get_context())) {
                $stages = $coursework->marking_stages();
                foreach ($stages as $stage => $s) {
                    if ($stage != 'final_agreed_1') {
                        $initialassessorno = substr("$stage", -1);
                        $gradedsubmissions = $coursework->get_graded_submissions_by_stage($stage);
                        $grade = count($this->get_submissions_for_assessor($coursework, $gradedsubmissions));
                        $gradedheader .= "<br>" . get_string('initialassessorno', 'mod_coursework', $initialassessorno);
                        $finalgrade .= "<br>" . $grade;
                    }
                }
            }

            $publishedsubmissions = $coursework->get_published_submissions();
            $published = count($this->get_submissions_for_assessor($coursework, $publishedsubmissions));

        }

        // BUILD table
        $tablehtml = '<table class="gradingsummary display"><tbody>';
        $tablehtml .= $warningmessage;
        // participants row
        $tablehtml .= '<tr><th >'.get_string('participants', 'mod_coursework').'</th><td>'.$participants.'</td></tr>';
        // number of submission row
        $tablehtml .= '<tr><th >'.get_string('submitted', 'mod_coursework').'</th><td>'.$submitted.'</td></tr>';
        // submissions needs grading row
        $tablehtml .= '<tr><th >'.get_string('needsgrading', 'mod_coursework').'</th><td>'.$needsgrading.'</td></tr>';
        // submissions graded
        if (has_capability('mod/coursework:addinitialgrade', $coursework->get_context()) && !is_siteadmin($USER)) {
            $tablehtml .= '<tr><th >' . get_string('graded', 'mod_coursework') . $stagename . $gradedheader . '</th><td>' . $graded . '</td></tr>';
        } else {
            $tablehtml .= '<tr><th >' . get_string('graded', 'mod_coursework') . $stagename . $gradedheader . '</th><td>' . $finalgrade . '</td></tr>';
        }
        // submissions graded and published
        $tablehtml .= '<tr><th >'.get_string('gradedandpublished', 'mod_coursework').'</th><td>'.$published.'</td></tr>';

        $tablehtml .= '</tbody></table>';

        return $tablehtml;
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
