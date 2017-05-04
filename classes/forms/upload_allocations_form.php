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
 * @package    mod
 * @subpackage coursework
 * @copyright  2016 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir.'/formslib.php');



class upload_allocations_form extends moodleform {

    private $cmid;

    function __construct($cmid)  {
        $this->cmid =   $cmid;

        parent::__construct();
    }

    function definition()   {
        $mform =& $this->_form;

        $mform->addElement('filepicker', 'allocationsdata', get_string('allocationsfile','coursework'), null, array( 'accepted_types' => '*.csv'));
        $mform->addRule('allocationsdata', null, 'required');

      //  $mform->addElement('checkbox','overwrite','',get_string('overwritegrades','coursework'));
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


        $this->add_action_buttons(true,get_string('uploadallocations','coursework'));
    }

    function display()  {
        return $this->_form->toHtml();
    }

}