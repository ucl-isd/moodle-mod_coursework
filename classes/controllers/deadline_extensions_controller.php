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
 * @property \mod_coursework\framework\table_base deadlineextension
 * @property allocatable allocatable
 * @property deadline_extension_form form
 * @package mod_coursework\controllers
 */
#[\AllowDynamicProperties]
class deadline_extensions_controller extends controller_base {

    protected function show_deadline_extension() {
        global $USER, $PAGE;

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('show', $this->deadlineextension);

        $PAGE->set_url('/mod/coursework/actions/deadline_extensions/show.php', $this->params);

        $this->render_page('show');
    }

    protected function new_deadline_extension() {
        global $USER, $PAGE;

        $params = $this->set_default_current_deadline();
        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('new', $this->deadlineextension);

        $PAGE->set_url('/mod/coursework/actions/deadline_extensions/new.php', $params);
        $createurl = $this->get_router()->get_path('create deadline extension');

        $this->form = new deadline_extension_form(
            $createurl, array_merge(
                $params, ['courseworkid' => $this->coursework->id]
            )
        );

        $this->form->set_data($this->deadlineextension);

        $this->render_page('new');

    }

    protected function create_deadline_extension() {
        global $USER;

        $createurl = $this->get_router()->get_path('create deadline extension');
        $courseworkid = required_param('courseworkid', PARAM_INT);
        $this->coursework = coursework::find($courseworkid) ?? null;
        $allocatabletype = required_param('allocatabletype', PARAM_TEXT);
        $allocatableid = required_param('allocatableid', PARAM_INT);
        $classname = "\\mod_coursework\\models\\$allocatabletype";
        $allocatable = $classname::find($allocatableid);
        $this->form = new deadline_extension_form(
            $createurl,
            [
                'courseworkid' => $this->coursework->id,
                'allocatableid' => $allocatable->id(),
                'allocatabletype' => $allocatable->type(),
            ]
        );
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
            $this->deadlineextension = deadline_extension::build($data);

            $ability = new ability(user::find($USER), $this->coursework);
            $ability->require_can('create', $this->deadlineextension);

            $this->deadlineextension->save();
            redirect(
                $courseworkpageurl,
                get_string('extension_saved', 'mod_coursework', $allocatable->name()),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else {
            $this->set_default_current_deadline();
            $this->render_page('new');
        }

    }

    /**
     * The difference between this and update_deadline_extension seems to be which page calls them.
     * I.e. actions/deadline_extensions/edit.php calls this and update.php calls the other.
     * Those pages in turn seem to be requested when the user is submitting form data or displaying it.
     * Not ideal and could do with refactoring.
     * @return void
     */
    protected function edit_deadline_extension() {
        global $USER, $PAGE;

        $params = [
            'id' => $this->params['id'],
        ];
        $this->deadlineextension = deadline_extension::find($params);

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('edit', $this->deadlineextension);

        $PAGE->set_url('/mod/coursework/actions/deadline_extensions/edit.php', $params);
        $updateurl = $this->get_router()->get_path('update deadline extension');

        $formdata = ['courseworkid' => $this->coursework->id, 'extensionid' => $this->params['extensionid']];
        $this->form = new deadline_extension_form($updateurl, $formdata);
        $this->deadlineextension->extra_information = [
            'text' => $this->deadlineextension->extra_information_text,
            'format' => $this->deadlineextension->extra_information_format,
        ];
        $this->form->set_data(array_merge((array)$this->deadlineextension, $formdata));

        $this->render_page('edit');
    }

    /**
     * Delete a deadline extension from the database.
     * @return bool
     */
    public function delete_deadline_extension(): bool {
        global $USER;
        $extensionid = $this->params['extensionid'];
        $ability = new ability(user::find($USER), $this->coursework);
        $deadlineextension = deadline_extension::find(['id' => $extensionid]);
        if ($deadlineextension && $deadlineextension->can_be_deleted()) {
            $ability->require_can('edit', $deadlineextension);
            $deadlineextension->delete();
            return true;
        }
        return false;
    }

    /**
     * The difference between this and edit_deadline_extension seems to be which page calls them
     * I.e. actions/deadline_extensions/update.php calls this and edit.php calls the other.
     * Those pages in turn seem to be requested when the user is submitting form data or displaying it.
     * Not ideal and could do with refactoring.
     * @return void
     */
    protected function update_deadline_extension() {
        global $USER;

        $updateurl = $this->get_router()->get_path('update deadline extension');
        $this->form = new deadline_extension_form(
            $updateurl,
            [
                'courseworkid' => $this->coursework->id,
                'courseworkid' => $this->coursework->id,
                'extensionid' => $this->params['id']
            ]
        );
        $courseworkpageurl = $this->get_path('coursework', ['coursework' => $this->coursework]);
        if ($this->cancel_button_was_pressed()) {
            redirect($courseworkpageurl);
        }
        /**
         * @var deadline_extension $deadline_extension
         */

        $ability = new ability(user::find($USER), $this->coursework);
        $values = $this->form->get_data();
        $this->deadlineextension = deadline_extension::find(['id' => $this->params['id']]);

        $ability->require_can('update', $this->deadlineextension);

        if ($this->form->is_validated()) {
            $values->extra_information_text = $values->extra_information['text'];
            $values->extra_information_format = $values->extra_information['format'];
            $this->deadlineextension->update_attributes($values);
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
        $this->deadlineextension = deadline_extension::build($params);
        // Default to current deadline
        // check for personal deadline first o
        if ($this->coursework->personaldeadlineenabled) {
            $personaldeadline = $DB->get_record('coursework_person_deadlines', $params);
            if ($personaldeadline) {
                $this->coursework->deadline = $personaldeadline->personal_deadline;
            }
        }
        $this->deadlineextension->extended_deadline = $this->coursework->deadline;
        return $params;
    }

    public function validation($data) {
        global $CFG;
        if ($this->coursework->personaldeadlineenabled && $personaldeadline = $this->personal_deadline()) {
            $deadline = $personaldeadline->personal_deadline;
        } else {
            $deadline = $this->coursework->deadline;
        }

        if ( $data['extended_deadline'] <= $deadline) {
            return get_string('alert_validate_deadline', 'coursework');
        }

        return false;
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
