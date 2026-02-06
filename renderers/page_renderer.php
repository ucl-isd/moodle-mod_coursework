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
use mod_coursework\forms\choose_student_for_submission_mform;
use mod_coursework\forms\moderator_agreement_mform;
use mod_coursework\forms\plagiarism_flagging_mform;
use mod_coursework\forms\student_submission_form;
use mod_coursework\grade_judge;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\models\moderation;
use mod_coursework\models\plagiarism_flag;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use mod_coursework\render_helpers\grading_report\data\cell_data_base;
use mod_coursework\router;

/**
 * Makes the pages
 */
class mod_coursework_page_renderer extends plugin_renderer_base {
    /**
     * @param feedback $feedback
     * @return string
     * @throws \core\exception\coding_exception
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
     * @param $submission
     * @return string
     * @throws \core\exception\coding_exception
     */
    public function show_viewpdf_page($submission) {
        $this->page->set_pagelayout('popup');

        $html = '';
        $objectrenderer = $this->get_object_renderer();
        $html .= $this->output->header();
        $html .= $objectrenderer->render_viewpdf($submission);
        $html .= $this->output->footer();
        return $html;
    }

    /**
     * @param moderation $moderation
     * @throws \core\exception\coding_exception
     */
    public function show_moderation_page($moderation) {
        $html = '';

        $objectrenderer = $this->get_object_renderer();
        $html .= $objectrenderer->render_moderation($moderation);

        echo $this->output->header();
        echo $html;
        echo $this->output->footer();
    }

    public function confirm_feedback_removal_page(feedback $teacherfeedback, $confirmurl) {
        global $SITE;

        $gradingtitle =
            get_string('markingfor', 'coursework', $teacherfeedback->get_submission()->get_allocatable_name());

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
     * @throws \core\exception\coding_exception
     * @throws \core\exception\moodle_exception
     * @throws coding_exception
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
     * @param coursework $coursework
     * @param user $student
     * @return stdClass Template data for rendering.
     * @throws \core\exception\moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function student_view_page($coursework, $student): stdClass {
        // Coursework not yet open for submissions.
        if (!$coursework->start_date_has_passed()) {
            $template = new stdClass();
            $template->startdate = $coursework->startdate;
            return $template;
        }

        // If coursework groups and the student is not in any group.
        if ($coursework->is_configured_to_have_group_submissions() && !$coursework->student_is_in_any_group($student)) {
            $template = new stdClass();
            $template->notingroup = true;
            return $template;
        }

        // $submission here means the existing stuff. Might be the group of the student. The only place where
        // it matters in in pre-populating the form, where it should be empty if this student did not submit
        // the files.
        $submission = $coursework->get_user_submission($student);
        if (!$submission) {
            $submission = $coursework->build_own_submission($student);
        }

        // This should probably not be in the renderer.
        if (
            $coursework->has_individual_autorelease_feedback_enabled() &&
            $coursework->individual_feedback_deadline_has_passed() &&
            !$submission->is_published() && $submission->ready_to_publish()
        ) {
            $submission->publish();
        }

        // WIP - student overview.
        $ability = new ability($student->id(), $coursework);

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
            if ($submission && $submission->id && $ability->can('finalise', $submission) && $submission->has_files()) {
                $template->final = $this->finalise_submission_button($coursework, $submission);
            }
        }

        if ((!$submission || !$submission->persisted()) && $ability->can('new', $submission)) {
            if ($coursework->start_date_has_passed()) {
                $template->submissionbutton = $this->new_submission_button($submission);
            }
        } else if ($submission && $ability->can('edit', $submission)) {
            $template->submissionbutton = $this->edit_submission_button($coursework, $submission);
        }

        return $template;
    }

    /**
     * Makes the HTML interface that allows us to specify what student we wish to display the submission form for.
     * This has to come first so that we can load the student submission form with the relevant student id.
     *
     * @param int $coursemoduleid
     * @param choose_student_for_submission_mform $chooseform
     * @return string HTML
     * @throws \core\exception\coding_exception
     */
    public function choose_student_to_submit_for($coursemoduleid, $chooseform) {
        // Drop down to choose the student if we have no student id.
        // We don't really need to process this form, we just get the studentid as a param and use it.

        $html = $this->output->header();

        $chooseform->set_data(['cmid' => $coursemoduleid]);
        ob_start(); // Forms library echos stuff.
        $chooseform->display();
        $html .= ob_get_contents();
        ob_end_clean();

        $html .= $this->output->footer();

        return $html;
    }

    /**
     * Prepare and render feedback editing page.
     *
     * @param feedback $feedback The feedback object being processed.
     * @param assessor_feedback_mform $simpleform The marking form to display.
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function edit_feedback_page(feedback $feedback, assessor_feedback_mform $simpleform) {
        $coursework = $feedback->get_coursework();
        $submission = $feedback->get_submission();
        $submissionfiles = $submission->get_submission_files();
        $pagename = get_string('submissionfor', 'coursework', $submission->get_allocatable_name());
        $this->page->set_title($pagename);

        // Template.
        $template = new stdClass();
        $template->title = $pagename;

        // Behat running?
        if (defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING) {
            $template->behatrunning = true;
        }

        // PDF or not?
        $template->showpdf = false;
        if ($submissionfiles && method_exists($submissionfiles, 'get_files')) {
            foreach ($submissionfiles->get_files() as $file) {
                if ($file->get_mimetype() === 'application/pdf') {
                    $template->showpdf = true;
                    $template->pdfurl = $this->get_object_renderer()->make_file_url($file);
                    break; // Pdf found.
                }
            }
        }

        // Submission metadata.
        $template->submission = $this->submission_metadata($submission, $coursework, $submissionfiles);

        // Advanced marking.
        $template->advancedmarking = false;
        if ($coursework->is_using_advanced_grading()) {
            $template->advancedmarking = true;
            $gradingcontroller = $coursework->get_advanced_grading_active_controller();
            $gradingdefinition = $gradingcontroller->get_definition();
            // Is this a marking guide?
            $template->isguide = isset($gradingdefinition->guide_criteria);
        }

        // Agreement stage.
        $isagreeing = ($feedback->stageidentifier == 'final_agreed_1');
        if ($isagreeing) {
            $previousfeedbacks = $submission->get_assessor_feedbacks();
            if (!empty($previousfeedbacks)) {
                if ($template->advancedmarking) {
                    // Advanced marking.
                    $template->previousfeedback = $this->render_comparison_view(
                        $previousfeedbacks,
                        $gradingcontroller,
                        $gradingdefinition,
                        $template->isguide
                    );
                } else {
                    // Simple direct grading.
                    $renderedlist = [];
                    $objrenderer = new mod_coursework_object_renderer($this->page, $this->target);
                    foreach ($previousfeedbacks as $prev) {
                        $renderedlist[] = $objrenderer->render_feedback($prev, false);
                    }
                    $template->previousfeedback = implode('', $renderedlist);
                }
            }
        }

        // Output all the things.
        // Form part.
        ob_start();
        $simpleform->display();
        $template->marking = ob_get_clean();
        // Standard bit.
        echo $this->output->header();
        echo $this->render_from_template('mod_coursework/marking/main', $template);
        echo $this->output->footer();
    }



    /**
     * Agree feedback - output markers feedback in mustache.
     *
     * @param array $previousfeedbacks Array of feedback objects.
     * @param gradingform_controller $gradingcontroller
     * @param stdClass $gradingdefinition
     * @param bool $isguide
     * @return string HTML.
     */
    protected function render_comparison_view(
        array $previousfeedbacks,
        gradingform_controller $gradingcontroller,
        stdClass $gradingdefinition,
        bool $isguide
    ): string {
        $criteria = $isguide ? $gradingdefinition->guide_criteria : $gradingdefinition->rubric_criteria;

        $markersdata = [];
        foreach ($previousfeedbacks as $index => $feedback) {
            // Use controller to get instances for specific feedback.
            $instance = $gradingcontroller->get_current_instance($feedback->assessorid, $feedback->id);

            if ($instance) {
                $filling = $isguide ? $instance->get_guide_filling() : $instance->get_rubric_filling();

                $markerobj = new stdClass();
                $markerobj->label = get_string('marker', 'mod_coursework') . " " . ($index + 1);
                $markerobj->fillings = $filling['criteria'] ?? [];
                $markersdata[$index] = $markerobj;
            }
        }

        $template = new stdClass();
        $template->reviewcriteria = [];

        foreach ($criteria as $criterion) {
            $criterionitem = new stdClass();
            $criterionitem->name = $isguide ? $criterion['shortname'] : $criterion['description'];
            $criterionitem->markers = [];

            foreach ($markersdata as $markerinfo) {
                $marker = new stdClass();
                $marker->label = $markerinfo->label;
                $marker->score = 0;
                $marker->maxscore = 0;
                $marker->remark = '';

                $currentfilling = null;
                foreach ($markerinfo->fillings as $fill) {
                    if ($fill['criterionid'] == $criterion['id']) {
                        $currentfilling = $fill;
                        break;
                    }
                }

                if ($isguide) {
                    $marker->maxscore = (float)($criterion['maxscore'] ?? 0);
                    $marker->score = $currentfilling ? (float)($currentfilling['score'] ?? 0) : 0;
                    $marker->remark = $currentfilling ? format_text($currentfilling['remark'], FORMAT_HTML) : '';
                } else {
                    foreach ($criterion['levels'] as $level) {
                        $lvlscore = (float)$level['score'];
                        if ($lvlscore > $marker->maxscore) {
                            $marker->maxscore = $lvlscore;
                        }
                        if ($currentfilling && $currentfilling['levelid'] == $level['id']) {
                            $marker->score = $lvlscore;
                            $marker->remark = format_text($currentfilling['remark'] ?? '', FORMAT_HTML);
                        }
                    }
                }

                $percentraw = ($marker->maxscore > 0) ? ($marker->score / $marker->maxscore) * 100 : 0;
                $marker->percent = (int)round($percentraw);

                $criterionitem->markers[] = $marker;
            }
            $template->reviewcriteria[] = $criterionitem;
        }

        return $this->render_from_template('mod_coursework/marking/review', $template);
    }

    /**
     * Prepare submission metadata for mustache template.
     *
     * @param submission $submission The submission object.
     * @param coursework $coursework The coursework settings object.
     * @param mixed $submissionfiles Submitted files object.
     * @return stdClass Structured data for the template.
     */
    protected function submission_metadata(submission $submission, coursework $coursework, $submissionfiles): stdClass {
        $template = new stdClass();
        $template->submissiondata = new stdClass();
        $template->submissiondata->files = [];

        if ($submissionfiles && method_exists($submissionfiles, 'get_files')) {
            foreach ($submissionfiles->get_files() as $file) {
                $f = new stdClass();
                $f->url = $this->get_object_renderer()->make_file_url($file);
                $f->datemodified = $file->get_timemodified();
                $f->filename = $file->get_filename();

                // Finalised.
                $f->finalised = ($submission->finalisedstatus == 1);

                $template->submissiondata->files[] = $f;
            }
        }

        // Late.
        $template->submittedlate = ($submission->was_late() !== false);

        // Plagiarism.
        $template->submissiondata->flaggedplagiarism = cell_data_base::get_flagged_plagiarism_status($submission);

        // TODO - turnitin stuff.

        return $template;
    }

    /**
     * @param moderation $newmoderation
     * @throws \core\exception\coding_exception
     * @throws \core\exception\moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    public function new_moderation_page($newmoderation) {

        global $SITE, $DB;

        $submission = $newmoderation->get_submission();
        $gradingtitle = get_string('moderationfor', 'coursework', $submission->get_allocatable_name());

        $this->page->set_pagelayout('standard');
        $this->page->navbar->add($gradingtitle);
        $this->page->set_title($SITE->fullname);
        $this->page->set_heading($SITE->fullname);

        $html = $this->output->heading($gradingtitle);
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
        global $SITE;

        $submission = $newplagiarismflag->get_submission();
        $gradingtitle = get_string('plagiarismflaggingfor', 'coursework', $submission->get_allocatable_name());

        $this->page->set_pagelayout('standard');
        $this->page->navbar->add($gradingtitle);
        $this->page->set_title($SITE->fullname);
        $this->page->set_heading($SITE->fullname);

        $html = $this->output->heading($gradingtitle);

        $submiturl = $this->get_router()->get_path('create plagiarism flag', ['plagiarism_flag' => $newplagiarismflag]);
        $simpleform = new plagiarism_flagging_mform($submiturl, ['submissionid' => $submission->id()]);
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

        global $SITE;

        $submission = $plagiarismflag->get_submission();
        $gradingtitle = get_string('plagiarismflaggingfor', 'coursework', $submission->get_allocatable_name());

        $this->page->set_pagelayout('standard');
        $this->page->navbar->add($gradingtitle);
        $this->page->set_title($SITE->fullname);
        $this->page->set_heading($SITE->fullname);

        $submiturl = $this->get_router()->get_path('update plagiarism flag', ['flag' => $plagiarismflag, 'submission' => $submission]);
        $simpleform = new plagiarism_flagging_mform($submiturl, ['plagiarismflagid' => $plagiarismflag->id]);

        $plagiarismflag->plagiarismcomment = ['text' => $plagiarismflag->comment,
                                                    'format' => $plagiarismflag->commentformat];

        $simpleform->set_data($plagiarismflag);
        echo $this->output->header();
        $simpleform->display();
        echo $this->output->footer();
    }

    /**
     * Return submission output data for Mustache.
     *
     * @param submission $submission
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
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
        if ($lateseconds = $submission->was_late()) {
            $template->late = format_time($lateseconds) . " " . strtolower(get_string('late', 'mod_coursework'));
        }

        // Mark.
        if ($submission->is_published()) {
            $judge = new grade_judge($coursework);
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
     * @param bool $isnew
     * @throws ReflectionException
     * @throws \core\exception\coding_exception
     * @throws \core\exception\moodle_exception
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function submission_page($submitform, $submission, $isnew = true) {
        $template = new stdClass();

        $title = $submission->get_coursework()->is_configured_to_have_group_submissions()
            ? ($isnew ? 'addgroupsubmission' : 'editgroupsubmission')
            : ($isnew ? 'addyoursubmission' : 'edityoursubmission');
        $template->title = get_string($title, 'mod_coursework');

        $coursework = $submission->get_coursework();
        $template->markingguideurl = mod_coursework_object_renderer::get_marking_guide_url($coursework);
        $template->plagiarism = plagiarism_similarity_information($submission->get_coursework()->get_course_module());

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
     * @param student_submission_form $submitform
     * @return string
     * @throws \core\exception\coding_exception
     * @throws coding_exception
     */
    public function submit_on_behalf_of_student_interface($student, $submitform) {
        // Allow submission on behalf of the student.

        $html = $this->output->header();

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
     * @return renderer_base
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

        $html .= '<p class="small">' . get_string('finalise_button_info', 'mod_coursework') . '</p>';

        $stringname = $coursework->is_configured_to_have_group_submissions() ? 'finalisegroupsubmission' : 'finaliseyoursubmission';
        $finalisesubmissionpath =
            $this->get_router()->get_path('finalise submission', ['submission' => $submission], true);
        $button = new single_button($finalisesubmissionpath, get_string($stringname, 'mod_coursework'), 'post', single_button::BUTTON_SUCCESS);
        $button->add_confirm_action(get_string('finalise_button_confirm', 'mod_coursework'));
        $button->class = 'd-block';
        $html .= $this->output->render($button);

        $html .= '</div>';

        return $html;
    }

    /**
     * Return an object suitable for rendering in a Mustache template.
     *
     * @param coursework $coursework
     * @param submission $submission
     * @return stdClass with 'label' and 'url' properties.
     * @throws \core\exception\moodle_exception
     * @throws coding_exception
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
     * @throws \core\exception\moodle_exception
     * @throws coding_exception
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
     * Form to upload CSV
     *
     * @param $uploadform
     * @param $csvtype - type will be used to create lang string
     * @return string
     * @throws coding_exception
     */
    public function csv_upload($uploadform, $csvtype) {

        $html = $this->output->header();

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
        $html = $this->output->header();

        // Likely to get string key 'processmarkingsheetupload' or 'processallocationsupload'.
        $title = get_string('process' . $csvtype, 'mod_coursework');
        $html .= html_writer::start_tag('h3');
        $html .= $title;
        $html .= html_writer::end_tag('h3');
        $html .= html_writer::start_tag('p');
        $html .= get_string('process' . $csvtype . 'desc', 'mod_coursework');
        $html .= html_writer::end_tag('p');

        $html .= html_writer::start_tag('p');

        if (!empty($processingresults)) {
            $html .= get_string('followingerrors', 'mod_coursework') . "<br />";
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

        $html .= html_writer::tag('p', html_writer::link($this->page->url, $this->page->heading));
        $html .= html_writer::tag('p', html_writer::link('/mod/coursework/view.php?id=' . $this->page->cm->id, get_string('continuetocoursework', 'coursework')));

        $html .= $this->output->footer();

        return $html;
    }

    public function feedback_upload($form) {

        $html = $this->output->header();

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

        $html = $this->output->header($title);

        $html .= html_writer::start_tag('h3');
        $html .= $title;
        $html .= html_writer::end_tag('h3');

        $html .= html_writer::start_tag('p');
        $html .= get_string('feedbackuploadresultsdesc', 'mod_coursework');
        $html .= html_writer::end_tag('p');

        $html .= html_writer::start_tag('p');

        if (!empty($processingresults)) {
            $html .= get_string('fileuploadresults', 'mod_coursework') . "<br />";
            foreach ($processingresults as $file => $result) {
                $html .= get_string('fileuploadresult', 'mod_coursework', ['filename' => $file, 'result' => $result]) . "<br />";
            }
            $html .= html_writer::end_tag('p');
        } else {
            $html .= get_string('nofilesfound', 'mod_coursework');
        }

        $html .= html_writer::tag('p', html_writer::link('/mod/coursework/view.php?id=' . $this->page->cm->id, get_string('continuetocoursework', 'coursework')));

        $html .= $this->output->footer();

        return $html;
    }

    /**
     * View a summary listing of all courseworks in the current course.
     *
     * @param $courseid
     * @return string
     * @throws \core\exception\moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function view_course_index($courseid) {
        global $CFG, $DB, $USER;
        $o = '';
        $course = $DB->get_record('course', ['id' => $courseid]);
        $strplural = get_string('modulenameplural', 'assign');
        if (!get_coursemodules_in_course('coursework', $course->id, 'm.deadline')) {
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
        $table->head = [ucfirst($formatname), 'Courseworks', 'Deadline', 'Submission', 'Grade'];

        $currentsection = '';
        $printsection = '';
        foreach ($modinfo->instances['coursework'] as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            $coursework = coursework::find($cm->instance);
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
                if ($coursework->usegroups) {
                    $allocatable = $coursework->get_coursework_group_from_user_id($USER->id);
                } else {
                    $allocatable = user::find($USER->id, false);
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
            if (
                isset($gradinginfo->items[0]->grades[$USER->id]) &&
                !$gradinginfo->items[0]->grades[$USER->id]->hidden
            ) {
                $grade = $gradinginfo->items[0]->grades[$USER->id]->str_grade;
            } else {
                $grade = '-';
            }
            $url = $CFG->wwwroot . '/mod/coursework/view.php';
            $link = "<a href=\"{$url}?id={$coursework->coursemodule->id}\">{$coursework->name}</a>";
            $table->data[] = [
                'sectionname' => $printsection,
                'cmname' => $link,
                'timedue' => $timedue,
                'submissioninfo' => $submitted,
                'gradeinfo' => $grade];
        }
        return html_writer::table($table);
    }

    /**
     * Populate template object properties with values for this submission
     * suitable for rendering with a Mustache template.
     *
     * @param stdClass $template Existing data object to be rendered.
     * @param submission $submission Submission instance whose status is to be
     * displayed.
     * @throws coding_exception
     */
    private function add_submission_status(stdClass $template, submission $submission): void {
        $template->submission = new stdClass();

        switch ($submission->get_state(true)) {
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
}
