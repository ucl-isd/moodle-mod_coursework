<?php

namespace mod_coursework\controllers;
use mod_coursework\ability;
use mod_coursework\allocation\allocatable;
use mod_coursework\forms\deadline_extension_form;
use mod_coursework\models\deadline_extension;
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
        $create_url = $this->get_router()->get_path('create deadline extension');

        $this->form = new deadline_extension_form($create_url, array('coursework' => $this->coursework));
        $this->form->set_data($this->deadline_extension);

        $this->render_page('new');

    }

    protected function create_deadline_extension() {
        global $USER;

        $create_url = $this->get_router()->get_path('create deadline extension');
        $this->form = new deadline_extension_form($create_url, array('coursework' => $this->coursework));
        $coursework_page_url = $this->get_path('coursework', array('coursework' => $this->coursework));
        if ($this->cancel_button_was_pressed()) {
            redirect($coursework_page_url);
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
            redirect($coursework_page_url);
        } else {
            $this->set_default_current_deadline();
            $this->render_page('new');
        }

    }

    protected function edit_deadline_extension() {
        global $USER, $PAGE;

        $params = array(
            'id' => $this->params['id'],
        );
        $this->deadline_extension = deadline_extension::find($params);

        $ability = new ability(user::find($USER), $this->coursework);
        $ability->require_can('edit', $this->deadline_extension);

        $PAGE->set_url('/mod/coursework/actions/deadline_extensions/edit.php', $params);
        $update_url = $this->get_router()->get_path('update deadline extension');

        $this->form = new deadline_extension_form($update_url, array('coursework' => $this->coursework));
        $this->deadline_extension->extra_information = array(
            'text' => $this->deadline_extension->extra_information_text,
            'format' => $this->deadline_extension->extra_information_format,
        );
        $this->form->set_data($this->deadline_extension);

        $this->render_page('edit');
    }

    protected function update_deadline_extension() {
        global $USER;

        $update_url = $this->get_router()->get_path('update deadline extension');
        $this->form = new deadline_extension_form($update_url, array('coursework' => $this->coursework));
        $coursework_page_url = $this->get_path('coursework', array('coursework' => $this->coursework));
        if ($this->cancel_button_was_pressed()) {
            redirect($coursework_page_url);
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
            redirect($coursework_page_url);
        } else {
            $this->render_page('edit');
        }

    }

    /**
     * Set the deadline to default current deadline if the extension was never given before
     * @return array
     */
    protected function set_default_current_deadline()
    {
        global $DB;
        $params = array(
            'allocatableid' => $this->params['allocatableid'],
            'allocatabletype' => $this->params['allocatabletype'],
            'courseworkid' => $this->params['courseworkid'],
        );
        $this->deadline_extension = deadline_extension::build($params);
        // Default to current deadline
        // check for personal deadline first o
        if ($this->coursework->personaldeadlineenabled){
            $personal_deadline =  $DB->get_record('coursework_person_deadlines', $params);
            if ($personal_deadline) {
                $this->coursework->deadline = $personal_deadline->personal_deadline;
            }
        }
        $this->deadline_extension->extended_deadline = $this->coursework->deadline;
        return $params;
    }


}