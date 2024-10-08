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
 * @copyright  2017 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use Behat\Mink\Exception\ExpectationException;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/coursework/tests/behat/pages/page_base.php');

/**
 * Holds the functions that know about the HTML structure of the student page.
 *
 *
 */
class mod_coursework_behat_login_page extends mod_coursework_behat_page_base {

    public function load() {
        $this->getContext()->visit_page('login');
    }

    /**
     * @param stdClass $user
     * @throws Behat\Mink\Exception\ElementNotFoundException
     * @throws Behat\Mink\Exception\ExpectationException
     */
    public function login($user) {

        $this->getContext()->wait_till_element_exists('input#username');

        if ($this->getContext()->running_javascript()) {
            $this->getSession()->wait(1000);
        }

        $this->getPage()->fillField('username', $user->username);
        $this->getPage()->fillField('password', $user->password);
        $this->getContext()->find_button('Log in')->press();

        try {
            if ($this->getContext()->running_javascript()) {
                $this->getSession()->wait(10 * 1000);
            }
            $this->should_have_text('Log out');

        } catch(ExpectationException $e) {
            $this->getContext()->show_me_the_page();
            throw new ExpectationException('User '.$user->username.' could not be logged in', $this->getSession());
        }

    }

}
