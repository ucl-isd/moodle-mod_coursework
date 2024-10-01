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

namespace mod_coursework\render_helpers\grading_report\cells;
use coding_exception;
use html_writer;
use mod_coursework\ability;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use mod_coursework_submission_files;
use moodle_url;
use pix_icon;

/**
 * Class feedback_cell
 */
class submission_cell extends cell_base {

    /**
     * @param grading_table_row_base $rowobject
     * @throws coding_exception
     * @return string
     */
    public function get_table_cell($rowobject) {

        global $USER, $OUTPUT, $DB, $CFG;

        $content = '';

        $ability = new ability(user::find($USER), $this->coursework);

        if ($rowobject->has_submission() && $ability->can('show', $rowobject->get_submission())) {
            // The files and the form to resubmit them.
            $submissionfiles = $rowobject->get_submission_files();
            if ($submissionfiles) {
                $content .= $this->get_renderer()->render_submission_files(new mod_coursework_submission_files($submissionfiles));
            }

            if ($ability->can('revert', $rowobject->get_submission())) {
                $url = new moodle_url('/mod/coursework/actions/revert.php',
                                      ['cmid' => $rowobject->get_course_module_id(),
                                            'submissionid' => $rowobject->get_submission_id()]);
                $content .= html_writer::empty_tag('br');
                $revertstring = get_string('revert', 'coursework');
                $content .= html_writer::link($url, $revertstring);
            }
        } else {
            $content .= $ability->get_last_message();
        }

        $ability = new ability(user::find($USER), $rowobject->get_coursework());

        $submissiononbehalfofallocatable = submission::build([
                                                                     'allocatableid' => $rowobject->get_allocatable()
                                                                         ->id(),
                                                                     'allocatabletype' => $rowobject->get_allocatable()
                                                                         ->type(),
                                                                     'courseworkid' => $rowobject->get_coursework()->id,
                                                                     'createdby' => $USER->id,
                                                                 ]);

        if (($rowobject->get_submission()&& !$rowobject->get_submission()->finalised)
            || !$rowobject->get_submission()) {

            if ($ability->can('new', $submissiononbehalfofallocatable) && (!$rowobject->get_coursework()->has_deadline()
                    || $rowobject->get_coursework()->allow_late_submissions() || ($rowobject->get_personal_deadlines() >= time() || ($rowobject->has_extension() && $rowobject->get_extension()->extended_deadline > time())))) {

                // New submission on behalf of button

                $url = $this->get_router()
                    ->get_path('new submission', ['submission' => $submissiononbehalfofallocatable], true);

                $label =
                    'Submit on behalf';

                $content .= $OUTPUT->action_link($url,
                                                 $label,
                                                 null,
                                                 ['class' => 'new_submission']);
            } else if ($rowobject->has_submission() &&
                       $ability->can('edit', $rowobject->get_submission()) &&
                       !$rowobject->has_feedback() ) {

                // Edit submission on behalf of button

                $url = $this->get_router()
                    ->get_path('edit submission', ['submission' => $rowobject->get_submission()], true);

                $label =
                    'Edit submission on behalf of this ' . ($rowobject->get_coursework()
                        ->is_configured_to_have_group_submissions() ?
                        'group' : 'student');
                $icon = new pix_icon('edit', $label, 'coursework');

                $content .= ' '.$OUTPUT->action_icon($url,
                                                 $icon,
                                                 null,
                                                 ['class' => 'edit_submission']);
            }
        }

        // File id
        if ($rowobject->has_submission()) {
            $content .= html_writer::empty_tag('br');
            $content .= $rowobject->get_filename_hash();
        }

        return $this->get_new_cell_with_class($content);
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options  = []) {

        $tablename = (isset($options['tablename'])) ? $options['tablename'] : '';

        $fileid = $this->helper_sortable_heading(get_string('tableheadid', 'coursework'),
                                                 'hash',
                                                  $options['sorthow'],
                                                  $options['sortby'],
                                                  $tablename);

        return get_string('tableheadfilename', 'coursework') .' /<br>' . $fileid;
    }

    /**
     * @return string
     */
    public function get_table_header_class() {
        return 'tableheadfilename';
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'submission';
    }
}
