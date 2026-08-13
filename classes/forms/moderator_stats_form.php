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
 * Form to display at the bottom of the Grading Audit page for moderator to confirm the statistics.
 *
 * @package   mod_coursework
 * @author    Conn Warwicker <conn.warwicker@catalyst-eu.net>
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\forms;

use core\context\module;
use mod_coursework\audit;
use mod_coursework\models\coursework;
use moodleform;
use stdClass;

/**
 * Simple form providing a grade and comment area that will feed straight into the feedback table so
 * that the final comment for the gradebook can be added.
 */
class moderator_stats_form extends moodleform {
    #[\Override]
    protected function definition() {

        $options = [
            '' => get_string('chooseoption', 'coursework'),
            0 => get_string('no'),
            1 => get_string('yes'),
        ];

        $heading = get_string('statsappraisal', 'coursework');

        $appraisal = audit::get_moderator_appraisal($this->_customdata['courseworkid'], $this->_customdata['context']->id);
        if ($appraisal && !$appraisal->finalised) {
            $heading .= ' (' . get_string('draft', 'coursework') . ')';
        } else if ($appraisal && $appraisal->finalised) {
            $heading .= ' (' . get_string('finalised', 'coursework') . ')';
        }

        $this->_form->addElement('header', 'stats', $heading);

        $this->_form->addElement('static', 'user', get_string('user'), '-');
        $this->_form->addElement('static', 'time', get_string('timemodified', 'mod_coursework'), '-');

        $this->_form->addElement(
            'select',
            'representative',
            get_string('statsappraisal:representative', 'coursework'),
            $options,
        );
        $this->_form->addRule('representative', null, 'required', null, 'client');
        $this->_form->addElement(
            'select',
            'markingcriteriaconsistent',
            get_string('statsappraisal:markingcriteriaconsistent', 'coursework'),
            $options,
        );
        $this->_form->addRule('markingcriteriaconsistent', null, 'required', null, 'client');
        $this->_form->addElement(
            'editor',
            'markingcriteriarecommendations',
            get_string('statsappraisal:recommendations', 'coursework'),
        );
        $this->_form->addElement(
            'select',
            'markersmarkingconsistent',
            get_string('statsappraisal:markersmarkingconsistent', 'coursework'),
            $options,
        );
        $this->_form->addRule('markersmarkingconsistent', null, 'required', null, 'client');
        $this->_form->addElement(
            'editor',
            'markersmarkingrecommendations',
            get_string('statsappraisal:recommendations', 'coursework'),
        );
        $this->_form->addElement(
            'select',
            'feedbackappropriate',
            get_string('statsappraisal:feedbackappropriate', 'coursework'),
            $options,
        );
        $this->_form->addRule('feedbackappropriate', null, 'required', null, 'client');
        $this->_form->addElement(
            'editor',
            'feedbackrecommendations',
            get_string('statsappraisal:recommendations', 'coursework'),
        );
        $this->_form->addElement(
            'editor',
            'goodpracticecomments',
            get_string('statsappraisal:goodpracticecomments', 'coursework'),
        );
        $this->_form->addElement(
            'editor',
            'generalcomments',
            get_string('statsappraisal:generalcomments', 'coursework'),
        );
        $this->_form->addElement(
            'filemanager',
            'file',
            get_string('file'),
            null,
            [
                'maxfiles' => 1,
                'accepted_types' => ['document'],
                'return_types' => FILE_INTERNAL | FILE_EXTERNAL,
            ]
        );
        $this->add_submit_buttons();

        $this->_form->setType('representative', PARAM_INT);
        $this->_form->setType('markingcriteriaconsistent', PARAM_INT);
        $this->_form->setType('markersmarkingconsistent', PARAM_INT);
        $this->_form->setType('feedbackappropriate', PARAM_INT);
        $this->_form->setType('markingcriteriarecommendations', PARAM_RAW);
        $this->_form->setType('markersmarkingrecommendations', PARAM_RAW);
        $this->_form->setType('feedbackrecommendations', PARAM_RAW);
        $this->_form->setType('goodpracticecomments', PARAM_RAW);
        $this->_form->setType('generalcomments', PARAM_RAW);

        // Rules to show/hide elements.
        $this->_form->hideIf('markingcriteriarecommendations', 'markingcriteriaconsistent', 'neq', 0);
        $this->_form->hideIf('markersmarkingrecommendations', 'markersmarkingconsistent', 'neq', 0);
        $this->_form->hideIf('feedbackrecommendations', 'feedbackappropriate', 'neq', 0);
    }

    /**
     * Add the submit buttons.
     */
    public function add_submit_buttons(): void {
        $buttonarray = [
            $this->_form->createElement('submit', 'submitdraft', get_string('saveasdraft', 'coursework')),
            $this->_form->createElement('submit', 'submitfinal', get_string('saveandfinalise', 'coursework')),
        ];

        // Get the current appraisal record to determine if the form is finalised or not.
        $appraisal = audit::get_moderator_appraisal($this->_customdata['courseworkid'], $this->_customdata['context']->id);
        if ($appraisal) {
            $buttonarray[] = $this->_form->createElement(
                'submit',
                'submitremove',
                get_string('removeappraisal', 'coursework')
            );
        }

        $buttonarray[] = $this->_form->createElement('cancel', 'cancel', null, ['formnovalidate' => '']);
        $this->_form->addGroup($buttonarray, 'buttonar', '', [' '], false);
        $this->_form->closeHeaderBefore('buttonar');
    }

    /**
     * Process the submitted form data.
     * @param stdClass $data
     */
    public function process_data(\stdClass $data): void {
        $data = (array)$data;
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $value['text'] ?? null;
            }
        }

        $data['finalised'] = 0;
        if (isset($data['submitfinal'])) {
            $data['finalised'] = 1;
        }

        audit::save_appraisal($data, $this->_customdata['context'], $this->_customdata['courseworkid']);
    }

    /**
     * Get the data to set into the form.
     * @return array
     */
    public function get_moderator_appraisal_form_data(coursework $coursework, module $context): array {
        $data = audit::get_moderator_appraisal($coursework->id, $context->id);
        if (!$data) {
            return [];
        }
        if (!is_null($data->markingcriteriarecommendations)) {
            $data->markingcriteriarecommendations = ['text' => $data->markingcriteriarecommendations];
        }
        if (!is_null($data->markersmarkingrecommendations)) {
            $data->markersmarkingrecommendations = ['text' => $data->markersmarkingrecommendations];
        }
        if (!is_null($data->feedbackrecommendations)) {
            $data->feedbackrecommendations = ['text' => $data->feedbackrecommendations];
        }
        if (!is_null($data->goodpracticecomments)) {
            $data->goodpracticecomments = ['text' => $data->goodpracticecomments];
        }
        if (!is_null($data->generalcomments)) {
            $data->generalcomments = ['text' => $data->generalcomments];
        }
        // Get the file is there is one.
        $draftitemid = file_get_submitted_draft_itemid('file');
        file_prepare_draft_area(
            $draftitemid,
            $context->id,
            'mod_coursework',
            'appraisal',
            $coursework->id,
        );
        $data->file = $draftitemid;
        return (array)$data;
    }
}
