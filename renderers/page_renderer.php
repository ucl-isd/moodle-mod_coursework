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
use mod_coursework\forms\student_submission_form;
use mod_coursework\forms\moderator_agreement_mform;
use mod_coursework\forms\plagiarism_flagging_mform;
use mod_coursework\models\coursework;
use mod_coursework\models\user;
use mod_coursework\models\feedback;
use mod_coursework\models\submission;
use mod_coursework\models\moderation;
use mod_coursework\models\plagiarism_flag;
use mod_coursework\router;
use mod_coursework\warnings;
use mod_coursework\models\group;

/**
 * Makes the pages
 */
class mod_coursework_page_renderer extends plugin_renderer_base {

    /**
     * @param feedback $feedback
     */
    public function show_feedback_page($feedback, bool $ajax) {
        $html = '';

        $objectrenderer = $this->get_object_renderer();

        if (!$ajax) {
            $html .= $this->output->header();
        }
        $html .= $objectrenderer->render_feedback($feedback);
        if (!$ajax)  {
            $html .= $this->output->footer();
        }

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
     * @param bool $ajax
     * @throws coding_exception
     */
    public function edit_feedback_page(feedback $teacherfeedback, $assessor, $editor, $ajax = false) {

        global $SITE;

        $gradingtitle =
            get_string('gradingfor', 'coursework', $teacherfeedback->get_submission()->get_allocatable_name());

        $this->page->set_pagelayout('standard');
        $this->page->navbar->add($gradingtitle);
        $this->page->set_title($SITE->fullname);
        $this->page->set_heading($SITE->fullname);

        $html = '';

        $gradedby = ($teacherfeedback->assessorid == 0) ? get_string('automaticagreement', 'mod_coursework') : fullname($assessor);
        $lasteditedby = ((!$teacherfeedback->get_coursework()->sampling_enabled() || $teacherfeedback->get_submission()->sampled_feedback_exists())
            && $teacherfeedback->assessorid == 0 && $teacherfeedback->timecreated == $teacherfeedback->timemodified )
            ? get_string('automaticagreement', 'mod_coursework') : fullname($editor);

        $html .= $this->output->heading($gradingtitle);
        $html .= '<table class = "grading-details">';
        $html .= '<tr><th>' . get_string('gradedby', 'coursework') . '</th><td>' . $gradedby . '</td></tr>';
        $html .= '<tr><th>' . get_string('lasteditedby', 'coursework') . '</th><td>' . $lasteditedby . ' on ' .
            userdate($teacherfeedback->timemodified, '%a, %d %b %Y, %H:%M') . '</td></tr>';
        $files = $teacherfeedback->get_submission()->get_submission_files();
        $filesstring = count($files) > 1 ? 'submissionfiles' : 'submissionfile';

        $html .= '<tr><th>' . get_string($filesstring, 'coursework') . '</th><td>' . $this->get_object_renderer()
            ->render_submission_files_with_plagiarism_links(new mod_coursework_submission_files($files)) . '</td></tr>';
        $html .= '</table>';

        $submiturl = $this->get_router()->get_path('update feedback', ['feedback' => $teacherfeedback]);
        $simpleform = new assessor_feedback_mform($submiturl, ['feedback' => $teacherfeedback]);

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

        if ($ajax) {
            $formhtml = $simpleform->render();
            $filemanageroptions = $simpleform->get_file_options();
            $editoroptions = $simpleform->get_editor_options();

            $commentoptions = $this->get_comment_options($simpleform);
            echo json_encode(['formhtml' => $html . $formhtml, 'filemanageroptions' => $filemanageroptions, 'editoroptions' => $editoroptions, 'commentoptions' => $commentoptions]);

        } else {
            $this->page->set_pagelayout('standard');
            $this->page->navbar->add($gradingtitle);
            $this->page->set_title($SITE->fullname);
            $this->page->set_heading($SITE->fullname);
            echo $this->output->header();
            echo $html;
            $simpleform->display();
            echo $this->output->footer();
        }
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
     * @throws \coding_exception
     * @throws \moodle_exception
     * @return string
     */
    public function student_view_page($coursework, $student) {

        $html = '';

        // If the coursework has been configured to use groups and the student is not in any
        // groups, then we need to show an error message.
        if ($coursework->is_configured_to_have_group_submissions() && !$coursework->student_is_in_any_group($student)) {
            $html .= '<div class= "alert">'.get_string('not_in_any_group_student_warning', 'mod_coursework').'</div>';
            return $html;
        }

        $coursemodule = $coursework->get_course_module();

        // $submission here means the existing stuff. Might be the group of the student. The only place where
        // it matters in in pre-populating the form, where it should be empty if this student did not submit
        // the files.
        /**
         * @var \mod_coursework\models\submission $submission
         */
        $submission = $coursework->get_user_submission($student);
        $newsubmission = $coursework->build_own_submission($student);
        if (!$submission) {
            $submission = $newsubmission;
        }

        // This should probably not be in the renderer.
        if ($coursework->has_individual_autorelease_feedback_enabled() &&
            $coursework->individual_feedback_deadline_has_passed() &&
            !$submission->is_published() && $submission->ready_to_publish()
        ) {

            $submission->publish();
        }

        // WIP - student overview, and initialise template.
        // TODO - feels odd. Why is this a seperate function?
        // Probably single function with data and buttons would be better?
        $template = $this->coursework_student_overview($submission);

        // Buttons.
        $ability = new ability($student, $coursework);

        if ($coursework->start_date_has_passed()) {
            // Add/Edit links.
            if ($ability->can('new', $submission)) {
                $template->editurl = $this->get_router()->get_path('new submission', ['submission' => $submission], true);
            } else if ($submission && $ability->can('edit', $submission)) {
                $template->editurl = $this->get_router()->get_path('edit submission', ['submission' => $submission], true);
            }
        } else {
            // Coursework submission has not started yet.
            $template->notopen = true;
            $template->opendate = userdate($coursework->startdate);
        }

        // Finalise.
        if ($submission && $submission->id && $ability->can('finalise', $submission)) {
            $template->final = $this->finalise_submission_button($coursework, $submission);
        }

        // TODO - where shoudl this go?
        // Feedback.
        if ($submission && $submission->is_published()) {
            $template->feedback = $this->existing_feedback_from_teachers($submission);
        }

        // TODO - what should this look like? Where should it go?
        $template->plagdisclosure = plagiarism_similarity_information($coursemodule);


        $html .= $this->render_from_template('mod_coursework/submission', $template);
        // var_dump($template);
        return $html;

        // TODO - how does this fit in?
        // if TII plagiarism enabled check if user agreed/disagreed EULA
        $shouldseeeula = has_user_seen_tii_eula_agreement();

        if ($ability->can('new', $submission) && (!$coursework->tii_enabled() || $shouldseeeula)) {
            if ($coursework->start_date_has_passed()) {
                $html .= $this->new_submission_button($submission);
            } else {
                $html .= '<div class="alert">' . get_string('notstartedyet', 'mod_coursework', userdate($coursework->startdate)) . '</div>';
            }
        } else if ($submission && $ability->can('edit', $submission)) {
            $html .= $this->edit_submission_button($coursework, $submission);
        }

        if ($submission && $submission->id && $ability->can('finalise', $submission)) {
            $html .= $this->finalise_submission_button($coursework, $submission);

        }

        if ($submission && $submission->is_published()) {
            $html .= $this->existing_feedback_from_teachers($submission);
        }

        return $html;
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
     * @param bool $ajax
     * @throws coding_exception
     */
    public function new_feedback_page($newfeedback, $ajax = false) {
        global $SITE, $DB;

        $submission = $newfeedback->get_submission();
        $gradingtitle = get_string('gradingfor', 'coursework', $submission->get_allocatable_name());

        $this->page->set_pagelayout('standard');
        $this->page->navbar->add($gradingtitle);
        $this->page->set_title($SITE->fullname);
        $this->page->set_heading($SITE->fullname);

        $html = '';

        // Warning in case there is already some feedback from another teacher
        $conditions = ['submissionid' => $newfeedback->submissionid,
                            'stage_identifier' => $newfeedback->stage_identifier];
        if (feedback::exists($conditions)) {
            $html .= '<div class="alert">Another user has already submitted feedback for this student. Your changes will not be saved.</div>';
        }

        $html .= $this->output->heading($gradingtitle);
        $html .= '<table class = "grading-details">';
        $assessor = $DB->get_record('user', ['id' => $newfeedback->assessorid]);
        $html .= '<tr><th>' . get_string('assessor', 'coursework') . '</th><td>' . fullname($assessor) . '</td></tr>';

        $files = $submission->get_submission_files();
        $filesstring = count($files) > 1 ? 'submissionfiles' : 'submissionfile';
        $objectrenderer = $this->get_object_renderer();
        $html .= '<tr><th>' . get_string($filesstring,
                                         'coursework') . '</th><td>' . $objectrenderer->render_submission_files_with_plagiarism_links(new \mod_coursework_submission_files($files),
                                                                                                                                       false) . '</td></tr>';
        $html .= '</table>';

        $submiturl = $this->get_router()->get_path('create feedback', ['feedback' => $newfeedback]);
        $simpleform = new assessor_feedback_mform($submiturl, ['feedback' => $newfeedback]);

        $coursework = coursework::find($newfeedback->courseworkid);

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

        if ($ajax) {
            $formhtml = $simpleform->render();
            $filemanageroptions = $simpleform->get_file_options();
            $editoroptions = $simpleform->get_editor_options();

            $commentoptions = $this->get_comment_options($simpleform);
            echo json_encode(['formhtml' => $html . $formhtml, 'filemanageroptions' => $filemanageroptions, 'editoroptions' => $editoroptions, 'commentoptions' => $commentoptions]);

        } else {
            $this->page->set_pagelayout('standard');
            $this->page->navbar->add($gradingtitle);
            $this->page->set_title($SITE->fullname);
            $this->page->set_heading($SITE->fullname);
            echo $this->output->header();
            echo $html;
            $simpleform->display();
            echo $this->output->footer();
        }
    }

    /**
     *
     * @param $simpleform
     * @return array
     */
    private function get_comment_options($simpleform) {
        $gradingformguidecontroller = $simpleform->get_grading_controller();

        if (!$gradingformguidecontroller) {
            return null;
        }
        $definition = $gradingformguidecontroller->get_definition();
        if (!property_exists($definition, 'guide_comments')) {
            return null;
        }
        $comments = $definition->guide_comments;

        $commentoptions = [];
        foreach ($comments as $id => $comment) {
            $commentoption = new stdClass();
            $commentoption->id = $id;
            $commentoption->description = $comment['description'];
            $commentoptions[] = $commentoption;
        }
        return $commentoptions;
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
        $reportoptions['mode'] = 2; // Load first number of records specified by perpage first
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
         * @var mod_coursework_grading_report_renderer $grading_report_renderer
         */
        $gradingreportrenderer = $this->page->get_renderer('mod_coursework', 'grading_report');
        $html .= $gradingreportrenderer->submissions_header();

        $warnings = new warnings($coursework);
        // Show any warnings that may need to be here
        if ($coursework->use_groups == 1) {
            $html .= $warnings->students_in_mutiple_grouos();
        }
        $html .= $warnings->percentage_allocations_not_complete();
        $html .= $warnings->student_in_no_group();

        $pageurl = $this->page->url;
        $params = $this->page->url->params();
        $links = [];

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

        $finalisedsubmissions = submission::$pool[$coursework->id]['finalised'][1] ?? [];
        if ($finalisedsubmissions && !empty($gradingreport->get_table_rows_for_page())
            && !empty($submissions)) {

            $url = $pageurl.'&download=1';
            $links[$url] = get_string('download_submitted_files', 'coursework');
        }
        // export final grades button
        if (has_capability('mod/coursework:viewallgradesatalltimes',
                           $this->page->context) && has_capability('mod/coursework:canexportfinalgrades', $this->page->context)
            && $coursework->get_finalised_submissions()
        ) {
            $url = $pageurl.'&export=1';
            $links[$url] = get_string('exportfinalgrades', 'mod_coursework');
        }

        if (!empty($gradingreport->get_table_rows_for_page()) && !empty($submissions)
            &&(has_capability('mod/coursework:addinitialgrade', $this->page->context)
            || has_capability('mod/coursework:addagreedgrade', $this->page->context)
            || has_capability('mod/coursework:addallocatedagreedgrade', $this->page->context)
            || has_capability('mod/coursework:administergrades', $this->page->context))
            && $coursework->get_finalised_submissions()) {
            // Export grading sheet
            $url = $pageurl.'&export_grading_sheet=1';
            $links[$url] = get_string('exportgradingsheets', 'mod_coursework');
            // Import grading sheet
            $url = '/mod/coursework/actions/upload_grading_sheet.php?cmid='.$this->page->cm->id;
            $links[$url] = get_string('uploadgradingworksheet', 'mod_coursework');
            // Import annotated submissions
            $url = '/mod/coursework/actions/upload_feedback.php?cmid='.$this->page->cm->id;
            $links[$url] = get_string('uploadfeedbackfiles', 'mod_coursework');
        }


        if ($firstnamealpha || $lastnamealpha || $groupnamealpha || $group != -1) {
            $html .= $warnings->filters_warning();
        }

        /**
         * @var mod_coursework_grading_report_renderer $grading_report_renderer
         */

        $html .= html_writer::start_tag('div', ['class' => 'wrapper_table_submissions']);
        $html .= $gradingreportrenderer->render_grading_report($gradingreport, $coursework->has_multiple_markers());
        $html .= html_writer::end_tag('div');

        // Publish button if appropriate.
        if ($coursework->has_stuff_to_publish() && has_capability('mod/coursework:publish', $this->page->context)) {
            $customdata = ['cmid' => $coursework->get_course_module()->id,
                                'gradingreport' => $gradingreport,
                                'coursework' => $coursework];
            $publishform = new mod_coursework\forms\publish_form(null, $customdata);
            $html .= $publishform->display();
        }

        return $html;
    }

    /**
     * @param $coursework
     * @param $viewallstudentspage
     * @param $viewallstudentsperpage
     * @param $viewallstudentssortby
     * @param $viewallstudentssorthow
     * @param $group
     * @param int $displayallstudents
     * @param $firstnamealpha
     * @param $lastnamealpha
     * @param $groupnamealpha
     * @return string
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function non_teacher_allocated_grading_page($coursework, $viewallstudentspage, $viewallstudentsperpage, $viewallstudentssortby, $viewallstudentssorthow, $group, $displayallstudents, $firstnamealpha, $lastnamealpha, $groupnamealpha) {
        $pageurl = $this->page->url;

        $html = '';

        if (has_capability('mod/coursework:viewallstudents', $this->page->context)) {

            $reportoptions = [];
            $reportoptions['page'] = $viewallstudentspage;
            $reportoptions['group'] = $group;
            $reportoptions['perpage'] = $viewallstudentsperpage;
            $reportoptions['sortby'] = $viewallstudentssortby;
            $reportoptions['sorthow'] = $viewallstudentssorthow;
            $reportoptions['tablename'] = 'viewallstudents';
            $reportoptions['unallocated'] = true;
            $reportoptions['showsubmissiongrade'] = false;
            $reportoptions['showgradinggrade'] = false;
            $reportoptions['firstnamealpha'] = $firstnamealpha;
            $reportoptions['lastnamealpha'] = $lastnamealpha;
            $reportoptions['groupnamealpha'] = $groupnamealpha;

            $gradingreport = $coursework->renderable_grading_report_factory($reportoptions);

            $anyunallocatedstudents = $gradingreport->get_participant_count() > 0;

            if (!empty($anyunallocatedstudents)) {
                $customdata = ['cmid' => $coursework->get_course_module()->id,
                    'displayallstudents' => $displayallstudents];

                $displayvalue = (empty($displayallstudents)) ? 1 : 0;
                $buttontext = (empty($displayallstudents)) ? get_string('showallstudents', 'coursework') : get_string('hideallstudents', 'coursework');
                $buttontclass = (empty($displayallstudents)) ? 'show-students-btn' : 'hide-students-btn';
                $downloadurl = new moodle_url($pageurl, ['displayallstudents' => $displayvalue]);
                $html .= html_writer::tag('p', html_writer::link($downloadurl, $buttontext, ['class' => $buttontclass, 'id' => 'id_displayallstudentbutton']));
            }

            if (!empty($displayallstudents) && !empty($anyunallocatedstudents)) {
                /**
                 * @var mod_coursework_grading_report_renderer $grading_report_renderer
                 */
                $gradingreportrenderer = $this->page->get_renderer('mod_coursework', 'grading_report');
                $html .= $gradingreportrenderer->submissions_header(get_string('submissionnotallocatedtoassessor', 'coursework'));

                /**
                 * @var mod_coursework_grading_report_renderer $grading_report_renderer
                 */

                $html .= html_writer::start_tag('div', ['class' => 'wrapper_table_submissions']);
                $html .= $gradingreportrenderer->render_grading_report($gradingreport, $coursework->has_multiple_markers(), true);
                $html .= html_writer::end_tag('div');

                /**
                 * @var mod_coursework_grading_report_renderer $grading_report_renderer
                 */
            }
        }
        return $html;

    }

    /**
     * @param submission $submission
     * @param student_submission_form $submitform
     * @return string
     * @throws coding_exception
     */
    protected function file_submission_form($submission, $submitform) {
        $files = $submission->get_submission_files();
        $coursework = $submission->get_coursework();

        $html = '';

        $html .= html_writer::start_tag('h1');
        $html .= get_string('submissioninstructionstitle', 'coursework');
        $html .= html_writer::end_tag('h1');

        $html .= $this->output->box_start('generalbox instructions');
        $html .= html_writer::tag('p', get_string('submissioninstructions', 'coursework'));
        $html .= $this->output->box_end();

        $filesstring =
            'yoursubmissionstatus';// $files->has_multiple_files() ? 'yoursubmissionfiles' : 'yoursubmissionfile';

        $html .= html_writer::start_tag('h3');
        $html .= get_string($filesstring, 'coursework');
        $html .= html_writer::end_tag('h3');

        $table = new html_table();

        $row = new html_table_row();
        $row->cells[] = get_string('submissionfile', 'coursework') . ': ';
        $row->cells[] = $this->get_object_renderer()
            ->render_submission_files_with_plagiarism_links(new mod_coursework_submission_files($files));
        $table->data[] = $row;

        $html .= html_writer::table($table);

        $fileoptions = $coursework->get_file_options();

        // Get any files that were previously submitted. This fetches an itemid from the $_GET params.
        $draftitemid = file_get_submitted_draft_itemid('submission');
        // Put them into a draft area.
        file_prepare_draft_area($draftitemid,
                                $this->page->context->id,
                                'mod_coursework',
                                'submission',
                                $submission->id,
                                $fileoptions);

        // Load that area into the form.
        $submission->submissionfiles = $draftitemid;

        $submitform->set_data($submission);

        // TODO should be impossible to change files after the deadline, or if grading has happened.
        ob_start();
        $submitform->display();
        $html .= ob_get_clean();

        return $html;
    }

    /**
     * @param submission $submission
     * @return string
     * @throws coding_exception
     */
    protected function existing_feedback_from_teachers($submission) {

        global $USER;

        $coursework = $submission->get_coursework();

        $html = '';

        // Start with final feedback. Use moderated grade?

        $finalfeedback = $submission->get_final_feedback();

        $ability = new ability(user::find($USER), $submission->get_coursework());

        if ($finalfeedback && $ability->can('show', $finalfeedback)) {
            $html .= $this->get_object_renderer()->render_feedback($finalfeedback);
        }

        if ($submission->has_multiple_markers() && $coursework->students_can_view_all_feedbacks()) {
            $assessorfeedbacks = $submission->get_assessor_feedbacks();
            foreach ($assessorfeedbacks as $feedback) {
                if ($ability->can('show', $feedback)) {
                    $html .= $this->get_object_renderer()->render_feedback($feedback);
                }
            }
        }

        if ($html) {
            $html = html_writer::tag('h3', get_string('feedback', 'coursework')) . $html;
        }

        return $html;
    }

    /**
     * @param submission $submission
     * @return stdClass
     */
    protected function coursework_student_overview($submission): stdClass {
        global $USER;

        $coursework = $submission->get_coursework();
        $files = $submission->get_submission_files();

        // WIP - return submission output for mustache.
        $template = new stdClass();
        $template->file = $this->get_object_renderer()
        ->render_submission_files_with_plagiarism_links(new mod_coursework_submission_files($files));
        $template->status = $submission->get_status_text(); // TODO - make better.

        // Date.
        if ($submission->persisted() && $submission->time_submitted()) {
            $template->date = date('jS M g:ia', $submission->time_submitted());
        }

        // Late.
        if ($submission->is_late() && (!$submission->has_extension() || !$submission->submitted_within_extension())) {
            $template->late = true;
        }

        // Mark.
        if ($submission && $submission->is_published()) {
            $judge = new \mod_coursework\grade_judge($coursework);
            $gradeforgradebook = $judge->get_grade_capped_by_submission_time($submission);
            $template->mark = $judge->grade_to_display($gradeforgradebook);
        } else if ($submission->get_state() >= submission::PARTIALLY_GRADED) {
            $template->mark = get_string('notpublishedyet', 'mod_coursework');
        }

        // Group submission.
        if ($coursework->is_configured_to_have_group_submissions()) {
            $template->group = true;
            if ($submission->persisted()) {
                $submitter = $submission->get_last_updated_by_user();
                $template->submitter = $submitter->name();
            }
        }

        return $template;
    }

    /**
     * @param student_submission_form $submitform
     * @param submission $ownsubmission
     * @throws \coding_exception
     */
    public function new_submission_page($submitform, $ownsubmission) {
        $html = '';

        $html .= html_writer::start_tag('h3');
        $stringname = $ownsubmission->get_coursework()->is_configured_to_have_group_submissions() ? 'addgroupsubmission' : 'addyoursubmission';
        $html .= get_string($stringname, 'mod_coursework');
        $html .= html_writer::end_tag('h3');

        $html .= $this->marking_preview_html($ownsubmission);

        if ($ownsubmission->get_coursework()->early_finalisation_allowed()) {
            $html .= $this->finalise_warning();
        }
        $html .= plagiarism_similarity_information($ownsubmission->get_coursework()->get_course_module());
        ob_start();
        $submitform->display();
        $html .= ob_get_clean();

        echo $this->output->header();
        echo $html;
        echo $this->output->footer();
    }

    /**
     * @param student_submission_form $submitform
     * @param submission $submission
     * @throws \coding_exception
     */
    public function edit_submission_page($submitform, $submission) {
        $html = '';

        $html .= html_writer::start_tag('h3');
        $stringname = $submission->get_coursework()->is_configured_to_have_group_submissions() ? 'editgroupsubmission' : 'edityoursubmission';
        $html .= get_string($stringname, 'mod_coursework');
        $html .= ' ' . $submission->get_coursework()->name;
        $html .= html_writer::end_tag('h3');

        $html .= $this->marking_preview_html($submission);

        if ($submission->get_coursework()->early_finalisation_allowed()) {
            $html .= $this->finalise_warning();
        }
        $html .= '<div class="alert">'.get_string('replacing_an_existing_file_warning', 'mod_coursework').'</div>';
        if ($submission->get_coursework()->deadline_has_passed() && !$submission->has_valid_extension()) {
            $html .= '<div class="alert">'.get_string('late_submissions_warning', 'mod_coursework').'</div>';
        }

        ob_start();
        $submitform->display();
        $html .= ob_get_clean();

        echo $this->output->header();
        echo $html;
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
        return '<p class="small">' . get_string('finalise_button_info', 'mod_coursework') . '</small>';
    }

    /**
     * @param coursework $coursework
     * @param submission $submission
     * @return string
     * @throws coding_exception
     */
    protected function edit_submission_button($coursework, $submission) {
        $html = '';
        $stringname = $coursework->is_configured_to_have_group_submissions() ? 'editgroupsubmission' : 'edityoursubmission';
        $button = new \single_button($this->get_router()
            ->get_path('edit submission', ['submission' => $submission], true),
                                     get_string($stringname, 'mod_coursework'), 'get');
        $html .= $this->output->render($button);
        return $html;
    }

    /**
     * @param submission $submission
     * @return string
     * @throws coding_exception
     */
    protected function new_submission_button($submission) {
        $html = '';
        $stringname = $submission->get_coursework()->is_configured_to_have_group_submissions() ? 'addgroupsubmission' : 'addyoursubmission';

        $url = $this->get_router()->get_path('new submission', ['submission' => $submission], true);
        $label = get_string($stringname, 'mod_coursework');
        $button = new \single_button($url, $label, 'get');
        $html .= $this->output->render($button);
        return $html;
    }

    /**
     * @param submission $ownsubmission
     * @return string
     * @throws coding_exception
     */
    protected function marking_preview_html($ownsubmission) {
        $html = '';

        if ($ownsubmission->get_coursework()->is_using_advanced_grading()) {
            $controller = $ownsubmission->get_coursework()->get_advanced_grading_active_controller();
            $previewhtml = $controller->render_preview($this->page);
            if (!empty($previewhtml)) {
                $html .= '<h4>';
                $html .= get_string('marking_guide_preview', 'mod_coursework');
                $html .= '</h4>';
                $html .= $previewhtml;
                return $html;
            }
            return $html;
        }
        return $html;
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

    public function datatables_render($coursework) {
        global $CFG;

        $langmessages = [
            'notification_yes_label' => get_string('notification_yes_label', 'mod_coursework'),
            'notification_no_label' => get_string('notification_no_label', 'mod_coursework'),
            'notification_confirm_label' => get_string('notification_confirm_label', 'mod_coursework'),
            'notification_info' => str_replace(' ', '_', get_string('notification_info', 'mod_coursework')),
            'notification_leave_form_message' => str_replace(' ', '_', get_string('notification_leave_form_message', 'mod_coursework')),
            'notification_leave_form_title' => str_replace(' ', '_', get_string('notification_leave_form_title', 'mod_coursework')),
            'alert_extension_save_successful' => str_replace(' ', '_', get_string('alert_extension_save_successful', 'mod_coursework')),
            'alert_no_extension' => str_replace(' ', '_', get_string('alert_no_extension', 'mod_coursework')),
            'alert_personaldeadline_save_successful' => str_replace(' ', '_', get_string('alert_personaldeadline_save_successful', 'mod_coursework')),
            'alert_validate_deadline' => str_replace(' ', '_', get_string('alert_validate_deadline', 'mod_coursework')),
            'url_root' => $CFG->wwwroot,
            'alert_feedback_save_successful' => str_replace(' ', '_', get_string('alert_feedback_save_successful', 'mod_coursework')),
            'alert_feedback_remove_successful' => str_replace(' ', '_', get_string('alert_feedback_remove_successful', 'mod_coursework')),
            'alert_request_error' => str_replace(' ', '_', get_string('alert_request_error', 'mod_coursework')),
            'alert_feedback_draft_save_successful' => str_replace(' ', '_', get_string('alert_feedback_draft_save_successful', 'mod_coursework')),
        ];

        $modalheader = html_writer::tag('h5', 'new Extension', [
            'class' => 'modal-title',
            'id' => 'extension-modal-title',
        ]);
        $modalheader .= html_writer::start_tag('button', [
            'type' => 'button',
            'class' => 'close btn-extension-close',
            'aria-label' => 'Close',
            'data-dismiss' => 'modal',
        ]);
        $modalheader .= html_writer::span('&times;', '', ['aria-hidden' => 'true']);
        $modalheader .= html_writer::end_tag('button');

        $modalbody = html_writer::start_tag('form', ['id' => 'form-extension']);
        $content = html_writer::empty_tag('input', [
            'name' => 'allocatabletype',
            'type' => 'hidden',
            'value' => '',
            'id' => 'extension-allocatabletype',
        ]);
        $content .= html_writer::empty_tag('input', [
            'name' => 'allocatableid',
            'type' => 'hidden',
            'value' => '',
            'id' => 'extension-allocatableid',
        ]);
        $content .= html_writer::empty_tag('input', [
            'name' => 'courseworkid',
            'type' => 'hidden',
            'value' => '',
            'id' => 'extension-courseworkid',
        ]);
        $content .= html_writer::empty_tag('input', [
            'name' => 'id',
            'type' => 'hidden',
            'value' => '',
            'id' => 'extension-id',
        ]);
        $content .= html_writer::empty_tag('input', [
            'name' => 'submissionid',
            'type' => 'hidden',
            'value' => '',
            'id' => 'extension-submissionid',
        ]);
        $content .= html_writer::empty_tag('input', [
            'name' => 'name',
            'type' => 'hidden',
            'id' => 'extension-name',
            'value' => '',
        ]);
        $content .= html_writer::empty_tag('input', [
            'name' => 'aid',
            'type' => 'hidden',
            'value' => '',
            'id' => 'button-id',
        ]);
        $modalbody .= html_writer::div($content, 'display-none');

        if ($coursework->deadline) {
            $contentdefaultdeadline = 'Default deadline: ' . userdate($coursework->deadline);
            $contentdefaultdeadline = html_writer::div($contentdefaultdeadline, 'col-md-12', ['id' => 'extension-time-content']);
            $modalbody .= html_writer::div($contentdefaultdeadline, 'form-group row fitem');

            $contentextendeddeadline = html_writer::tag('label', get_string('extended_deadline', 'mod_coursework'));
            $contentextendeddeadlinediv = html_writer::div($contentextendeddeadline, 'col-md-3');
            $minnutesstep = 5;
            $input = \html_writer::tag(
                'input', '', [
                    'type' => "datetime-local",
                    'step' => $minnutesstep * 60,
                    'id' => "extension-extend-deadline",
                    'name' => "extension-extend-deadline",
                ]
            );
            $contentextendeddeadlinediv .= html_writer::div($input, 'col-md-6');
            $modalbody .= html_writer::div($contentextendeddeadlinediv, 'form-group row fitem');

            $extensionreasons = coursework::extension_reasons();
            if (!empty($extensionreasons)) {
                $selectextensionreasons = html_writer::tag('label', get_string('extension_reason', 'mod_coursework'));
                $selectextensionreasonsdiv = html_writer::div($selectextensionreasons, 'col-md-3');
                $selectextensionreasons = html_writer::select($extensionreasons, '', '', false, [
                    'id' => 'extension-reason-select',
                    'class' => 'form-control',
                ]);
                $selectextensionreasonsdiv .= html_writer::div($selectextensionreasons, 'col-md-9 form-inline felement', ['data-fieldtype' => 'select']);
                $modalbody .= html_writer::div($selectextensionreasonsdiv, 'form-group row fitem');
            }

            $contentextrainformation = html_writer::tag('label', get_string('extra_information', 'mod_coursework'), [
                'class' => 'col-form-label d-inline', 'for' => 'id_extra_information',
            ]);
            $contentextrainformationdiv = html_writer::div($contentextrainformation, 'col-md-3');
            $contentextrainformation = html_writer::tag('textarea', '', [
                'class' => 'form-control',
                'rows' => '8',
                'spellcheck' => 'true',
                'id' => 'id_extra_information',
            ]);
            $contentextrainformationdiv .= html_writer::div($contentextrainformation, 'col-md-9 form-inline felement', ['data-fieldtype' => 'editor']);
            $modalbody .= html_writer::div($contentextrainformationdiv, 'form-group row fitem', ['id' => 'fitem_id_extra_information']);
        }

        $modalbody .= html_writer::end_tag('form');

        $modalfooter = html_writer::empty_tag('img', [
            'src' => $CFG->wwwroot . '/mod/coursework/pix/loadding.gif',
            'alt' => 'Load...',
            'width' => '25',
            'class' => 'loading_moderation icon',
            'style' => 'visibility: hidden;',
        ]);
        $modalfooter .= html_writer::tag('button', 'Save', [
            'type' => 'button',
            'class' => 'btn btn-primary',
            'id' => 'extension-submit',
        ]);
        $modalfooter .= html_writer::tag('button', 'Close', [
            'type' => 'button',
            'class' => 'btn btn-secondary btn-extension-close',
            'data-dismiss' => 'modal',
        ]);
        $modalfooter .= html_writer::tag('button', 'Back', [
            'type' => 'button',
            'class' => 'btn btn-secondary',
            'id' => 'extension-back',
        ]);
        $modalfooter .= html_writer::tag('button', 'Next', [
            'type' => 'button',
            'class' => 'btn btn-secondary',
            'id' => 'extension-next',
        ]);

        $html = html_writer::div($modalheader, 'modal-header');
        $html .= html_writer::div($modalbody, 'modal-body');
        $html .= html_writer::div($modalfooter, 'modal-footer');
        $html = html_writer::div($html, 'modal-content');
        $html = html_writer::div($html, 'modal-dialog modal-lg vertical-align-center', ['role' => 'document']);
        $html = html_writer::div($html, 'vertical-alignment-helper');
        $html = html_writer::div($html, 'modal fade', [
            'id' => 'modal-ajax',
            'tabindex' => '-1',
            'role' => 'dialog',
            'aria-labelledby' => 'modelTitleId',
            'aria-hidden' => 'true',
        ]);
        $html .= html_writer::empty_tag('input', [
            'name' => '',
            'type' => 'hidden',
            'data-lang' => json_encode($langmessages),
            'id' => 'datatables_lang_messages',
        ]);
        $html = html_writer::div($html);

        return $html;
    }

    public function render_modal() {
        $result = $this->render_advance_plugins_form();
        $result .= $this->modal_grading_render();
        return $result;
    }

    protected function render_advance_plugins_form() {
        $form = new \mod_coursework\forms\advance_plugins_form();
        $result = '<div class="hide">' . $form->render() . '</div>';
        return $result;
    }

    /**
     * @return string
     */
    public function modal_grading_render() {
        $this->page->requires->string_for_js('insertcomment', 'gradingform_guide');
        $html = '<div class="modal fade" tabindex="-1" role="dialog" id="modal-grading">
                  <div class="modal-dialog modal-lg modal-grading" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                      </div>
                                            <div class="hide">
                        <input id="cell_selector" type="hidden" />
                        <input id="cell_type" type="hidden" />
                      </div>
                      <div class="modal-body">
                        <i class="fa fa-spin fa-spinner"></i> loading
                      </div>
                       </div><!-- /.modal-content -->
                  </div><!-- /.modal-dialog -->
                </div><!-- /.modal -->
         <style>.modal table.feedback {table-layout: fixed;} .modal .gradingform_rubric, .modal #guide-advancedgrading {width: 100%; overflow-x: scroll} .modal .form-inline>div {width: 100%}</style>';
        return $html;
    }

}
