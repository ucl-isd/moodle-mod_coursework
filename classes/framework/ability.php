<?php

namespace mod_coursework\framework;
use mod_coursework\ability\rule;

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
     * @var \stdClass;
     */
    protected $user;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var rule[]
     */
    protected $rules = array();

    /**
     * We use a different instance of the class for each user. This makes it a bit cleaner.
     *
     * @param $user
     */
    public function __construct($user) {
        $this->user = $user;
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
     * @return mixed
     */
    protected function get_action_type($thing) {

        if (is_string($thing)) {
            return $thing;
        }

        $class_name_with_namespace = get_class($thing);
        $bits = explode('\\', $class_name_with_namespace); // 'mod_coursework\models\submission'
        $classname = end($bits); // 'submission'

        // For non-standard things like decorated classes:
        $map = $this->classname_mappings();
        if (array_key_exists($classname, $map)) {
            return $map[$classname];
        }

        return $classname;
    }

    /**
     * Stores the logic for whether users can do something.
     *
     * @param $action
     * @param $type
     * @return \closure
     * @throws \coding_exception
     */
    protected function get_rule($action, $type) {

        $rules = $this->rules();

        if (array_key_exists($type, $rules) && array_key_exists($action, $rules[$type])) {
            return $rules[$type][$action];
        }

        return false;
    }

    /**
     * Override to map non-standard things like decorated classes. 'decorated_class_name' => 'normal_class_name'
     *
     * @return array
     */
    protected function classname_mappings() {
        return array();
    }

    /**
     * @return table_base|\stdClass
     */
    protected function get_user() {
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