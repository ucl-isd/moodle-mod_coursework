<?php

namespace mod_coursework\stages;
use mod_coursework\models\user;

/**
 * Class marking_stage represents a stage of the marking process. For a basic single marked coursework,
 * there will only be one. Double marking will have 3 (Two initial assessors and a final grade), and if
 * moderation is enabled, there will be one more.
 *
 * @package mod_coursework
 */
class final_agreed extends base {

    /**
     * Tells us whether the allocation table needs to deal with this one.
     *
     * @return bool
     */
    public function uses_allocation() {
        return false;
    }

    /**
     * @return string
     */
    protected function strategy_name() {
        return 'none';
    }

    /**
     * @return string
     * @throws \coding_exception
     */
    public function allocation_table_header() {
        return get_string('agreedgrade', 'mod_coursework');
    }

    /**
     * @return string
     */
    protected function assessor_capability() {
        return 'mod/coursework:addagreedgrade';
    }
}