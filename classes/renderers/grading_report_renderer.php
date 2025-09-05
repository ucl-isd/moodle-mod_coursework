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

namespace mod_coursework\renderers;

use mod_coursework\ability;
use mod_coursework\allocation\allocatable;
use mod_coursework\grading_report;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\coursework;
use mod_coursework\models\user;
use mod_coursework\render_helpers\grading_report\cells\cell_interface;
use mod_coursework\render_helpers\grading_report\data\actions_cell_data;
use mod_coursework\render_helpers\grading_report\data\marking_cell_data;
use mod_coursework\render_helpers\grading_report\data\student_cell_data;
use mod_coursework\render_helpers\grading_report\data\submission_cell_data;
use mod_coursework\render_helpers\grading_report\sub_rows\sub_rows_interface;
use stdClass;

/**
 * Class to render the grading / submission table.
 *
 */
class grading_report_renderer extends \core\output\plugin_renderer_base {

    /**
     * Renders the grading report.
     *
     * @param grading_report $gradingreport
     * @return bool|string
     * @throws \core\exception\moodle_exception
     */
    public function render_grading_report(grading_report $gradingreport) {

        $tablerows = $gradingreport->get_table_rows_for_page();

        // Sort the table rows.
        $this->sort_table_rows($tablerows);

        $template = new stdClass();
        $template->coursework = [
            'id' => $gradingreport->get_coursework()->id,
            'title' => $gradingreport->get_coursework()->name,
        ];
        $template->defaultduedate = $gradingreport->get_coursework()->get_deadline();
        $template->isgroupsubmission = $gradingreport->get_coursework()->is_configured_to_have_group_submissions();
        $template->releasemarks = $this->prepare_release_marks_button($gradingreport->get_coursework());
        $template->tr = [];
        $template->markerfilter = [];

        /** @var grading_table_row_base $rowobject */
        foreach ($tablerows as $rowobject) {
            $trdata = $this->get_table_row_data($gradingreport->get_coursework(), $rowobject);
            $this->set_tr_marker_filter($trdata);

            // Collect markers for filter.
            if (!empty($trdata->markers)) {
                // Add valid markers to filter, preserving only first occurrence.
                foreach (array_filter($trdata->markers, fn($m) => isset($m->markerid)) as $marker) {
                    if (!array_key_exists($marker->markerid, $template->markerfilter)) {
                        $template->markerfilter[$marker->markerid] = $marker;
                    }
                }
            }
            $template->tr[] = $trdata;
        }
        $template->markerfilter = array_values($template->markerfilter);

        return $this->render_from_template('mod_coursework/submissions/table', $template);
    }

    protected function sort_table_rows($tablerows) {
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
    }

    /**
     * Get the data for a single row in the table (representing one "allocatable").
     * @param coursework $coursework
     * @param grading_table_row_base $rowobject
     * @return stdClass
     */
    public static function get_table_row_data(coursework $coursework, grading_table_row_base $rowobject): object {
        $trdata = new stdClass();
        // Prepare data for table cells.
        self::prepare_student_cell_data($coursework, $rowobject, $trdata);
        self::prepare_submission_cell_data($coursework, $rowobject, $trdata);
        self::prepare_marking_cell_data($coursework, $rowobject, $trdata);
        self::prepare_actions_cell_data($coursework, $rowobject, $trdata);
        self::set_tr_status($trdata);
        return $trdata;
    }

    /**
     * Export the data for a single table row to make it accessible from JS.
     * Enables a table row to be re-rendered from JS when updated via modal form.
     * @param int $alloctableid
     * @param string $allocatabletype
     * @return ?object
     */
    public static function export_one_row_data(coursework $coursework, int $alloctableid, string $allocatabletype): ?object {
        global $USER;
        $classname = "\\mod_coursework\\models\\$allocatabletype";
        $alloctable = $classname::get_object($alloctableid);
        if (!$alloctable) {
            return null;
        }
        $rowclass = $coursework->has_multiple_markers()
            ? 'mod_coursework\grading_table_row_multi'
            : 'mod_coursework\grading_table_row_single';
        $ability = new ability(user::find($USER, false), $coursework);
        $row = new $rowclass($coursework, $alloctable);
        if (!$ability->can('show', $row)) {
            return null;
        }
        $data = self::get_table_row_data($coursework, $row);

        // We need to add some to this because the tr and actions templates both use fields from parent as well as row.
        // Otherwise some action menu elements may be incomplete.
        $data->coursework = (object)['id' => $coursework->id()];
        $data->defaultduedate = $coursework->get_deadline();
        return $data;
    }

    /**
     * Prepare student cell data
     *
     * @param coursework $coursework
     * @param grading_table_row_base $rowobject
     * @param stdClass $trdata
     * @return void
     */
    protected static function prepare_student_cell_data(coursework $coursework, grading_table_row_base $rowobject, stdClass $trdata) {
        $dataprovider = new student_cell_data($coursework);
        $trdata->submissiontype = $dataprovider->get_table_cell_data($rowobject);;
    }

    /**
     * Prepare submission cell data
     *
     * @param coursework $coursework
     * @param grading_table_row_base $rowobject
     * @param stdClass $trdata
     * @return void
     */
    protected static function prepare_submission_cell_data(coursework $coursework, grading_table_row_base $rowobject, stdClass $trdata) {
        $dataprovider = new submission_cell_data($coursework);
        $trdata->submission = $dataprovider->get_table_cell_data($rowobject);
    }

    /**
     * Prepare marking cell data
     *
     * @param coursework $coursework
     * @param grading_table_row_base $rowobject
     * @param stdClass $trdata
     * @return void
     */
    protected static function prepare_marking_cell_data(coursework $coursework, grading_table_row_base $rowobject, stdClass $trdata) {
        $dataprovider = new marking_cell_data($coursework);
        $markingcelldata = $dataprovider->get_table_cell_data($rowobject);
        $trdata->markers = $markingcelldata->markers;
        $trdata->agreedmark = !empty($markingcelldata->agreedmark) ? $markingcelldata->agreedmark : null;
    }

    /**
     * Prepare actions cell data
     *
     * @param coursework $coursework
     * @param grading_table_row_base $rowobject
     * @param stdClass $trdata
     * @return void
     */
    protected static function prepare_actions_cell_data(
        coursework $coursework,
        grading_table_row_base $rowobject,
        stdClass $trdata
    ): void {
        $dataprovider = new actions_cell_data($coursework);
        $actions = $dataprovider->get_table_cell_data($rowobject);
        $trdata->actions = $actions;
    }

    /**
     * Prepare release marks button.
     *
     * @param coursework $coursework
     * @return stdClass|null
     * @throws \core\exception\moodle_exception
     * @throws coding_exception
     */
    protected function prepare_release_marks_button(coursework $coursework): ?stdClass {
        [$canrelease] = $coursework->can_release_marks();
        if (!$canrelease) {
            return null;
        }

        $releasemarks = new stdClass();
        $releasemarks->warning = '';
        $releasemarks->url = new moodle_url(
            '/mod/coursework/actions/releasemarks.php',
            ['cmid' => $coursework->get_coursemodule_id()]
        );

        if ($coursework->blindmarking_enabled()) {
            $submissiontype = $coursework->is_configured_to_have_group_submissions() ? 'group' : 'user';
            $releasemarks->warning = get_string('anonymity_warning_' . $submissiontype, 'mod_coursework');
        }

        return $releasemarks;
    }

    /**
     * Set tr status.
     *
     * @param stdClass $trdata
     * @return void
     */
    protected static function set_tr_status(stdClass $trdata): void {
        $status = [];
        if (!empty($trdata->submission->extensiongranted)) {
            $status[] = 'extension-granted';
        }

        if (!empty($trdata->submission->submissiondata->flaggedplagiarism)) {
            $status[] = 'flagged-for-plagiarism';
        }

        if (!empty($trdata->submission->submissiondata->submittedlate)) {
            $status[] = 'late';
        }

        if (empty($trdata->submission->submissiondata)) {
            $status[] = 'not-submitted';
        }

        if (!empty($trdata->agreedmark->addfinalfeedback)) {
            $status[] = 'need-agreement';
        }

        if (!empty($trdata->agreedmark->mark->readyforrelease)) {
            $status[] = 'ready-for-release';
        }

        foreach ($trdata->markers as $marker) {
            if (!empty($marker->addfeedback)) {
                $status[] = 'need-marking';
            }
        }

        $trdata->status = implode(', ', $status);
    }

    /**
     * Set marker filter data for table row.
     *
     * @param stdClass $trdata Table row data
     */
    protected function set_tr_marker_filter(stdClass $trdata): void {
        if (empty($trdata->markers)) {
            return;
        }

        $trdata->markerfilter = implode(', ', array_column((array)$trdata->markers, 'markeridentifier'));
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
