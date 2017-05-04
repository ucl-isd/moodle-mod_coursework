<?php

namespace mod_coursework\decorators;

use mod_coursework\framework\decorator;
use mod_coursework\models\feedback;
use mod_coursework\models\submission;

/**
 * Class submission_groups_decorator exists in order to wrap the coursework model when we have group
 * submissions enabled. We want to make sure that the students get the grade of the group thing rather
 * than their own missing assignment.
 *
 * @property submission wrapped_object
 * @package mod_coursework\decorators
 */
class submission_groups_decorator extends decorator {

    /**
     * @param $user
     * @return bool
     * @throws \coding_exception
     */
    public function user_is_in_same_group($user) {

        if (!$this->wrapped_object->get_coursework()->is_configured_to_have_group_submissions()) {
            throw new \coding_exception('Asking for groups membership of a submissions when we are not using groups');
        }

        $group = $this->wrapped_object->get_allocatable();

        return groups_is_member($group->id, $user->id);
    }

}