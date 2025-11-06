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
use AllowDynamicProperties;
use core\notification;
use Exception;
use mod_coursework\ability;
use mod_coursework\allocation\allocatable;
use mod_coursework\exceptions\access_denied;
use mod_coursework\forms\personaldeadline_form;
use mod_coursework\forms\personaldeadline_form_bulk;
use mod_coursework\framework\table_base;
use mod_coursework\models\deadline_extension;
use mod_coursework\models\personaldeadline;

/**
 * Class personaldeadline_controller is responsible for handling restful requests related
 * to the personaldeadline.
 *
 * @property table_base deadline_extension
 * @property allocatable allocatable
 * @property personaldeadline_form form
 * @package mod_coursework\controllers
 */
#[AllowDynamicProperties]
class personaldeadlines_controller extends controller_base {
    /**
     * Handle the generation of personal deadline form and processing data.
     * @return void
     * @throws access_denied
     */
    protected function new_personaldeadline() {
        global $USER, $PAGE, $OUTPUT;

        $courseworkpageurl = $this->get_path('coursework', ['coursework' => $this->coursework]);

        $urlparams = $this->set_default_current_deadline();
        $ability = new ability($USER->id, $this->coursework);
        $ability->require_can('edit', $this->personaldeadline);

        $isusingbulkform = (isset($this->params['allocatableid_arr'])
            && is_array($this->params['allocatableid_arr'])) || !is_numeric($this->params['allocatableid']);

        $createurl = $this->get_router()->get_path('edit personal deadline');

        $PAGE->set_url('/mod/coursework/actions/personaldeadline/new.php', $urlparams);
        $formparams = [
            'courseworkid' => $this->coursework->id,
            'courseid' => $this->params['courseid'],
            'allocatableid' => $this->params['allocatableid'],
            'allocatabletype' => $this->params['allocatabletype'],
            'multipleuserdeadlines' => $this->params['multipleuserdeadlines'],
        ];

        $this->form = !$isusingbulkform
            ? new personaldeadline_form($createurl, $formparams)
            : new personaldeadline_form_bulk(
                $createurl,
                array_merge(
                    ['coursework' => $this->coursework],
                    $formparams
                )
            );

        $this->personaldeadline->setpersonaldeadlinespage = $this->params['setpersonaldeadlinespage'];
        $this->personaldeadline->multipleuserdeadlines = $this->params['multipleuserdeadlines'];

        $this->personaldeadline->allocatableid = $this->params['allocatableid'];
        $this->form->set_data($this->personaldeadline);
        if ($this->cancel_button_was_pressed()) {
            redirect($courseworkpageurl);
        }
        if ($this->form->is_validated()) {
            $data = $this->form->get_data();
            if (empty($data->multipleuserdeadlines)) {
                if (!$this->get_personaldeadline(
                    $this->params['allocatableid'],
                    $this->params['allocatabletype'],
                    $this->params['courseworkid']
                )) { // Personal deadline doesnt exist.
                    // Add new.
                    $data->createdbyid = $USER->id;
                    $this->personaldeadline = personaldeadline::build($data);
                    $this->personaldeadline->save();
                    $this->personaldeadline->trigger_created_updated_event('create');
                } else {
                    // Update.
                    $data->lastmodifiedbyid = $USER->id;
                    $data->timemodified = time();
                    $this->personaldeadline->update_attributes($data);
                    $this->personaldeadline->trigger_created_updated_event('update');
                }

                $this->update_calendar_event(
                    $this->params['allocatableid'],
                    $this->params['allocatabletype'],
                    $data->personaldeadline
                );
                notification::success(
                    get_string(
                        'alert_personaldeadline_save_successful',
                        'coursework',
                        (object)[
                            'name' => $this->personaldeadline->get_allocatable()->name(),
                            'deadline' => userdate($this->personaldeadline->personaldeadline),
                        ]
                    )
                );
            } else {
                // Bulk submission.
                foreach (json_decode($data->allocatableid) as $allocatableid) {
                    $data->allocatableid = $allocatableid;
                    $data->id = '';
                    $findparams = [
                        'allocatableid' => $allocatableid,
                        'allocatabletype' => $data->allocatabletype,
                        'courseworkid' => $data->courseworkid,
                    ];
                    $this->personaldeadline = personaldeadline::find_or_build($findparams);

                    if (empty($this->personaldeadline->personaldeadline)) { // Personal deadline doesnt exist.
                        // Add new.
                        $data->createdbyid = $USER->id;
                        $this->personaldeadline = personaldeadline::build($data);
                        $this->personaldeadline->save();
                        $this->personaldeadline->trigger_created_updated_event('create');
                    } else {
                        // Update.
                        $data->id = $this->personaldeadline->id;
                        $data->lastmodifiedbyid = $USER->id;
                        $data->timemodified = time();
                        $this->personaldeadline->update_attributes($data);
                        $this->personaldeadline->trigger_created_updated_event('update');
                    }
                    $this->update_calendar_event(
                        $allocatableid,
                        $data->allocatabletype,
                        $this->personaldeadline->personaldeadline
                    );
                    notification::success(
                        get_string(
                            'alert_personaldeadline_save_successful',
                            'coursework',
                            (object)[
                                'name' => $this->personaldeadline->get_allocatable()->name(),
                                'deadline' => userdate($this->personaldeadline->personaldeadline),
                            ]
                        )
                    );
                }
            }
            redirect($courseworkpageurl);
        } else {
            $PAGE->set_pagelayout('standard');
            echo $OUTPUT->header();
            $this->form->display();
            echo $OUTPUT->footer();
            die();
        }

    }

    /**
     * Get the allocatable for this personal deadline.
     * @return mixed
     */
    public function get_allocatable() {
        $allocatableclass = "\\mod_coursework\\models\\{$this->params['allocatabletype']}";
        return $allocatableclass::find($this->params['allocatableid']);
    }

    /**
     * Update the calendar event and timeline with this deadline.
     * @param int $allocatableid
     * @param string $allocatabletype
     * @param int $personaldeadlineunix the new time to set
     * @return void
     * @throws Exception
     */
    public function update_calendar_event(int $allocatableid, string $allocatabletype, int $personaldeadlineunix) {
        $existingextension = deadline_extension::get_for_allocatable($this->coursework->id, $allocatableid, $allocatabletype);
        // Update calendar/timeline event to the latest of the new personal deadline or existing extension.
        $this->coursework->update_user_calendar_event(
            $allocatableid,
            $allocatabletype,
            max($personaldeadlineunix, $existingextension->extended_deadline ?? 0)
        );
    }

    /**
     * Set the deadline to default coursework deadline if the personal deadline was never given before
     * @return array
     */
    protected function set_default_current_deadline() {
        $params = [
            'allocatableid' => $this->params['allocatableid'],
            'allocatabletype' => $this->params['allocatabletype'],
            'courseworkid' => $this->params['courseworkid'],
        ];

        // If the allocatableid is an array then the current page will probably be setting multiple the personal deadlines
        // We use the first element in the array to setup the personal deadline object
        $params['allocatableid'] = (is_array($this->params['allocatableid'])) ? current($this->params['allocatableid']) : $this->params['allocatableid'];

         $this->personaldeadline = personaldeadline::find_or_build($params);

        $params['allocatableid'] = $this->params['allocatableid'];

        // If the allocatableid is an array then the current page will probably be setting multiple the personal deadlines
        // of multiple allocatable ids in which case set the personal deadline to the coursework default
        if ($this->params['multipleuserdeadlines'] || !$this->get_personaldeadline(
                $this->params['allocatableid'],
                $this->params['allocatabletype'],
                $this->params['courseworkid'])) { // if no personal deadline then use coursework deadline
            $this->personaldeadline->personaldeadline = $this->coursework->deadline;

        }

        return $params;
    }

    /**
     * Get the personal deadline
     * @return mixed
     */
    public static function get_personaldeadline(int $allocatableid, string $allocatabletype, int $courseworkid) {
        global $DB;
        $params = [
            'allocatableid' => $allocatableid,
            'allocatabletype' => $allocatabletype,
            'courseworkid' => $courseworkid,
        ];

        return $DB->get_record('coursework_person_deadlines', $params);
    }

    /**
     * @param $time
     * @return bool
     */
    protected function validated($time) {
        if (strtotime($time) <= time()) {
            return false;
        }
        return true;
    }
}
