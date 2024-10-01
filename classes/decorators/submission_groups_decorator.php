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
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\decorators;

use mod_coursework\framework\decorator;
use mod_coursework\models\feedback;
use mod_coursework\models\submission;

/**
 * Class submission_groups_decorator exists in order to wrap the coursework model when we have group
 * submissions enabled. We want to make sure that the students get the grade of the group thing rather
 * than their own missing assignment.
 *
 * @property submission wrappedobject
 * @package mod_coursework\decorators
 */
class submission_groups_decorator extends decorator {

    /**
     * @param $user
     * @return bool
     * @throws \coding_exception
     */
    public function user_is_in_same_group($user) {

        if (!$this->wrappedobject->get_coursework()->is_configured_to_have_group_submissions()) {
            throw new \coding_exception('Asking for groups membership of a submissions when we are not using groups');
        }

        $group = $this->wrappedobject->get_allocatable();

        return groups_is_member($group->id, $user->id);
    }

}
