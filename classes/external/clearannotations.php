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
use core_external\external_value;
use mod_coursework\ability;
use mod_coursework\models\user;


/**
 * External service to get a single row of data (one user) for the grading table to re-render it from JS.
 *
 * @package   mod_coursework
 * @copyright 2025 UCL
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 4.5
 */
class clearannotations extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'submissionid' => new external_value(PARAM_INT, 'The submission ID', VALUE_REQUIRED),
            'fileid' => new external_value(PARAM_INT, 'The annotated file ID', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $extensionid
     */
    public static function execute(int $submissionid, int $fileid): array {
        global $PAGE, $USER;
        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'submissionid' => $submissionid,
                'fileid' => $fileid,
            ]
        );

        $submission = \mod_coursework\models\submission::find($params['submissionid']);
        if (!$submission) {
            return [
                'success' => false,
                'result' => null,
                'errorcode' => 'submission_not_found',
                'message' => get_string('refreshpageforchanges', 'mod_coursework'),
            ];
        }

        $coursework = $submission->get_coursework();

        if (empty($coursework->enablepdfjs())) {
            throw new \Exception('coursework enablepdfjs not enabled');
        }

        // The export function contains a capability check so we don't have an extra one here.
        $PAGE->set_context($coursework->get_context());

        $user = user::find($USER);
        $ability = new ability($user, $coursework);
        if ($ability->cannot('show', $submission)) {
            return [
                'success' => false,
                'errorcode' => 'permission_denied',
                'message' => get_string('refreshpageforchanges', 'mod_coursework'),
            ];
        }

        $fs = get_file_storage();
        $file = $fs->get_file_by_id($fileid);

        if (!$file) {
            return [
                'success' => false,
                'errorcode' => 'file not found',
                'message' => get_string('refreshpageforchanges', 'mod_coursework'),
            ];
        }

        if (
            $file->get_userid() != $USER->id
            || $file->get_itemid() != $submissionid
            || $file->get_filearea() != 'submissionannotations'
        ) {
            return [
                'success' => false,
                'errorcode' => 'invalid file',
                'message' => get_string('refreshpageforchanges', 'mod_coursework'),
            ];
        }

        if (!$file->delete()) {
            return [
                'success' => false,
                'errorcode' => 'unable to delete file',
                'message' => get_string('refreshpageforchanges', 'mod_coursework'),
            ];
        }
        return [
            'success' => true,
            // JSON encode the row data, so that we need not modify expected structure in execute_returns() when it changes.
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
            'errorcode' => new external_value(PARAM_RAW, 'The error code if applicable'),
            'message' => new external_value(PARAM_RAW, 'The message to show user'),
        ]);
    }
}
