<?php
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
     * @param grading_report $grading_report
     * @return string
     */
    public function render_grading_report($grading_report) {

        $options = $grading_report->get_options();
        $tablerows = $grading_report->get_table_rows_for_page();
        $cell_helpers = $grading_report->get_cells_helpers();
        $sub_row_helper = $grading_report->get_sub_row_helper();

        if (empty($tablerows)) {
            return '<div class="no-users">'.get_string('nousers', 'coursework').'</div><br>';
        }

        $table_html = $this->start_table();
        $table_html .= $this->make_table_headers($cell_helpers, $options);
        $table_html .= '</thead>';
        $table_html .= '<tbody>';
        $table_html .= $this->make_rows($tablerows, $cell_helpers, $sub_row_helper);
        $table_html .= '</tbody>';
        $table_html .= $this->end_table();

        return  $table_html;
    }

    /**
     * @param cell_interface $cell_helper
     * @param array $options
     * @return string
     */
    protected function make_header_cell($cell_helper, $options) {
        $table_html = '<th class='.$cell_helper->get_table_header_class().'>';

        $header_name = $cell_helper->get_table_header($options);
        $table_html .= $header_name;
        $table_html .= $cell_helper->get_table_header_help_icon();
        $table_html .= '</th>';
        return $table_html;
    }

    /**
     * @param grading_table_row_base $row_object
     * @param cell_interface[] $cell_helpers
     * @param sub_rows_interface $sub_row_helper
     * @return string
     */
    protected function make_row_for_allocatable($row_object, $cell_helpers, $sub_row_helper) {

        $class = (!$row_object->get_coursework()->has_multiple_markers())? "submissionrowsingle": "submissionrowmulti";

        $table_html = '<tr class="'.$class.'" id="' . $this->grading_table_row_id($row_object->get_allocatable(), $row_object->get_coursework()) . '">';

        foreach ($cell_helpers as $cell_helper) {
            $table_html .= $cell_helper->get_table_cell($row_object);
        }
        $table_html .= '</tr>';

        $table_html .= $sub_row_helper->get_row_with_assessor_feedback_table($row_object, count($cell_helpers));
        return $table_html;
    }

    /**
     * @return string
     */
    public function submissions_header($header_text='') {
        $submisions = (!empty($header_text))    ? $header_text  :   get_string('submissions', 'mod_coursework');

        return html_writer::tag('h3', $submisions);
    }

    /**
     * @param $cell_helpers
     * @param $options
     * @return string
     */
    protected function make_table_headers($cell_helpers, $options) {

        $table_html = $this->make_upper_headers($cell_helpers);

        $table_html .= '<tr>';
        foreach ($cell_helpers as $cell_helper) {
            $table_html .= $this->make_header_cell($cell_helper, $options);
        }
        $table_html .= '</tr>';
        return $table_html;
    }

    /**
     * @return string
     */
    protected function start_table() {
        $table_html = '
            <table class="submissions display">
                <thead>
        ';
        return $table_html;
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
     * @return string
     */
    protected function make_rows($tablerows, $cell_helpers, $sub_row_helper) {
        $table_html = '';
        foreach ($tablerows as $row_object) {
            $table_html .= $this->make_row_for_allocatable($row_object, $cell_helpers, $sub_row_helper);
        }
        return $table_html;
    }

    /**
     * Groupings for the header cells on the next row down.
     *
     * @param cell_interface[] $cell_helpers
     * @return string
     */
    private function make_upper_headers($cell_helpers) {
        global $OUTPUT;
        $html = '';
        $headers = $this->upper_header_names_and_colspans($cell_helpers);

        foreach ($headers as $header_name => $colspan) {
            $html .= '<th colspan="'.$colspan.'"">';
            $html .= get_string($header_name.'_table_header', 'mod_coursework');
            $html .= get_string($header_name.'_table_header', 'mod_coursework')?
                    ($OUTPUT->help_icon($header_name.'_table_header', 'mod_coursework')) : '';
            $html .= '</th>';
        }

        return $html;
    }

    /**
     * @param cell_interface[] $cell_helpers
     * @return mixed
     */
    private function upper_header_names_and_colspans($cell_helpers) {
        $headers = array();

        foreach ($cell_helpers as $helper) {
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