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
use core_user;
use html_writer;
use mod_coursework\ability;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\deadline_extension;
use mod_coursework\models\user;
use pix_icon;

/**
 * Class feedback_cell
 */
class time_submitted_cell extends cell_base {

    /**
     * @param grading_table_row_base $rowobject
     * @throws coding_exception
     * @return string
     */
    public function get_table_cell($rowobject) {

        $data = $this->prepare_content_cell($rowobject);

        return $this->get_new_cell_with_order_data($data);
    }

    public function prepare_content_cell($rowobject) {
        global $OUTPUT, $USER;

        $content = '';
        $timesubmitted = $displayeddeadline = 0;

        $coursework = $rowobject->get_coursework();
        $submission = $rowobject->get_submission();

        if ($submission) {

            // If we have groups enabled and this is not the student who submitted the
            // group files, show who did.
            if ($coursework->is_configured_to_have_group_submissions() && !$rowobject->has_submission()) {
                $user = user::get_object($submission->userid);

                if ($rowobject->can_view_username()) {
                    $content .= "Submitted by";
                    $content .= html_writer::empty_tag('br');
                    $content .= $OUTPUT->user_picture($user);
                    $content .= $rowobject->get_user_name(true);
                }
                $content .= html_writer::empty_tag('br');
            }

            $timesubmitted = $submission->time_submitted();

            $content .= userdate($timesubmitted, '%a, %d %b %Y, %H:%M');
            $content .= html_writer::empty_tag('br');

            if ($lateseconds = $submission->was_late()) {
                $days = floor($lateseconds / 86400);
                $hours = floor($lateseconds / 3600) % 24;
                $minutes = floor($lateseconds / 60) % 60;
                $seconds = $lateseconds % 60;

                $content .= html_writer::start_span('late_submission');
                $content .= get_string('late', 'coursework');
                $content .= ' ('.$days . get_string('timedays', 'coursework') . ', ';
                $content .= $hours . get_string('timehours', 'coursework') . ', ';
                $content .= $minutes . get_string('timeminutes', 'coursework') . ', ';
                $content .= $seconds . get_string('timeseconds', 'coursework') . ')';
                $content .= html_writer::end_span();

            } else {
                $content .= html_writer::span('(' . get_string('ontime', 'mod_coursework') . ')', 'ontime_submission');
            }

            if ($submission->get_allocatable()->type() == 'group') {
                if ($rowobject->can_view_username() || $rowobject->is_published()) {
                    $content .= ' by ' . $submission->get_last_submitter()->profile_link();
                }
            }
        } else {

        }

        $content .= '<div class="extension-submission">';
        $allocatableid = $rowobject->get_allocatable()->id();
        $allocatabletype = $rowobject->get_allocatable()->type();
        $coursework = $rowobject->get_coursework();
        $newextensionparams = [
            'allocatableid' => $allocatableid,
            'allocatabletype' => $allocatabletype,
            'courseworkid' => $coursework->id,
        ];

        $extension = deadline_extension::find_or_build($newextensionparams);
        $ability = new ability(user::find($USER), $rowobject->get_coursework());

        if ($extension->persisted()) {
            $content .= 'Extension: </br>'.userdate($extension->extended_deadline, '%a, %d %b %Y, %H:%M');
            $displayeddeadline = $extension->extended_deadline;
        }

        if ($extension->id) {
            $newextensionparams['id'] = $extension->id;
        }
        if ($submission) {
            $newextensionparams['submissionid'] = $submission->id;
        }

        $deadline = $deadline ?? $coursework->deadline;
        $contenttime = [
            'time' => date('d-m-Y H:i', $deadline),
            'time_content' => userdate($deadline),
            'is_have_deadline' => ($coursework->deadline > 0) ? 1 : 0,
        ];

        if ($ability->can('new', $extension) && $coursework->extensions_enabled()) {
            $link = $this->get_router()->get_path('new deadline extension', $newextensionparams);
            $title = 'New extension';
            $content .= $OUTPUT->action_link($link,
                $title,
                null,
                ['class' => 'new_deadline_extension', 'data-name' => $rowobject->get_allocatable()->name(), 'data-params' => json_encode($newextensionparams), 'data-time' => json_encode($contenttime) ]);

        } else if ($ability->can('edit', $extension) && $coursework->extensions_enabled()) {
            $link = $this->get_router()->get_path('edit deadline extension', ['id' => $extension->id]);
            $icon = new pix_icon('edit', 'Edit extension', 'coursework');

            $content .= $OUTPUT->action_icon($link,
                $icon,
                null,
                ['class' => 'edit_deadline_extension', 'data-name' => $rowobject->get_allocatable()->name(), 'data-params' => json_encode($newextensionparams), 'data-time' => json_encode($contenttime)]);
        }

        $content .= '</div>';

        return ['display' => $content, '@data-order' => $this->standardize_time_for_compare($timesubmitted) . '|' . $this->standardize_time_for_compare($displayeddeadline)];
    }

    /**
     * return 11-char string
     *
     * @param $time
     * @return mixed
     */
    private function standardize_time_for_compare($time) {
        $zerotoadd = 10;
        if ($time > 1) {
            $length = ceil(log10($time));
            $zerotoadd = 11 - $length;
            $zerotoadd = $zerotoadd < 0 ? 0 : $zerotoadd;
        }
        $result = $time;
        for ($i = 0; $i < $zerotoadd; $i++) {
            $result = '0' . $result;
        }
        return $result;
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options  = []) {

        // Adding this line so that the sortable heading function will make a sortable link unique to the table
        // If tablename is set
        $tablename = (!empty($options['tablename'])) ? $options['tablename'] : '';

        return $this->helper_sortable_heading(get_string('tableheadsubmissiondate', 'coursework'),
            'timesubmitted',
            $options['sorthow'],
            $options['sortby'],
            $tablename);
    }

    /**
     * @return string
     */
    public function get_table_header_class() {
        return 'tableheaddate';
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'submission';
    }
}
