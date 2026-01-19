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
use core_user;
use mod_coursework\models\coursework;

/**
 * External service to delete an extension.
 *
 * @package   mod_coursework
 * @copyright 2025 UCL
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 4.5
 */
class assessorallocation extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseworkid' => new external_value(PARAM_INT),
            'allocatableid' => new external_value(PARAM_INT),
            'stageidentifier' => new external_value(PARAM_TEXT),
            'assessorid' => new external_value(PARAM_INT),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $extensionid
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \invalid_parameter_exception
     */
    public static function execute(int $courseworkid, int $allocatableid, string $stageidentifier, int $assessorid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseworkid' => $courseworkid,
            'allocatableid' => $allocatableid,
            'stageidentifier' => $stageidentifier,
            'assessorid' => $assessorid,
        ]);

        $coursework = coursework::get_object($params['courseworkid']);

        $assessor = core_user::get_user($params['assessorid']);

        $context = $coursework->get_context();
        self::validate_context($context);
        require_capability('mod/coursework:allocate', $context);

        $stage = $coursework->get_stage($params['stageidentifier']);
        $allocatable = $coursework->get_allocatable_from_id($params['allocatableid']);

        $alreadymarkedbyassessor = false;
        foreach ($allocatable->get_initial_feedbacks($coursework) as $feedback) {
            if ($feedback->assessorid == $params['assessorid']) {
                $alreadymarkedbyassessor = true;
            }
        }

        if (
            $alreadymarkedbyassessor
            ||
            $stage->assessor_already_allocated_for_this_submission($allocatable, $assessor)
        ) {
            return [
                'success' => false,
                'error' => get_string('samemarkererror', 'mod_coursework'),
            ];
        } else if ($assessorid == 0) {
            $stage->destroy_allocation($allocatable);
        } else {
            $stage->make_manual_allocation($allocatable, $assessor);
        }

        return [
            'success' => true,
            'error' => '',
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
            'error' => new external_value(PARAM_RAW, 'The message to show user'),
        ]);
    }
}
