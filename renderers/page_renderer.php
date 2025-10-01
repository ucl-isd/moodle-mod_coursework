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
use mod_coursework\forms\assessor_feedback_mform;
use mod_coursework\forms\moderator_agreement_mform;
use mod_coursework\forms\plagiarism_flagging_mform;
use mod_coursework\forms\student_submission_form;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\models\moderation;
use mod_coursework\models\plagiarism_flag;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use mod_coursework\router;
use mod_coursework\warnings;

/**
 * Makes the pages
 */
class mod_coursework_page_renderer extends plugin_renderer_base {

    /**
     * @param feedback $feedback
     */
    public function show_feedback_page($feedback) {
        $html = '';
        $objectrenderer = $this->get_object_renderer();
        $html .= $this->output->header();
        $html .= $objectrenderer->render_feedback($feedback);
        $html .= $this->output->footer();
        return $html;
    }

    /**
     * @param moderation $moderation
     */
    public function show_moderation_page($moderation) {
        $html = '';

        $objectrenderer = $this->get_object_renderer();
        $html .= $objectrenderer->render_moderation($moderation);

        echo $this->output->header();
        echo $html;
        echo $this->output->footer();
    }

    /**
     * Renders the HTML for the edit page
     *
     * @param feedback $teacherfeedback
     * @param $assessor
     * @param $editor
     * @throws coding_exception
     */
    public function edit_feedback_page(feedback $teacherfeedback, $assessor, $editor) {

        global $SITE;

        $areagreeing = $teacherfeedback->stage_identifier == 'final_agreed_1';
        $gradingtitle = $areagreeing
            ? get_string('gradingforagree', 'coursework', $teacherfeedback->get_submission()->get_allocatable_name())
            : get_string('gradingfor', 'coursework', $teacherfeedback->get_submission()->get_allocatable_name());

        $this->page->navbar->add($gradingtitle);
        $this->page->set_title($SITE->fullname);
        $this->page->set_heading($SITE->fullname);

        // Template grading details.
        $template = new stdClass();
        $template->title = $gradingtitle;
        // Marker.
        $template->marker = ($teacherfeedback->assessorid == 0) ? get_string('automaticagreement', 'mod_coursework') : fullname($assessor);

        // Submission.
        $submission = $teacherfeedback->get_submission();
        $files = $submission->get_submission_files();
        $objectrenderer = $this->get_object_renderer();
        $template->submission = $objectrenderer->render_submission_files_with_plagiarism_links(new \mod_coursework_submission_files($files), false);

        // Last edit.
        $lastmarked = ((!$teacherfeedback->get_coursework()->sampling_enabled() || $teacherfeedback->get_submission()->sampled_feedback_exists())
            && $teacherfeedback->assessorid == 0 && $teacherfeedback->timecreated == $teacherfeedback->timemodified )
            ? get_string('automaticagreement', 'mod_coursework') : fullname($editor);
        $template->lasteditedby = $lastmarked . userdate($teacherfeedback->timemodified, '%a, %d %b %Y, %H:%M');

        $submiturl = $this->get_router()->get_path('update feedback', ['feedback' => $teacherfeedback]);
        $simpleform = new assessor_feedback_mform(
            $submiturl, ['feedback' => $teacherfeedback]
        );

        $teacherfeedback->feedbackcomment = [
            'text' => $teacherfeedback->feedbackcomment,
            'format' => $teacherfeedback->feedbackcommentformat,
        ];

        // Load any files into the file manager.
        $draftitemid = file_get_submitted_draft_itemid('feedback_manager');
        file_prepare_draft_area($draftitemid,
                                $teacherfeedback->get_context()->id,
                                'mod_coursework',
                                'feedback',
                                $teacherfeedback->id);
        $teacherfeedback->feedback_manager = $draftitemid;

        $simpleform->set_data($teacherfeedback);

        $isusingmarkingguide = $teacherfeedback->get_coursework()->is_using_marking_guide();
        // Set page to wide for marking guide.
        $this->page->set_pagelayout($isusingmarkingguide ? 'incourse' : 'standard');

        $this->page->set_title($SITE->fullname);
        $this->page->set_heading($SITE->fullname);
        echo $this->output->header();
        echo $this->render_from_template('mod_coursework/marking_details', $template);
        // SHAME - Can we add an id to the form.
        echo "<div id='coursework-markingform'>";
        $simpleform->display();
        echo "</div>";
        echo $this->output->footer();
    }

    public function confirm_feedback_removal_page(feedback $teacherfeedback, $confirmurl) {
        global $SITE;

        $gradingtitle =
            get_string('gradingfor', 'coursework', $teacherfeedback->get_submission()->get_allocatable_name());

        $this->page->set_pagelayout('standard');
        $this->page->navbar->add($gradingtitle);
        $this->page->set_title($SITE->fullname);
        $this->page->set_heading($SITE->fullname);

        echo $this->output->header();
        echo $this->output->confirm(get_string('confirmremovefeedback', 'mod_coursework'), $confirmurl, $this->page->url);
        echo $this->output->footer();
    }

    /**
     * Renders the HTML for the edit page
     *
     * @param moderation $moderatoragreement
     * @param $assessor
     * @param $editor
     */
    public function edit_moderation_page(moderation $moderatoragreement, $assessor, $editor) {

        global $SITE;

        $title =
            get_string('moderationfor', 'coursework', $moderatoragreement->get_submission()->get_allocatable_name());

        $this->page->set_pagelayout('standard');
        $this->page->navbar->add($title);
        $this->page->set_title($SITE->fullname);
        $this->page->set_heading($SITE->fullname);

        $html = '';

        $moderatedby = fullname($assessor);
        $lasteditedby = fullname($editor);

        $html .= $this->output->heading($title);
        $html .= '<table class = "moderating-details">';
        $html .= '<tr><th>' . get_string('moderatedby', 'coursework') . '</th><td>' . $moderatedby . '</td></tr>';
        $html .= '<tr><th>' . get_string('lasteditedby', 'coursework') . '</th><td>' . $lasteditedby . ' on ' .
            userdate($moderatoragreement->timemodified, '%a, %d %b %Y, %H:%M') . '</td></tr>';
        $html .= '</table>';

        $submiturl = $this->get_router()->get_path('update moderation', ['moderation' => $moderatoragreement]);
        $simpleform = new moderator_agreement_mform($submiturl, ['moderation' => $moderatoragreement]);

        $moderatoragreement->modcomment = ['text' => $moderatoragreement->modcomment,
                                                'format' => $moderatoragreement->modcommentformat];

        $simpleform->set_data($moderatoragreement);

        echo $this->output->header();
        echo $html;
        $simpleform->display();
        echo $this->output->footer();
    }

    /**
     * @param \mod_coursework\models\coursework $coursework
     * @param user $student
     * @return string
     */
    public function student_view_page($coursework, $student) {
        // Coursework not yet open for submissions.
        if (!$coursework->start_date_has_passed()) {
            $template = new stdClass();
            $template->startdate = $coursework->startdate;
            return $this->render_from_template('mod_coursework/submission', $template);
        }

        // If coursework groups and the student is not in any group.
        if ($coursework->is_configured_to_have_group_submissions() && !$coursework->student_is_in_any_group($student)) {
            $template = new stdClass();
            $template->notingroup = true;
            return $this->render_from_template('mod_coursework/submission', $template);
        }

        // $submission here means the existing stuff. Might be the group of the student. The only place where
        // it matters in in pre-populating the form, where it should be empty if this student did not submit
        // the files.
        $submission = $coursework->get_user_submission($student);
        if (!$submission) {
            $submission = $coursework->build_own_submission($student);
        }

        // This should probably not be in the renderer.
        if ($coursework->has_individual_autorelease_feedback_enabled() &&
            $coursework->individual_feedback_deadline_has_passed() &&
            !$submission->is_published() && $submission->ready_to_publish()
        ) {
            $submission->publish();
        }

        // WIP - student overview.
        $ability = new ability($student, $coursework);

        if ($coursework->start_date_has_passed()) {
            // Main data.
            // TODO - feels odd. Why is this a seperate function?
            // Probably single function with data and buttons would be better?
            $template = $this->coursework_student_overview($submission);
            $template->cansubmit = true;

            // Buttons from here on down.
            // Add/Edit links.
            if ($ability->can('new', $submission)) {
                $template->editurl = $this->get_router()->get_path('new submission', ['submission' => $submission], true);
            } else if ($submission && $ability->can('edit', $submission)) {
                $template->editurl = $this->get_router()->get_path('edit submission', ['submission' => $submission], true);
            }

            // Finalise.
            if ($submission && $submission->id && $ability->can('finalise', $submission)) {
                $template->final = $this->finalise_submission_button($coursework, $submission);
            }
        }

        if ($ability->can('new', $submission)) {
            if ($coursework->start_date_has_passed()) {
                $template->submissionbutton = $this->new_submission_button($submission);
            }
        } else if ($submission && $ability->can('edit', $submission)) {
            $template->submissionbutton = $this->edit_submission_button($coursework, $submission);
        }

        return $this->render_from_template('mod_coursework/submission', $template);
    }

    /**
     * Makes the HTML interface that allows us to specify what student we wish to display the submission form for.
     * This has to come first so that we can load the student submission form with the relevant student id.
     *
     * @param int $coursemoduleid
     * @param \mod_coursework\forms\choose_student_for_submission_mform $chooseform
     * @internal param \coursework $coursework
     * @return string HTML
     */
    public function choose_student_to_submit_for($coursemoduleid, $chooseform) {
        // Drop down to choose the student if we have no student id.
        // We don't really need to process this form, we just get the studentid as a param and use it.
        $html = '';

        $html .= $this->output->header();

        $chooseform->set_data(['cmid' => $coursemoduleid]);
        ob_start(); // Forms library echos stuff.
        $chooseform->display();
        $html .= ob_get_contents();
        ob_end_clean();

        $html .= $this->output->footer();

        return $html;
    }

    /**
     * @param $newfeedback
     * @throws coding_exception
     */
    public function new_feedback_page(feedback $newfeedback) {
        global $SITE, $DB;

        $submission = $newfeedback->get_submission();
        $areagreeing = optional_param('stage_identifier', '', PARAM_TEXT) == 'final_agreed_1';
        $gradingtitle = $areagreeing
            ? get_string('gradingforagree', 'coursework', $submission->get_allocatable_name())
            : get_string('gradingfor', 'coursework', $submission->get_allocatable_name());

        $this->page->navbar->add($gradingtitle);
        $this->page->set_title($SITE->fullname);
        $this->page->set_heading($SITE->fullname);

        // Template grading details.
        $template = new stdClass();
        $template->title = $gradingtitle;
        // Warning in case there is already some feedback from another teacher.
        $conditions = ['submissionid' => $newfeedback->submissionid,
                            'stage_identifier' => $newfeedback->stage_identifier];
        if (feedback::exists($conditions)) {
            $template->alert = 'Another user has already submitted feedback for this student. Your changes will not be saved.';
        }

        // Marker.
        $marker = $DB->get_record('user', ['id' => $newfeedback->assessorid]);
        $template->marker = fullname($marker);

        // Submission.
        $files = $submission->get_submission_files();
        $objectrenderer = $this->get_object_renderer();
        $template->submission = $objectrenderer->render_submission_files_with_plagiarism_links(
            new \mod_coursework_submission_files($files), false
        );

        $submiturl = $this->get_router()->get_path('create feedback', ['feedback' => $newfeedback]);
        $simpleform = new assessor_feedback_mform($submiturl, ['feedback' => $newfeedback]);

        $coursework = coursework::find($newfeedback->get_submission()->get_coursework()->id());

        // auto-populate Agreed Feedback with comments from initial marking
        if ($coursework && $coursework->autopopulatefeedbackcomment_enabled() && $newfeedback->stage_identifier == 'final_agreed_1') {
            // get all initial stages feedbacks for this submission
            $initialfeedbacks = $DB->get_records('coursework_feedbacks', ['submissionid' => $newfeedback->submissionid]);

            $teacherfeedback = new feedback();
            $feedbackcomment = '';
            $count = 1;
            foreach ($initialfeedbacks as $initialfeedback) {
                // put all initial feedbacks together for the comment field
                $feedbackcomment .= get_string('assessorcomments', 'mod_coursework', $count);
                $feedbackcomment .= $initialfeedback->feedbackcomment;
                $feedbackcomment .= '<br>';
                $count ++;
            }

            $teacherfeedback->feedbackcomment = ['text' => $feedbackcomment];
            // popululate the form with initial feedbacks
            $simpleform->set_data($teacherfeedback);
        }

        $needswidepage = $coursework->is_using_marking_guide();
        $this->page->set_pagelayout($needswidepage ? 'incourse' : 'standard');
        $this->page->navbar->add($gradingtitle);
        $this->page->set_title($SITE->fullname);
        $this->page->set_heading($SITE->fullname);
        echo $this->output->header();
        echo $this->render_from_template('mod_coursework/marking_details', $template);
        // SHAME - Can we add an id to the form.
        echo "<div id='coursework-markingform'>";
        $simpleform->display();
        echo "</div>";
        echo $this->output->footer();
    }

    /**
     * @param moderation $newmoderation
     * @throws coding_exception
     */
    public function new_moderation_page($newmoderation) {

        global $SITE, $DB;

        $submission = $newmoderation->get_submission();
        $gradingtitle = get_string('moderationfor', 'coursework', $submission->get_allocatable_name());

        $this->page->set_pagelayout('standard');
        $this->page->navbar->add($gradingtitle);
        $this->page->set_title($SITE->fullname);
        $this->page->set_heading($SITE->fullname);

        $html = '';

        $html .= $this->output->heading($gradingtitle);
        $html .= '<table class = "moderating-details">';
        $moderator = $DB->get_record('user', ['id' => $newmoderation->moderatorid]);
        $html .= '<tr><th>' . get_string('moderator', 'coursework') . '</th><td>' . fullname($moderator) . '</td></tr>';
        $html .= '</table>';

        $submiturl = $this->get_router()->get_path('create moderation agreement', ['moderation' => $newmoderation]);
        $simpleform = new moderator_agreement_mform($submiturl, ['moderation' => $newmoderation]);
        echo $this->output->header();
        echo $html;
        $simpleform->display();
        echo $this->output->footer();
    }

    /**
     * @param plagiarism_flag $newplagiarismflag
     * @throws coding_exception
     */
    public function new_plagiarism_flag_page($newplagiarismflag) {

        global $SITE, $DB;

        $submission = $newplagiarismflag->get_submission();
        $gradingtitle = get_string('plagiarismflaggingfor', 'coursework', $submission->get_allocatable_name());

        $this->page->set_pagelayout('standard');
        $this->page->navbar->add($gradingtitle);
        $this->page->set_title($SITE->fullname);
        $this->page->set_heading($SITE->fullname);

        $html = '';

        $html .= $this->output->heading($gradingtitle);

        $submiturl = $this->get_router()->get_path('create plagiarism flag', ['plagiarism_flag' => $newplagiarismflag]);
        $simpleform = new plagiarism_flagging_mform($submiturl, ['plagiarism_flag' => $newplagiarismflag]);
        echo $this->output->header();
        echo $html;
        $simpleform->display();
        echo $this->output->footer();

    }

    /**
     * @param plagiarism_flag $plagiarismflag
     * @param $creator
     * @param $editor
     * @throws coding_exception
     */
    public function edit_plagiarism_flag_page(plagiarism_flag $plagiarismflag, $creator, $editor) {

        global $SITE, $DB;

        $submission = $plagiarismflag->get_submission();
        $gradingtitle = get_string('plagiarismflaggingfor', 'coursework', $submission->get_allocatable_name());

        $this->page->set_pagelayout('standard');
        $this->page->navbar->add($gradingtitle);
        $this->page->set_title($SITE->fullname);
        $this->page->set_heading($SITE->fullname);

        $html = '';

        $createddby = fullname($creator);
        $lasteditedby = fullname($editor);

        $html .= $this->output->heading($gradingtitle);

        $html .= '<table class = "plagiarism-flag-details">';
        $html .= '<tr><th>' . get_string('createdby', 'coursework') . '</th><td>' . $createddby . '</td></tr>';
        $html .= '<tr><th>' . get_string('lasteditedby', 'coursework') . '</th><td>' . $lasteditedby . ' on ' .
            userdate($plagiarismflag->timemodified, '%a, %d %b %Y, %H:%M') . '</td></tr>';
        $html .= '</table>';

        if ($submission->is_published()) {
            $html .= '<div class ="alert">' . get_string('gradereleasedtostudent', 'coursework') . '</div>';
        }

        $submiturl = $this->get_router()->get_path('update plagiarism flag', ['flag' => $plagiarismflag, 'submission' => $submission]);
        $simpleform = new plagiarism_flagging_mform($submiturl, ['plagiarism_flag' => $plagiarismflag]);

        $plagiarismflag->plagiarismcomment = ['text' => $plagiarismflag->comment,
                                                    'format' => $plagiarismflag->comment_format];

        $simpleform->set_data($plagiarismflag);
        echo $this->output->header();
        echo $html;
        $simpleform->display();
        echo $this->output->footer();

    }

    /**
     * @param coursework $coursework
     * @param $page
     * @param $perpage
     * @param $sortby
     * @param $sorthow
     * @param $group
     * @param $firstnamealpha
     * @param $lastnamealpha
     * @param $groupnamealpha
     * @return string
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function teacher_grading_page($coursework, $page, $perpage, $sortby, $sorthow, $group, $firstnamealpha, $lastnamealpha, $groupnamealpha) {
        $html = '';

        // Grading report display options.
        $reportoptions = [];
        $reportoptions['page'] = $page;
        $reportoptions['group'] = $group;
        $reportoptions['perpage'] = $perpage;
        $reportoptions['mode'] = \mod_coursework\grading_report::MODE_GET_ALL; // Load all students as pagination is removed for now.
        $reportoptions['sortby'] = $sortby;
        $reportoptions['sorthow'] = $sorthow;
        $reportoptions['showsubmissiongrade'] = false;
        $reportoptions['showgradinggrade'] = false;
        $reportoptions['firstnamealpha'] = $firstnamealpha;
        $reportoptions['lastnamealpha'] = $lastnamealpha;
        $reportoptions['groupnamealpha'] = $groupnamealpha;

        $gradingreport = $coursework->renderable_grading_report_factory($reportoptions);
        $gradingsheet = new \mod_coursework\export\grading_sheet($coursework, null, null);
        // get only submissions that user can grade
        $submissions = $gradingsheet->get_submissions();
        /**
         * @var \mod_coursework\renderers\grading_report_renderer $grading_report_renderer
         */
        $gradingreportrenderer = new \mod_coursework\renderers\grading_report_renderer($this->page, RENDERER_TARGET_GENERAL);

        $warnings = new warnings($coursework);
        // Show any warnings that may need to be here
        if ($coursework->use_groups == 1) {
            $html .= $warnings->students_in_mutiple_grouos();
        }
        $html .= $warnings->percentage_allocations_not_complete();
        $html .= $warnings->student_in_no_group();

        // display 'Group mode' with the relevant groups
        $currenturl = new moodle_url('/mod/coursework/view.php', ['id' => $coursework->get_course_module()->id]);
        $html .= groups_print_activity_menu($coursework->get_course_module(), $currenturl->out(), true);
        if (groups_get_activity_groupmode($coursework->get_course_module()) != 0 && $group != 0) {
            $html .= '<div class="alert">'.get_string('groupmodechosenalert', 'mod_coursework').'</div>';
        }

        // reset table preferences
        if ($firstnamealpha || $lastnamealpha || $groupnamealpha) {
            $url = new moodle_url('/mod/coursework/view.php', ['id' => $coursework->get_course_module()->id, 'treset' => 1]);

            $html .= html_writer::start_div('mdl-right');
            $html .= html_writer::link($url, get_string('resettable'));
            $html .= html_writer::end_div();
        }

        if ($firstnamealpha || $lastnamealpha || $groupnamealpha || $group != -1) {
            $html .= $warnings->filters_warning();
        }

        /**
         * @var \mod_coursework\renderers\grading_report_renderer $grading_report_renderer
         */

        $html .= html_writer::start_tag('div', ['class' => 'wrapper_table_submissions']);
        $html .= $gradingreportrenderer->render_grading_report($gradingreport);
        $html .= html_writer::end_tag('div');

        foreach (['modal_handler_extensions', 'modal_handler_personal_deadlines'] as $amd) {
            $this->page->requires->js_call_amd(
                "mod_coursework/$amd",
                'init',
                ['courseworkId' => $coursework->id]
            );
        }

        return $html;
    }

    /**
     * Return submission output data for Mustache.
     *
     * @param submission $submission
     * @return stdClass
     */
    private function coursework_student_overview($submission): stdClass {
        $template = new stdClass();
        $this->add_submission_status($template, $submission);
        $coursework = $submission->get_coursework();

        $files = $submission->get_submission_files();
        $template->file = $this->get_object_renderer()
            ->render_submission_files_with_plagiarism_links(new mod_coursework_submission_files($files));

        // Date.
        if ($submission->persisted()) {
            $template->date = $submission->time_submitted();
        }

        // Was the submission late?
        if ($submission->is_late() && (!$submission->has_extension() || !$submission->submitted_within_extension())) {
            $deadline = $coursework->personaldeadlineenabled ? $submission->submission_personal_deadline() : $coursework->deadline;
            $deadline = $submission->has_extension() ? $submission->extension_deadline() : $deadline;
            $lateseconds = $submission->time_submitted() - $deadline;
            $template->late = format_time($lateseconds) . " " . strtolower(get_string('late', 'mod_coursework'));
        }

        // Mark.
        if ($submission->is_published()) {
            $judge = new \mod_coursework\grade_judge($coursework);
            $gradeforgradebook = $judge->get_grade_capped_by_submission_time($submission);
            $template->mark = $judge->grade_to_display($gradeforgradebook);
        }

        // Group submission.
        if ($coursework->is_configured_to_have_group_submissions() && $submission->persisted()) {
            $template->groupsubmitter = $submission->get_last_updated_by_user()->name();
        }

        return $template;
    }

    /**
     * Return submission page.
     *
     * @param student_submission_form $submitform
     * @param submission $submission
     */
    public function submission_page($submitform, $submission, $isnew = true) {
        $html = '';
        $template = new stdClass();

        $title = $submission->get_coursework()->is_configured_to_have_group_submissions()
            ? ($isnew ? 'addgroupsubmission' : 'editgroupsubmission')
            : ($isnew ? 'addyoursubmission' : 'edityoursubmission');
        $template->title = get_string($title, 'mod_coursework');

        $template->markingguide = $this->marking_preview_link($submission);
        $template->plagarism = plagiarism_similarity_information($submission->get_coursework()->get_course_module());

        if ($submission->get_coursework()->early_finalisation_allowed()) {
            $template->finalise = true;
        }

        if ($submission->get_coursework()->deadline_has_passed() && !$submission->has_valid_extension()) {
            $template->late = true;
        }

        // Submit form.
        ob_start();
        $submitform->display();
        $template->form = ob_get_clean();

        echo $this->output->header();
        echo $this->render_from_template('mod_coursework/submission_page', $template);
        echo $this->output->footer();
    }



    /**
     * @return router
     */
    protected function get_router() {
        return router::instance();
    }

    /**
     * Shows the interface for a teacher to
     *
     * @param $student
     * @param \mod_coursework\forms\student_submission_form $submitform
     * @return string
     */
    public function submit_on_behalf_of_student_interface($student, $submitform) {
        // Allow submission on behalf of the student.
        $html = '';

        $html .= $this->output->header();

        $title = get_string('submitonbehalfofstudent', 'mod_coursework', fullname($student));
        $html .= html_writer::start_tag('h3');
        $html .= $title;
        $html .= html_writer::end_tag('h3');

        ob_start(); // Forms library echos stuff.
        $submitform->display();
        $html .= ob_get_contents();
        ob_end_clean();

        $html .= $this->output->footer();

        return $html;
    }

    /**
     * @return mod_coursework_object_renderer
     */
    private function get_object_renderer() {
        return $this->page->get_renderer('mod_coursework', 'object');
    }

    /**
     * @param coursework $coursework
     * @param submission $submission
     * @return string
     * @throws coding_exception
     */
    protected function finalise_submission_button($coursework, $submission) {

        $html = '<div>';
        $html .= $this->finalise_warning();
        $stringname = $coursework->is_configured_to_have_group_submissions() ? 'finalisegroupsubmission' : 'finaliseyoursubmission';
        $finalisesubmissionpath =
            $this->get_router()->get_path('finalise submission', ['submission' => $submission], true);
        $button = new \single_button($finalisesubmissionpath, get_string($stringname, 'mod_coursework'), 'post',single_button::BUTTON_SUCCESS);
        $button->add_confirm_action(get_string('finalise_button_confirm', 'mod_coursework'));
        $button->class = 'd-block';
        $html .= $this->output->render($button);

        $html .= '</div>';

        return $html;

    }

    /**
     * @return string
     * @throws coding_exception
     */
    public function finalise_warning() {
        return '<div class="my-3"><div class="alert alert-info">' . get_string('finalise_button_info', 'mod_coursework') . '</div></div>';
    }

    /**
     * Return an object suitable for rendering in a Mustache template.
     *
     * @param coursework $coursework
     * @param submission $submission
     * @return stdClass with 'label' and 'url' properties.
     */
    protected function edit_submission_button($coursework, $submission) {
        $submissionbutton = new stdClass();

        $submissionbutton->label = get_string(
            $coursework->is_configured_to_have_group_submissions() ? 'editgroupsubmission' : 'edityoursubmission',
            'mod_coursework'
        );
        $submissionbutton->url = $this->get_router()->get_path('edit submission', ['submission' => $submission], true);
        return $submissionbutton;
    }

    /**
     * Return an object suitable for rendering in a Mustache template.
     *
     * @param submission $submission
     * @return stdClass with 'label' and 'url' properties.
     */
    protected function new_submission_button($submission): stdClass {
        $submissionbutton = new stdClass();
        $submissionbutton->label = get_string(
            $submission->get_coursework()->is_configured_to_have_group_submissions() ? 'addgroupsubmission' : 'addyoursubmission',
            'mod_coursework'
        );
        $submissionbutton->url = $this->get_router()->get_path('new submission', ['submission' => $submission], true);
        return $submissionbutton;
    }

    /**
     * @param submission $ownsubmission
     * @return string
     */
    protected function marking_preview_link($ownsubmission) {
        // TODO - this is now a copy of get_marking_guide_url.
        // Can we just reuse that and pass through $controller?

        if ($ownsubmission->get_coursework()->is_using_advanced_grading()) {
            $controller = $ownsubmission->get_coursework()->get_advanced_grading_active_controller();

            if ($controller->is_form_defined() && ($options = $controller->get_options()) && !empty($options['alwaysshowdefinition'])) {
                // Extract method name using reflection for protected method access.
                $reflectionclass = new ReflectionClass($controller);
                $getmethodname = $reflectionclass->getMethod('get_method_name');
                $getmethodname->setAccessible(true);
                $methodname = $getmethodname->invoke($controller);
                $template = new stdClass();
                $template->markingguideurl = new moodle_url('/grade/grading/form/' . $methodname . '/preview.php',
                        ['areaid' => $controller->get_areaid()]);

                return $this->render_from_template('mod_coursework/description', $template);

            }
        }
        return null;
    }

    /**
     * Form to upload CSV
     *
     * @param $uploadform
     * @param $csvtype - type will be used to create lang string
     * @return string
     * @throws coding_exception
     */
    public function csv_upload($uploadform, $csvtype) {

        $html = '';

        $html .= $this->output->header();

        $title = get_string($csvtype, 'mod_coursework');
        $html .= html_writer::start_tag('h3');
        $html .= $title;
        $html .= html_writer::end_tag('h3');

         $html .= $uploadform->display();

        $html .= $this->output->footer();

        return $html;

    }

    /**
     * Information about upload results, errors etc
     *
     * @param $processingresults
     * @param $csvcontent
     * @param $csvtype - type will be used to create lang string
     * @return string
     * @throws coding_exception
     */
    public function process_csv_upload($processingresults, $csvcontent, $csvtype) {

        $html = '';

        $html .= $this->output->header();

        $title = get_string('process'.$csvtype, 'mod_coursework');
        $html .= html_writer::start_tag('h3');
        $html .= $title;
        $html .= html_writer::end_tag('h3');
        $html .= html_writer::start_tag('p');
        $html .= get_string('process'.$csvtype.'desc', 'mod_coursework');;
        $html .= html_writer::end_tag('p');

        $html .= html_writer::start_tag('p');

        if (!empty($processingresults)) {

            $html .= get_string('followingerrors', 'mod_coursework')."<br />";
            if (!is_array($processingresults)) {
                $html .= $processingresults . "<br />";
            } else {
                foreach ($processingresults as $line => $error) {
                    $line = $line + 1;
                    if ($error !== true) {
                        $html .= "Record " . $line . ": " . $error . "<br />";
                    }
                }
            }
            $html .= html_writer::end_tag('p');
        } else {
            $html .= get_string('noallocationerrorsfound', 'mod_coursework');
        }

        $html .= html_writer::tag('p', html_writer::link('/mod/coursework/view.php?id='.$this->page->cm->id, get_string('continuetocoursework', 'coursework')));

        $html .= $this->output->footer();

        return $html;

    }

    public function feedback_upload($form) {

        $html = '';

        $html .= $this->output->header();

        $title = get_string('feedbackupload', 'mod_coursework');
        $html .= html_writer::start_tag('h3');
        $html .= $title;
        $html .= html_writer::end_tag('h3');

        $html .= $form->display();

        $html .= $this->output->footer();

        return $html;

    }

    public function process_feedback_upload($processingresults) {
        $title = get_string('feedbackuploadresults', 'mod_coursework');

        $this->page->set_pagelayout('standard');
        $this->page->navbar->add($title);

        $html = '';

        $html .= $this->output->header($title);

        $html .= html_writer::start_tag('h3');
        $html .= $title;
        $html .= html_writer::end_tag('h3');

        $html .= html_writer::start_tag('p');
        $html .= get_string('feedbackuploadresultsdesc', 'mod_coursework');;
        $html .= html_writer::end_tag('p');

        $html .= html_writer::start_tag('p');

        if (!empty($processingresults)) {

            $html .= get_string('fileuploadresults', 'mod_coursework')."<br />";
            foreach ($processingresults as $file => $result) {
                $html .= get_string('fileuploadresult', 'mod_coursework', ['filename' => $file, 'result' => $result]). "<br />";
            }
            $html .= html_writer::end_tag('p');
        } else {
            $html .= get_string('nofilesfound', 'mod_coursework');
        }

        $html .= html_writer::tag('p', html_writer::link('/mod/coursework/view.php?id='.$this->page->cm->id, get_string('continuetocoursework', 'coursework')));

        $html .= $this->output->footer();

        return $html;

    }
    /**
     * View a summary listing of all courseworks in the current course.
     *
     * @return string
     */
    public function view_course_index($courseid) {
        global $CFG, $DB, $USER;
        $o = '';
        $course = $DB->get_record('course', ['id' => $courseid]);
        $strplural = get_string('modulenameplural', 'assign');
        if (!$cms = get_coursemodules_in_course('coursework', $course->id, 'm.deadline')) {
            $o .= $this->get_renderer()->notification(get_string('thereareno', 'moodle', $strplural));
            $o .= $this->get_renderer()->continue_button(new moodle_url('/course/view.php', ['id' => $course->id]));
            return $o;
        }
        $usesections = course_format_uses_sections($course->format);
        $modinfo = get_fast_modinfo($course);
        if ($usesections) {
            $sections = $modinfo->get_section_info_all();
        }
        $table = new html_table();
        // table headers
        $formatname = course_get_format($course)->get_format();
        $table->head =  [ucfirst($formatname), 'Courseworks', 'Deadline', 'Submission', 'Grade'];

        $currentsection = '';
        $printsection = '';
        foreach ($modinfo->instances['coursework'] as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            $coursework = coursework::find($cm->instance);
            $sectionname = '';
            if ($usesections && $cm->sectionnum) {
                $sectionname = get_section_name($course, $sections[$cm->sectionnum]);
                if ($sectionname !== $currentsection) {
                    if ($sectionname) {
                        $printsection = $sectionname;
                    }
                    $currentsection = $sectionname;
                } else {
                    $printsection = '';
                }
            }

            $submitted = '';
            $timedue = ($coursework->deadline) ? date('l, d F Y, h:i A', $coursework->deadline) : "No deadline";
            if ($coursework->can_grade()) { // teachers
                $submitted = count($coursework->get_all_submissions());
            } else if ($coursework->can_submit()) { // Students
                if ($coursework->use_groups) {
                    $allocatable = $coursework->get_student_group($USER);
                } else {
                    $allocatable = $USER;
                    $allocatable = user::find($allocatable);
                }
                if ($allocatable) {
                    $timedue = $coursework->get_allocatable_deadline($allocatable->id); // get deadline based on user taking into considerations personal deadline and extension
                    $timedue = ($timedue) ? date('l, d F Y, h:i A', $timedue) : "No deadline";
                    $usersubmission = $coursework->get_user_submission($allocatable);
                    if ($usersubmission) {
                        $submitted = $usersubmission->get_status_text();
                    } else {
                        $submitted = get_string('statusnotsubmitted', 'coursework');
                    }
                } else { // message that student is not in the group
                    $submitted = "Not in group, can't submit";
                }
            }
            $gradinginfo = grade_get_grades($course->id, 'mod', 'coursework', $cm->instance, $USER->id);
            if (isset($gradinginfo->items[0]->grades[$USER->id]) &&
                !$gradinginfo->items[0]->grades[$USER->id]->hidden ) {
                $grade = $gradinginfo->items[0]->grades[$USER->id]->str_grade;
            } else {
                $grade = '-';
            }
            $url = $CFG->wwwroot.'/mod/coursework/view.php';
            $link = "<a href=\"{$url}?id={$coursework->coursemodule->id}\">{$coursework->name}</a>";
            $table->data[] = [//'cmid' => $cm->id,
                'sectionname' => $printsection,
                'cmname' => $link,
                'timedue' => $timedue,
                'submissioninfo' => $submitted,
                'gradeinfo' => $grade];
        }
        $o = html_writer::table($table);
        return $o;
    }

    /**
     * Populate template object properties with values for this submission
     * suitable for rendering with a Mustache template.
     *
     * @param stdClass $template Existing data object to be rendered.
     * @param submission $submission Submission instance whose status is to be
     * displayed.
     */
    private function add_submission_status(stdClass $template, submission $submission): void {
        $template->submission = new stdClass();

        switch ($submission->get_state()) {
            case submission::NOT_SUBMITTED:
                $template->submission->badge = 'warning';
                $template->submission->status = get_string('statusnotsubmitted', 'mod_coursework');
                break;

            case submission::SUBMITTED:
                $template->submission->badge = 'warning';

                if ($submission->get_coursework()->allowearlyfinalisation) {
                    $template->submission->status = get_string('statusnotsubmitted', 'mod_coursework');
                } else {
                    $template->submission->status = get_string('submitted', 'mod_coursework');
                }

                break;

            case submission::FINALISED:
                $template->submission->badge = 'warning';
                $template->submission->status = get_string('submitted', 'mod_coursework');
                break;

            case submission::PARTIALLY_GRADED:
            case submission::FULLY_GRADED:
            case submission::FINAL_GRADED:
                $template->submission->badge = 'warning';
                $template->submission->status = get_string('statusinmarking', 'mod_coursework');

                break;

            case submission::PUBLISHED:
                $template->submission->badge = 'success';
                $template->submission->status = get_string('statusreleased', 'mod_coursework');
                break;
        }
    }

    /**
     * Where there is a validation failure, redisplay form and allow Moodle to explain what error is for user.
     * @param submission $submission
     * @param assessor_feedback_mform $form
     * @return void
     */
    public function redisplay_form(submission $submission, assessor_feedback_mform $form) {
        $coursework = $submission->get_coursework();
        $files = $submission->get_submission_files();
        $objectrenderer = $this->get_object_renderer();

        $this->page->set_context($coursework->get_context());
        $this->page->set_pagelayout('standard');
        $templatedata = (object)[
            'submission' => $objectrenderer->render_submission_files_with_plagiarism_links(
                new \mod_coursework_submission_files($files), false
            ),
            'title' => get_string('gradingfor', 'coursework', $submission->get_allocatable_name()),
        ];
        echo $this->output->header();
        echo $this->render_from_template('mod_coursework/marking_details', $templatedata);
        $form->display();
        echo $this->output->footer();
        die();
    }
}
