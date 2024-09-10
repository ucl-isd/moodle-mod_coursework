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

namespace mod_coursework\render_helpers\grading_report\cells;
use coding_exception;
use html_table_cell;
use html_writer;
use mod_coursework\ability;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\user;
use mod_coursework_submission_files;
use moodle_url;

/**
 * Class feedback_cell
 */
class plagiarism_cell extends cell_base {

    /**
     * @param grading_table_row_base $rowobject
     * @throws coding_exception
     * @return string
     */
    public function get_table_cell($rowobject) {
        global $USER;

        $content = '';
        $ability = new ability(user::find($USER), $rowobject->get_coursework());

        if ($rowobject->has_submission() && $ability->can('show', $rowobject->get_submission())) {
            // The files and the form to resubmit them.
            $submissionfiles = $rowobject->get_submission_files();
            if ($submissionfiles) {
                $content .= $this->get_renderer()->render_plagiarism_links(new mod_coursework_submission_files($submissionfiles));
            }
        }

        return $this->get_new_cell_with_class($content);
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options  = []) {
        return get_string('plagiarism', 'mod_coursework');
    }

    /**
     * @return string
     */
    public function get_table_header_class() {
        return 'plagiarism';
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'submission';
    }
}
