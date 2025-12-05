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
use context;
use core\exception\invalid_parameter_exception;
use core\exception\moodle_exception;
use core_form\dynamic_form;
use mod_coursework\ability;
use mod_coursework\controllers\personaldeadlines_controller;
use mod_coursework\exceptions\access_denied;
use mod_coursework\models\coursework;
use mod_coursework\models\deadline_extension;
use mod_coursework\models\group;
use mod_coursework\models\personaldeadline;
use mod_coursework\models\user;
use moodle_url;

/**
 * Class personaldeadline_form is responsible for new and edit actions related to the
 * personaldeadlines.
 *
 */
class personaldeadline_form extends dynamic_form {
    /**
     * Coursework object.
     * @var coursework|null
     */
    protected ?coursework $coursework = null;

    /**
     * Coursework object.
     * @var personaldeadline|null
     */
    protected ?personaldeadline $existingdeadline = null;

    /**
     * Allocatable object.
     * @var user|group|null
     */
    protected user|group|null $allocatable = null;

    /**
     * Form definition.
     */
    protected function definition() {
        global $OUTPUT;

        $customdata = $this->_customdata ?? $this->_ajaxformdata;

        $this->coursework = $this->get_coursework();

        $existingdeadlinerecord = personaldeadlines_controller::get_personaldeadline(
            $customdata['allocatableid'],
            $customdata['allocatabletype'],
            $customdata['courseworkid'],
        );
        $this->existingdeadline = $existingdeadlinerecord
            ? personaldeadline::find($existingdeadlinerecord)
            : null;

        $this->allocatable = $this->existingdeadline
            ? $this->existingdeadline->get_allocatable()
            : (
            $customdata['allocatabletype'] == 'user'
                    ? user::find($customdata['allocatableid'])
                    : group::find($customdata['allocatableid'])
            );

        $this->_form->addElement('hidden', 'allocatabletype');
        $this->_form->settype('allocatabletype', PARAM_ALPHANUMEXT);
        $this->_form->addElement('hidden', 'allocatableid');
        $this->_form->settype('allocatableid', PARAM_RAW);
        $this->_form->addElement('hidden', 'courseworkid');
        $this->_form->settype('courseworkid', PARAM_INT);
        $this->_form->addElement('hidden', 'id');
        $this->_form->settype('id', PARAM_INT);
        $this->_form->addElement('hidden', 'setpersonaldeadlinespage');
        $this->_form->settype('setpersonaldeadlinespage', PARAM_INT);
        $this->_form->addElement('hidden', 'multipleuserdeadlines');
        $this->_form->settype('multipleuserdeadlines', PARAM_INT);

        $this->_form->addElement(
            'html',
            $OUTPUT->render_from_template(
                'coursework/form_header_personal_deadline',
                $this->get_header_mustache_data()
            )
        );

        if ($customdata['existingdeadline']->deadline ?? false) {
            $this->_form->setDefault(
                'personaldeadline',
                $customdata['existingdeadline']->deadline->personaldeadline
            );
        } else {
            $this->_form->setDefault('personaldeadline', time());
        }
        // Date and time picker.
        $maxextensionmonths = $CFG->coursework_max_extension_deadline ?? 0;
        $maxyear = (int)date("Y") + max(ceil($maxextensionmonths / 12), 2);
        $this->_form->addElement(
            'date_time_selector',
            'personaldeadline',
            get_string('personaldeadline', 'mod_coursework'),
            ['startyear' => (int)date("Y"), 'stopyear'  => $maxyear]
        );

        // Submit button.
        // Don't add these if AJAX submission as the modal has its own buttons to submit using JS.
        if (!isset($this->_ajaxformdata)) {
            $this->add_action_buttons();
        }
    }

    /**
     * Add mustache data for form header template.
     * @return object
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function get_header_mustache_data(): object {
        global $DB;
        $data = (object)[
            'deadlines' => [
                // Default deadline.
                (object)[
                    'label' => get_string('default_deadline_short', 'mod_coursework'),
                    'value' => userdate($this->coursework->deadline, get_string('strftimerecentfull', 'langconfig')),
                    'notes' => [],
                    'class' => 'light',
                    'hasnotes' => false,
                ],
            ],
        ];

        // User specific deadlines.
        if ($this->existingdeadline && $this->existingdeadline->personaldeadline ?? null) {
            $deadline = (object)[
                'label' => get_string('personaldeadline', 'mod_coursework'),
                'value' => userdate($this->existingdeadline->personaldeadline, get_string('strftimerecentfull', 'langconfig')),
                'notes' => [
                    get_string(
                        'createdbyname',
                        'coursework',
                        fullname($DB->get_record('user', ['id' => $this->existingdeadline->createdbyid]))
                    ),
                ],
                'hasnotes' => true,
                'class' => 'info',
            ];
            if ($this->existingdeadline->lastmodifiedbyid ?? false) {
                $deadline->notes[] = get_string(
                    'modifiedbyname',
                    'coursework',
                    fullname(
                        $DB->get_record('user', ['id' => $this->existingdeadline->lastmodifiedbyid])
                    )
                );
            }
            $deadline->notes[] = get_string(
                'editedtime',
                'coursework',
                userdate(
                    max($this->existingdeadline->timecreated, $this->existingdeadline->timemodified),
                    get_string('strftimerecentfull', 'langconfig')
                )
            );
            $deadline->hasnotes = true;
            $data->deadlines[] = $deadline;
        }
        if ($this->allocatable->name() ?? false) {
            $data->title = get_string(
                $this->existingdeadline ? 'edit_personaldeadline_for' : 'new_personaldeadline_for',
                'coursework',
                $this->allocatable->name()
            );
        }
        return $data;
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     * @throws \coding_exception
     */
    public function validation($data, $files) {
        $errors = [];
        if ($data['personaldeadline'] <= time()) {
            $errors['personaldeadline'] = get_string('alert_validate_deadline', 'coursework');
        }

        return $errors;
    }


    /**
     * Returns context where this form is used
     *
     * This context is validated in {@see external_api::validate_context()}
     *
     * If context depends on the form data, it is available in $this->_ajaxformdata or
     * by calling $this->optional_param()
     *
     * @return context
     **/
    protected function get_context_for_dynamic_submission(): context {
        return $this->get_coursework()->get_context();
    }

    /**
     * Get the coursework object.
     * @return coursework
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function get_coursework(): coursework {
        if ($this->coursework) {
            return $this->coursework;
        } else {
            $datasource = $this->_customdata ?? $this->_ajaxformdata;
            $this->coursework = coursework::find($datasource['courseworkid']);
        }
        return $this->coursework;
    }

    /**
     * Checks if current user has access to this form, otherwise throws exception.
     * @throws access_denied
     */
    protected function check_access_for_dynamic_submission(): void {
        if (!$this->can_edit()) {
            throw new access_denied($this->get_coursework(), 'No permission to edit personal deadline');
        }
    }

    /**
     * Can the user edit this personal deadline or create one?
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function can_edit(): bool {
        global $USER;
        $datasource = $this->_customdata ?? $this->_ajaxformdata;
        $deadline = personaldeadlines_controller::get_personaldeadline(
            $datasource['allocatableid'],
            $datasource['allocatabletype'],
            $datasource['courseworkid'],
        );
        $ability = new ability($USER->id, $this->get_coursework());
        $deadline = personaldeadline::find_or_build($deadline);
        $deadline->courseworkid = $this->coursework->id();
        return $ability->can('edit', $deadline);
    }

    /**
     * Process the form submission, used if form was submitted via AJAX.
     * Can return scalar values or arrays json-encoded, will be passed to the caller JS.
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws invalid_parameter_exception
     */
    public function process_dynamic_submission(): array {
        global $USER;

        // By the time we reach here, $this->validation has already happened so no need to repeat.
        $data = $this->get_data();
        $errors = [];
        $warnings = [];

        if (!$this->can_edit()) {
            $errors[] = get_string('nopermissiongeneral', 'mod_coursework');
        }

        // If we reach this far with no errors, we can create/update the deadline.
        if (empty($errors)) {
            if ($this->existingdeadline->id ?? null) {
                if ($this->existingdeadline->personaldeadline != $data->personaldeadline) {
                    // Updating.
                    $data->id = $this->existingdeadline->id;
                    $this->existingdeadline->update_attributes($data);
                    $this->existingdeadline->trigger_created_updated_event('update');
                }
            } else {
                // Creating.
                $data->createdbyid = $USER->id;
                $this->existingdeadline = personaldeadline::build($data);
                $this->existingdeadline->save();
                $this->existingdeadline->trigger_created_updated_event('create');
            }
        }
        $extension = deadline_extension::get_extension_for_student($this->allocatable, $this->coursework);
        // Update calendar/timeline event to the latest of the new personal deadline or existing extension.
        $this->coursework->update_user_calendar_event(
            $this->allocatable->id(),
            $this->allocatable->type(),
            max($data->personaldeadline, $extension->extended_deadline ?? 0)
        );
        return [
            'success' => empty($errors),
            'resultcode' => empty($errors) ? 'saved' : 'error',
            'message' => get_string(
                empty($errors) ? 'alert_personaldeadline_save_successful' : 'alert_personaldeadline_save_unsuccessful',
                'coursework',
                (object)[
                    'name' => $this->allocatable->name(),
                    'deadline' => userdate(
                        $data->personaldeadline,
                        get_string('strftimerecentfull', 'langconfig')
                    ),
                ]
            ),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Set the data that the modal form needs to display.
     * @return void
     * @throws \coding_exception
     */
    public function set_data_for_dynamic_submission(): void {
        $data = [
            'courseworkid' => $this->coursework->id,
            'allocatableid' => $this->allocatable->id(),
            'allocatabletype' => $this->allocatable->type(),
        ];

        if ($this->existingdeadline->id ?? null) {
            // If editing existing deadline.
            $data['deadlineid'] = $this->existingdeadline->id;
            $data['personaldeadline'] = $this->existingdeadline->personaldeadline;
        } else {
            $data['personaldeadline'] = $this->get_coursework()->deadline;
        }
        $this->set_data($data);
    }

    /**
     *  Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *  This is used in the form elements sensitive to the page url, such as Atto autosave in 'editor'
     *  If the form has arguments (such as 'id' of the element being edited), the URL should
     *  also have respective argument.
     * @return moodle_url
     * @throws moodle_exception
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/mod/coursework/view.php', ['id' => $this->coursework->id]);
    }
}
