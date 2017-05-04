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

namespace mod_coursework;

/**
 * Page that prints a table of all students and all markers so that first marker, second marker, moderators
 * etc can be allocated manually or automatically.
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2012 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use html_writer;
use mod_coursework\models\coursework;

defined('MOODLE_INTERNAL') || die();

/**
 * Acts as a holder for the data needed to render a widget where the user can define a moderation set.
 */
class sampling_set_widget {

    /**
     * @var array
     */
    protected $rules;

    /**
     * If we are adding a rule via the form, we want to show the right inputs so the user can specify upper ad/or
     * lower bounds.
     *
     * @var string
     */
    protected $requestedrule;

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * We need to know the rules in order to make a new object.
     *
     * @param array $rules
     * @param coursework $coursework
     * @param bool|string $requestedrule
     */
    public function __construct(array $rules, coursework $coursework, $requestedrule = false) {
        $this->rules = $rules;
        $this->requestedrule = $requestedrule;
        $this->coursework = $coursework;
    }

    /**
     * Getter
     *
     * @return array
     */
    public function get_rules() {
        return $this->rules;
    }

    /**
     * @return bool|string
     */
    public function get_requested_rule() {
        return $this->requestedrule;
    }

    /**
     * Return a list of class name suffixes from the directory that has all the rules in.
     *
     * @return models\moderation_set_rule[]
     */
    public function get_potential_rules() {

        $classes = $this->get_potential_rule_class_names();

        $suffixes = array();
        foreach ($classes as $shortname => $classname) {

            $label = get_string($shortname, 'mod_coursework');
            $suffixes[$shortname] = $label;
        }

        return $suffixes;

    }

    /**
     * Return a list of class name suffixes from the directory that has all the rules in.
     *
     * @return array of classnames keyed by shortname
     */
    public function get_potential_rule_class_names() {

        global $CFG, $DB;

        $dirname = $CFG->dirroot.'/mod/coursework/classes/sample_set_rule/*.php';
        $files = glob($dirname);

        $classes = array();
        foreach ($files as $file) {

            $matches = array(); // In case we have stuff left over.
            preg_match('/([^\/]+)\.php/', $file, $matches);
            /* @var models\moderation_set_rule $fullclassname */
            $rulename = $matches[1];
            $fullclassname = '\mod_coursework\sample_set_rule\\'. $rulename;

            if (!$fullclassname::allow_multiple()) {
                $params = array(
                    'courseworkid' => $this->coursework->id,
                    'rulename' => $rulename
                );
                $alreadygotone = $DB->record_exists('coursework_mod_set_rules', $params);
                if ($alreadygotone) {
                    continue;
                }
            }

            $classes[$rulename] = $fullclassname;
        }

        return $classes;
    }

    /**
     * Simple getter for coursework instance
     *
     * @return coursework
     */
    public function get_coursework() {
        return $this->coursework;
    }

    /**
     * Getter for retrieving the current allocation strategy from the linked coursework instance.
     *
     * @return string
     */
    public function get_sampling_strategy() {
        return $this->coursework->moderatorallocationstrategy;
    }

    /**
     * This will get the form elements needed to configure a new rule for each of the rule classes
     * that can potentially be added to this coursework.
     *
     * @return string
     */
    public function get_add_rule_form_elements() {

        $html = '';

        $classes = $this->get_potential_rule_class_names();

        foreach ($classes as $shortname => $class) {
            $attributes = array(
                'class' => 'rule-config',
                'id' => 'rule-config-'.$shortname,
                'style' => 'display:none' // Always hide, so they only get revealed by clicking the radio buttons.
            );
            $html .= html_writer::start_tag('div', $attributes);
            /* @var models\moderation_set_rule $instance */
            $instance = new $class();
            $html .= $instance->get_form_elements();
            $html .= html_writer::end_tag('div');
        }

        return $html;
    }

}
