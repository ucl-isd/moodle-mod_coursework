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
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Class mod_coursework_behat_new_extension_page is responsible for representing the new
 * extension page and its operation for the steps in Behat tests. Any reference to the page
 * CSS should be in this class only.
 *
 */
class mod_coursework_behat_new_extension_page extends mod_coursework_behat_page_base {

    /**
     * @param int $time
     * @throws \Behat\Mink\Exception\ElementNotFoundException
     */
    public function add_active_extension($time) {
        global $CFG;

        // Select the date from the dropdown
        $this->fill_in_date_field('extended_deadline', $time);

        // Choose an extension reason from the dropdown if it's there
        if (!empty($CFG->coursework_extension_reasons_list)) {
            $this->getPage()->fillField('pre_defined_reason', 0);
        }

        $fieldnode = $this->getPage()->findField('Extra information');

        $field = behat_field_manager::get_form_field($fieldnode, $this->getSession());
        // Delegates to the field class.
        $field->set_value('Extra info here');

        $this->submit_form();
    }

    public function submit_form() {
        $this->getPage()->find('css', '#id_submitbutton')->click();
    }

}
