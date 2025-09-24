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

namespace mod_coursework\forms;
use core\exception\invalid_parameter_exception;
use core_form\dynamic_form;
use mod_coursework\ability;
use mod_coursework\models\coursework;
use mod_coursework\models\deadline_extension;
use mod_coursework\models\personal_deadline;
use mod_coursework\models\user;
use mod_coursework\models\group;

/**
 * Class deadline_extension_form is responsible for new and edit actions related to the
 * deadline_extensions.
 *
 */
class deadline_extension_form extends dynamic_form {

    /**
     * Coursework object.
     * @var coursework
     */
    protected coursework $coursework;

    /**
     * Extension object.
     * @var deadline_extension
     */
    protected deadline_extension $extension;

    /**
     * Existing personal deadline object (if any).
     * @var personal_deadline|null
     */
    protected ?personal_deadline $personaldeadline;

    /**
     * Allocatable object.
     * @var user|group
     */
    protected user|group $allocatable;

    /**
     * Predefined reasons for extending a coursework (for dropdown).
     * @var array
     */
    protected array $extensionreasons;

    /**
     * Form definition.
     */
    protected function definition() {
        global $OUTPUT, $CFG;

        $this->_form->addElement('hidden', 'allocatabletype');
        $this->_form->settype('allocatabletype', PARAM_ALPHANUMEXT);
        $this->_form->addElement('hidden', 'allocatableid');
        $this->_form->settype('allocatableid', PARAM_INT);
        $this->_form->addElement('hidden', 'courseworkid');
        $this->_form->settype('courseworkid', PARAM_INT);
        $this->_form->addElement('hidden', 'extensionid');
        $this->_form->settype('extensionid', PARAM_INT);

        $this->set_instance_vars();

        $mustachedata = $this->get_header_mustache_data();
        $this->_form->addElement(
            'html',
            $OUTPUT->render_from_template('coursework/form_header_extension', $mustachedata)
        );

        // Date and time picker.
        $maxextensionmonths = $CFG->coursework_max_extension_deadline ?? 0;
        $maxyear = (int)date("Y") + max(ceil($maxextensionmonths / 12), 2);
        $this->_form->addElement(
            'date_time_selector',
            'extended_deadline',
            get_string('extended_deadline', 'mod_coursework'),
            ['startyear' => (int)date("Y"), 'stopyear'  => $maxyear]
        );

        $this->_form->setDefault('extended_deadline', max($this->get_user_latest_deadline(), time()));
        $this->_form->disabledIf('extended_deadline', 'deleteextension', 'eq', 1);

        if (!empty($this->extensionreasons)) {
            $this->_form->addElement('select',
                                     'pre_defined_reason',
                                     get_string('extension_reason',
                                                'mod_coursework'),
                $this->extensionreasons);
            $this->_form->disabledIf('pre_defined_reason', 'deleteextension', 'eq', 1);
            if ($this->extension) {
                $this->_form->setDefault('pre_defined_reason', $this->extension->pre_defined_reason);
            }
        }

        $editoroptions = [
            'subdirs'  => 0, 'context'  => $this->coursework->get_context(), 'enable_filemanagement' => false,
            'maxfiles' => 0, 'maxbytes' => 0, 'noclean' => 0, 'trusttext' => 0, 'autosave' => false,
        ];
        $this->_form->addElement(
            'editor', 'extra_information',
            get_string('extra_information', 'mod_coursework'),
            null,
            $editoroptions
        );
        $this->_form->setType('extra_information', PARAM_RAW);
        $this->_form->disabledIf('extra_information', 'deleteextension', 'eq', 1);

        if ($this->extension->id) {
            $this->_form->addElement(
                'checkbox',
                'deleteextension',
                get_string('extension_delete', 'mod_coursework')
            );
        }

        // Submit buttons.
        // Don't add these if AJAX submission as the modal has its own buttons to submit using JS.
        if (!isset($this->_ajaxformdata)) {
            $this->add_action_buttons();
        }
    }

    /**
     * Of all the possible deadlines applying to the user, what's the latest one?
     * @return int
     */
    protected function get_user_latest_deadline(): int {
        return max(
            $this->personaldeadline->personal_deadline ?? 0,
            $this->extension->extended_deadline ?? 0,
            $this->coursework->deadline ?? 0
        );
    }

    /**
     * Add mustache data for form header template.
     * @return object
     */
    private function get_header_mustache_data(): object {
        $data = (object)[
            'deadlines' => [
                // Default deadline.
                (object)[
                    'label' => get_string('default_deadline_short', 'mod_coursework'),
                    'value' => userdate($this->coursework->deadline, get_string('strftimerecentfull', 'langconfig')),
                    'class' => '',
                ],
            ],
        ];

        // User specific deadlines.
        if ($this->personaldeadline && $this->personaldeadline->personal_deadline ?? null) {
            $data->deadlines[] = (object)[
                'label' => get_string('personal_deadline', 'mod_coursework'),
                'value' => userdate($this->personaldeadline->personal_deadline, get_string('strftimerecentfull', 'langconfig')),
                'class' => 'info',
            ];
        }

        // Individual user deadline extension.
        if ($this->extension->extended_deadline ?? null) {
            $data->deadlines[] = (object)[
                'label' => get_string('extension_granted', 'mod_coursework'),
                'value' => userdate($this->extension->extended_deadline, get_string('strftimerecentfull', 'langconfig')),
                'class' => 'success',
            ];
        }

        if ($this->allocatable->name() ?? false) {
            $titlestringkey = $this->extension->id ?? false ? 'editing' : 'adding';
            $data->title = get_string(
                "extension_$titlestringkey", 'mod_coursework', $this->allocatable->name()
            );
        }

        return $data;
    }

    /**
     * Validation.
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $CFG;
        $errors = [];
        if ($data['deleteextension'] ?? null == 1) {
            if (!$this->extension->can_be_deleted()) {
                $errors['deleteextension'] = get_string('extension_cannot_delete', 'mod_coursework');
            } else {
                // No validation needed - extension is not yet in use so can be deleted. Other fields don't need checking.
                return [];
            }
        }
        $maxdeadline = $CFG->coursework_max_extension_deadline;
        $deadline = $this->get_user_latest_deadline();

        if ($data['extended_deadline']) {
            if ($data['extended_deadline'] <= $deadline) {
                $errors['extended_deadline'] = get_string('alert_validate_deadline', 'coursework');
            }
            if ($data['extended_deadline'] >= strtotime("+$maxdeadline months", $deadline)) {
                $errors['extended_deadline'] = get_string('alert_validate_deadline_months', 'coursework', $maxdeadline);
            }
        }
        return $errors;
    }

    /**
     * If user has a personal deadline, get the object.
     * @return personal_deadline|null
     */
    public function personal_deadline(): ?personal_deadline {
        global $DB;
        $params = [
            'allocatableid' => $this->allocatable->id(),
            'allocatabletype' => $this->allocatable->type(),
            'courseworkid' => $this->coursework->id(),
        ];
        return personal_deadline::find($DB->get_record('coursework_person_deadlines', $params)) ?: null;
    }

    /**
     * Set instance variables for this object.
     * @return void
     * @throws invalid_parameter_exception
     */
    private function set_instance_vars() {
        $datasource = $this->_customdata ?? $this->_ajaxformdata;
        $extensionid = $datasource['extensionid'] ?? null;
        if ($extensionid && is_numeric($extensionid)) {
            // We are editing an existing extension.
            $extension = deadline_extension::find($extensionid);
            if ($extension) {
                $this->extension = $extension;
            } else {
                throw new invalid_parameter_exception("Extension ID $extensionid not found");
            }
            $this->allocatable = $this->extension->get_allocatable();
            $this->coursework = $this->extension->get_coursework();
        } else {
            $coursework = coursework::find($datasource['coursework']->id ?? $datasource['courseworkid']);
            if (get_class($coursework) == 'mod_coursework\decorators\coursework_groups_decorator') {
                // If the coursework is in group mode, coursework::find returns a wrapped object so unwrap.
                $this->coursework = $coursework->wrapped_object();
            } else {
                $this->coursework = $coursework;
            }
            $allocatabletype = $datasource['allocatabletype'];
            $allocatableid = $datasource['allocatableid'];
            $classname = "\\mod_coursework\\models\\$allocatabletype";
            $this->allocatable = $classname::find($allocatableid);
            if (!$this->allocatable) {
                throw new invalid_parameter_exception("Allocatable ID $allocatableid not found");
            }

            // Build default extension.
            $this->extension = deadline_extension::build([
                'courseworkid' => $this->coursework->id,
                'allocatableid' => $allocatableid,
                'allocatabletype' => $allocatabletype,
            ]);
        }
        $this->extensionreasons = coursework::extension_reasons();
        $this->personaldeadline = $this->coursework->personaldeadlineenabled ? $this->personal_deadline() : null;
    }


    /**
     * Returns context where this form is used
     *
     * This context is validated in {@see external_api::validate_context()}
     *
     * If context depends on the form data, it is available in $this->_ajaxformdata or
     * by calling $this->optional_param()
     *
     * @return \context
     **/
    protected function get_context_for_dynamic_submission(): \context {
        $this->set_instance_vars();
        return $this->coursework->get_context();
    }


    /**
     * Checks if current user has access to this form, otherwise throws exception.
    */
    protected function check_access_for_dynamic_submission(): void {
        global $USER;
        $ability = new ability(user::find($USER), $this->coursework);
        if ($this->extension && $this->extension->extended_deadline) {
             $ability->require_can('edit', $this->extension);
        } else {
            $ability->require_can('new', $this->extension);
        }
    }

     /**
      * Process the form submission, used if form was submitted via AJAX.
      * Can return scalar values or arrays json-encoded, will be passed to the caller JS.
      * @return array
      */
    public function process_dynamic_submission(): array {
        global $USER;
        // By the time we reach here, $this->validation has already happened so no need to repeat.
        $data = $this->get_data();
        $errors = [];
        $warnings = [];

        // Code adapted from deadline_extensions_controller->update_deadline_extension().
        $ability = new ability(user::find($USER), $this->coursework);
        if (!$ability->can($this->extension->id ? 'update' : 'new', $this->extension)) {
            $errors[] = get_string('nopermissiongeneral', 'mod_coursework');
        }
        // Extension object expects different format for extra_information to match coursework_extensions DB table.
        $data->extra_information_text = $data->extra_information['text'] ?? null;
        $data->extra_information_format = $data->extra_information['format'] ?? null;

        if ($data->deleteextension ?? null == 1) {
            if (!$this->extension->can_be_deleted()) {
                $errors[] = get_string('extension_cannot_delete', 'mod_coursework');
                return [
                    'success' => empty($errors),
                    'resultcode' => 'cannotdelete',
                    'message' => '',
                    'errors' => $errors,
                    'warnings' => $warnings,
                ];
            } else if ($data->suredelete ?? false) {
                $ability->require_can('update', $this->extension);
                $this->extension->delete();
                return [
                    'success' => empty($errors),
                    'resultcode' => 'deleted',
                    'message' => get_string('extension_deleted,', 'mod_coursework', $this->allocatable->name()),
                    'errors' => $errors,
                    'warnings' => $warnings,
                ];
            } else {
                // JS to ask to "Are you sure?".
                return [
                    'success' => empty($errors),
                    'resultcode' => 'confirmdelete',
                    'message' => get_string('confirmremoveextension', 'mod_coursework', $this->allocatable->name()),
                    'errors' => $errors,
                    'warnings' => $warnings,
                ];
            }

        }
        // If we reach this far with no errors, we can update the extension.
        if (empty($errors)) {
            $this->extension->update_attributes($data);
            $personaldeadline = personal_deadline::get_personal_deadline_for_student($this->allocatable, $this->coursework);
            // Update calendar/timeline event to the latest of the new extension date or existing personal deadline.
            $this->coursework->update_user_calendar_event(
                $this->allocatable->id(),
                $this->allocatable->type(),
                max($data->extended_deadline, $personaldeadline->personal_deadline ?? 0)
            );
        }
        return [
            'success' => empty($errors),
            'resultcode' => 'saved',
            'message' => get_string('extension_saved', 'mod_coursework', $this->extension->get_allocatable()->name()),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Set the data that the modal form needs to display.
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        $data = [
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->allocatable->id(),
            'allocatabletype' => $this->allocatable->type(),
            'deleteextension' => 0,
        ];

        if ($this->extension->id) {
            // If editing existing extension.
            $data['extensionid'] = $this->extension->id;
            if ($this->extension->extra_information_text) {
                $data['extra_information'] = [
                    'text' => $this->extension->extra_information_text,
                    'format' => $this->extension->extra_information_format,
                ];
            }
        }
        $this->set_data($data);
    }

    /**
     *  Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *  This is used in the form elements sensitive to the page url, such as Atto autosave in 'editor'
     *  If the form has arguments (such as 'id' of the element being edited), the URL should
     *  also have respective argument.
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/mod/coursework/view.php', ['id' => $this->coursework->id]);
    }
}
