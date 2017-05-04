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

namespace mod_coursework;

/**
 * Displays the information a student sees when they submit or have submitted work
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2012 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use context;
use mod_coursework\models\submission;
use stored_file;

defined('MOODLE_INTERNAL') || die();


/**
 * Represents the files a student has submitted.
 */
class submission_files extends files {

    /**
     * @var submission
     */
    protected $submission;

    /**
     * Constructor
     *
     * @param array $files
     * @param models\submission $submission
     */
    public function __construct($files, $submission) {
        $this->submission = $submission;
        parent::__construct($files);
    }

    /**
     * Getter for type.
     *
     * @return string
     */
    public function get_file_area_name() {
        return 'submission';
    }

    /**
     * Returns the first (and only, hopefully) file so it can be renamed.
     *
     * @return stored_file|null
     */
    public function get_first_submitted_file() {

        if (empty($this->files)) {
            return null;
        }
        return reset($this->files);
    }

    /**
     * Chained getter returns the coursemodule id.
     *
     * @return int
     */
    public function get_course_module_id() {
        return $this->get_submission()->get_course_module_id();
    }

    /**
     * Returns the id of the submission that these files are part of.
     *
     * @return int
     */
    public function get_submission_id() {
        return $this->get_submission()->id;
    }

    /**
     * @return context
     */
    public function get_context() {
        return $this->get_submission()->get_context();
    }

    /**
     * @return models\coursework
     */
    public function get_coursework() {
        return $this->get_submission()->get_coursework();
    }

    /**
     * @return submission
     */
    public function get_submission() {
        return $this->submission;
    }
}
