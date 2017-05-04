<?php

/**
 * Class framework_user_table allows us to test the table_base class on a reasonably stable table
 * with known columns.
 *
 * @property mixed username
 */
class framework_user_table extends \mod_coursework\framework\table_base {

    /**
     * @var string
     */
    protected static $table_name = 'user';

}