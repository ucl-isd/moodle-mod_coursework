<?php

namespace mod_coursework\render_helpers\grading_report;

/**
 * Each variation on the grading report should provide an abstract factory that conforms to this interface.
 * The renderer then uses a template method to assemble the pieces.
 * 
 * Interface component_factory_interface
 * @package mod_coursework\render_helpers\grading_report
 */
interface component_factory_interface {


    public function get_cells();

    public function get_sub_rows();

}