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
use mod_coursework\models\deadline_extension;
use mod_coursework\models\feedback;
use mod_coursework\models\moderation;
use mod_coursework\models\moderation_set_rule;
use mod_coursework\models\personaldeadline;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use mod_coursework\personaldeadline\table\row\builder;
use mod_coursework\renderers\grading_report_renderer;
use mod_coursework\router;
use mod_coursework\warnings;

defined('MOODLE_INTERNAL') || die();

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
     * @throws \core\exception\moodle_exception
     * @throws coding_exception
     */
    public function render_feedback(feedback $feedback, $showtitle = true) {
        $template = new stdClass();

        $template->markingstage = $feedback->stageidentifier;

        $submission = $feedback->get_submission();
        $coursework = $feedback->get_coursework();
        $studentname = $submission->get_allocatable_name();

        if ($showtitle) {
            // Determine the feedback title.
            if ($feedback->is_agreed_grade()) {
                $template->title = get_string('finalfeedback', 'mod_coursework', $studentname);
            } else if ($feedback->is_moderation()) {
                $template->title = get_string('moderatorfeedback', 'mod_coursework', $studentname);
            } else {
                $stage = $feedback->get_assessor_stage_no();
                $template->title = get_string('componentfeedback', 'mod_coursework', ['stage' => $stage, 'student' => $studentname]);
            }
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
                $userpicture = new user_picture($feedback->assessor()->get_raw_record());
                $userpicture->size = 100;
                $template->markerimg = $userpicture->get_url($this->page)->out(false);
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
            $template->advancedgradinghtml = $this->render_advanced_grading($coursework, $feedback);
        }

        if ($template->feedbackcomment || isset($template->feedbackfileshtml)) {
            $template->separator = true;
        }

        // Return html from template.
        return $this->render_from_template('mod_coursework/feedback', $template);
    }

    /**
     * Render advanced grading for students.
     *
     * @param coursework $coursework
     * @param feedback $feedback
     * @return string Template HTML or ''
     */
    protected function render_advanced_grading(coursework $coursework, feedback $feedback): string {
        $gradingcontroller = $coursework->get_advanced_grading_active_controller();
        if (!$gradingcontroller) {
            return '';
        }

        $instance = $gradingcontroller->get_current_instance(
            $feedback->assessorid,
            $feedback->id
        );

        if (!$instance) {
            return '';
        }

        $template = new stdClass();
        $template->customgrading = [];

        $gradingdefinition = $gradingcontroller->get_definition();
        $isguide = isset($gradingdefinition->guide_criteria);

        // Filling method & criteria for the guide or rubric.
        if ($isguide) {
            $filling = $instance->get_guide_filling();
            $criteria = $gradingdefinition->guide_criteria;
        } else {
            $filling = $instance->get_rubric_filling();
            $criteria = $gradingdefinition->rubric_criteria;
        }
        $fillings = $filling['criteria'] ?? [];

        // Criteria.
        foreach ($criteria as $criterion) {
            $criterionid = $criterion['id'];
            $currentfilling = null;
            foreach ($fillings as $f) {
                if ($f['criterionid'] == $criterionid) {
                    $currentfilling = $f;
                    break;
                }
            }

            $item = new stdClass();
            $item->id = $criterionid;
            $item->name = $isguide ? $criterion['shortname'] : $criterion['description'];

            $description = $criterion['description'] ?? '';
            $format = $criterion['descriptionformat'] ?? FORMAT_HTML;
            $item->description = $isguide ? format_text($description, $format) : '';

            // Criteria marks and feedback.
            $item->maxscore = 0;
            $item->score = 0;
            $item->remark = '';

            if ($isguide) {
                $item->maxscore = (float)($criterion['maxscore'] ?? 0);
                $item->score = $currentfilling ? (float)($currentfilling['score'] ?? 0) : 0;
                $item->remark = $currentfilling ? format_text($currentfilling['remark'] ?? '', FORMAT_HTML) : '';
            } else if (isset($criterion['levels'])) {
                // Rubric data using array for 'levels'.
                foreach ($criterion['levels'] as $level) {
                    if ((float)$level['score'] > $item->maxscore) {
                        $item->maxscore = (float)$level['score'];
                    }
                    if ($currentfilling && $currentfilling['levelid'] == $level['id']) {
                        $item->score = (float)$level['score'];
                        $item->remark = format_text($currentfilling['remark'] ?? '', FORMAT_HTML);
                        // NOTE: Using s() not format_text().
                        // So rubric definition is plain text, without filters, and removing legacy DB content.
                        $item->rubricdefinition = s($level['definition'] ?? '');
                    }
                }
            }

            // Have we got comments? remark or rubric definition.
            if (!empty($item->remark) || !empty($item->rubricdefinition)) {
                $item->hascomments = true;
            }

            // Percentage for progress - round to the nearest whole number.
            $percent = ($item->maxscore > 0) ? ($item->score / $item->maxscore) * 100 : 0;
            $item->percent = (int)round($percent);

            $template->customgrading[] = $item;
        }

        return $this->render_from_template('mod_coursework/feedback/advanced_grading', $template);
    }

    /**
     * Renders a coursework feedback as a row in a table.
     * This is for the grading report when we have multiple markers and we want an AJAX pop up *
     * with details of the feedback. Also for the student view.
     *
     * @param submission $submission
     * @return string
     * @throws \core\exception\moodle_exception
     * @throws coding_exception
     */
    public function render_viewpdf(submission $submission) {
        $template = new stdClass();

        $studentname = $submission->get_allocatable_name();

        $template->title = get_string('viewsubmission', 'mod_coursework', $studentname);
        $template->files = [];

        $annotatedfiles = $submission->get_file_annotations();
        foreach ($submission->get_submission_files()->get_files() as $file) {
            if ($file->get_mimetype() !== 'application/pdf') {
                continue;
            }

            $model = [
                'filename' => $file->get_filename(),
                'href' => self::make_file_url($file),
                'fileid' => $file->get_id(),
                'submissionid' => $submission->id,
            ];

            if (isset($annotatedfiles[$file->get_id()])) {
                $annotatedfile = $annotatedfiles[$file->get_id()];
                $model['annotatedfileurl'] = self::make_file_url($annotatedfile);
                $model['annotatedfileid'] = $annotatedfile->get_id();
            }

            $template->files[] = (object)$model;
        }

        $template->multiplefiles = (count($template->files) > 1);

        $this->page->requires->js_call_amd(
            "mod_coursework/viewpdf",
            'init',
        );
        return $this->render_from_template('mod_coursework/viewpdf', $template);
    }


    /**
     * Renders a coursework moderation as a row in a table.
     *
     * @param moderation $moderation
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     */
    public function render_moderation(moderation $moderation) {

        $title =
            get_string('moderationfor', 'coursework', $moderation->get_submission()->get_allocatable_name());

        $out = '';
        $moderatedby = core_user::get_fullname(core_user::get_user($moderation->moderatorid));
        $lasteditedby = core_user::get_fullname(core_user::get_user($moderation->lasteditedby));

        $table = new html_table();
        $table->attributes['class'] = 'moderation';
        $table->id = 'moderation' . $moderation->id;
        $header = new html_table_cell();
        $header->colspan = 2;
        $header->text = $title;
        $table->head[] = $header;

        // Moderated by
        $tablerow = new html_table_row();
        $leftcell = new html_table_cell();
        $rightcell = new html_table_cell();
        $leftcell->text = get_string('moderatedby', 'coursework');
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
        return implode($br, $filesarray);
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
        return implode($br, $filesarray);
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

        $ability = new ability($USER->id, $files->get_coursework());

        $coursework = $files->get_coursework();
        $submissionfiles = $files->get_files();
        $submission = $files->get_submission();
        $filesarray = [];

        foreach ($submissionfiles as $file) {
            $link = $this->make_file_link($files, $file);

            if ($ability->can('view_plagiarism', $submission)) {
                // With no stuff to show, $plagiarismlinks comes back as '<br />'.
                $link .= '<div class ="percent">' . $this->render_file_plagiarism_information($file, $coursework, $submission) . '</div>';
            }

            if ($withresubmitbutton) {
                $link .= '<div class ="subbutton">' . $this->render_resubmit_to_plagiarism_button($coursework, $submission) . '</div>';
            }

            $filesarray[] = $link;
        }

        $br = html_writer::empty_tag('br');
        return implode($br, $filesarray);
    }

    /**
     * Outputs the files as a HTML list.
     *
     * @param mod_coursework_submission_files $files
     * @return string
     */
    public function render_plagiarism_links($files) {

        global $USER;

        $ability = new ability($USER->id, $files->get_coursework());

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
        return implode($br, $filesarray);
    }

    /**
     * Displays a coursework so that we can see the intro, deadlines etc at the top of view.php
     *
     * @param mod_coursework_coursework $coursework renderable object containing coursework
     * @return string html
     * @throws ReflectionException
     * @throws \core\exception\coding_exception
     * @throws \core\exception\moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function render_mod_coursework_coursework(mod_coursework_coursework $coursework): string {
        global $USER;
        $student = user::find($USER, false);
        $coursework = $coursework->wrapped_object();
        $template = new stdClass();
        $template->cmid = $coursework->get_coursemodule_id();

        // Capability checks
        $canallocate = has_capability('mod/coursework:allocate', $coursework->get_context());
        $cangrade = has_capability('mod/coursework:addinitialgrade', $this->page->context);
        $canpublish = has_capability('mod/coursework:publish', $this->page->context);
        $cansubmit = has_capability('mod/coursework:submit', $this->page->context);

        // Warnings.
        $warnings = new warnings($coursework);
        // Allocation.
        if ($canallocate) {
            $warnings->not_enough_assessors();
            $warnings->percentage_allocations_not_complete();
        }
        // Groups.
        if ($coursework->usegroups == 1) {
            $warnings->students_in_mutiple_groups();
            $warnings->student_in_no_group();
        }

        if (groups_get_activity_groupmode($coursework->get_course_module()) != NOGROUPS) {
            $group = groups_get_activity_group($coursework->get_course_module(), true);
            $warnings->group_mode_chosen_warning($group);

            if ($group != 0) {
                $warnings->filters_warning();
            }
        }

        $template->warnings = $warnings->get_warnings();

        // Teacher data.
        $submissionstable = '';
        if ($cangrade || $canpublish) {
            // Submissions table.
            $gradingreportrenderer = new grading_report_renderer($this->page, RENDERER_TARGET_GENERAL);
            $templatedata = $gradingreportrenderer->get_grading_report_data($coursework);
            $submissionstable = $this->render_from_template('mod_coursework/submissions/table', $templatedata);

            // Marking summary.
            $template->canmark = true;
            $template->markingsummary = $templatedata->markingsummary;
            $template->dropdown = $this->get_export_upload_links($coursework);
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
            $template->introdates = $this->add_intro_dates($coursework);
            $template->description = format_module_intro('coursework', $coursework, $coursework->get_coursemodule_id());

            // Marking guide - from advanced grading.
            $template->markingguideurl = self::get_marking_guide_url($coursework);
        }

        if ($coursework->is_general_feedback_released()) {
            $template->generalfeedback = $coursework->get_general_feedback();
        }

        $intro = $this->render_from_template('mod_coursework/intro', $template);

        return $intro . $submissionstable;
    }

    /**
     * Outputs the buttons etc to choose and trigger the auto allocation mechanism. Do this as part of the main form so we
     * can choose some allocations, then click a button to auto-allocate the rest.
     * @param mod_coursework_allocation_widget $allocationwidget
     * @return string
     * @throws \core\exception\moodle_exception
     * @throws coding_exception
     */
    public function render_mod_coursework_allocation_widget(mod_coursework_allocation_widget $allocationwidget) {
        $coursework = $allocationwidget->get_coursework();
        $template = new stdClass();

        // Prepare strategy options for the select dropdown.
        $template->strategy = [];
        $options = manager::get_allocation_classnames();
        $currentstrategy = $allocationwidget->get_assessor_allocation_strategy();

        foreach ($options as $value => $string) {
            $strategyoption = new stdClass();
            $strategyoption->value = $value;
            $strategyoption->string = $string;
            if ($value === $currentstrategy) {
                $strategyoption->selected = true;
            }
            $template->strategy[] = $strategyoption;
        }

        // Get the HTML for the strategy-specific configuration options.
        $template->strategyoptionshtml = $this->get_allocation_strategy_form_elements($coursework);

        return $this->render_from_template('mod_coursework/allocate/allocationwidget', $template);
    }

    /**
     * @return router
     */
    protected function get_router() {
        return router::instance();
    }

    public function render_mod_coursework_sampling_set_widget(mod_coursework_sampling_set_widget $samplingwidget) {

        $coursework = $samplingwidget->get_coursework();
        $template = new stdClass();
        $template->headers = [];
        $template->columns = [];

        // Prepare headers.
        for ($i = 0; $i < $samplingwidget->get_coursework()->get_max_markers(); $i++) {
            $template->headers[] = get_string('markerheading', 'mod_coursework', $i + 1);
        }

        // Prepare scale input.
        $scale = "";
        if ($coursework->grade > 0) {
            $comma = "";
            for ($i = 0; $i <= $coursework->grade; $i++) {
                $scale .= $comma . $i;
                $comma = ",";
            }
        } else {
            $gradescale = grade_scale::fetch(['id' => abs($coursework->grade)]);
            $scale = $gradescale->scale;
        }
        $template->scaleinput = html_writer::empty_tag('input', ['id' => 'scale_values', 'type' => 'hidden', 'value' => $scale]);

        // Prepare columns.
        // Assessor 1 column is always manual.
        $assessor1cell = html_writer::start_tag('div', ['class' => 'samples_strategy']);
        $assessor1cell  .= get_string('markeronedefault', 'mod_coursework');
        $assessor1cell  .= html_writer::end_tag('div');
        $template->columns[]['html'] = $assessor1cell;

        // Prepare columns for other assessors.
        $javascript = false;
        for ($i = 2; $i <= $coursework->get_max_markers(); $i++) {
            $samplingstrategies = ['0' => get_string('sampling_manual', 'mod_coursework'),
                                              '1' => get_string('sampling_automatic', 'mod_coursework')];

            // Check whether any rules have been saved for this stage
            $selected = ($coursework->has_automatic_sampling_at_stage('assessor_' . $i)) ? '1' : false;

            $samplingcell = html_writer::start_tag('div', ['class' => 'samples_strategy']);
            $samplingcell .= html_writer::label(get_string('sampletype', 'mod_coursework'), "assessor_{$i}_samplingstrategy");
            $samplingcell .= html_writer::select(
                $samplingstrategies,
                "assessor_{$i}_samplingstrategy",
                $selected,
                false,
                ['id' => "assessor_{$i}_samplingstrategy", 'class' => "assessor_sampling_strategy sampling_strategy_detail"]
            );
            $samplingcell .= html_writer::end_tag('div');

            if ($i == $coursework->get_max_markers()) {
                $javascript = true;
            }

            $graderules = html_writer::start_tag('h4');
            $graderules .= get_string('markrules', 'mod_coursework');
            $graderules .= html_writer::end_tag('h4');
            $graderules .= $this->get_sampling_strategy_form_elements($coursework, $i, $javascript);
            $samplingcell .= html_writer::div($graderules, '', ['id' => "assessor_{$i}_automatic_rules"]);

            $template->columns[]['html'] = $samplingcell;
        }

        return $this->render_from_template('mod_coursework/allocate/samplingwidget', $template);
    }

    /**
     * Outputs a rule object on screen so we can see what it does.
     *
     * @param moderation_set_rule $rule
     * @return html_table_row
     * @throws coding_exception
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
            if (str_contains($fullclassname, 'base')) {
                continue;
            }
            preg_match('/([^\/]+).php/', $fullclassname, $matches);
            $classname = $matches[1];
            $fullclassname = '\mod_coursework\allocation\strategy\\' . $classname;
            // We want the elements from all the strategies so we can show/hide them.
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

    protected function get_sampling_strategy_form_elements($coursework, $assessornumber, $loadjavascript = false) {

        global $CFG, $DB;

        $html = '';
        $javascript = '';
        $classdir = $CFG->dirroot . '/mod/coursework/classes/sample_set_rule/';

        $sampleplugins = $DB->get_records('coursework_sample_set_plugin', null, 'pluginorder');

        foreach ($sampleplugins as $plugin) {
            preg_match('/([^\/]+).php/', $classdir . "/" . $plugin->rulename . ".php", $matches);
            $classname = $matches[1];
            $fullclassname = '\mod_coursework\sample_set_rule\\' . $classname;

            $samplingrule = new $fullclassname($coursework);

            $html .= $samplingrule->add_form_elements($assessornumber);

            if ($loadjavascript) {
                $javascript .= $samplingrule->add_form_elements_js($assessornumber);
            }
        }

        return $html . " " . $javascript;
    }

    /**
     * @param coursework $coursework
     * @param submission $submission
     * @throws coding_exception
     * @return string
     */
    protected function resubmit_to_plagiarism_button($coursework, $submission) {
        $html = html_writer::start_tag(
            'form',
            ['action' => $this->page->url,
                'method' => 'POST']
        );
        $html .= html_writer::empty_tag(
            'input',
            ['type' => 'hidden',
                                                        'name' => 'submissionid',
            'value' => $submission->id]
        );
        $html .= html_writer::empty_tag(
            'input',
            ['type' => 'hidden',
                                                        'name' => 'id',
            'value' => $coursework->get_coursemodule_id()]
        );
        $plagiarismpluginnames = [];
        foreach ($coursework->get_plagiarism_helpers() as $helper) {
            $plagiarismpluginnames[] = $helper->human_readable_name();
        }
        $plagiarismpluginnames = implode(' ', $plagiarismpluginnames);

        $resubmit = get_string('resubmit', 'coursework', $plagiarismpluginnames);
        $html .= html_writer::empty_tag(
            'input',
            ['type' => 'submit',
                                                        'value' => $resubmit,
            'name' => 'resubmit']
        );
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
        return plagiarism_get_links($plagiarismlinksparams);
    }

    /**
     * @param mod_coursework_submission_files $files
     * @param stored_file $file
     * @param string $classname
     * @return string
     */
    protected function make_file_link($files, $file, $classname = 'submissionfile') {
        if (
            $files->get_file_area_name() == 'submission'
            &&
            $files->get_coursework()->enablepdfjs()
            &&
            ($file->get_mimetype() == 'application/pdf')
        ) {
            return $this->make_pdfjs_link($file, $classname);
        }

        $filename = $file->get_filename();

        $image = $this->output->pix_icon(
            file_file_icon($file),
            $filename,
            'moodle',
            ['class' => 'submissionfileicon']
        );

        return html_writer::link($this->make_file_url($file), $image . $filename, ['class' => $classname]);
    }

    /**
     * @param stored_file $file
     * @return moodle_url
     */
    public function make_file_url($file) {
        return moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            'mod_coursework',
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );
    }

    /**
     * @param stored_file $file
     * @param string $classname
     * @return string
     * @throws \core\exception\moodle_exception
     * @throws coding_exception
     */
    private function make_pdfjs_link($file, $classname = 'submissionfile') {
        $filename = $file->get_filename();

        $image = $this->output->pix_icon(
            file_file_icon($file),
            $filename,
            'moodle',
            ['class' => 'submissionfileicon']
        );

        $viewurl = new moodle_url("/mod/coursework/actions/feedbacks/viewpdf.php", ['submissionid' => $file->get_itemid()]);

        $retval = html_writer::link($viewurl, $image . $filename, ['class' => $classname, 'target' => '_blank']);

        $downloadimage = $this->output->pix_icon('i/export', get_string('download'), 'core');
        $retval .= html_writer::link($this->make_file_url($file), $downloadimage);

        return $retval;
    }

    /**
     * @param coursework $coursework
     * @param submission $submission
     * @return string
     * @throws coding_exception
     */
    protected function render_resubmit_to_plagiarism_button($coursework, $submission) {
        global $USER;

        $ability = new ability($USER->id, $coursework);
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
     * @param mod_coursework_coursework|coursework $coursework
     * @return moodle_url|null Null if there's no advanced grading form set up.
     * @throws ReflectionException
     * @throws \core\exception\moodle_exception
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

            return new moodle_url(
                '/grade/grading/form/' . $methodname . '/preview.php',
                ['areaid' => $controller->get_areaid()]
            );
        }

        return null;
    }

    /**
     * @param coursework $coursework
     * @return stdClass
     * @throws coding_exception
     * @throws dml_exception
     */
    private function add_intro_dates(coursework $coursework) {
        global $USER;

        $template = new stdClass();

        // Fetch student and deadline information.
        $user = user::find($USER, false);

        // Handle coursework deadline details.
        if ($coursework->has_deadline()) {
            // Determine the effective deadline.
            if ($personaldeadline = personaldeadline::get_personaldeadline_for_student($user, $coursework)) {
                $template->duedate = $personaldeadline->personaldeadline;
            } else {
                $template->duedate = $coursework->deadline;
            }

            if ($coursework->allow_late_submissions()) {
                $template->latesubmissionsallowed = true;
            }

            if ($coursework->personaldeadlines_enabled() && (!has_capability('mod/coursework:submit', $this->page->context) || is_siteadmin($user))) {
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
     * @throws coding_exception
     */
    protected function existing_feedback_from_teachers($submission) {

        global $USER;

        $coursework = $submission->get_coursework();

        $html = '';

        // Start with final feedback. Use moderated grade?

        $finalfeedback = $submission->get_final_feedback();

        $ability = new ability($USER->id, $submission->get_coursework());

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
     * Makes the HTML table for allocating markers to students and returns it.
     *
     * @param mod_coursework_personaldeadlines_table $personaldeadlinestable
     * @return string
     * @throws \core\exception\moodle_exception
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function render_mod_coursework_personaldeadlines_table(mod_coursework_personaldeadlines_table $personaldeadlinestable) {
        $courseworkpageurl = $this->get_router()->get_path('coursework', ['coursework' => $personaldeadlinestable->get_coursework()]);
        $tablehtml = '<div class="return_to_page">' . html_writer::link($courseworkpageurl, get_string('returntocourseworkpage', 'mod_coursework')) . '</div>';
        $tablehtml .= '<div class="alert">' . get_string('nopersonaldeadlineforextensionwarning', 'mod_coursework') . '</div>';
        $tablehtml .= '<div class="largelink">' . html_writer::link('#', get_string('setdateforselected', 'mod_coursework', $personaldeadlinestable->get_coursework()->get_allocatable_type()), ['id' => 'selected_dates']) . '</div>';

        if (has_capability('mod/coursework:revertfinalised', $this->page->context)) {
            $tablehtml .= '<div class="largelink">' . html_writer::link('#', get_string('unfinaliseselected', 'mod_coursework', $personaldeadlinestable->get_coursework()->get_allocatable_type()), ['id' => 'selected_unfinalise']) . '</div>';
        }
        $tablehtml .= '<br />';
        $url = $this->get_router()->get_path('edit personal deadline', []);

        $tablehtml .= '<form  action="' . $url . '" id="coursework_personaldeadline_form" method="post">';

        $tablehtml .= '<input type="hidden" name="courseworkid" value="' . $personaldeadlinestable->get_coursework()->id() . '" />';
        $tablehtml .= '<input type="hidden" name="allocatabletype" value="' . $personaldeadlinestable->get_coursework()->get_allocatable_type() . '" />';
        $tablehtml .= '<input type="hidden" name="setpersonaldeadlinespage" value="1" />';
        $tablehtml .= '<input type="hidden" name="multipleuserdeadlines" value="1" />';
        $tablehtml .= '<input type="hidden" name="selectedtype" id="selectedtype" value="date" />';

        $tablehtml .= '

            <table class="personaldeadline display">
                <thead>
                <tr>

        ';

        $allocatablecellhelper = $personaldeadlinestable->get_allocatable_cell();
        $personaldeadlinescellhelper = $personaldeadlinestable->get_personaldeadline_cell();
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
            $tablehtml .= $this->render_personaldeadline_table_row($row);
        }

        $tablehtml .= '
                </tbody>
            </table>
        ';

        $tablehtml .= '</form>';

        return $tablehtml;
    }

    /**
     * This is used on the old bulk personal deadlines page i.e. actions/set_personaldeadlines.php.
     * @param builder $personaldeadlinerow
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     */
    private function render_personaldeadline_table_row($personaldeadlinerow) {

        global $USER;

        $coursework = $personaldeadlinerow->get_coursework();

        $personaldeadline =
            personaldeadline::get_personaldeadline_for_student(user::find($personaldeadlinerow->get_allocatable()->id()), $coursework);

        if (!$personaldeadline) {
            $personaldeadline = personaldeadline::build(
                [
                    'allocatableid' => $personaldeadlinerow->get_allocatable()->id(),
                    'allocatabletype' => $personaldeadlinerow->get_allocatable()->type(),
                    'courseworkid' => $personaldeadlinerow->get_coursework()->id,
                ]
            );
        }

        $ability = new ability($USER->id, $coursework);
        $disabledelement = (!$personaldeadline || ($personaldeadline && $ability->can('edit', $personaldeadline)) ) ? "" : " disabled='disabled' ";

        $rowhtml = '<tr id="' . $personaldeadlinerow->get_allocatable()->type() . '_' . $personaldeadlinerow->get_allocatable()->id() . '">';
        $rowhtml .= '<td>';
        $rowhtml .= '<input type="checkbox" name="allocatableid_arr[' . $personaldeadlinerow->get_allocatable()->id() . ']" id="date_' . $personaldeadlinerow->get_allocatable()->type() . '_' . $personaldeadlinerow->get_allocatable()->id() . '" class="date_select" value="' . $personaldeadlinerow->get_allocatable()->id() . '" ' . $disabledelement . ' >';
        $rowhtml .= '<input type="hidden" name="allocatabletype_' . $personaldeadlinerow->get_allocatable()->id() . '" value="' . $personaldeadlinerow->get_allocatable()->type() . '" />';
        $rowhtml .= '</td>';

        $allocatablecellhelper = $personaldeadlinerow->get_allocatable_cell();
        $personaldeadlinescellhelper = $personaldeadlinerow->get_personaldeadline_cell();
        $rowhtml .= $allocatablecellhelper->get_table_cell($personaldeadlinerow);
        $rowhtml .= $personaldeadlinescellhelper->get_table_cell($personaldeadlinerow);
        $rowhtml .= '';
        $rowhtml .= "<td>" . $personaldeadlinerow->get_submission_status() . "</td>";
        $rowhtml .= '</tr>';

        return $rowhtml;
    }

    /**
     * Generates the dropdown data for export and upload links.
     *
     * @param coursework $coursework The coursework activity object.
     * @return array An array containing the structured dropdown data.
     * @throws \core\exception\moodle_exception
     * @throws coding_exception
     */
    private function get_export_upload_links(coursework $coursework): array {
        $cmid = $this->page->cm->id;
        $viewurl = '/mod/coursework/view.php';
        $submissions = $coursework->get_all_submissions();
        $hasfinalised = $coursework->get_finalised_submissions();
        $finalised = submission::$pool[$coursework->id]['finalisedstatus'][submission::FINALISED_STATUS_FINALISED] ?? [];
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
                        'lang' => 'finalmarks',
                        'cap' => ($can('mod/coursework:viewallgradesatalltimes') && $can('mod/coursework:canexportfinalgrades') && $hasfinalised),
                    ],
                    [
                        'url' => new moodle_url($viewurl, ['id' => $cmid, 'export_grading_sheet' => 1]),
                        'lang' => 'markingspreadsheet',
                        'cap' => $canmark,
                    ],
                ],
            ],
            'upload' => [
                'name' => get_string('upload'),
                'actions' => [
                    [
                        'url' => new moodle_url('/mod/coursework/actions/upload_grading_sheet.php', ['cmid' => $cmid]),
                        'lang' => 'markingspreadsheet',
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
}
