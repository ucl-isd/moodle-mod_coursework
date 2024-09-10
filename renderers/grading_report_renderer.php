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
 * @copyright  2017 University of London Computer Centre {@link ulcc.ac.uk}
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

    /**
     * @var
     */
    protected $rowclass;

    /**
     * @var
     */
    protected $ismultiplemarkers;

    /**
     * @param grading_report $grading_report
     * param $ismultiplemarkers
     * @return string
     */
    public function render_grading_report($gradingreport, $ismultiplemarkers) {
        $langelement = $this->generate_lang_element();

        $options = $gradingreport->get_options();
        $tablerows = $gradingreport->get_table_rows_for_page();
        $cellhelpers = $gradingreport->get_cells_helpers();
        $subrowhelper = $gradingreport->get_sub_row_helper();
        if (count($tablerows) == $gradingreport->realtotalrows) {
            $options['class'] = 'full-loaded';
        }
        $this->rowclass = $ismultiplemarkers ? 'submissionrowmulti' : 'submissionrowsingle';

        if (empty($tablerows)) {
            return $langelement . '<div class="no-users">'.get_string('nousers', 'coursework').'</div><br>';
        }

        $tablehtml = $this->start_table($options);
        $tablehtml .= $this->make_table_headers($cellhelpers, $options, $ismultiplemarkers);
        $tablehtml .= '</thead>';
        $tablehtml .= '<tbody>';
        $tablehtml .= $this->make_rows($tablerows, $cellhelpers, $subrowhelper, $ismultiplemarkers, );
        $tablehtml .= '</tbody>';
        $tablehtml .= $this->end_table();

        return  $langelement . $tablehtml;
    }

    /**
     *
     * @return mixed
     */
    private function generate_lang_element() {
        $langmessages = [
            'download_submitted_files' => get_string('download_submitted_files', 'mod_coursework'),
            'exportfinalgrades' => get_string('exportfinalgrades', 'mod_coursework'),
            'exportgradingsheets' => get_string('exportgradingsheets', 'mod_coursework'),
            'loadingpagination' => get_string('loadingpagination', 'mod_coursework'),
        ];
        $result = html_writer::empty_tag('input', [
            'name' => '',
            'type' => 'hidden',
            'data-lang' => json_encode($langmessages),
            'id' => 'element_lang_messages',
        ]);
        $result = html_writer::div($result);

        return $result;
    }

    /**
     * @param cell_interface $cell_helper
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
     * @param grading_table_row_base $row_object
     * @param cell_interface[] $cell_helpers
     * @param sub_rows_interface $sub_row_helper
     * @return string
     */
    protected function make_row_for_allocatable($rowobject, $cellhelpers, $subrowhelper) {

        // $class = (!$row_object->get_coursework()->has_multiple_markers()) ? "submissionrowsingle": "submissionrowmulti";
        $class = $this->rowclass;

        $submission = $rowobject->get_submission();
        $allocatable = $rowobject->get_allocatable();

        $tablehtml = '<tr class="'.$class.'" id="' . $this->grading_table_row_id($rowobject->get_allocatable(), $rowobject->get_coursework()) . '">';
        $ismultiplemarkers = $this->ismultiplemarkers;
        $tblassessorfeedbacks = $subrowhelper->get_row_with_assessor_feedback_table($rowobject, count($cellhelpers));
        if ($ismultiplemarkers) {
            $tablehtml .= '<td class="details-control"></td>';
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
     * @param $cell_helpers
     * @param $options
     * @return string
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
     * @param $cell_helpers
     * @param $sub_row_helper
     * @param $ismultiplemarkers
     * @return string
     */
    public function make_rows($tablerows, $cellhelpers, $subrowhelper, $ismultiplemarkers) {
        $tablehtml = '';

        $this->rowclass = $ismultiplemarkers ? 'submissionrowmulti' : 'submissionrowsingle';
        $this->ismultiplemarkers = $ismultiplemarkers;

        foreach ($tablerows as $rowobject) {
            $tablehtml .= $this->make_row_for_allocatable($rowobject, $cellhelpers, $subrowhelper);
        }
        return $tablehtml;
    }

    /**
     * Groupings for the header cells on the next row down.
     *
     * @param cell_interface[] $cell_helpers
     * @return string
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
     * @param cell_interface[] $cell_helpers
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
