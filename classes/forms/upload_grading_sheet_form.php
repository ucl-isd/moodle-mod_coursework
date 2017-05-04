<?php
/**
 * Created by PhpStorm.
 * User: Nigel.Daley
 * Date: 10/08/2015
 * Time: 18:27
 */



require_once($CFG->libdir.'/formslib.php');



class upload_grading_sheet_form extends moodleform {

    private $cmid;

    function __construct($cmid)  {
        $this->cmid =   $cmid;

        parent::__construct();
    }

    function definition()   {
        $mform =& $this->_form;

        $mform->addElement('filepicker', 'gradingdata', get_string('gradingsheetfile','coursework'), null, array( 'accepted_types' => '*.csv'));
        $mform->addRule('gradingdata', null, 'required');

        $mform->addElement('checkbox','overwrite','',get_string('overwritegrades','coursework'));
        $mform->addElement('hidden','cmid',$this->cmid);

        $mform->setType('cmid',PARAM_RAW);

        $choices = csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'delimiter_name', get_string('csvdelimiter', 'tool_uploaduser'), $choices);
        if (array_key_exists('cfg', $choices)) {
            $mform->setDefault('delimiter_name', 'cfg');
        } else if (get_string('listsep', 'langconfig') == ';') {
            $mform->setDefault('delimiter_name', 'semicolon');
        } else {
            $mform->setDefault('delimiter_name', 'comma');
        }

        $choices = core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'tool_uploaduser'), $choices);
        $mform->setDefault('encoding', 'UTF-8');


        $this->add_action_buttons(true,get_string('uploadgradingworksheet','coursework'));
    }

    function display()  {
        return $this->_form->toHtml();
    }




}