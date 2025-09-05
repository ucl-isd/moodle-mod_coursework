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


/**
 * External service to get a single row of data (one user) for the grading table to re-render it from JS.
 *
 * @package   mod_coursework
 * @copyright 2025 UCL
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 4.5
 */
class get_grading_table_row_data extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseworkid' => new external_value(PARAM_INT, 'The coursework ID', VALUE_REQUIRED),
            'allocatableid' => new external_value(PARAM_INT, 'The allocatable ID', VALUE_REQUIRED),
            'allocatabletype' => new external_value(PARAM_TEXT, 'The allocatable type', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $extensionid
     */
    public static function execute(int $courseworkid, int $allocatableid, string $allocatabletype): array {
        global $PAGE;
        $params = self::validate_parameters(
            self::execute_parameters(),
            [
                'courseworkid' => $courseworkid,
                'allocatableid' => $allocatableid,
                'allocatabletype' => $allocatabletype,
            ]
        );

        $coursework = \mod_coursework\models\coursework::find($params['courseworkid']);
        if (!$coursework) {
            return [
                'success' => false,
                'result' => null,
                'errorcode' => 'courseworknotfound',
                'message' => get_string('refreshpageforchanges', 'mod_coursework'),
            ];
        }

        if (!in_array($allocatabletype, ['user', 'group'])) {
            return [
                'success' => false,
                'result' => null,
                'errorcode' => 'invalidtype',
                'message' => '',
            ];
        }

        // The export function contains a capability check so we don't have an extra one here.
        $PAGE->set_context($coursework->get_context());
        $rowdata = \mod_coursework\renderers\grading_report_renderer::export_one_row_data(
            $coursework, $allocatableid, $allocatabletype
        );
        if (!$rowdata) {
            return [
                'success' => false,
                'result' => null,
                'errorcode' => 'rownotfound',
                'message' => get_string('refreshpageforchanges', 'mod_coursework'),
            ];
        }
        return [
            'success' => true,
            // JSON encode the row data, so that we need not modify expected structure in execute_returns() when it changes.
            'result' => json_encode($rowdata),
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
            'result' => new external_value(PARAM_RAW, 'The JSON encoded row data'),
            'errorcode' => new external_value(PARAM_RAW, 'The error code if applicable'),
            'message' => new external_value(PARAM_RAW, 'The message to show user'),
        ]);
    }
}
