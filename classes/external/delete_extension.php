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
use mod_coursework\models\deadline_extension;

/**
 * External service to delete an extension.
 *
 * @package   mod_coursework
 * @copyright 2025 UCL
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 4.5
 */
class delete_extension extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'extensionid' => new external_value(PARAM_INT, 'The extension ID to delete', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $extensionid
     */
    public static function execute(int $extensionid): array {
        global $USER;
        $result = ['success' => false, 'message' => null];

        $params = self::validate_parameters(self::execute_parameters(), ['extensionid' => $extensionid]);
        $extension = deadline_extension::find($params['extensionid']);
        if (!$extension) {
            return [
                'success' => false,
                'message' => get_string('extension_not_found', 'mod_coursework', $extension->get_allocatable()->name()),
            ];
        }
        $coursework = $extension->get_coursework();
        $ability = $coursework ? new ability($USER->id, $coursework) : null;
        if ($ability && $ability->can('update', $extension) && $extension->can_be_deleted()) {
            $extension->delete();
            return [
                'success' => true,
                'message' => get_string('extension_deleted', 'mod_coursework', $extension->get_allocatable()->name()),
            ];
        } else {
            $result['message'] = get_string('nopermissiongeneral', 'mod_coursework');
            return $result;
        }
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
}
