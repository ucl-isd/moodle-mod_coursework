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
use mod_coursework\models\coursework;

/**
 * External service to delete an extension.
 *
 * @package   mod_coursework
 * @copyright 2025 UCL
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 4.5
 */
class allocatableinsampletoggle extends external_api {
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
            'togglestate' => new external_value(PARAM_BOOL),
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
    public static function execute(int $courseworkid, int $allocatableid, string $stageidentifier, bool $togglestate): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseworkid' => $courseworkid,
            'allocatableid' => $allocatableid,
            'stageidentifier' => $stageidentifier,
            'togglestate' => $togglestate,
        ]);

        $coursework = coursework::get_object($params['courseworkid']);

        self::validate_context($coursework->get_context());
        require_capability('mod/coursework:allocate', $coursework->get_context());

        $stage = $coursework->get_stage($params['stageidentifier']);

        if (empty($stage->uses_sampling())) {
            return ['success' => false];
        }

        $allocatable = $coursework->get_allocatable_from_id($params['allocatableid']);
        $stage->toggle_alloctable_sampling($allocatable, $params['togglestate']);

        return [
            'success' => true,
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
        ]);
    }
}
