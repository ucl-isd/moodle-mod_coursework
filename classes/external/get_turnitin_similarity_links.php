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

namespace mod_coursework\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use mod_coursework\models\coursework;
use mod_coursework\models\submission;


/**
 * External service to get Turnitin similarity links for a submission or series of submissions (for the grading page).
 *
 * @package   mod_coursework
 * @copyright 2026 UCL
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 4.5
 */
class get_turnitin_similarity_links extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseworkid' => new external_value(PARAM_INT, 'ID of the coursework'),
            'submissionids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'ID of the submission'),
                'ID of the submissions (or empty array if all submissions required)'
            ),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $courseworkid
     * @param int[] $submissionids
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     */
    public static function execute(int $courseworkid, array $submissionids = []): array {
        global $CFG;
        require_once("$CFG->libdir/plagiarismlib.php");

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['courseworkid' => $courseworkid, 'submissionids' => $submissionids]
        );

        $coursework = coursework::find($params['courseworkid']);
        if (!$coursework) {
            return [
                'success' => false,
                'result' => null,
                'errorcode' => 'courseworknotfound',
                'message' => get_string('coursework_not_found', 'mod_coursework'),
            ];
        }

        if (!isset(plagiarism_load_available_plugins()['turnitin']) || !$coursework->tii_enabled()) {
            return [
                'success' => false,
                'result' => null,
                'errorcode' => 'turnitinnotavailable',
                'message' => get_string('notavailable'),
            ];
        }
        $courseid = $coursework->get_course()->id;
//todo check capability re markers.
        $submissionfilesdata = submission::get_all_submission_files_data($coursework, $submissionids);
        if (empty($submissionfilesdata)) {
            return ['success' => true, 'result' => [], 'errorcode' => null, 'message' => ''];
        }

        $result = [];
        foreach ($submissionfilesdata as $submissionfilesarray) {
            foreach ($submissionfilesarray as $submissionfile) {
                if (!isset($result[$submissionfile->submissionid])) {
                    $result[$submissionfile->submissionid] = [
                        'submissionid' => (int)$submissionfile->submissionid,
                        'files' => [],
                    ];
                }

                $result[$submissionfile->submissionid]['files'][] = (object)[
                    'fileid' => (int)$submissionfile->file->get_id(),
                    'linkshtml' => plagiarism_get_links([
                        'userid' => $submissionfile->authorid, // User or for group submissions, first member of group.
                        'file' => $submissionfile->file,
                        'cmid' => $coursework->cmid,
                        'course' => $courseid,
                        'coursework' => $coursework->id(),
                        'modname' => 'coursework',
                    ]),
                ];
            }
        }
        return [
            'success' => true,
            'result' => array_values($result),
            'errorcode' => null,
            'message' => '',
        ];
    }

    /**
     * Describe the return structure of the external service.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether successful'),
            'result' => new external_multiple_structure(
                new external_single_structure([
                    'submissionid' => new external_value(PARAM_INT, 'ID of submission'),
                    'files' => new external_multiple_structure(
                        new external_single_structure([
                            'fileid' => new external_value(PARAM_INT, 'ID of file'),
                            'linkshtml' => new external_value(PARAM_RAW, 'Links HTML from Turnitin', VALUE_REQUIRED, ''),
                        ])
                    ),
                ])
            ),
            'errorcode' => new external_value(PARAM_RAW, 'The error code if applicable'),
            'message' => new external_value(PARAM_RAW, 'The message to show user'),
        ]);
    }
}
