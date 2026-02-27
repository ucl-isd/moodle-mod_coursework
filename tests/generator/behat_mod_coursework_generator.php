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
 * @package mod_coursework
 * @author Andrew Hancox <andrewdchancox@googlemail.com>
 * @author Open Source Learning <enquiries@opensourcelearning.co.uk>
 * @link https://opensourcelearning.co.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  2026 onwards UCL <andrewdchancox@googlemail.com>
 */

use core\exception\coding_exception;
use mod_coursework\models\coursework;

/**
 * @package mod_coursework
 * @author Andrew Hancox <andrewdchancox@googlemail.com>
 * @author Open Source Learning <enquiries@opensourcelearning.co.uk>
 * @link https://opensourcelearning.co.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright 2024, Andrew Hancox
 */
class behat_mod_coursework_generator extends behat_generator_base {
    /**
     * Get a list of the entities that Behat can create using the generator step.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'submissions' => [
                'singular' => 'submission',
                'datagenerator' => 'submission',
                'required' => ['allocatable', 'coursework'],
                'switchids' => [
                    'allocatable' => 'allocatableid',
                    'coursework' => 'courseworkid',
                ],
            ],
            'feedbacks' => [
                'singular' => 'feedback',
                'datagenerator' => 'feedback',
                'required' => ['allocatable', 'coursework', 'assessor'],
                'switchids' => [
                    'allocatable' => 'allocatableid',
                    'coursework' => 'courseworkid',
                    'assessor' => 'assessorid',
                ],
            ],
        ];
    }

    protected function get_assessor_id(string $assessor): int {
        if ($user = \core_user::get_user_by_username($assessor)) {
            return $user->id;
        }
        throw new coding_exception('Unable to resolve assessor.');
    }

    protected function get_allocatable_id(string $allocatable): int {
        if ($user = \core_user::get_user_by_username($allocatable)) {
            return $user->id;
        }
        throw new coding_exception('Unable to resolve allocatable.');
    }

    protected function get_coursework_id(string $coursework): int {
        if ($courseworkobj = coursework::find(['name' => $coursework])) {
            $id = $courseworkobj->id();
        }

        if (empty($id)) {
            throw new coding_exception('Unable to resolve coursework.');
        }

        return $id;
    }
}
