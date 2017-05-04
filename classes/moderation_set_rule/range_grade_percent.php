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

use html_writer;
use mod_coursework\allocation\allocatable;
use mod_coursework\models\moderation_set_rule;

defined('MOODLE_INTERNAL') || die();


/**
 * Defines a rule that will include all students above or below a particular percentage of
 * the total grade.
 */
class range_grade_percent extends moderation_set_rule {

    /**
     * This will take the current set and the list of students who could potentially be added
     * and adjust them. e.g. if the rule says 'include all below 40% of total grade, it will
     * calculate what 40% is, then move any below it from the $potentialstudents array to
     * the $moderationset array.
     *
     * @param allocatable[] $moderation_set
     * @param allocatable[] $potential_allocatables
     * @param \mod_coursework\stages\base $stage
     * @return mixed
     */
    public function adjust_set(array &$moderation_set, array &$potential_allocatables, $stage) {

        $maxgrade = $this->get_coursework()->get_max_grade();
        // Convert percentages to raw grades for comparison.
        $upperlimit = ($this->upperlimit / 100) * $maxgrade;
        $lowerlimit = ($this->lowerlimit / 100) * $maxgrade;

        foreach ($potential_allocatables as $id => $allocatable) {

            if ($this->allocatable_is_not_yet_graded($allocatable)) {
                continue;
            }

            $grade = $this->get_allocatable_final_grade($allocatable);

            if ($grade <= $upperlimit &&
                $grade >= $lowerlimit) {

                $moderation_set[$id] = $allocatable;
                unset ($potential_allocatables[$id]);
            }
        }
    }

    /**
     * Returns a human readable range e.g. 0 - 40%
     * @return string
     */
    public function get_numeric_boundaries() {
        $lower = empty($this->lowerlimit) ? '0' : $this->lowerlimit.'%';
        return $lower.' - '.$this->upperlimit.'%';
    }

    /**
     * Tells us where this ought to be in relation to other rules. The one for percent of total must happen last,
     * so this is how we enforce it.
     *
     * @return mixed
     */
    public function get_default_rule_order() {
        return 0;
    }

    /**
     * Some rules make no sens when there are multiple e.g. 'include at least x% of the total number'.
     *
     * @static
     * @return mixed
     */
    public static function allow_multiple() {
        return true;
    }

    /**
     * Each rule may have different form elements that we need to add in order for a new one to be
     * @return mixed
     */
    public function get_form_elements() {

        $html = '';

        // Explain that this won't happen until some grades appear.
        $html .= html_writer::start_tag('div class="message"');
        $html .= get_string('modsetgradeexplain', 'mod_coursework');
        $html .= html_writer::end_tag('div');

        // Upper limit.
        $html .= html_writer::start_tag('p');
        $html .= get_string('upperlimit', 'mod_coursework').' ';
        $attributes = array(
            'name' => 'rule_range_grade_percent_upperlimit',
            'size' => 3
        );
        $html .= html_writer::empty_tag('input', $attributes).'%';
        $html .= html_writer::end_tag('p');
        // Lower limit.
        $html .= html_writer::start_tag('p');
        $html .= get_string('lowerlimit', 'mod_coursework').' ';
        $attributes = array(
            'name' => 'rule_range_grade_percent_lowerlimit',
            'size' => 3
        );
        $html .= html_writer::empty_tag('input', $attributes).'%';
        $html .= html_writer::end_tag('p');

        return $html;
    }

}
