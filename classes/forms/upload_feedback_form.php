<?php
/**
 * Created by PhpStorm.
 * User: Nigel.Daley
 * Date: 10/08/2015
 * Time: 18:27
 */



require_once($CFG->libdir.'/formslib.php');



class upload_feedback_form extends moodleform {

    private     $cmid;
    private     $coursework;

    function __construct($coursework, $cmid)  {
        $this->cmid         =   $cmid;
        $this->coursework   =   $coursework;

        parent::__construct();
    }

    function definition()   {
        $mform =& $this->_form;

        $mform->addElement('filepicker', 'feedbackzip', get_string('feedbackzipfile','coursework'), null, array( 'accepted_types' => '*.zip'));
        $mform->addRule('feedbackzip', null, 'required');
        $mform->addHelpButton('feedbackzip', 'feedbackzipfile','coursework');

        $mform->addElement('advcheckbox','overwrite','',get_string('overwritefeedback','coursework'),null,array(0,1));
        $mform->addElement('hidden','cmid',$this->cmid);
        $mform->setType('cmid',PARAM_RAW);

        $options = array();

        if ($this->coursework->get_max_markers() > 1) {

            $capability = array('mod/coursework:addinitialgrade', 'mod/coursework:editinitialgrade');
            if (has_any_capability($capability, $this->coursework->get_context()) && !has_capability('mod/coursework:administergrades',$this->coursework->get_context())) {
                $options['initialassessor'] = get_string('initialassessor', 'coursework');

            } else if (has_capability('mod/coursework:administergrades',$this->coursework->get_context())){
                $options['assessor_1'] = get_string('assessorupload', 'coursework', '1');
                if ($this->coursework->get_max_markers() >= 2) $options['assessor_2'] = get_string('assessorupload', 'coursework', '2');
                if ($this->coursework->get_max_markers() >= 3) $options['assessor_3'] = get_string('assessorupload', 'coursework', '3');
            }

            $capability = array('mod/coursework:addagreedgrade', 'mod/coursework:editagreedgrade', 'mod/coursework:administergrades');
            if (has_any_capability($capability, $this->coursework->get_context())) $options['final_agreed_1'] = get_string('finalagreed', 'coursework');

            $mform->addElement('select', 'feedbackstage', get_string('feedbackstage', 'coursework'), $options);
        } else {
            $mform->addElement('hidden','feedbackstage','assessor_1');
            $mform->setType('feedbackstage',PARAM_RAW);
        }

        // Disable overwrite current feedback files checkbox if user doesn't have edit capability
        if(!has_capability('mod/coursework:editinitialgrade',$this->coursework->get_context())) {
            $mform->disabledIf('overwrite', 'feedbackstage', 'eq', 'initialassessor');
        }

        if(!has_capability('mod/coursework:editagreedgrade',$this->coursework->get_context()) && !has_capability('mod/coursework:administergrades',$this->coursework->get_context()) ) {
            $mform->disabledIf('overwrite', 'feedbackstage', 'eq', 'final_agreed_1');
        }


        $this->add_action_buttons(true,get_string('uploadfeedbackzip','coursework'));
    }

    function display()  {
        return $this->_form->toHtml();
    }

}