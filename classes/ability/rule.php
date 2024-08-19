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

namespace mod_coursework\ability;

/**
 * Class rule is responsible for representing one rule that has been defined in the ability class.
 * It holds the code fragment that we are evaluating and also other data about it like whether it
 * should allow or prevent.
 *
 */
class rule {

    /**
     * @var string
     */
    protected $action;

    /**
     * @var string
     */
    protected $class_name;

    /**
     * @var callable
     */
    protected $rule_function;

    /**
     * @var bool
     */
    protected $allow;

    /**
     * @param string $action
     * @param string $class_name
     * @param $rule_function
     * @param bool $allow
     */
    public function __construct($action, $class_name, $rule_function, $allow = true) {
        $this->action = $action;
        $this->class_name = $class_name;
        $this->rule_function = $rule_function;
        $this->allow = $allow;
    }

    /**
     * Tells us whether this rule is a match for this class and action
     *
     * @param string $action
     * @param mixed $object
     * @return bool
     */
    public function matches($action, $object) {
        return $this->action_matches($action) && $this->class_matches($object);
    }

    /**
     * Tells us if this rule explicitly allows the action for this object.
     *
     * @param $object
     * @return bool
     */
    public function allows($object) {
        $rule = $this->rule_function;
        return $rule($object) && $this->allow;
    }

    /**
     * Tells us if this rule explicitly prevent the action for this object.
     *
     * @param $object
     * @return bool
     */
    public function prevents($object) {
        $rule = $this->rule_function;
        return $rule($object) && !$this->allow;
    }

    /**
     * @param $action
     * @return bool
     */
    protected function action_matches($action) {
        return $action == $this->action;
    }

    /**
     * @param $object
     * @return bool
     */
    protected function class_matches($object) {
        if (get_class($object) == $this->class_name) {
            return true;
        }
        if (get_parent_class($object) == $this->class_name) {
            return true;
        }
        return false;
    }
}
