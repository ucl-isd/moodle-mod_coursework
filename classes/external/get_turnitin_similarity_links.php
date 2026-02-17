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
use mod_coursework\grading_report;
use mod_coursework\models\coursework;
use mod_coursework\models\submission;
use mod_coursework\ability;


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
                'ID of the submissions required'
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
    public static function execute(int $courseworkid, array $submissionids): array {
        global $CFG, $USER;
        require_once("$CFG->libdir/plagiarismlib.php");
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['courseworkid' => $courseworkid, 'submissionids' => array_unique($submissionids, SORT_NUMERIC)]
        );

        $coursework = coursework::get_from_id($params['courseworkid']);
        if (!$coursework) {
            return [
                'success' => false,
                'result' => [],
                'errorcode' => 'courseworknotfound',
                'message' => get_string('coursework_not_found', 'mod_coursework'),
            ];
        }

        if (!$coursework->tii_enabled()) {
            return [
                'success' => false,
                'result' => [],
                'errorcode' => 'turnitinnotavailable',
                'message' => get_string('notavailable'),
            ];
        }
        $context = $coursework->get_context();
        self::validate_context($context);

        if (empty($params['submissionids'])) {
            return ['success' => true, 'result' => [], 'errorcode' => null, 'message' => ''];
        }

        // We do not use a capability check here, but instead limit to submissions that the user can see in grading report.
        $visiblesubmissionids = grading_report::get_visible_row_submission_ids($coursework);
        $invalidids = array_filter(
            $params['submissionids'],
            fn($id) => !in_array($id, $visiblesubmissionids)
        );

        if (!empty($invalidids)) {
            return [
                'success' => false,
                'result' => [],
                'errorcode' => 'submissionsnotfound',
                'message' => get_string('invalidsubmissionids', 'mod_coursework', implode(',', $invalidids)),
            ];
        }

        $noabilityids = [];
        $ability = new ability($USER->id, $coursework);
        $foundsubmissions = submission::get_multiple($params['submissionids']);
        // Check we have the ability to see plagiarism details for all requested and found submissions.
        foreach ($foundsubmissions as $foundsubmission) {
            if ($foundsubmission->courseworkid != $courseworkid || !$ability->can('view_plagiarism', $foundsubmission)) {
                $noabilityids[] = $foundsubmission->id;
            }
        }
        // Check if we are requesting any submissions that were not found.
        $foundsubmissionids = array_keys($foundsubmissions);
        foreach ($params['submissionids'] as $submissionid) {
            if (!in_array($submissionid, $foundsubmissionids)) {
                $noabilityids[] = $submissionid;
            }
        }
        if (!empty($noabilityids)) {
            return [
                'success' => false,
                'result' => [],
                'errorcode' => 'submissionsnotavailable',
                'message' => get_string('invalidsubmissionids', 'mod_coursework', implode(',', $noabilityids)),
            ];
        }

        $submissionfilesdata = submission::get_all_submission_files_data($coursework, $params['submissionids']);
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

                try {
                    // Get links from the plagiarism_turnitin plugin.
                    $links = submission::plagiarism_get_links($submissionfile->authorid, $submissionfile->file, $coursework);
                } catch (\Exception $e) {
                    return [
                        'success' => false,
                        'result' => [],
                        'errorcode' => 'turnitinerror',
                        'message' => ($CFG->debugdisplay ?? false) ? $e->getMessage() : '',
                    ];
                }

                $result[$submissionfile->submissionid]['files'][] = (object)[
                    'fileid' => (int)$submissionfile->file->get_id(),
                    'linkshtml' => $links,
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
