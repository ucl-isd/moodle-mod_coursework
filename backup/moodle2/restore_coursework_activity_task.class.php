<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/coursework/backup/moodle2/restore_coursework_stepslib.php');

class restore_coursework_activity_task extends restore_activity_task
{
    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder.
     *
     * @return array of restore_decode_rule
     */
    static public function define_decode_rules()
    {
        $rules = array();

        $rules[] = new restore_decode_rule('COURSEWORKBYID',
                                           '/mod/coursework/view.php?id=$1',
                                           'course_module');
        $rules[] = new restore_decode_rule('CORSEWORKINDEX',
                                           '/mod/corsework/index.php?id=$1',
                                           'course_module');

        return $rules;

    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder.
     *
     * @return array
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('coursework', array('intro'), 'assign');

        return $contents;
    }
    
    /**
     * Define (add) particular settings this activity can have.
     */
    protected function define_my_settings()
    {
        // No particular settings for this activity.
    }
    
    /**
     * Define (add) particular steps this activity can have.
     */
    protected function define_my_steps()
    {
        // Only has one structure step.
        $this->add_step(new restore_coursework_activity_structure_step('coursework_structure', 'coursework.xml'));
    }
    
}