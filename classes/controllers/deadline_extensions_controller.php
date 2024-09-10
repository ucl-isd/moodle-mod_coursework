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

namespace mod_coursework\controllers;
use mod_coursework\ability;
use mod_coursework\allocation\allocatable;
use mod_coursework\decorators\coursework_groups_decorator;
use mod_coursework\forms\deadline_extension_form;
use mod_coursework\models\coursework;
use mod_coursework\models\deadline_extension;
use mod_coursework\models\group;
use mod_coursework\models\user;

/**
 * Class deadline_extensions_controller is responsible for handling restful requests related
 * to the deadline_extensions.
 *
 * @property \mod_coursework\framework\table_base deadline_extension
 * @property allocatable allocatable
 * @property deadline_extension_form form
 * @package mod_coursework\controllers
 */
class deadline_extensions_controller extends controller_base {

    protected function show_deadline_extension() {
        global $USER, $PAGE;

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('show', $this->deadline_extension);

        $PAGE->set_url('/mod/coursework/actions/deadline_extensions/show.php', $this->params);

        $this->render_page('show');
    }

    protected function new_deadline_extension() {
        global $USER, $PAGE;

        $params = $this->set_default_current_deadline();

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('new', $this->deadline_extension);

        $PAGE->set_url('/mod/coursework/actions/deadline_extensions/new.php', $params);
        $createurl = $this->get_router()->get_path('create deadline extension');

        $this->form = new deadline_extension_form($createurl, ['coursework' => $this->coursework]);
        $this->form->set_data($this->deadline_extension);

        $this->render_page('new');

    }

    protected function create_deadline_extension() {
        global $USER;

        $createurl = $this->get_router()->get_path('create deadline extension');
        $this->form = new deadline_extension_form($createurl, ['coursework' => $this->coursework]);
        $courseworkpageurl = $this->get_path('coursework', ['coursework' => $this->coursework]);
        if ($this->cancel_button_was_pressed()) {
            redirect($courseworkpageurl);
        }
        /**
         * @var deadline_extension $deadline_extension
         */
        if ($this->form->is_validated()) {
            $data = $this->form->get_data();
            $data->extra_information_text = $data->extra_information['text'];
            $data->extra_information_format = $data->extra_information['format'];
            $this->deadline_extension = deadline_extension::build($data);

            $ability = new ability(user::find($USER), $this->coursework);
            $ability->require_can('create', $this->deadline_extension);

            $this->deadline_extension->save();
            redirect($courseworkpageurl);
        } else {
            $this->set_default_current_deadline();
            $this->render_page('new');
        }

    }

    protected function edit_deadline_extension() {
        global $USER, $PAGE;

        $params = [
            'id' => $this->params['id'],
        ];
        $this->deadline_extension = deadline_extension::find($params);

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('edit', $this->deadline_extension);

        $PAGE->set_url('/mod/coursework/actions/deadline_extensions/edit.php', $params);
        $updateurl = $this->get_router()->get_path('update deadline extension');

        $this->form = new deadline_extension_form($updateurl, ['coursework' => $this->coursework]);
        $this->deadline_extension->extra_information = [
            'text' => $this->deadline_extension->extra_information_text,
            'format' => $this->deadline_extension->extra_information_format,
        ];
        $this->form->set_data($this->deadline_extension);

        $this->render_page('edit');
    }

    protected function update_deadline_extension() {
        global $USER;

        $updateurl = $this->get_router()->get_path('update deadline extension');
        $this->form = new deadline_extension_form($updateurl, ['coursework' => $this->coursework]);
        $courseworkpageurl = $this->get_path('coursework', ['coursework' => $this->coursework]);
        if ($this->cancel_button_was_pressed()) {
            redirect($courseworkpageurl);
        }
        /**
         * @var deadline_extension $deadline_extension
         */

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('update', $this->deadline_extension);

        $values = $this->form->get_data();
        if ($this->form->is_validated()) {
            $values->extra_information_text = $values->extra_information['text'];
            $values->extra_information_format = $values->extra_information['format'];
            $this->deadline_extension->update_attributes($values);
            redirect($courseworkpageurl);
        } else {
            $this->render_page('edit');
        }

    }

    /**
     * Set the deadline to default current deadline if the extension was never given before
     * @return array
     */
    protected function set_default_current_deadline() {
        global $DB;
        $params = [
            'allocatableid' => $this->params['allocatableid'],
            'allocatabletype' => $this->params['allocatabletype'],
            'courseworkid' => $this->params['courseworkid'],
        ];
        $this->deadline_extension = deadline_extension::build($params);
        // Default to current deadline
        // check for personal deadline first o
        if ($this->coursework->personaldeadlineenabled) {
            $personaldeadline = $DB->get_record('coursework_person_deadlines', $params);
            if ($personaldeadline) {
                $this->coursework->deadline = $personaldeadline->personal_deadline;
            }
        }
        $this->deadline_extension->extended_deadline = $this->coursework->deadline;
        return $params;
    }

    /**
     * Begin Ajax functions
     */
    public function ajax_submit_mitigation($dataparams) {
        global $USER, $OUTPUT;
        $extendeddeadline = false;
        $response = [];
        $this->coursework = coursework::find(['id' => $this->params['courseworkid']]);
        $cm = get_coursemodule_from_instance(
            'coursework', $this->coursework->id, 0, false, MUST_EXIST
        );
        require_login($this->coursework->course, false, $cm);
        $params = $this->set_default_current_deadline();
        $ability = new ability(user::find($USER), $this->coursework);
        $errors = $this->validation($dataparams);
        $data = (object) $dataparams;
        if (!$errors) {
            if ($data->id > 0) {
                $this->deadline_extension = deadline_extension::find(['id' => $data->id]);
                $ability->require_can('edit', $this->deadline_extension);
                $data->createdbyid = $USER->id;
                $this->deadline_extension->update_attributes($data);
            } else {
                $this->deadline_extension = deadline_extension::build($data);
                $ability->require_can('new', $this->deadline_extension);
                $this->deadline_extension->save();
                $dataparams['id'] = $this->deadline_extension->id;
            }

            $content = $this->table_cell_response($dataparams);

            $response = [
                'error' => 0,
                'data' => $dataparams,
                'content' => $content,
            ];
            echo json_encode($response);
        } else {
            $response = [
                'error' => 1,
                'messages' => $errors,
            ];

            echo json_encode($response);
        }
    }

    public function validation($data) {
        global $CFG;
        $maxdeadline = $CFG->coursework_max_extension_deadline;

        if ($this->coursework->personaldeadlineenabled && $personaldeadline = $this->personal_deadline()) {
            $deadline = $personaldeadline->personal_deadline;
        } else {
            $deadline = $this->coursework->deadline;
        }

        if ( $data['extended_deadline'] <= $deadline) {
            return $errors = 'The new deadline must be later than the current deadline';
        }

        return false;
    }

    public function submission_exists($data) {
        global $DB;

        return  $DB->record_exists('coursework_submissions', [
                            'courseworkid' => $data['courseworkid'],
                            'allocatableid' => $data['allocatableid'],
                            'allocatabletype' => $data['allocatabletype'],
        ]);
    }

    public function personal_deadline() {
        global $DB;

        $extensionid = optional_param('id', 0,  PARAM_INT);

        if ($extensionid != 0) {
            $ext = $DB->get_record('coursework_extensions', ['id' => $extensionid]);
            $allocatableid = $ext->allocatableid;
            $allocatabletype = $ext->allocatabletype;
            $courseworkid = $ext->courseworkid;
        } else {

            $allocatableid = required_param('allocatableid', PARAM_INT);
            $allocatabletype = required_param('allocatabletype', PARAM_ALPHANUMEXT);
            $courseworkid = required_param('courseworkid', PARAM_INT);
        }

        $params = [
            'allocatableid' => $allocatableid ?? 0,
            'allocatabletype' => $allocatabletype ?? 0,
            'courseworkid' => $courseworkid ?? 0,
        ];

        return  $personaldeadline = $DB->get_record('coursework_person_deadlines', $params);
    }

    public function ajax_edit_mitigation($dataparams) {
        global $USER;
        $response = [];
        if ($dataparams['id'] > 0) {
            $this->coursework = coursework::find(['id' => $this->params['courseworkid']]);
            $cm = get_coursemodule_from_instance(
                'coursework', $this->coursework->id, 0, false, MUST_EXIST
            );
            require_login($this->coursework->course, false, $cm);

            $ability = new ability(user::find($USER), $this->coursework);
            $deadlineextension = deadline_extension::find(['id' => $dataparams['id']]);
            if (empty($deadlineextension)) {
                $response = [
                    'error' => 1,
                    'message' => 'This Deadline Extension does not exist!',
                ];
            } else {
                $ability->require_can('edit', $deadlineextension);
                $timecontent = '';
                $time = '';
                if ($this->coursework->personaldeadlineenabled &&  $personaldeadline = $this->personal_deadline()) {
                    $timecontent = 'Personal deadline: ' . userdate($personaldeadline->personal_deadline);
                    $time = date('d-m-Y H:i', $personaldeadline->personal_deadline);
                } else if ($this->coursework->deadline) {
                    // Current deadline for comparison
                    $timecontent = 'Default deadline: ' . userdate($this->coursework->deadline);
                    $time = date('d-m-Y H:i', $this->coursework->deadline);
                }

                if (!empty($deadlineextension->extended_deadline) && $deadlineextension->extended_deadline > 0) {
                    $time = date('d-m-Y H:i', $deadlineextension->extended_deadline);
                }

                $deadlineextensiontransform = [
                    'time_content' => $timecontent,
                    'time' => $time,
                    'text' => $deadlineextension->extra_information_text,
                    'allocatableid' => $deadlineextension->allocatableid,
                    'allocatabletype' => $deadlineextension->allocatabletype,
                    'courseworkid' => $deadlineextension->courseworkid,
                    'id' => $deadlineextension->id,
                    'pre_defined_reason' => $deadlineextension->pre_defined_reason,
                ];
                $response = [
                    'error' => 0,
                    'data' => $deadlineextensiontransform,
                ];
            }
        } else {
            $response = [
                'error' => 1,
                'message' => 'ID can not be lower than 1!',
            ];
        }
        echo json_encode($response);
    }

    public function ajax_new_mitigation($dataparams) {
        global $USER, $DB;
        $response = [];
        $this->coursework = coursework::find(['id' => $this->params['courseworkid']]);
        $cm = get_coursemodule_from_instance(
            'coursework', $this->coursework->id, 0, false, MUST_EXIST
        );
        require_login($this->coursework->course, false, $cm);

        $params = [
            'allocatableid' => $this->params['allocatableid'],
            'allocatabletype' => $this->params['allocatabletype'],
            'courseworkid' => $this->params['courseworkid'],
        ];

        $ability = new ability(user::find($USER), $this->coursework);
        $deadlineextension = deadline_extension::build($params);
        $ability->require_can('new', $deadlineextension);
        $timecontent = '';
        $time = '';
        if ($this->coursework->deadline) {
            $personaldeadline = $DB->get_record('coursework_person_deadlines', $params);
            if ($personaldeadline) {
                $timecontent = 'Personal deadline: ' . userdate($personaldeadline->personal_deadline);
                // $this->coursework->deadline = $personal_deadline->personal_deadline;
                $time = date('d-m-Y H:i', $personaldeadline->personal_deadline);
            } else {
                $timecontent = 'Default deadline: ' . userdate($this->coursework->deadline);
                $time = date('d-m-Y H:i', $this->coursework->deadline);
            }
        }

        $deadlineextensiontransform = [
            'time_content' => $timecontent,
            'time' => $time,
        ];

        $response = [
            'error' => 0,
            'data' => $deadlineextensiontransform,
        ];

        echo json_encode($response);
    }

    /**
     * function table_cell_response
     * @param array $dataparams
     *
     * Generate html cell base on time_submitted_cell
     * @return html_string $content
     *
     */

    public function table_cell_response($dataparams) {
        $participant = ($dataparams['allocatabletype'] && $dataparams['allocatabletype'] == 'group') ? group::find($dataparams['allocatableid']) : user::find($dataparams['allocatableid']);
        $coursework = ($this->coursework instanceof coursework_groups_decorator) ? $this->coursework->wrapped_object() : $this->coursework;
        if ($this->coursework->has_multiple_markers()) {
            $rowobject = new \mod_coursework\grading_table_row_multi($coursework, $participant);
        } else {
            $rowobject = new \mod_coursework\grading_table_row_single($coursework, $participant);
        }

        $timesubmittedcell = new  \mod_coursework\render_helpers\grading_report\cells\time_submitted_cell(['coursework' => $this->coursework]);

        $content = $timesubmittedcell->prepare_content_cell($rowobject);

        return $content;
    }

}
