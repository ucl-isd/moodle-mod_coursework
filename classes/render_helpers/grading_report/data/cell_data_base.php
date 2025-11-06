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
 * Base class for grading report cell data providers.
 *
 * @package    mod_coursework
 * @copyright  2025 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Alex Yeung <k.yeung@ucl.ac.uk>
 */

namespace mod_coursework\render_helpers\grading_report\data;

use core\clock;
use core\di;
use mod_coursework\ability;
use mod_coursework\models\coursework;
use mod_coursework\models\plagiarism_flag;
use mod_coursework\models\submission;

/**
 * Abstract base class for cell data providers.
 */
abstract class cell_data_base implements cell_data_interface {
    /** @var coursework */
    protected coursework $coursework;

    /** @var ability */
    protected ability $ability;

    /** @var clock Clock instance. */
    protected readonly clock $clock;

    /**
     * Constructor.
     * @param coursework $coursework
     * @return void
     */
    public function __construct(coursework $coursework) {
        global $USER;

        $this->coursework = $coursework;
        $this->ability = new ability($USER->id, $this->coursework);
        $this->clock = di::get(clock::class);
    }

    /**
     * Check if the submission should be flagged for plagiarism.
     *
     * @param submission $submission
     * @return string|bool
     */
    protected function get_flagged_plagiarism_status(submission $submission): string|bool {
        $flag = plagiarism_flag::get_plagiarism_flag($submission);
        if (!$flag || !($flag->status == plagiarism_flag::INVESTIGATION || $flag->status == plagiarism_flag::NOTCLEARED)) {
            return false;
        }
        return get_string('plagiarism_'.$flag->status, 'mod_coursework');
    }
}
