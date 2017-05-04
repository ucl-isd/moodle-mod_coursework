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

/*
 * this is a mform designed to allow the toggling of the displaying of students not allocated to the current user
 *
 */

namespace mod_coursework\forms;

use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/lib/formslib.php');

class view_all_students_mform extends moodleform    {

    function definition()   {

        $this->_form->addElement('hidden', 'id', $this->_customdata['cmid']);
        $this->_form->setType('id', PARAM_INT);


        $buttontext = (empty($this->_customdata['displayallstudents']))    ? get_string('viewallstudents', 'coursework') : get_string('hideallstudents','coursework');
        $hiddenvalue    =  (empty($this->_customdata['displayallstudents']))    ? 1 : 0;
        $this->_form->addElement('submit', 'displayallstudentbutton', $buttontext);
        $this->_form->addElement('hidden', 'displayallstudents', $hiddenvalue);
        $this->_form->setType('displayallstudents', PARAM_INT);


    }

    /**
     * Bypasses the bit that echos the HTML so we can join it to a string.
     *
     * @return string
     */
    public function display() {
        return $this->_form->toHtml();
    }


}