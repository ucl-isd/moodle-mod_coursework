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
 * Creates an mform for moderator agreement
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2017 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\forms;

global $CFG;

use coding_exception;

use mod_coursework\models\plagiarism_flag;
use moodleform;

require_once($CFG->libdir.'/formslib.php');

/**
 * Simple form providing a plagiarism status and comment area that will feed straight into the coursework_plagiarism_flagging table
 */
class plagiarism_flagging_mform extends moodleform {

    /**
     * @var int the id of the submission that the grade pertains to
     */
    public $submission_id;


    /**
     * Makes the form elements.
     */
    public function definition() {

        $mform =& $this->_form;

        /**
         * @var $plagiarism_flag plagiarism_flag
         */
        $plagiarism_flag = $this->_customdata['plagiarism_flag'];

        $mform->addElement('hidden', 'submissionid', $plagiarism_flag->get_submission()->id);
        $mform->setType('submissionid', PARAM_INT);

        // plagiarism status
        $options = array(plagiarism_flag::INVESTIGATION => get_string('plagiarism_'.plagiarism_flag::INVESTIGATION , 'coursework'),
                         plagiarism_flag::RELEASED => get_string('plagiarism_'.plagiarism_flag::RELEASED, 'coursework'),
                         plagiarism_flag::CLEARED => get_string('plagiarism_'.plagiarism_flag::CLEARED, 'coursework'),
                         plagiarism_flag::NOTCLEARED => get_string('plagiarism_'.plagiarism_flag::NOTCLEARED, 'coursework'));

        $mform->addElement('select', 'status',
                            get_string('status', 'coursework'),
                            $options,
                            array('id' => 'plagiarism_status'));

        $mform->addHelpButton('status', 'status', 'mod_coursework');

        $mform->addElement('editor', 'plagiarismcomment', get_string('comment', 'mod_coursework'), array('id' => 'plagiarism_comment'));
        $mform->setType('editor', PARAM_RAW);

        $mform->hideIf('plagiarismcomment', 'status', 'eq', "1");

        $this->add_action_buttons();
    }

    /**
     * This is just to grab the data and add it to the plagiarismflag object.
     *
     * @param plagiarism_flag $plagiarismflag
     * @return plagiarism_flag
     */
    public function process_data(plagiarism_flag $plagiarismflag) {

        $formdata = $this->get_data();

        $plagiarismflag->status = $formdata->status;
        $plagiarismflag->comment = $formdata->plagiarismcomment['text'];
        $plagiarismflag->comment_format = $formdata->plagiarismcomment['format'];

        return $plagiarismflag;
    }
}

