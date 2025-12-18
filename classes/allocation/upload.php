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

namespace mod_coursework\allocation;

use csv_import_reader;
use mod_coursework\models\coursework;
use mod_coursework\models\group;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use moodle_exception;
use stdClass;

/**
 * @package    mod_coursework
 * @copyright  2016 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class to manage assessor allocations upload
 */
class upload {
    /**
     * @var coursework
     */
    private $coursework;

    public function __construct($coursework) {
        $this->coursework = $coursework;
    }

    /**
     * Validate csv content cell by cell
     *
     * @param $content
     * @param $encoding
     * @param $delimeter
     * @return array|bool
     * @throws moodle_exception
     */
    public function validate_csv($content, $encoding, $delimeter) {
        global $CFG, $DB;

        $assessoridentifier = $CFG->coursework_allocation_identifier;

        $iid = csv_import_reader::get_new_iid('courseworkallocationsdata');
        $csvreader = new csv_import_reader($iid, 'courseworkallocationsdata');

        $csvreader->load_csv_content($content, $encoding, $delimeter);
        $csvloaderror = $csvreader->get_error();

        if (!is_null($csvloaderror)) {
            throw new \core\exception\moodle_exception('csvloaderror', '', null, $csvloaderror);
        }

        $columns = $csvreader->get_columns();

        if (empty($columns)) {
            $csvreader->close();
            $csvreader->cleanup();
            throw new \core\exception\moodle_exception('courseworkemptycsv');
        }

        $csvreader->init();

        $errors = [];
        $s = 0;
        // find out if this is a group or individual coursework
        $allocatabletype = $this->coursework->get_allocatable_type();
        // find all individual users or groups in this coursework
        $allocatables = $this->coursework->get_allocatables();
        $allocatables = ($allocatabletype == 'group') ? array_keys($allocatables) : $allocatables;
        // find all assessors for this coursework
        $assessors = get_enrolled_users($this->coursework->get_context(), 'mod/coursework:addinitialgrade');
        $assessors = array_keys($assessors); // keep only assessors' ids
        $allocatablesinfile = [];

        $csvcells = ['allocatable'];
        $stages = $this->coursework->get_max_markers();
        for ($i = 1; $i <= $stages; $i++) {
            $csvcells[] = 'assessor_' . $i;
        }

        while ($line = $csvreader->next()) {
            $allocatable = null;

            $cells = $csvcells;
            $assessorsinfile = [];

            if (count($line) != count($csvcells)) {
                $errors = get_string('incorrectnumberofcolumns', 'coursework', (object)['expected' => count($csvcells), 'found' => count($line)]);
                break;
            }
            foreach ($line as $keynum => $value) {
                // validate allocatable (user or group)
                if ($cells[$keynum] == 'allocatable') {
                    // check if allocatable exists in the file
                    if (empty($value)) {
                        $errors[$s] = get_string($allocatabletype . 'namemissing', 'coursework');
                        break;
                    }

                    if ($allocatabletype == 'user') {
                        // get user id
                        $suballocatable = $DB->get_record('user', [$assessoridentifier => $value]);
                        $allocatable = ($suballocatable) ? user::find($suballocatable->id) : '';
                    } else {
                        // get group id
                        $suballocatable = $DB->get_record('groups', ['courseid' => $this->coursework->course,
                                                                        'name' => $value]);
                        $allocatable = ($suballocatable) ? group::find($suballocatable->id) : '';
                    }

                    // check if allocatable exists in this coursework
                    $allocatableid = $allocatable ? $allocatable->id() : null;
                    $allocatableincoursework = $allocatableid ? $allocatables[$allocatableid] : null;
                    if (!$allocatableincoursework) {
                        // E.g. string key 'usernotincoursework'.
                        $errors[$s] = get_string($allocatabletype . 'notincoursework', 'coursework', $value);
                        break;
                    }
                    // duplicate user or group
                    if ($allocatable && in_array($allocatableid, $allocatablesinfile)) {
                        $errors[$s] = get_string('duplicate' . $allocatabletype, 'coursework', $value);
                        break;
                    }
                    $allocatablesinfile[] = $allocatableid;
                }

                // validate assessor if exists in the coursework and has one of the capabilities allowing them to grade
                // in initial stage
                if (str_starts_with($cells[$keynum], 'assessor')) {
                    // skip empty assessors fields
                    if (empty($value)) {
                        continue;
                    }

                    $assessor = $DB->get_record('user', [$assessoridentifier => $value]);

                    if (!$assessor || !in_array($assessor->id, $assessors)) {
                        $errors[$s] = get_string('markernotincoursework', 'coursework', $value);
                        continue;
                    }

                    // Check if current assessor is not already allocated for this allocatable in different stage.
                    // Or is not already in the file in previous stage.
                    if ($assessor) {
                        $iserror = ($allocatable
                            && $this->coursework->assessor_has_allocation_for_student_not_in_current_stage(
                                $allocatable,
                                $assessor->id,
                                $cells[$keynum]
                            )
                            )
                            || in_array($assessor->id, $assessorsinfile);
                        if ($iserror) {
                            $errors[$s] = get_string('markeralreadyallocated', 'coursework', $keynum);
                            continue;
                        }
                    }
                    $assessorsinfile[] = $assessor->id;
                }
            }
            $s++;
        }

        return (!empty($errors)) ? $errors : false;
    }

    /**
     * Process csv and add records to the DB
     *
     * @param $content
     * @param $encoding
     * @param $delimiter
     * @param $processingresults
     * @return false|\lang_string|string
     * @throws moodle_exception
     */
    public function process_csv($content, $encoding, $delimiter, $processingresults) {

        global $CFG, $DB;

        $assessoridentifier = $CFG->coursework_allocation_identifier;

        $iid = csv_import_reader::get_new_iid('courseworkallocationsdata');
        $csvreader = new csv_import_reader($iid, 'courseworkallocationsdata');

        $csvreader->load_csv_content($content, $encoding, $delimiter);
        $csvloaderror = $csvreader->get_error();

        if (!is_null($csvloaderror)) {
            throw new \core\exception\moodle_exception('csvloaderror', '', null, $csvloaderror);
        }

        // find out if this is a group or individual coursework
        $allocatabletype = $this->coursework->get_allocatable_type();
        $columns = $csvreader->get_columns();

        if (empty($columns)) {
            $csvreader->close();
            $csvreader->cleanup();
            throw new \core\exception\moodle_exception('courseworkemptycsv', 'error');
        }

        $csvreader->init();

        $s = 0;
        $csvcells = ['allocatable'];
        $stages = $this->coursework->get_max_markers();
        for ($i = 1; $i <= $stages; $i++) {
            $csvcells[] = 'assessor_' . $i;
        }

        while ($line = $csvreader->next()) {
            $allocatable = null;

            // We will not process the content of any line that has been flagged up with an error
            if (is_array($processingresults) && array_key_exists($s, $processingresults)) {
                $s++;
                continue;
            }

            $cells = $csvcells;

            if (count($line) != count($csvcells)) {
                $errors = get_string('incorrectnumberofcolumns', 'coursework', (object)['expected' => count($csvcells), 'found' => count($line)]);
                break;
            }

            foreach ($line as $keynum => $value) {
                // create record in coursework_allocation_pairs
                // or update it

                // get allocatable
                if ($cells[$keynum] == 'allocatable') {
                    if ($allocatabletype == 'user') {
                        // get user id
                        $suballocatable = $DB->get_record('user', [$assessoridentifier => $value]);
                        $allocatable = ($suballocatable) ? user::find($suballocatable->id) : '';
                    } else {
                        // get group id
                        $suballocatable = $DB->get_record('groups', ['courseid' => $this->coursework->course,
                            'name' => $value]);
                        $allocatable = ($suballocatable) ? group::find($suballocatable->id) : '';
                    }
                }
                if ($allocatable && str_starts_with($cells[$keynum], 'assessor') && !empty($value)) {
                    $assessor = $DB->get_record('user', [$assessoridentifier => $value]);

                    $params = ['courseworkid' => $this->coursework->id,
                                    'allocatableid' => $allocatable->id,
                                    'allocatabletype' => $allocatabletype,
                                    'stageidentifier' => $cells[$keynum]];

                    $allocation = $DB->get_record('coursework_allocation_pairs', $params);

                    if (!$allocation) {
                        // create allocation
                        $this->add_allocation($assessor->id, $cells[$keynum], $allocatable);
                    } else {
                        // update allocation if submission was not marked yet
                        $subdbrecord = $DB->get_record('coursework_submissions', ['courseworkid' => $this->coursework->id,
                                                                                       'allocatabletype' => $allocatabletype,
                                                                                       'allocatableid' => $allocatable->id]);
                        $submission = submission::find($subdbrecord);

                        if (!$submission || !$submission->get_assessor_feedback_by_stage($cells[$keynum])) {
                            $this->update_allocation($allocation->id, $assessor->id);
                        }
                    }
                }
            }

            $s++;
        }

        return (!empty($errors)) ? $errors : false;
    }

    /**
     * Add allocation pair
     *
     * @param $assessorid
     * @param $stageidentifier
     * @param $allocatable
     * @return bool|int
     * @throws \dml_exception
     */
    public function add_allocation($assessorid, $stageidentifier, $allocatable) {
        global $DB;

        $addallocation = new stdClass();
        $addallocation->id = '';
        $addallocation->courseworkid = $this->coursework->id;
        $addallocation->assessorid = $assessorid;
        $addallocation->ismanual = 1;
        $addallocation->stageidentifier = $stageidentifier;
        $addallocation->allocatableid = $allocatable->id();
        $addallocation->allocatabletype = $allocatable->type();

        return $DB->insert_record('coursework_allocation_pairs', $addallocation, true);
    }

    /**
     * Update allocation pair
     *
     * @param $allocationid
     * @param $assessorid
     * @return bool
     * @throws \dml_exception
     */
    public function update_allocation($allocationid, $assessorid) {
        global $DB;

        $updateallocation = new stdClass();
        $updateallocation->id = $allocationid;
        $updateallocation->ismanual = 1;
        $updateallocation->assessorid = $assessorid;

        return $DB->update_record('coursework_allocation_pairs', $updateallocation);
    }
}
