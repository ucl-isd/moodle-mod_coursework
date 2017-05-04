<?php

use mod_coursework\ability\rule;

/**
 * Class abiity_rule_test is responsible for testing the rule class that is part of the ability system
 *
 */
class abiity_rule_test extends basic_testcase {

    // Test what happens when we have a rule that matches and returns true

    public function test_allows_when_allowed_and_rule_returns_true() {
        $coursework = new \mod_coursework\models\coursework();
        $rule_function = function ($object) {
            return true;
        };
        $rule = new rule('set on fire', 'mod_coursework\models\coursework', $rule_function, true );
        $this->assertTrue($rule->allows($coursework));
    }

    public function test_allows_when_prevent_and_rule_returns_true() {
        $coursework = new \mod_coursework\models\coursework();
        $rule_function = function ($object) {
            return true;
        };
        $rule = new rule('set on fire', 'mod_coursework\models\coursework', $rule_function, false);
        $this->assertFalse($rule->allows($coursework));
    }

    public function test_prevents_when_allowed_and_rule_returns_true() {
        $coursework = new \mod_coursework\models\coursework();
        $rule_function = function ($object) {
            return true;
        };
        $rule = new rule('set on fire', 'mod_coursework\models\coursework', $rule_function, true);
        $this->assertFalse($rule->prevents($coursework));
    }

    public function test_prevents_when_prevent_and_rule_returns_true() {
        $coursework = new \mod_coursework\models\coursework();
        $rule_function = function ($object) {
            return true;
        };
        $rule = new rule('set on fire', 'mod_coursework\models\coursework', $rule_function, false);
        $this->assertTrue($rule->prevents($coursework));
    }

    // Test what happens when we have a rule that matches and returns false

    public function test_allows_when_allowed_and_rule_returns_false() {
        $coursework = new \mod_coursework\models\coursework();
        $rule_function = function ($object) {
            return false;
        };
        $rule = new rule('set on fire', 'mod_coursework\models\coursework', $rule_function, true);
        $this->assertFalse($rule->allows($coursework));
    }

    public function test_allows_when_prevent_and_rule_returns_false() {
        $coursework = new \mod_coursework\models\coursework();
        $rule_function = function ($object) {
            return false;
        };
        $rule = new rule('set on fire', 'mod_coursework\models\coursework', $rule_function, false);
        $this->assertFalse($rule->allows($coursework));
    }

    public function test_prevents_when_allowed_and_rule_returns_false() {
        $coursework = new \mod_coursework\models\coursework();
        $rule_function = function ($object) {
            return false;
        };
        $rule = new rule('set on fire', 'mod_coursework\models\coursework', $rule_function, true);
        $this->assertFalse($rule->prevents($coursework));
    }

    public function test_prevents_when_prevent_and_rule_returns_false() {
        $coursework = new \mod_coursework\models\coursework();
        $rule_function = function ($object) {
            return false;
        };
        $rule = new rule('set on fire', 'mod_coursework\models\coursework', $rule_function, false);
        $this->assertFalse($rule->prevents($coursework));
    }

    
} 