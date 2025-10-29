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

use core\exception\coding_exception;
use core\exception\invalid_parameter_exception;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\restricted_context_exception;

/**
 * External service to allow late submissions for an individual user.
 *
 * @package   mod_coursework
 * @copyright 2025 UCL
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 4.5
 */
class allow_late_submissions extends external_api {

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
            'status' => new external_value(PARAM_INT, 'New status to set (1 allowed, 0 not allowed)', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $courseworkid
     * @param int $allocatableid
     * @param string $allocatabletype
     * @param int $status the new status 1 = allowed, 0 = not allowed.
 */
    public static function execute(int $courseworkid, int $allocatableid, string $allocatabletype, int $status): array {
        global $PAGE;
        $params = self::validate_parameters(self::execute_parameters(),
            [
                'courseworkid' => $courseworkid,
                'allocatableid' => $allocatableid,
                'allocatabletype' => $allocatabletype,
                'status' => $status,
            ]
        );
        if (!in_array($params['allocatabletype'], ['user', 'group'])) {
            throw new coding_exception("Invalid allocatable type " . $params['allocatabletype']);
        }
        $coursework = \mod_coursework\models\coursework::find($params['courseworkid']);
        if (get_class($coursework) == 'mod_coursework\decorators\coursework_groups_decorator') {
            // If the coursework is in group mode, coursework::find returns a wrapped object so unwrap.
            $coursework = $coursework->wrapped_object();
        }
        if (!$coursework) {
            throw new invalid_parameter_exception("Coursework " . $params['courseworkid'] . " not found");
        } else if ($coursework->allowlatesubmissions) {
            throw new coding_exception("Late submissions allowed at activity level. Not appropriate to grant at user level");
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
        $allocatable = $params['allocatabletype'] === 'user'
            ? \mod_coursework\models\user::find($params['allocatableid'])
            : \mod_coursework\models\group::find($params['allocatableid']);
        if (!$allocatable) {
            throw new invalid_parameter_exception("Allocatable " . $params['allocatableid'] . " not found");
        }
        $result = \mod_coursework\models\deadline_extension::set_user_allowed_to_submit_late_without_extension(
            $params['courseworkid'],
            $params['allocatabletype'],
            $params['allocatableid'],
            $params['status'] === 1
        );
        return [
            'success' => $result,
            // JSON encode the row data, so that we need not modify expected structure in execute_returns() when it changes.
            'result' => json_encode($rowdata),
            'errorcode' => null,
            'message' => get_string(
                $params['status'] == 1 ? 'latesubmissionsallowedfor' : 'latesubmissionsnotallowedfor',
                'coursework',
                $allocatable->name()
            ),
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
            'message' => new external_value(PARAM_RAW, 'The message to show user'),
        ]);
    }


    /**
     * Makes sure user may execute functions in this context.
     *
     * @param \context $context
     * @since Moodle 2.0
     */
    public static function validate_context($context) {
        if ($context->contextlevel !== CONTEXT_MODULE) {
            throw new restricted_context_exception();
        }
        require_capability('mod/coursework:allowlatesubmissionsuser', $context);
        parent::validate_context($context);
    }
}
