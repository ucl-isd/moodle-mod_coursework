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

use mod_coursework\models\feedback;

defined('MOODLE_INTERNAL') || die();


/**
 * Represents the files a student has submitted.
 */
class feedback_files extends files {

    /**
     * @var feedback
     */
    protected $feedback;

    /**
     * Constructor
     *
     * @param array $files
     * @param feedback $feedback
     */
    public function __construct($files, $feedback) {
        $this->feedback = $feedback;
        parent::__construct($files);
    }

    /**
     * Getter for type so we can access the file area.
     *
     * @return string
     */
    public function get_file_area_name() {
        return 'feedback';
    }

    /**
     * Tells us whether to show plagiarism links for this file. Feedback files are not sent for plagiarism checking
     * so always false.
     *
     * @return bool
     */
    public function show_plagiarism_links() {
        return false;
    }
}
