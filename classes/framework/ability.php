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

namespace mod_coursework\framework;
use closure;
use coding_exception;
use mod_coursework\ability\rule;
use mod_coursework\models\user;
use stdClass;

/**
 * This class provides a central point where all of the can/cannot decisions are stored.
 * It differs from the built-in Moodle permissions system (which it uses), as it encapsulates
 * logic around the business rules of the plugin. For example, if students should not be able to
 * submit because groups are enabled and they are not in one of the selected groups, then this is
 * the place where that logic should go.
 *
 * Override it with a subclass and provide an array of rules (see rules() method comments).
 * Feed in overriding environment in the constructor. You provide a closure for each rule, so
 * you can use $this to access the environment objects you store.
 *
 * $ability = new mod_whatever_ability($USER, $assignment);
 * $allowed = $ability->can('new', $submission);
 *
 * @package mod_coursework\framework
 */
abstract class ability {
    /**
     * @var ?user $user;
     */
    protected ?user $user = null;

    /**
     * The user ID.
     * @var int $userid
     */
    protected int $userid;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var rule[]
     */
    protected $rules = [];

    /**
     * We use a different instance of the class for each user. This makes it a bit cleaner.
     *
     * @param int $userid
     */
    public function __construct(int $userid) {
        $this->userid = $userid;
    }

    /**
     * Tells us if the user is allowed to do something with an optional object to test against
     * e.g. 'new', with an instance of the submission class.
     *
     * @param string $action
     * @param $thing
     * @return bool
     */
    public function can($action, $thing) {

        $this->reset_message();

        // New approach.
        // The rules explicitly allow or prevent an action and are added in order of importance.
        // The first matching one wins. If a rule does not have anything to say (e.g. allow if
        // its the right user and it's not), then we skip it.
        foreach ($this->rules as $rule) {
            if ($rule->matches($action, $thing)) {
                if ($rule->allows($thing)) {
                    return true;
                }
                if ($rule->prevents($thing)) {
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * Inverse of can().
     *
     * @param $action
     * @param $thing
     * @return bool
     */
    public function cannot($action, $thing) {
        return !$this->can($action, $thing);
    }

    /**
     * Returns the class name without the namespace, or the thing it was supplied with if it is already
     * a string.
     *
     * @param $thing
     * @return false|string
     */
    protected function get_action_type($thing) {

        if (is_string($thing)) {
            return $thing;
        }

        $classnamewithnamespace = get_class($thing);
        $bits = explode('\\', $classnamewithnamespace); // 'mod_coursework\models\submission'
        // 'submission'

        return end($bits);
    }

    /**
     * Stores the logic for whether users can do something.
     *
     * @param $action
     * @param $type
     * @return closure
     * @throws coding_exception
     */
    protected function get_rule($action, $type) {

        $rules = $this->rules();

        if (array_key_exists($type, $rules) && array_key_exists($action, $rules[$type])) {
            return $rules[$type][$action];
        }

        return false;
    }

    /**
     * @return bool|table_base|user|null
     * @throws \dml_exception
     * @throws coding_exception
     */
    protected function get_user() {
        if ($this->user === null) {
            $this->user = user::find($this->userid);
        }
        return $this->user;
    }

    /**
     * @return string
     */
    public function get_last_message() {
        return $this->message;
    }

    protected function reset_message() {
        $this->message = '';
    }

    /**
     * @param $message
     */
    protected function set_message($message) {
        $this->message = $message;
    }

    /**
     * Stores a rule for later.
     *
     * @param string $action
     * @param string $class
     * @param $function
     */
    protected function allow($action, $class, $function) {
        $rule = new rule($action, $class, $function, true);

        $this->rules[] = $rule;
    }

    /**
     * Stores a rule for later.
     *
     * @param string $action
     * @param string $class
     * @param $function
     */
    protected function prevent($action, $class, $function) {
        $rule = new rule($action, $class, $function, false);

        $this->rules[] = $rule;
    }
}
