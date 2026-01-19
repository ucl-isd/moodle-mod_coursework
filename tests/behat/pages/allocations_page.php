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

use Behat\Mink\Exception\ElementNotFoundException;
use mod_coursework\allocation\allocatable;
use mod_coursework\models\user;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/coursework/tests/behat/pages/page_base.php');

/**
 * Holds the functions that know about the HTML structure of the student page.
 *
 *
 */
class mod_coursework_behat_allocations_page extends mod_coursework_behat_page_base {
    public function save_everything() {
        $this->getpage()->pressButton('save_manual_allocations_1');

        if ($this->getpage()->hasLink('Continue')) {
            $this->getpage()->clickLink('Continue');
        }
    }

    public function show_assessor_allocation_settings() {
        $this->getpage()->find('css', '#assessor_allocation_settings_header')->click();
        $this->getsession()->wait(1000);
    }

    /**
     * @param allocatable $allocatable
     */
    public function should_not_have_moderator_allocated($allocatable) {
        $locator = '#' . $allocatable->type() . '_' . $allocatable->id() . ' .moderator_1 .existing-assessor';
        $this->should_not_have_css($locator);
    }

    /**
     * @param allocatable $allocatable
     * @param allocatable $assessor
     */
    public function should_have_moderator_allocated($allocatable, $assessor) {
        $locator = '#' . $allocatable->type() . '_' . $allocatable->id() . ' .moderator_1 .existing-assessor';
        $this->should_have_css($locator, $assessor->name());
    }

    /**
     * Enable automatic sampling.
     * @param $stage
     * @throws ElementNotFoundException
     */
    public function enable_atomatic_sampling_for($stage) {
        $elementid = '#assessor_' . $stage . '_samplingstrategy';
        $node = $this->getpage()->find('css', $elementid);

        $node->selectOption('Automatic');
    }

    /**
     * @param $stage
     * @throws \Behat\Mink\Exception\ElementException
     */
    public function enable_total_rule_for_stage($stage) {
        $elementid = '#assessor_' . $stage . '_sampletotal_checkbox';
        $node = $this->getpage()->find('css', $elementid);

        $node->check();
    }

    /**
     * @param $stage
     * @throws \Behat\Mink\Exception\ElementException
     */
    public function add_grade_range_rule_for_stage($stage) {
        $elementid = 'assessor_' . $stage . '_addgradderule';

        $this->getpage()->clickLink($elementid);
    }

    /**
     * @param $stage
     * @param $ruleno
     * @throws \Behat\Mink\Exception\ElementException
     */
    public function enable_grade_range_rule_for_stage($stage, $ruleno) {
        $elementid = '#assessor_' . $stage . '_samplerules_' . $ruleno;
        $node = $this->getpage()->find('css', $elementid);

        $node->check();
    }

    /**
     * @param $stage
     * @param $ruleno
     * @param $type
     * @throws ElementNotFoundException
     */
    public function select_type_of_grade_range_rule_for_stage($stage, $ruleno, $type) {
        $elementid = '#assessor_' . $stage . '_sampletype_' . $ruleno;
        $node = $this->getpage()->find('css', $elementid);

        $node->selectOption($type);
    }

    /**
     * @param $range
     * @param $stage
     * @param $ruleno
     * @param $value
     * @throws ElementNotFoundException
     */
    public function select_range_for_grade_range_rule_for_stage($range, $stage, $ruleno, $value) {
        $elementid = '#assessor_' . $stage . '_sample' . $range . '_' . $ruleno;
        $node = $this->getpage()->find('css', $elementid);

        $node->selectOption($value);
    }

    /**
     * @param $percentage
     * @param $stage
     * @throws ElementNotFoundException
     */
    public function select_total_percentage_for_stage($percentage, $stage) {

        // Increment stage as the this will match the id of the element;
        $stage++;

        $elementid = '#assessor_' . $stage . '_sampletotal';
        $node = $this->getpage()->find('css', $elementid);

        $node->selectOption($percentage);
    }

    /**
     * @param $coursework
     * @param $user
     * @param $otheruser
     * @param $stagenumber
     * @return bool
     */
    public function automatically_included_in_sample($coursework, $user, $otheruser, $stagenumber): bool {
        global $DB;

        $othersql = (!empty($otheruser)) ? "OR allocatableid = $otheruser->id" : '';

        $sql = "SELECT *
                     FROM {coursework_sample_set_mbrs}
                     WHERE courseworkid = :courseworkid
                     AND stageidentifier = :stage
                     AND (allocatableid = :user $othersql)";

        $stage = "assessor_" . $stagenumber;

        $params = [
            'courseworkid' => $coursework->id,
            'user' => $user->id,
            'stage' => $stage,
        ];

        return $DB->record_exists_sql($sql, $params);
    }

    public function save_sampling_strategy() {
        $this->getpage()->pressButton('save_manual_sampling');
    }
}
