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

use mod_coursework\audit;
use mod_coursework\event\moderator_appraisal_created;
use mod_coursework\event\moderator_appraisal_deleted;
use mod_coursework\event\moderator_appraisal_updated;
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

        $appraisal = audit::get_moderator_appraisal($this->_customdata['courseworkid']);
        if ($appraisal && !$appraisal->finalised) {
            $heading .= ' (' . get_string('draft', 'coursework') . ')';
        } else if ($appraisal && $appraisal->finalised) {
            $heading .= ' (' . get_string('finalised', 'coursework') . ')';
        }

        $this->_form->addElement('header', 'stats', $heading);

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
        $appraisal = audit::get_moderator_appraisal($this->_customdata['courseworkid']);
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

        $this->upsert($data);

        // Process the file upload if there was one.
        file_save_draft_area_files(
            $data['file'],
            $this->_customdata['context']->id,
            'mod_coursework',
            'appraisal',
            $this->_customdata['courseworkid'],
            [
                'maxfiles' => 1,
            ]
        );
    }

    /**
     * Remove appraisal.
     */
    public function remove_appraisal(): void {
        global $DB, $USER;
        $record = $DB->get_record('coursework_moderator_appraisals', [
            'courseworkid' => $this->_customdata['courseworkid'],
        ]);
        if (!$record) {
            return;
        }

        $DB->delete_records('coursework_moderator_appraisals', [
            'courseworkid' => $this->_customdata['courseworkid'],
        ]);

        get_file_storage()->delete_area_files(
            $this->_customdata['context']->id,
            'mod_coursework',
            'appraisal',
            $this->_customdata['courseworkid']
        );

        $event = moderator_appraisal_deleted::create([
            'objectid' => $record->id,
            'userid' => $USER->id ?? 0,
            'context' => $this->_customdata['context'],
            'other' => [
                'courseworkid' => $this->_customdata['courseworkid'],
            ],
        ]);
        $event->trigger();
    }

    /**
     * Update or insert field/value pair for this appraisal.
     * @param array $data
     * @return bool|int
     */
    protected function upsert(array $data) {
        global $DB, $USER;
        $record = new stdClass();
        $record->courseworkid = $this->_customdata['courseworkid'];
        $record->representative = $data['representative'];
        $record->markingcriteriaconsistent = $data['markingcriteriaconsistent'];
        $record->markingcriteriarecommendations = $data['markingcriteriarecommendations'];
        $record->markersmarkingconsistent = $data['markersmarkingconsistent'];
        $record->markersmarkingrecommendations = $data['markersmarkingrecommendations'];
        $record->feedbackappropriate = $data['feedbackappropriate'];
        $record->feedbackrecommendations = $data['feedbackrecommendations'];
        $record->goodpracticecomments = $data['goodpracticecomments'];
        $record->generalcomments = $data['generalcomments'];
        $record->modifiedtime = time();
        $record->modifiedbyuserid = $USER->id;
        $record->finalised = $data['finalised'];

        // Does a record exist already?
        $id = $DB->get_field('coursework_moderator_appraisals', 'id', [
            'courseworkid' => $this->_customdata['courseworkid'],
        ]);

        if ($id) {
            $record->id = $id;
            $updated = $DB->update_record('coursework_moderator_appraisals', $record);
            if ($updated) {
                $event = moderator_appraisal_updated::create([
                    'objectid' => $id,
                    'userid' => $USER->id ?? 0,
                    'context' => $this->_customdata['context'],
                    'other' => [
                        'courseworkid' => $this->_customdata['courseworkid'],
                    ],
                ]);
                $event->trigger();
            }
            return $updated;
        } else {
            $newid = $DB->insert_record('coursework_moderator_appraisals', $record);
            $event = moderator_appraisal_created::create([
                'objectid' => $newid,
                'userid' => $USER->id ?? 0,
                'context' => $this->_customdata['context'],
                'other' => [
                    'courseworkid' => $this->_customdata['courseworkid'],
                ],
            ]);
            $event->trigger();
            return $newid;
        }
    }
}
