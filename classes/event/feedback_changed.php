<?php



namespace mod_coursework\event;

/**
 * Class feedback_changed is responsible for listening for changes to the feedbacks that have been
 * given to a student submission and to take action if needed. The main use case is for automatic
 * agreed grades to be awarded.
 *
 * @package mod_coursework\event
 */
class feedback_changed extends \core\event\base {

    /**
     * Override in subclass.
     *
     * Set all required data properties:
     *  1/ crud - letter [crud]
     *  2/ edulevel - using a constant self::LEVEL_*.
     *  3/ objecttable - name of database table if objectid specified
     *
     * Optionally it can set:
     * a/ fixed system context
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'coursework_feedbacks';
    }




}