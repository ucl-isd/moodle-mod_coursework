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
 * Page that prints a table of all students and all markers so that first marker, second marker,
 * moderators etc can be allocated manually or automatically.
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2012 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\moderation_set_rule;

use coding_exception;
use html_writer;
use mod_coursework\models\moderation_set_rule;
use stdClass;

defined('MOODLE_INTERNAL') || die();


/**
 * Defines a rule that will include all students between an upper and lower percentage of the total
 * number. Only really makes sense with lowerlimit as 0.
 */
class range_total_percent extends moderation_set_rule {

    /**
     * Constructor builds instance from optional DB record.
     *
     * @param bool|stdClass|int $dbrecord
     * @throws coding_exception
     */
    public function __construct($dbrecord = false) {

        parent::__construct($dbrecord);

        if ($dbrecord && $this->lowerlimit != 0) {
            $message = 'Lower limit of total percent moderation set should always be 0';
            throw new coding_exception($message);
        }
    }

    /**
     * This will take the current set and the list of students who could potentially be added
     * and adjust them. e.g. if the rule says 'include all below 40% of total grade, it will
     * calculate what 40% is, then move any below it from the $potentialstudents array to
     * the $moderationset array.
     *
     * @param array $moderation_set
     * @param array $potential_allocatables
     * @param \mod_coursework\stages\base $stage
     * @return mixed
     */
    public function adjust_set(array &$moderation_set, array &$potential_allocatables, $stage) {

        $total_number = count($moderation_set) + count($potential_allocatables);

        // We need to round up as we may have small numbers of students and e.g. 25% of 10 students ought to
        // equate to 3.
        $number_needed = ceil($total_number * ($this->upperlimit / 100));

        while (count($moderation_set) < $number_needed) {

            // Add random ones till we have enough.
            $id = array_rand($potential_allocatables);

            $moderation_set[$id] = $potential_allocatables[$id];
            unset ($potential_allocatables[$id]);

            $number_needed--;

        }
    }

    /**
     * Returns a human readable range e.g. 0 - 40%
     * @return string
     */
    public function get_numeric_boundaries() {
        return $this->upperlimit.'%';
    }

    /**
     * Tells us where this ought to be in relation to other rules. The one for percent of total must happen last,
     * so this is how we enforce it.
     *
     * @return mixed
     */
    public function get_default_rule_order() {
        return 1000;
    }

    /**
     * Some rules make no sens when there are multiple e.g. 'include at least x% of the total number'.
     *
     * @static
     * @return mixed
     */
    public static function allow_multiple() {
        return false;
    }

    /**
     * Each rule may have different form elements that we need to add in order for a new one to be
     * @return mixed
     */
    public function get_form_elements() {

        $html = '';

        // Upper limit.
        $html .= html_writer::start_tag('p');
        $html .= get_string('upperlimit', 'mod_coursework').' ';
        $attributes = array(
            'name' => 'rule_range_total_percent_upperlimit',
            'size' => 3
        );
        $html .= html_writer::empty_tag('input', $attributes).'%';
        $html .= html_writer::end_tag('p');
        return $html;
    }

    /**
     * Validates and saves data from the form elements defined by {@link get_form_elements()}.
     *
     * @return mixed
     */
    public function save_form_data() {

        global $DB;

        $upperlimit = optional_param('rule_'.self::get_name().'_upperlimit', '', PARAM_INT);

        // Validate.
        // Make sure we get a percentage as a whole number.
        $cleanlimit = round($upperlimit);
        $cleanlimit = min($cleanlimit, 100);
        $cleanlimit = max($cleanlimit, 0);

        if ($upperlimit !== '' && $cleanlimit !== 0) {
            $tosave = new stdClass();
            $tosave->courseworkid = $this->courseworkid;
            $tosave->rulename = self::get_name();
            $tosave->upperlimit = $cleanlimit;
            $tosave->lowerlimit = 0;
            $tosave->ruleorder = $this->get_default_rule_order();
            $DB->insert_record('coursework_mod_set_rules', $tosave);
        }

    }
}

