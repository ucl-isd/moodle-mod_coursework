<?php

/**
 * This class allows us to add functionality to the users, despite the fact that Moodle has no
 * core user class. Initially, it is using the active record approach, but this may need to change to
 * a decorator if Moodle implements such a class in future.
 */

namespace mod_coursework\models;

use mod_coursework\framework\table_base;
use \mod_coursework\allocation\allocatable;
use \mod_coursework\allocation\moderatable;

/**
 * @property int courseworkid
 * @property int studentid
 * @package mod_coursework\models
 *
 */
class assessment_set_membership extends table_base implements moderatable {

    /**
     * @var string
     */
    protected static $table_name = 'coursework_sample_set_mbrs';

}