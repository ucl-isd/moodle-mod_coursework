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
use csv_export_writer;
use mod_coursework\models\coursework;
use mod_coursework\models\submission;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/csvlib.class.php');

/**
 * Class csv is responsible for managing exports to a CSV file
 *
 * @package mod_coursework\export
 */
class csv {
    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @var csv_export_writer
     */
    protected $csvexport;

    protected $dateformat;
    /**
     * @var array
     */
    protected $csvcells;
    /**
     * @var string
     */
    protected $filename;

    /**
     * @param $coursework
     * @param $csvcells
     * @param $filename
     */
    public function __construct($coursework, $csvcells, $filename) {
        $this->coursework = $coursework;
        $this->dateformat = '%a, %d %b %Y, %H:%M';
        $this->csvcells = $csvcells;
        $this->filename = $filename;
    }

    /**
     * @throws coding_exception
     */
    public function export() {

        $this->csvexport = new csv_export_writer();
        $this->add_filename($this->filename);

        $csvdata = [];
        // headers
        $this->csvexport->add_data($this->add_headers($this->csvcells));

        /**
         * @var submission[] $submissions
         */
        $submissions = $this->get_submissions();

        // sort submissions by lastname
        usort($submissions, [$this, "sort_by_lastname"]);

        // loop through each submission in the coursework
        foreach ($submissions as $submission) {
            // add data to cvs
            $data = $this->add_csv_data($submission);
            $csvdata = array_merge($csvdata, $data);
        }

        $this->add_data_to_csv($csvdata);
        $this->csvexport->download_file();

        die;
    }

    /**
     * Create CSV cells
     * @param $submission
     * @param $student
     * @param $csvcells
     * @return array
     */
    public function add_cells_to_array($submission, $student, $csvcells) {
        $row = [];
        foreach ($csvcells as $csvcell) {
            if (str_starts_with($csvcell, 'assessor')) {
                $stageidentifier = 'assessor_' . (substr($csvcell, -1));
                $csvcell = substr($csvcell, 0, -1);
            }
            $class = "mod_coursework\\export\\csv\\cells\\" . $csvcell . "_cell";
            $cell = new $class($this->coursework);
            if (str_starts_with($csvcell, 'assessor')) {
                $cell = $cell->get_cell($submission, $student, $stageidentifier);
                if (is_array($cell)) {
                    $row = array_merge($row, $cell);
                } else {
                    $row[] = $cell;
                }
            } else if ($csvcell != 'stages' && $csvcell != 'moderationagreement' && $csvcell != 'otherassessors') {
                $cell = $cell->get_cell($submission, $student, false);
                if (is_array($cell)) {
                    $row = array_merge($row, $cell);
                } else {
                    $row[] = $cell;
                }
            } else {
                $stages = $cell->get_cell($submission, $student, false);
                $row = array_merge($row, $stages);
            }
        }
        return $row;
    }

    /**
     * create headers for CSV
     * @param $csvheaders
     */
    public function add_headers($csvheaders) {
        $headers = [];
        foreach ($csvheaders as $header) {
            if (str_starts_with($header, 'assessor')) {
                $stage = (substr($header, -1));
                $header = substr($header, 0, -1);
            }
            $class = "mod_coursework\\export\\csv\\cells\\" . $header . "_cell";
            $cell = new $class($this->coursework);
            if (str_starts_with($header, 'assessor')) {
                $head = $cell->get_header($stage);
                if (is_array($head)) {
                    $headers = array_merge($headers, $head);
                } else {
                    $headers[$header . $stage] = $head;
                }
            } else if ($header != 'stages' && $header != 'moderationagreement' && $header != 'otherassessors') {
                 $head = $cell->get_header(false);
                if (is_array($head)) {
                    $headers = array_merge($headers, $head);
                } else {
                    $headers[$header] = $head;
                }
            } else {
                $arrayheaders = $cell->get_header(false);
                $headers = array_merge($headers, $arrayheaders);
            }
        }

        return $headers;
    }

    /**
     * Add filename to the CSV
     * @param $filename
     * @return string
     */
    public function add_filename($filename) {

        $filename = clean_filename($filename);
        return $this->csvexport->filename = $filename . '.csv';
    }

    /**
     * Function to sort array in order of submission's lastname
     * @param $a
     * @param $b
     * @return int
     */
    protected function sort_by_lastname($a, $b) {

        return strcmp($a->lastname, $b->lastname);
    }

    /**
     * @param array $csvdata
     */
    private function add_data_to_csv($csvdata) {
        foreach ($csvdata as $data) {
            $this->csvexport->add_data($data);
        }
    }

    /**
     * @param null $groupid
     * @param string $selectedsubmissionids
     * @return array
     * @throws coding_exception
     */
    public function get_submissions($groupid = null, $selectedsubmissionids = '') {

        $submissions = submission::get_all_for_coursework($this->coursework->id);
        if ($selectedsubmissionids && $selectedsubmissionids = json_decode($selectedsubmissionids)) {
            $result = array_flip($selectedsubmissionids);
            foreach ($submissions as $submission) {
                if (array_key_exists($submission->id, $result)) {
                    $result[$submission->id] = $submission;
                }
            }
            $submissions = $result;
        }
        return $submissions;
    }

    /**
     * Function to add data to csv
     * @param submission $submission
     * @return array
     */
    public function add_csv_data($submission) {

        $csvdata = [];
        // retrieve all students (even if group coursework)
        $students = $submission->get_students();

        foreach ($students as $student) {
            $csvdata[] = $this->add_cells_to_array($submission, $student, $this->csvcells);
        }

        return $csvdata;
    }

    public function other_assessors_cells() {

        $cells = 0;
        if ($this->coursework->is_using_rubric()) {
            $criterias = $this->coursework->get_rubric_criteria();
            // We will increment by the number of criterias plus 1 for feedback
            $increment = (count($criterias) * 2) + 1;
        } else {
            $increment = 2;
        }

        for ($i = 1; $i < $this->coursework->get_max_markers(); $i++) {
            $cells = $cells + $increment; // one for grade, one for feedback
        }

        return $cells;
    }
}
