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

namespace mod_coursework\export;
use coding_exception;
use csv_import_reader;
use dml_exception;
use dml_missing_record_exception;
use dml_multiple_records_exception;
use mod_coursework\auto_grader\auto_grader;
use mod_coursework\export\csv\cells\cell_base;
use mod_coursework\grade_judge;
use mod_coursework\models\feedback;
use mod_coursework\models\submission;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/csvlib.class.php');

class import extends grading_sheet {
    public function validate_submissionfileid() {
        $this->get_submissions();
    }

    /**
     * Validate csv content cell by cell
     *
     * @param $content
     * @param $encoding
     * @param $delimeter
     * @param $csvcells
     * @return array|bool
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function validate_csv($content, $encoding, $delimeter, $csvcells) {
        $iid = csv_import_reader::get_new_iid('courseworkgradingdata');
        $csvreader = new csv_import_reader($iid, 'courseworkgradingdata');

        $csvreader->load_csv_content($content, $encoding, $delimeter);
        $csvloaderror = $csvreader->get_error();

        if (!is_null($csvloaderror)) {
            throw new \core\exception\moodle_exception('csvloaderror', '', null, $csvloaderror);
        }

        $columns = $csvreader->get_columns();

        if (empty($columns)) {
            $csvreader->close();
            $csvreader->cleanup();
            throw new \core\exception\moodle_exception('courseworkemptycsv', 'error', '');
        }

        $csvreader->init();

        $errors = [];
        $s = 0;

        $this->get_submissions();

        while ($line = $csvreader->next()) {
            $csv = $this->remove_other_assessors_grade($csvcells, $line);
            $cells = $csv;
            $i = 0;
            $submissionid = false;

            // if the csv headers count is different than expected return error
            if ((!$this->coursework->is_using_rubric() && count($line) != count($cells)) || ($this->coursework->is_using_rubric() && !$this->rubric_count_correct($cells, $line))) {
                $errors = get_string('incorrectfileformat', 'coursework');
                break;
            }

            $offset = 0;

            // Holds details on grades that have been successfully uploaded for the current line
            $uploadedgradecells = [];

            for ($z = 0; $z < count($line); $z++) {
                $value = $line[$z];
                $stageidentifier = $this->get_stageidentifier($submissionid, $cells[$i]);

                // remove numbers from cell names so they can be dynamically validated
                if (str_starts_with($cells[$i], 'assessor')) {
                    $cells[$i] = substr($cells[$i], 0, -1);
                }

                $class = "mod_coursework\\export\\csv\\cells\\" . $cells[$i] . "_cell";
                $cell = new $class($this->coursework);
                // Submission id field should always be first in the csv_cells array
                //
                if ($cells[$i] == 'submissionid') {
                    $submissionid = $value;
                }

                if (empty($submissionid)) {
                    $errors[$s][] = get_string('emptysubmissionid', 'coursework');
                }

                // Offsets the position of that we extract the data from $line based on data that has been extracted before

                if (
                    ($cells[$i] == "singlegrade" || $cells[$i] == "assessorgrade" || $cells[$i] == "agreedgrade")
                    && $this->coursework->is_using_rubric() && !($cells[$i] == "agreedgrade" && $this->coursework->finalstagegrading == 1)
                ) {
                    // Get the headers that would contain the rubric grade data
                    $rubricheaders = $cell->get_header(null);

                    // Find out the position of singlegrade
                    $position = $i;

                    // Get all data from the position of the grade to the length of rubricheaders
                    $rubriclinedata = array_slice($line, $position + $offset, count($rubricheaders), true);

                    // Pass the rubric data in
                    $result = $cell->validate_cell($rubriclinedata, $submissionid, $stageidentifier, $uploadedgradecells);

                    $z = $z + count($rubricheaders) - 1;
                    $offset = $offset + count($rubricheaders) - 1;
                } else {
                    $result = $cell->validate_cell($value, $submissionid, $stageidentifier, $uploadedgradecells);
                }

                if ($result !== true) {
                    $errors[$s] = $result;
                    break; // Go to next line on error
                } else if ($cells[$i] == "singlegrade" || $cells[$i] == "assessorgrade" || $cells[$i] == "agreedgrade" && !empty($value)) {
                    $uploadedgradecells[] = $stageidentifier;
                }
                $i++;
            }
            $s++;
        }

        return (!empty($errors)) ? $errors : false;
    }

    public function rubric_count_correct($csvheader, $linefromimportedcsv) {

        // get criteria of rubrics and match it to grade cells
        if ($this->coursework->is_using_rubric()) {
            $types = ["singlegrade", "assessorgrade"];

            if ($this->coursework->finalstagegrading == 0) {
                $types[] = "agreedgrade";
            }

            foreach ($types as $type) {
                $typefound = false;

                // $typepositions holds the places in the array of all vcolumns with headers that
                // Match the type e.g a column named singlegrade1 will match a singlegrade type
                $typepositions = false;
                $i = 0;

                foreach ($csvheader as $ch) {
                    if (str_contains($ch, $type)) {
                        if (empty($typepositions)) {
                            $typepositions = [];
                        }

                        $typefound = true;
                        $typepositions[] = $i;
                        // break;
                    }
                    $i++;
                }

                if (!empty($typefound)) {
                    // This var is needed to provide an offset.
                    // So the positions in the array we are looking for are correct.
                    // Even after a splice and add is carried out.
                    $offset = 0;

                    foreach ($typepositions as $position) {
                        $class = "mod_coursework\\export\\csv\\cells\\{$type}_cell";
                        $cell = new $class($this->coursework);

                        $headers = $cell->get_header(null);

                        unset($csvheader[$position + $offset]);
                        unset($linefromimportedcsv[$position + $offset]);
                        array_splice($csvheader, $position + $offset, 0, array_keys($headers));
                        array_splice($linefromimportedcsv, $position + $offset, 0, ['']);
                        $offset = $offset + count($headers) - 1;
                        $expectedsize = count($csvheader);
                        $actualsize = count($linefromimportedcsv);
                    }
                }
            }

            return  $expectedsize != $actualsize ? false : true;
        }
    }

    public function get_rubric_headers($csvheader) {

        // get criteria of rubrics and match it to grade cells
        if ($this->coursework->is_using_rubric()) {
            $types = ["singlegrade", "assessorgrade"];

            foreach ($types as $type) {
                if ($position = array_search($type, $csvheader)) {
                    $class = "mod_coursework\\export\\csv\\cells\\{$type}_cell";
                    $cell = new $class($this->coursework);

                    $headers = $cell->get_header(null);
                    unset($csvheader[$position]);

                    array_splice($csvheader, $position, 0, array_keys($headers));
                }
            }
        }

        return $csvheader;
    }

    /**
     * Process csv and add records to the DB
     *
     * @param $content
     * @param $encoding
     * @param $delimiter
     * @param $csvcells
     * @param $processingresults
     * @return false|\lang_string|string
     * @throws moodle_exception
     */
    public function process_csv($content, $encoding, $delimiter, $csvcells, $processingresults) {
        global $DB, $PAGE;

        $iid = csv_import_reader::get_new_iid('courseworkgradingdata');
        $csvreader = new csv_import_reader($iid, 'courseworkgradingdata');

        $csvreader->load_csv_content($content, $encoding, $delimiter);
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

        $s = 0;

        $submissionid = false;

        while ($line = $csvreader->next()) {
            // We will not process the content of any line that has been flagged up with an error
            if (is_array($processingresults) && array_key_exists($s, $processingresults)) {
                $s++;
                continue;
            }

            $csv = $this->remove_other_assessors_grade($csvcells, $line);
            $cells = $csv;
            $i = 0;

            $csvline = [];

            if (
                (!$this->coursework->is_using_rubric() && count($line) != count($cells))
                ||
                ($this->coursework->is_using_rubric() && !$this->rubric_count_correct($csv, $line))
            ) {
                $errors = get_string('incorrectfileformat', 'coursework');
                break;
            }

            $idfound = false;

            foreach ($line as $value) {
                if (empty($idfound) && $cells[$i] == 'submissionid') {
                    $submissionid = $value;
                    $idfound = true;
                }

                // Save the value into the csvline with the relevant pointer
                if (isset($cells[$i])) {
                    $csvline[$cells[$i]] = $value;
                }

                $i++;
            }
            $submission = submission::get_from_id($submissionid);

            // Is this submission graded? if yes did this user grade it?

            $coursework = $submission->get_coursework();

            $stages = [];

            if (!$coursework->has_multiple_markers()) {
                $stages['singlegrade'] = $this->get_stageidentifier($csvline['submissionid'], 'singlegrade');
                if (array_key_exists('agreedgrade', $csvline)) {
                    $stages['agreedgrade'] = 'final_agreed_1';
                }
            } else {
                foreach ($csvline as $k => $v) {
                    if (str_starts_with($k, 'assessorgrade') || str_starts_with($k, 'singlegrade')) {
                        $stages[$k] = $this->get_stageidentifier($csvline['submissionid'], $k);
                    } else if (str_starts_with($k, 'agreedgrade')) {
                        $stages[$k] = 'final_agreed_1';
                    }
                }
            }

            $a = 1;

            // Defines the start offest to be used when searching for a rubric in a uploaded csv, if the format of upload
            // Csv is changed this will require changing

            $rubricoffset = $rubricoffsetstart = ($coursework->is_configured_to_have_group_submissions()) ? 4 : 5;

            $numberofstages = count($stages);

            foreach ($stages as $k => $stage) {
                // When allocation is enabled
                if (has_capability('mod/coursework:administergrades', $PAGE->context) && $coursework->allocation_enabled() && $stage != 'final_agreed_1' && $coursework->has_multiple_markers() == true) {
                    $rubricoffset += 1;
                    if ($a == 1) {
                        $rubricoffsetstart  += 1;
                    }
                }
                // check for initial grade capability otherwise ignore it
                if (
                    $stage != 'final_agreed_1' && (!has_capability('mod/coursework:addinitialgrade', $PAGE->context)) &&
                    (!has_capability('mod/coursework:administergrades', $PAGE->context))
                ) {
                    continue;
                }

                $grade = $submission->get_assessor_feedback_by_stage($stage);

                $feedbackpointer = 'assessorfeedback' . $a;
                $gradepointer = 'assessorgrade' . $a;
                if ($k == 'singlegrade') {
                    $feedbackpointer = 'feedbackcomments';
                    $gradepointer = 'singlegrade';
                } else if ($k == 'agreedgrade') {
                    $feedbackpointer = 'agreedfeedback';
                    $gradepointer = 'agreedgrade';
                }

                // if sampling enabled check if this grade should be included in sample
                if ($this->coursework->sampling_enabled() && $stage != 'final_agreed_1') {
                    $insample = $submission->get_submissions_in_sample_by_stage($stage);
                    if (!$insample && $stage != 'assessor_1') {
                        continue;
                    }
                }

                // We need to carry out a further check to see if the coursework is using advanced grades.
                // If yes then we may need to generate the grade for the grade pointer as
                // They dont have grades

                if ($coursework->is_using_rubric() && !($stage == 'final_agreed_1' && $this->coursework->finalstagegrading == 1)) {
                        // Array that will hold the advanced grade data
                        $criteriagradedata = [];
                        $criteriagradedata['criteria'] = [];
                        $criterias = $this->coursework->get_rubric_criteria();
                        $numberofrubrics = count($criterias) * 2;

                    // If the stage is final a grade we need to make sure the offset is set to the position of the
                    // agreed grades in the csv, this is needed as some users will only have agreed grade capability
                    if ($stage == 'final_agreed_1') {
                        $stagemultiplier = $numberofstages - 1;

                        // The calculation below finds the position of the agreed grades in the uploaded csv

                        $rubricoffset = $rubricoffsetstart + $stagemultiplier + ($numberofrubrics * $stagemultiplier);

                        if ($coursework->allocation_enabled()) {
                            $rubricoffset += 1;
                        }
                        $rubricdata = array_slice($line, $rubricoffset, $numberofrubrics);

                        $feedbackdata = array_slice($line, $rubricoffset + $numberofrubrics, 1);

                        $csvline[$feedbackpointer] = $feedbackdata[0];
                    } else {
                        $rubricdata = array_slice($line, $rubricoffset, $numberofrubrics);

                        $feedbackdata = array_slice($line, $rubricoffset + $numberofrubrics, 1);

                        $csvline[$feedbackpointer] = $feedbackdata[0];

                        $rubricoffset = $rubricoffset + $numberofrubrics + 1;
                    }

                    $arrayvalues = array_filter($rubricdata);

                    if (!empty($arrayvalues)) {
                        $critidx = 0;
                        // This assumes that the data in the csv is in the correct criteria order.....it should be
                        foreach ($criterias as $c) {
                            $criteriagrade = [];

                            // We need to get the levelid for the value that the criteria has been given

                            $levelid = $this->get_value_rubric_levelid($c, $rubricdata[$critidx]);

                            $criteriagrade['levelid'] = $levelid;
                            $criteriagrade['remark'] = $rubricdata[$critidx + 1];

                            $criteriagradedata['criteria'][$c['id']] = $criteriagrade;

                            $critidx = $critidx + 2;
                        }
                    } else {
                        $criteriagradedata = false;
                    }

                    // Need to decide where the grade instance submit and get grade should be put as in order

                    // Pass the criteria  data into the csvline position for the grade data so we can generate a grade
                    $csvline[$gradepointer] = $criteriagradedata;

                    // In case there is another rubric to be extracted from the csv set the new value of the rubric offset
                } else if ($coursework->is_using_rubric() && ($stage == 'final_agreed_1' && $this->coursework->finalstagegrading == 1)) {
                    if (!isset($numberofrubrics)) {
                        $criterias = $this->coursework->get_rubric_criteria();

                        $numberofrubrics = count($criterias) * 2;
                    }
                    $stagemultiplier = $numberofstages - 1;

                    // The calculation below finds the position of the agreed grades in the uploaded csv

                    $rubricoffset = $rubricoffsetstart + $stagemultiplier + ($numberofrubrics * $stagemultiplier);

                    if ($coursework->allocation_enabled()) {
                        $rubricoffset += 1;
                    }

                    $gradearrvalue = array_slice($line, $rubricoffset, 2);

                    $csvline[$gradepointer] = $gradearrvalue[0];
                    $csvline[$feedbackpointer] = $gradearrvalue[1];
                }

                // don't create/update feedback if grade is empty
                if (!empty($csvline[$gradepointer])) {
                    $stageusesrubric = ($this->coursework->is_using_rubric()
                        && !($stage == 'final_agreed_1' && $this->coursework->finalstagegrading)) ? true : false;

                    if (empty($grade)) {
                        $cwfeedbackid = $this->add_grade($csvline['submissionid'], $csvline[$k], $csvline[$feedbackpointer], $stage, $stageusesrubric);
                    } else {
                        $cwfeedbackid = $this->get_coursework_feedback_id($csvline['submissionid'], $stage);
                        $this->edit_grade($cwfeedbackid, $csvline[$k], $csvline[$feedbackpointer], $stageusesrubric);
                    }
                    // if feedback created and coursework has automatic grading enabled update agreedgrade
                    if ($cwfeedbackid && $this->coursework->automaticagreement_enabled()) {
                        $this->auto_agreement($cwfeedbackid);
                    }
                }
                $a++;
            }

            $s++;
        }

        return (!empty($errors)) ? $errors : false;
    }

    /***
     * Returns the levelid for the given rubric value
     *
     * @param $criteria the criteria array, this must contain the levels element
     * @param $value the value that we will retrieve the levelid for
     * @return bool
     */
    public function get_value_rubric_levelid($criteria, $value) {
        $idfound = false;

        $levels = $criteria['levels'];

        if (is_numeric($value)) {
            foreach ($levels as $level) {
                if ((int)$level['score'] == (int)$value) {
                    $idfound = $level['id'];
                    break;
                }
            }
        }

        return $idfound;
    }

    /**
     * Add grade and feedback record
     *
     * @param $submissionid
     * @param $grade
     * @param $feedback
     * @param $stageidentifier
     * @param bool $usesrubric
     * @return bool|int
     * @throws dml_exception
     */
    public function add_grade($submissionid, $grade, $feedback, $stageidentifier, $usesrubric = false) {
        global $DB, $USER;

        // workout markernumber
        if ($stageidentifier == 'assessor_1') {
            // assessor_1 is always marker 1
            $markernumber = 1;
        } else {
            // get all feedbacks and add 1
            $feedbacks = $DB->count_records('coursework_feedbacks', ['submissionid' => $submissionid]);
            $markernumber = $feedbacks + 1;
        }

        $gradejudge = new grade_judge($this->coursework);
        $grade = $gradejudge->get_grade($grade);

        $addgrade = new stdClass();
        $addgrade->id = '';
        $addgrade->submissionid = $submissionid;
        $addgrade->assessorid = $USER->id;
        $addgrade->timecreated = time();
        $addgrade->timemodified = time();

        // We cant save the grade if this coursework uses rubrics as the grade has not been generated and the grade var contains
        // Criteria that will be used to genenrate the grade. We need the feedback id to do this so we need to make the feedback
        // First
        $addgrade->grade = (!$usesrubric) ? $grade : null;
        $addgrade->feedbackcomment = $feedback;
        $addgrade->lasteditedbyuser = $USER->id;
        $addgrade->markernumber = $markernumber;
        $addgrade->stageidentifier = $stageidentifier;
        $addgrade->finalised = 1;

        $feedbackid = $DB->insert_record('coursework_feedbacks', $addgrade, true);

        if ($usesrubric) {
            $controller = $this->coursework->get_advanced_grading_active_controller();
            // Find out how many criteria there are
            $gradinginstance = $controller->get_or_create_instance(0, $USER->id, $feedbackid);
            $rubricgrade = $gradinginstance->submit_and_get_grade($grade, $feedbackid);

            $addgrade->id = $feedbackid;
            $addgrade->grade = $rubricgrade;

            $DB->update_record('coursework_feedbacks', $addgrade);
        }

        return $feedbackid;
    }

    /**
     * Get feedbackid of existing feedback
     *
     * @param $submissionid
     * @param $stageidentifier
     * @return mixed
     * @throws dml_exception
     */
    public function get_coursework_feedback_id($submissionid, $stageidentifier) {
        global $DB;

        $record = $DB->get_record(
            'coursework_feedbacks',
            ['submissionid' => $submissionid,
                                                               'stageidentifier' => $stageidentifier],
            'id'
        );

        return $record->id;
    }

    /**
     * Edit grade and feedback record
     *
     * @param $cwfeedbackid
     * @param $grade
     * @param $feedback
     * @param bool $usesrubric
     * @return bool]
     * @throws dml_exception
     */
    public function edit_grade($cwfeedbackid, $grade, $feedback, $usesrubric = false) {
        global $DB, $USER;

        if (!$usesrubric) {
            $gradejudge = new grade_judge($this->coursework);
            $grade = $gradejudge->get_grade($grade);
        } else {
            $controller = $this->coursework->get_advanced_grading_active_controller();
            // Find out how many criteria there are
            $gradinginstance = $controller->get_or_create_instance(0, $USER->id, $cwfeedbackid);
            $rubricgrade = $gradinginstance->submit_and_get_grade($grade, $cwfeedbackid);
            $grade = $rubricgrade;
        }

        $update = false;

        // update record only if the value of grade or feedback is changed
        $currentfeedback = $DB->get_record('coursework_feedbacks', ['id' => $cwfeedbackid]);

        if ($currentfeedback->grade != $grade || cell_base::clean_cell($currentfeedback->feedbackcomment) != $feedback) {
            $editgrade = new stdClass();
            $editgrade->id = $cwfeedbackid;
            $editgrade->timemodified = time();
            $editgrade->grade = $grade;
            $editgrade->feedbackcomment = $feedback;
            $editgrade->lasteditedbyuser = $USER->id;
            $editgrade->finalised = 1;

             $update = $DB->update_record('coursework_feedbacks', $editgrade);

             // if record updated and coursework has automatic grading enabled update agreedgrade
            if ($update && $this->coursework->automaticagreement_enabled()) {
                 $this->auto_agreement($cwfeedbackid);
            }
        }

        return $update;
    }

    /**
     * Get stageidentifier for the current submission
     *
     * @param $submissionid
     * @param $cellidentifier
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_stageidentifier($submissionid, $cellidentifier) {
        global $DB, $USER;
        $submission = submission::get_from_id($submissionid);

        // single marked - singlegrade - allocated/notallocated
        $stageidentifier = 'assessor_1';

        // Double marked - singlegrade - allocated
        if (
            $this->coursework->get_max_markers() > 1 && ($cellidentifier == 'singlegrade' || $cellidentifier == 'feedbackcomments')
            && $this->coursework->allocation_enabled()
        ) {
            $dbrecord = $DB->get_record(
                'coursework_allocation_pairs',
                ['courseworkid' => $this->coursework->id,
                                                     'allocatableid' => $submission->allocatableid,
                                                     'allocatabletype' => $submission->allocatabletype,
                                                     'assessorid' => $USER->id,
                ]
            );
            $stageidentifier = $dbrecord->stageidentifier;
        }

        // Double marked - singlegrade - notallocated
        if (
            $this->coursework->get_max_markers() > 1 && ($cellidentifier == 'singlegrade' || $cellidentifier == 'feedbackcomments')
            && !$this->coursework->allocation_enabled()
        ) {
            // if any part of initial submission graded by the user then get stageidentifier from feedback
            // else workout
            $sql = "SELECT stageidentifier FROM {coursework_feedbacks}
                    WHERE submissionid = $submissionid
                    AND assessorid = $USER->id
                    AND stageidentifier <> 'final_agreed_1'";
            $record = $DB->get_record_sql($sql);
            if (!empty($record)) {
                $stageidentifier = $record->stageidentifier;
            } else if (!$this->coursework->sampling_enabled()) { // Samplings disabled
                // workout if any stage is still available
                $sql = "SELECT count(*) as graded FROM {coursework_feedbacks}
                        WHERE submissionid = $submissionid
                        AND stageidentifier <> 'final_agreed_1'";
                $record = $DB->get_record_sql($sql);

                if ($this->coursework->get_max_markers() > $record->graded) {
                    $stage = $record->graded + 1;
                    $stageidentifier = 'assessor_' . $stage;
                }
            } else if ($this->coursework->sampling_enabled()) { // samplings enabled
                $insample = ($subs = $submission->get_submissions_in_sample()) ? count($subs) : 0;
                $feedback = $DB->record_exists('coursework_feedbacks', ['submissionid' => $submissionid,
                                                                             'stageidentifier' => 'assessor_1']);
                // no sample or no feedback for sample yet
                if (!$insample || ($insample && !$feedback)) {
                    $stageidentifier = 'assessor_1';
                } else { // find out which sample wasn't graded yet
                    $samples = $submission->get_submissions_in_sample();
                    foreach ($samples as $sample) {
                        $feedback = $DB->record_exists('coursework_feedbacks', ['submissionid' => $submissionid,
                                                                                    'stageidentifier' => $sample->stageidentifier]);
                         // if feedback doesn't exist, we'll use this stage identifier for a new feedback
                        if (!$feedback) {
                            $stageidentifier = $sample->stageidentifier;
                            break;
                        }
                    }
                }
            }
        }

        // double marked - multiplegrade - allocated/notallocated
        if ($this->coursework->get_max_markers() > 1 && ($cellidentifier != 'singlegrade' && $cellidentifier != 'feedbackcomments')) {
            if (str_starts_with($cellidentifier, 'assessor')) {
                $stageidentifier = 'assessor_' . (substr($cellidentifier, -1));
            } else if (str_starts_with($cellidentifier, 'agreed')) {
                $stageidentifier = 'final_agreed_1';
            }
        }

        return $stageidentifier;
    }

    /**
     * Create agreed grade if all initial grade are present
     * @param $cwfeedbackid
     * @throws coding_exception
     * @throws dml_exception
     */
    public function auto_agreement($cwfeedbackid) {
        global $DB;
        $feedback = feedback::get_from_id($cwfeedbackid);

        $autofeedbackclassname = '\mod_coursework\auto_grader\\' . $this->coursework->automaticagreementstrategy;
        /**
         * @var auto_grader $auto_grader
         */
        $autograder = new $autofeedbackclassname(
            $this->coursework,
            $feedback->get_submission()->get_allocatable(),
            $this->coursework->automaticagreementrange
        );
        $autograder->create_auto_grade_if_rules_match();
    }

    public function remove_other_assessors_grade($csvcells, &$line) {
        if (in_array('otherassessors', $csvcells)) {
            // find position of otherassesors so we know from which key to unset
            $key = array_search('otherassessors', $csvcells);
            unset($csvcells[$key]);
            $othercells = $this->other_assessors_cells();
            if ($this->coursework->is_using_rubric()) {
                $singlegradeposition = array_search('singlegrade', $csvcells);

                $criterias = $this->coursework->get_rubric_criteria();

                $startposition = $singlegradeposition + ((count($criterias) * 2) + 1);
            } else {
                $startposition = array_search('otherassessors', $csvcells);
            }

            for ($i = $startposition; $i < $startposition + $othercells; $i++) {
                unset($line[$i]);
            }
            $csvcells = array_values($csvcells);
            $line = array_values($line);
        }

        return $csvcells;
    }
}
