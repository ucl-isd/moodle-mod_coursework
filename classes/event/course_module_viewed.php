<?php

namespace mod_coursework\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Class course_module_viewed is responsible for logging the fact that a user has viewed a coursework.
 *
 * @package mod_coursework\event
 */
class course_module_viewed extends \core\event\course_module_viewed {
    protected function init() {
        $this->data['objecttable'] = 'coursework';
        parent::init();
    }

    /**
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url("/mod/$this->objecttable/view.php", array('id' => $this->contextinstanceid));
    }
    // You might need to override get_url() and get_legacy_log_data() if view mode needs to be stored as well.
}


