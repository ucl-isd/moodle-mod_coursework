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

use mod_coursework\ability\rule;

/**
 * Class abiity_rule_test is responsible for testing the rule class that is part of the ability system
 * @group mod_coursework
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
