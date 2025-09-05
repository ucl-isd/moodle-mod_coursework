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

namespace mod_coursework\render_helpers\grading_report\sub_rows;

use html_table_row;
use html_writer;
use mod_coursework\ability;
use mod_coursework\assessor_feedback_row;
use mod_coursework\assessor_feedback_table;
use mod_coursework\grade_judge;
use mod_coursework\models\feedback;
use mod_coursework\models\user;
use mod_coursework\router;
use moodle_url;
use pix_icon;

/**
 * Class no_sub_rows
 */
#[\AllowDynamicProperties]
class multi_marker_feedback_sub_rows implements sub_rows_interface {

    /**
     * Has a new button already been shown?
     * @var bool
     */
    private bool $alreadyshownanewbutton = false;

    /**
     * @param \mod_coursework\grading_table_row_base $rowobject
     * @param int $columnwidth
     * @return string
     */
    public function get_row_with_assessor_feedback_table($rowobject, $columnwidth) {

        /* @var assessor_feedback_table $assessor_feedback_table */
        $assessorfeedbacktable = $rowobject->get_assessor_feedback_table();

        // The number of columns will vary according to what permissions the user has.
        $assessorfeedbacktable->set_column_width($columnwidth);

        return $this->render_assessor_feedback_table($assessorfeedbacktable);

    }

    /**
     * @return \mod_coursework_object_renderer
     */
    protected function get_renderer() {
        global $PAGE;
        return $PAGE->get_renderer('mod_coursework', 'object');
    }

    /**
     * @param $feedbackrow
     * @param $coursework
     * @param null $ability
     * @return string
     */
    public function get_grade_cell_content($feedbackrow, $coursework, $ability = null) {
        global $USER;

        if (empty($ability)) {
            $ability = new ability(user::find($USER), $coursework);
        }
        $needsgradedby = $coursework->allocation_enabled() && $feedbackrow->has_feedback()
            && $feedbackrow->get_graded_by() != $feedbackrow->get_assessor();
        $gradedby = $needsgradedby
            ? get_string('gradedbyname', 'mod_coursework', $feedbackrow->get_graders_name())
            : '';
        $editable = (!$feedbackrow->has_feedback() || $feedbackrow->get_feedback()->finalised)
            ? '' : '</br>'.get_string('notfinalised', 'coursework');
        $result = $this->comment_for_row($feedbackrow, $ability) . $gradedby . $editable;
        return $result;
    }

    /**
     * Renders the table of feedbacks from assessors, which appears under each student's submission in the
     * grading report of the multiple marker courseworks.
     *
     * @param assessor_feedback_table $assessorfeedbacktable
     * @return html_table_row
     */
    protected function render_assessor_feedback_table(assessor_feedback_table $assessorfeedbacktable) {

        global $USER, $PAGE;

        $coursework = $assessorfeedbacktable->get_coursework();
        $ability = new ability(user::find($USER, false), $coursework);
        $feedbackrows = $assessorfeedbacktable->get_renderable_feedback_rows();

        $allocatable = $assessorfeedbacktable->get_allocatable();

        $outputrows = '';
        $tablehtml = '';

        $this->alreadyshownanewbutton = false;
        /* @var $feedback_row assessor_feedback_row */
        foreach ($feedbackrows as $feedbackrow) {

            $stage = $feedbackrow->get_stage();

            // Don't show empty rows with nothing in them
            // As a part of Release 1 we decided to show all rows to apply styling correctly,
            // this is expected to be rewritten for Release 2
            /* if (!$feedback_row->get_assessor()->id() && (!$feedback_row->get_submission() ||
                                                         !$feedback_row->get_submission()->ready_to_grade() ||
                                                          $this->alreadyshownanewbutton)) {
                continue;
            }*/

            $outputrows .= ' <tr class="' . $this->row_class($feedbackrow) . '">';

            if ($coursework->sampling_enabled() && $stage->uses_sampling() && !$stage->allocatable_is_in_sample($allocatable)) {

                $outputrows .= '
                        <td class = "not_included_in_sample" colspan =3>'.get_string('notincludedinsample', 'mod_coursework').'</td>
                        </tr >';
            } else {

                $assessordetails = (empty($feedbackrow->get_assessor()->id()) && $coursework->allocation_enabled()) ?
                     get_string('assessornotallocated', 'mod_coursework') : $this->profile_link($feedbackrow);
                 $outputrows .=
                     '<td>' . $assessordetails. ' </td>
                     <td class="assessor_feedback_grade" data-class-name="' . get_class($this) . '">' .
                     $this->get_grade_cell_content($feedbackrow, $coursework, $ability) .
                     '</td>
                     <td >' . $this->date_for_column($feedbackrow) . '</td ></tr >';
            }
        }

        if (!empty($outputrows)) {

            $allocationstring = ($coursework->allocation_enabled())
                ? get_string('allocatedtoassessor', 'mod_coursework')
                : get_string('assessor', 'mod_coursework');
            /*
            $table_html = '
                <tr class = "submissionrowmultisub">

                  <td colspan = "11" class="assessors" >
                  <table class="assessors" id="assessorfeedbacktable_' . $assessor_feedback_table->get_coursework()
                    ->get_allocatable_identifier_hash($assessor_feedback_table->get_allocatable()) . '">
                    <tr>
                      <th>' . $allocation_string . '</th>
                      <th>' . get_string('grade', 'mod_coursework') . '</th>
                      <th>' . get_string('tableheaddate', 'mod_coursework') . '</th>
                    </tr>';

            $table_html .= $output_rows;

            $table_html .= '
                    </table>
                  </td>
                </tr>';
            */
            $tablehtml = '<table class="assessors" id="assessorfeedbacktable_' . $assessorfeedbacktable->get_coursework()
                ->get_allocatable_identifier_hash($assessorfeedbacktable->get_allocatable()) . '" style="display: none;">
                        <tr>
                          <th>' . $allocationstring . '</th>
                          <th>' . get_string('grade', 'mod_coursework') . '</th>
                          <th>' . get_string('tableheaddate', 'mod_coursework') . '</th>
                        </tr>';

                $tablehtml .= $outputrows;

                $tablehtml .= '
                        </table>';

            return $tablehtml;
        } else {

            if ($assessorfeedbacktable->get_submission() &&
                    ($assessorfeedbacktable->get_coursework()->deadline_has_passed() &&
                    $assessorfeedbacktable->get_submission()->finalised)) {

                $tablehtml = '<tr><td colspan = "11" class="nograde" ><table class="nograde">';
                $tablehtml .= '<tr>' . get_string('nogradescomments', 'mod_coursework') . '</tr>';
                $tablehtml .= '</table></td></tr>';
            }
            return $tablehtml;
        }
    }

    /**
     * @return router
     */
    private function get_router() {
        return router::instance();
    }

    /**
     * @param assessor_feedback_row $feedbackrow
     * @return string
     * @throws \coding_exception
     */
    protected function edit_existing_feedback_link($feedbackrow) {
        global $OUTPUT;

        $linktitle = get_string('editgrade', 'coursework');
        $icon = new pix_icon('edit', $linktitle, 'coursework');
        $linkid = "edit_feedback_" . $feedbackrow->get_feedback()->id;
        $link = $this->get_router()
            ->get_path('ajax edit feedback', ['feedback' => $feedbackrow->get_feedback()]);
        $iconlink = $OUTPUT->action_icon($link, $icon, null, ['id' => $linkid, 'class' => 'edit_feedback']);
        return $iconlink;
    }

    /**
     * @param assessor_feedback_row $feedbackrow
     * @param $submission
     * @return \mod_coursework\framework\table_base
     */
    protected function build_new_feedback($feedbackrow, $submission) {
        global $USER;

        $params = [
            'assessorid' => $USER->id,
            'stage_identifier' => $feedbackrow->get_stage()->identifier(),
        ];
        if ($submission) {
            $params['submissionid'] = $submission->id;
        }
        $newfeedback = feedback::build($params);
        return $newfeedback;
    }

    /**
     * @param assessor_feedback_row $feedbackrow
     * @return string
     * @throws \coding_exception
     */
    private function show_feedback_link($feedbackrow) {
        global $OUTPUT;

        $linktitle = get_string('viewfeedback', 'mod_coursework');
        $linkid = "show_feedback_" . $feedbackrow->get_feedback()->id;
        $link = $this->get_router()
            ->get_path('show feedback', ['feedback' => $feedbackrow->get_feedback()]);
        $iconlink = $OUTPUT->action_link($link,
                                         $linktitle,
                                         null,
                                         ['class' => 'show_feedback', 'id' => $linkid]);
        return $iconlink;
    }

    /**
     * @param assessor_feedback_row $feedbackrow
     * @return string
     * @throws \coding_exception
     */
    protected function new_feedaback_link($feedbackrow) {
        global $USER, $OUTPUT;

        $this->alreadyshownanewbutton = true;
        //        $this->displaytable = true; //todo this is deprecated and causes behat exception - was it doing anything useful?

        // New
        $linktitle = get_string('newfeedback', 'coursework');

        $newfeedbackparams = [
            'submission' => $feedbackrow->get_submission(),
            'assessor' => user::find($USER, false),
            'stage' => $feedbackrow->get_stage(),
        ];
        $link = $this->get_router()->get_path('ajax new feedback', $newfeedbackparams);
        $iconlink = $OUTPUT->action_link($link,
                                         $linktitle,
                                         null,
                                         ['class' => 'new_feedback']);
        return $iconlink;
    }

    /**
     * @param assessor_feedback_row $feedbackrow
     * @return string
     */
    public function profile_link($feedbackrow) {
        global $COURSE;

        $assessor = $feedbackrow->get_assessor();

        $profilelinkurl = new moodle_url('/user/profile.php', ['id' => $assessor->id(),
                                                                    'course' => $COURSE->id]);
        return html_writer::link($profilelinkurl, $assessor->name());
    }

    /**
     * @param assessor_feedback_row $feedbackrow
     * @return string
     */
    protected function row_class($feedbackrow) {
        $assessor = $feedbackrow->get_assessor();
        $rowclass = 'feedback-' . $assessor->id() . '-' . $feedbackrow->get_allocatable()
            ->id() . ' ' . $feedbackrow->get_stage()->identifier();
        return $rowclass;
    }

    /**
     * @param assessor_feedback_row $feedbackrow
     * @return string
     */
    public function date_for_column($feedbackrow) {
        if ($feedbackrow->has_feedback()) {
            return userdate($feedbackrow->get_feedback()->timecreated, '%a, %d %b %Y, %H:%M ');
        }
        return '';
    }

    /**
     * @param assessor_feedback_row $feedbackrow
     * @param ability $ability
     * @return string
     */
    protected function comment_for_row($feedbackrow, $ability) {
        global $USER;

        $submission = $feedbackrow->get_submission();

        $html = '';

        if ($feedbackrow->has_feedback()) {

            if ($ability->can('show', $feedbackrow->get_feedback()) || is_siteadmin($USER->id)) {
                $gradejudge = new grade_judge($feedbackrow->get_coursework());
                $html .= $gradejudge->grade_to_display($feedbackrow->get_feedback()->get_grade());
            } else {
                if (has_capability('mod/coursework:addagreedgrade', $feedbackrow->get_coursework()->get_context())
                     || has_capability('mod/coursework:addallocatedagreedgrade', $feedbackrow->get_coursework()->get_context())) {
                    $html .= get_string('grade_hidden_manager', 'mod_coursework');
                } else {
                    $html .= get_string('grade_hidden_teacher', 'mod_coursework');
                }
            }

            $gradeediting = get_config('mod_coursework', 'coursework_grade_editing');

            if ($ability->can('edit', $feedbackrow->get_feedback()) && !$submission->already_published()) {
                $html .= $this->edit_existing_feedback_link($feedbackrow);
            } else if ($ability->can('show', $feedbackrow->get_feedback())) {
                $html .= $this->show_feedback_link($feedbackrow);
            }
        } else {

            $newfeedback = $this->build_new_feedback($feedbackrow, $submission);
            if ($ability->can('new', $newfeedback) && !$this->alreadyshownanewbutton) {
                $html .= $this->new_feedaback_link($feedbackrow);
            }
        }

        return $html;
    }
}
