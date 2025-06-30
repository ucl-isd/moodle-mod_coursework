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
use mod_coursework\allocation\allocatable;
use mod_coursework\grading_report;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\render_helpers\grading_report\cells\cell_interface;
use mod_coursework\render_helpers\grading_report\sub_rows\sub_rows_interface;

/**
 * Class mod_coursework_grading_report_renderer is responsible for
 *
 */
class mod_coursework_grading_report_renderer extends plugin_renderer_base {

    // WIP - data for table rows.
    public function render_grading_report_new($gradingreport, $ismultiplemarkers) {

        $tablerows = $gradingreport->get_table_rows_for_page();

        // Sort the table rows.
        usort($tablerows, function($rowa, $rowb) {

            $submissiona = $rowa->get_submission();
            $submissionb = $rowb->get_submission();

            // Check if submission is not an object (i.e., it's null or false).
            $isnulloraa = !is_object($submissiona);
            $isnullorab = !is_object($submissionb);

            if ($isnulloraa && $isnullorab) {
                return 0;
            } elseif ($isnulloraa) {
                return 1;
            } elseif ($isnullorab) {
                return -1;
            }

            // Both submissions are objects, compare by timemodified.
            return $submissiona->timemodified <=> $submissionb->timemodified;
        });

        $template = new stdClass();
        $template->tr = [];

        foreach ($tablerows as $rowobject) {
            $trdata = new stdClass();

            $student = $rowobject->get_allocatable();
            /*
            print_r($student);
            print_r('<hr>');
            */

            // User.
            // TODO - id, name, email etc based on settings and permissions.
            $trdata->student = \core_user::get_fullname(\core_user::get_user($student->id));
            if ($student->id) {
                    $user = core_user::get_user($student->id);
                    $userpicture = new user_picture($user);
                    $userpicture->size = 100;
                    $image = $userpicture->get_url($this->page)->out(false);
                    $trdata->studentimg = $image;
            }

            // Submission.
            $submission = $rowobject->get_submission();
            if ($submission) {
                /*
                print_r($submission);
                print_r('<hr>');
                */
                $trdata->submission = new stdClass();
                $trdata->submission->datemodified = userdate($submission->timemodified);
                // TODO - submission related data (filename, fileurl, finalised, late, plagarism, extension etc.)
                // $trdata->submission->filename = ...
                // $trdata->submission->fileurl = ...

            }

            // Markers.
            // TODO - check this has multiple markers, or output something else?
            $assessorfeedbacktable = $rowobject->get_assessor_feedback_table();
            $trdata->markers = $this->render_markers($assessorfeedbacktable);

            // Agreed mark.
            $coursework = $assessorfeedbacktable->get_coursework();
            $allocatable = $rowobject->get_allocatable();
            $trdata->agreedmark = $this->render_agreed_mark($coursework, $rowobject);


            $template->tr[] = $trdata;
        }

        return $this->render_from_template('mod_coursework/submissions/table', $template);
    }


    protected function render_agreed_mark($coursework, $rowobject) {
        global $USER;

        // TODO - weird user model thing, why?
        $user = \mod_coursework\models\user::find($USER);
        $ability = new ability($user, $coursework);
        $stage = $coursework->get_final_agreed_marking_stage();
        $feedback = $stage->get_feedback_for_allocatable($rowobject->get_allocatable());

        $template = new stdClass();

        // Edit.
        if ($feedback && $ability->can('edit', $feedback)) {
            $template->editfeedback = true;
            $template->url = new moodle_url('/mod/coursework/actions/feedbacks/edit.php', ['feedbackid' => $feedback->id]);
            $template->mark = number_format($feedback->grade, 1);
            // TODO - draft, ready for release, released & timemodified.
            return $template;
        }

        // Add.
        $submission = $rowobject->get_submission();
        if ($submission) {
            $newfeedback = feedback::build(['submissionid' => $submission->id(),
                                            'assessorid' => $USER->id,
                                            'stage_identifier' => 'final_agreed_1',
                                            ]);
            if ($ability->can('new', $newfeedback) || is_siteadmin()) {
                $template->addfedback = true;
                $template->url = new moodle_url('/mod/coursework/actions/feedbacks/new.php',
                [
                    'isfinalgrade' => '1',
                    'stage_identifier' => 'final_agreed_1',
                    'submissionid' => $submission->id()
                ]
                );
                return $template;
            }
        }

        return $template;
    }

    protected function render_markers($assessorfeedbacktable) {
        global $USER, $PAGE;

        $template = new stdClass();
        $template->markers = []; // Array to hold data for each marker.

        $coursework = $assessorfeedbacktable->get_coursework();
        // $ability = new ability(user::find($USER, false), $coursework);
        $feedbackrows = $assessorfeedbacktable->get_renderable_feedback_rows();
        $allocatable = $assessorfeedbacktable->get_allocatable();

        // Iterate through feedback rows to gather marker data.
        foreach ($feedbackrows as $feedbackrow) {

            $markerdata = new stdClass();
            $marker = $feedbackrow->get_assessor();
            $submission = $feedbackrow->get_submission();
            $mark = $feedbackrow->get_grade();


            // Marker name.
            if (empty($marker->id()) && $coursework->allocation_enabled()) {
                $markerdata->markername = get_string('assessornotallocated', 'mod_coursework');
            } else {
                $markerdata->markername = \core_user::get_fullname(\core_user::get_user($marker->id));
                $markerdata->markerurl = new moodle_url('/user/profile.php', ['id' => $marker->id(), 'course' => $coursework->get_course_id()]);
                if ($marker->id()) {
                    $user = core_user::get_user($marker->id());
                    $userpicture = new user_picture($user);
                    $userpicture->size = 100;
                    $image = $userpicture->get_url($this->page)->out(false);
                    $markerdata->markerimg = $image;
                }
                // Filter data.
                $markerdata->markerfilter = str_replace(' ', '-', strtolower($markerdata->markername));
            }

            // Mark.
            if (!is_null($mark)) {
                $markerdata->mark = number_format($mark, 1);
                $feedbackobject = $feedbackrow->get_feedback();
                $markerdata->markurl = new moodle_url('/mod/coursework/actions/feedbacks/edit.php', [
                    'feedbackid' => $feedbackobject->id()
                ]);
                $markerdata->timemodified = $feedbackobject->timemodified;
                $markerdata->draft = !$feedbackobject->finalised;
            }
            elseif($submission) {
                $markerdata->addfeedback = true;
                // TODO - what is stage_identifier? Mocked for the moment.
                // Outputs the link, but some odd behaviour when adding/saving.
                // https://workflows.preview-moodle.ucl.ac.uk/mod/coursework/actions/feedbacks/new.php?submissionid=48&stage_identifier=assessor_1&assessorid=675041
                $markerdata->markurl = new moodle_url('/mod/coursework/actions/feedbacks/new.php', [
                    'submissionid' => $submission->id(),
                    'assessorid' => $marker->id(),
                    'stage_identifier' => 'assessor_'.$marker->id()
                ]);
            }



            $template->markers[] = $markerdata;
        }

        return $template->markers;
    }

    /**
     * @param grading_report $gradingreport
     * param $ismultiplemarkers
     * @return string
     */
    public function render_grading_report($gradingreport, $ismultiplemarkers) {
        $options = $gradingreport->get_options();
        $tablerows = $gradingreport->get_table_rows_for_page();
        $cellhelpers = $gradingreport->get_cells_helpers();
        $subrowhelper = $gradingreport->get_sub_row_helper();
        if (count($tablerows) == $gradingreport->realtotalrows) {
            $options['class'] = 'full-loaded';
        }
        $this->rowclass = $ismultiplemarkers ? 'submissionrowmulti' : 'submissionrowsingle';

        if (empty($tablerows)) {
            return '<div class="no-users">'.get_string('nousers', 'coursework').'</div><br>';
        }
        $tablehtml = $this->render_grading_report_new($gradingreport, $ismultiplemarkers);
        $tablehtml .= $this->start_table($options);
        $tablehtml .= $this->make_table_headers($cellhelpers, $options, $ismultiplemarkers);
        $tablehtml .= '</thead>';
        $tablehtml .= '<tbody>';
        $tablehtml .= $this->make_rows($tablerows, $cellhelpers, $subrowhelper, $ismultiplemarkers, );
        $tablehtml .= '</tbody>';
        $tablehtml .= $this->end_table();

        return  $tablehtml;
    }

    /**
     * @param cell_interface $cellhelper
     * @param array $options
     * @return string
     */
    protected function make_header_cell($cellhelper, $options) {
        $seq = empty($options['seq']) ? 0 : $options['seq'];
        $tablehtml = '<th class=' . $cellhelper->get_table_header_class() . ' data-seq=' . $seq . '>';

        $headername = $cellhelper->get_table_header($options);
        $tablehtml .= $headername;
        $tablehtml .= $cellhelper->get_table_header_help_icon();
        $tablehtml .= '</th>';
        return $tablehtml;
    }

    /**
     * @param grading_table_row_base $rowobject
     * @param cell_interface[] $cellhelpers
     * @param sub_rows_interface $subrowhelper
     * @param int $rownumber
     * @return string
     */
    protected function make_row_for_allocatable($rowobject, $cellhelpers, $subrowhelper, int $rownumber) {

        // $class = (!$row_object->get_coursework()->has_multiple_markers()) ? "submissionrowsingle": "submissionrowmulti";
        $class = $this->rowclass;

        $submission = $rowobject->get_submission();
        $allocatable = $rowobject->get_allocatable();
        $rowid = $this->grading_table_row_id($allocatable, $rowobject->get_coursework());
        $tablehtml = "<tr class=\"$class\" id=\"$rowid\" data-allocatable=\"$allocatable->id\">";
        $ismultiplemarkers = $this->ismultiplemarkers;
        $tblassessorfeedbacks = $subrowhelper->get_row_with_assessor_feedback_table($rowobject, count($cellhelpers));

        if ($ismultiplemarkers) {
            $rowclass = "row-$rownumber";
            $tablehtml .= '<td class="details-control ' . $rowclass . '"></td>';
        }

        foreach ($cellhelpers as $cellhelper) {
            $htmltd = trim($cellhelper->get_table_cell($rowobject));

            if ($ismultiplemarkers &&
                ($cellhelper instanceof \mod_coursework\render_helpers\grading_report\cells\user_cell ||
                    $cellhelper instanceof \mod_coursework\render_helpers\grading_report\cells\group_cell)
            ) {
                $htmltd = str_replace('</td>', $tblassessorfeedbacks.'</td>', $htmltd);
            }

            $tablehtml .= $htmltd;
        }
        $tablehtml .= '</tr>';

        if (!$ismultiplemarkers) {
            $tablehtml .= $tblassessorfeedbacks;
        }

        return $tablehtml;
    }

    /**
     * @return string
     */
    public function submissions_header($headertext = '') {
        $submisions = (!empty($headertext)) ? $headertext : get_string('submissions', 'mod_coursework');

        return html_writer::tag('h3', $submisions);
    }

    /**
     * @param $cellhelpers
     * @param $options
     * @param $ismultiplemarkers
     * @return string
     * @throws coding_exception
     */
    protected function make_table_headers($cellhelpers, $options, $ismultiplemarkers) {

        $tablehtml = $this->make_upper_headers($cellhelpers, $ismultiplemarkers);
        $tablehtml .= '<tr>';
        $trhtml = '';

        $i = 0;
        if ($ismultiplemarkers) {
            $trhtml .= '<th class="addition-multiple-button"></th>'; // This is for open or close buttons
            ++$i;
        }

        foreach ($cellhelpers as $cellhelper) {
            $options['seq'] = $i++;
            $trhtml .= $this->make_header_cell($cellhelper, $options);
        }
        return $tablehtml . $trhtml . '</tr>';
    }

    /**
     * @return string
     */
    protected function start_table($options  = []) {
        $options['width'] = '100%';
        $options['class'] = (!empty($options['class'])) ? $options['class'] : '';
        $options['class'] .= ' submissions datatabletest display compact';
        $options['id'] = 'dt_table';
        $tablehtml = \html_writer::start_tag('table', $options);
        $tablehtml .= \html_writer::start_tag('thead');
        return $tablehtml;
    }

    /**
     * @return string
     */
    protected function end_table() {
        return '
            </table>
        ';
    }

    /**
     * @param $tablerows
     * @param $cellhelpers
     * @param $subrowhelper
     * @param $ismultiplemarkers
     * @return string
     */
    public function make_rows($tablerows, $cellhelpers, $subrowhelper, $ismultiplemarkers) {
        $tablehtml = '';

        $this->rowclass = $ismultiplemarkers ? 'submissionrowmulti' : 'submissionrowsingle';
        $this->ismultiplemarkers = $ismultiplemarkers;

        $rownumber = 1;
        foreach ($tablerows as $rowobject) {
            $tablehtml .= $this->make_row_for_allocatable($rowobject, $cellhelpers, $subrowhelper, $rownumber);
            $rownumber++;
        }
        return $tablehtml;
    }

    /**
     * Groupings for the header cells on the next row down.
     *
     * @param $cellhelpers
     * @param $ismultiplemarkers
     * @return string
     * @throws coding_exception
     */
    private function make_upper_headers($cellhelpers, $ismultiplemarkers) {
        $html = '';
        $headers = $this->upper_header_names_and_colspans($cellhelpers);

        foreach ($headers as $headername => $colspan) {
            $colspanvalue = $colspan;

            if ($html == '' && $ismultiplemarkers) {
                $colspanvalue += 1;
            }

            $html .= '<th colspan="'.$colspanvalue.'">';
            $html .= get_string($headername.'_table_header', 'mod_coursework');
            $html .= get_string($headername.'_table_header', 'mod_coursework')
                ? ($this->output->help_icon($headername.'_table_header', 'mod_coursework')) : '';
            $html .= '</th>';
        }

        return $html;
    }

    /**
     * @param cell_interface[] $cellhelpers
     * @return mixed
     */
    private function upper_header_names_and_colspans($cellhelpers) {
        $headers = [];

        foreach ($cellhelpers as $helper) {
            if (!array_key_exists($helper->header_group(), $headers)) {
                $headers[$helper->header_group()] = 1;
            } else {
                $headers[$helper->header_group()]++;
            }
        }
        return $headers;
    }

    /**
     * @param allocatable $allocatable
     * @param coursework $coursework
     * @return string
     */
    public function grading_table_row_id(allocatable $allocatable, coursework $coursework) {
        return 'allocatable_' . $coursework->get_allocatable_identifier_hash($allocatable);
    }

}
