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

namespace mod_coursework\models;

use mod_coursework\allocation\allocatable;
use \mod_coursework\stages\base as stage_base;

/**
 * This file keeps track of upgrades to the eassessment module
 *
 * Sometimes, changes between versions involve alterations to database
 * structures and other major things that may break installations. The upgrade
 * function in this file will attempt to perform all the necessary actions to
 * upgrade your older installation to the current version. If there's something
 * it cannot do itself, it will tell you what you need to do.  The commands in
 * here will all be database-neutral, using the functions defined in DLL libraries.
 *
 * @package    mod
 * @subpackage coursework
 * @copyright  2011 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\framework\table_base;
use renderable;
use stdClass;

defined('MOODLE_INTERNAL') || die();


/**
 * Forms a base for the moderation set rules, which determine various sets of students which
 * need to be included in the set e.g. lowest 40%.
 */
abstract class moderation_set_rule extends table_base implements renderable {

    /**
     * @var string DB table this class relates to.
     */
    protected static $table_name = 'coursework_mod_set_rules';

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $courseworkid;

    /**
     * @var string end part of the class name if remove 'coursework_moderation_strategy_'.
     */
    public $rulename;

    /**
     * @var int what order will this be processed in compared to others e.g. 0 will be processed first, 1000 later.
     */
    public $ruleorder;

    /**
     * @var int Anyone with a grade/percent/rank/whatever lower than this will be included in the set.
     */
    public $upperlimit;

    /**
     * @var int Anyone with a grade/percent/rank/whatever higher than this will be included in the set.
     */
    public $lowerlimit;

    /**
     * @var int the number to aim for e.g. at least 5 from this range.
     */
    public $minimum;

    /**
     * @var array List of class properties that correspond with DB fields.
     */
    protected $fields = array(
        'id',
        'courseworkid',
        'rulename',
        'ruleorder',
        'upperlimit',
        'lowerlimit',
        'minimum'
    );


    /**
     * @param bool|int|stdClass $dbrecord
     */
    public function __construct($dbrecord = false) {
        parent::__construct($dbrecord);

        // We cannot set this from outside if we are making a new one.
        if (!isset($this->ruleorder)) {
            $this->ruleorder = $this->get_default_rule_order();
        }
    }

    /**
     * This will take the current set and the list of students who could potentially be added
     * and adjust them. e.g. if the rule says 'include all below 40% of total grade, it will
     * calculate what 40% is, then move any below it from the $potentialstudents array to
     * the $moderationset array.
     *
     * @abstract
     * @param array $moderationset
     * @param array $potential_allocatables
     * @param stage_base $stage
     * @return mixed
     */
    abstract public function adjust_set(array &$moderationset, array &$potential_allocatables, $stage);

    /**
     * Returns the name of the class without the 'coursework_moderation_set_rule_' prefix.
     */
    final public function get_name() {
        $fullname = get_class($this);
        $fullnamebits = explode('\\', $fullname);
        return end($fullnamebits);
    }

    /**
     * Returns a human readable range e.g. 0 - 40%
     * @return string
     */
    abstract public function get_numeric_boundaries();

    /**
     * Tells us where this ought to be in relation to other rules. The one for percent of total must happen last,
     * so this is how we enforce it.
     *
     * @abstract
     * @return mixed
     */
    abstract public function get_default_rule_order();

    /**
     * Some rules make no sense when there are multiple e.g. 'include at least x% of the total number'.
     *
     * @static
     * @abstract
     * @return mixed
     */
    public static function allow_multiple() {
        return true;
    }

    /**
     * Each rule may have different form elements that we need to add in order for a new one to be
     * @abstract
     * @return mixed
     */
    abstract public function get_form_elements();

    /**
     * Validates and saves data from the form elements defined by {@link get_form_elements()}.
     *
     * @abstract
     * @return mixed
     */
    public function save_form_data() {

        $upperlimit = optional_param('rule_'.self::get_name().'_upperlimit', '', PARAM_INT);
        $lowerlimit = optional_param('rule_'.self::get_name().'_lowerlimit', '', PARAM_INT);
        $minimum = optional_param('rule_'.self::get_name().'_minimum', '', PARAM_INT);

        // Validate.
        // Make sure we get a percentage as a whole number.
        $cleanupperlimit = round($upperlimit);
        $cleanupperlimit = min($cleanupperlimit, 100);
        $cleanupperlimit = max($cleanupperlimit, 0);
        $cleanlowerlimit = round($lowerlimit);
        $cleanlowerlimit = min($cleanlowerlimit, 100);
        $cleanlowerlimit = max($cleanlowerlimit, 0);
        // For percentage should be 100 or less. May need to be more otherwise.
        $cleanminimum = min($minimum, 100);

        // TODO error message for duff data.
        if ($upperlimit !== '' && $cleanupperlimit !== 0 &&
            $lowerlimit !== '' && $cleanlowerlimit !== 0 &&
            $upperlimit > $lowerlimit) {

            $this->rulename = self::get_name();
            $this->upperlimit = $cleanupperlimit;
            $this->lowerlimit = $cleanlowerlimit;
            $this->ruleorder = $this->get_default_rule_order();
            $this->minimum = $cleanminimum;
            $this->save();
        }
    }

    /**
     * @return mixed|\mod_coursework_coursework
     */
    protected function get_coursework() {
        if (!isset($this->coursework)) {
            $this->coursework = coursework::find($this->courseworkid);
        }
        return $this->coursework;
    }

    /**
     * @param allocatable $allocatable
     * @return bool|int
     */
    protected function get_allocatable_final_grade($allocatable) {
        $stage = $this->get_coursework()->get_final_agreed_marking_stage();
        $feedback = $stage->get_feedback_for_allocatable($allocatable);
        if ($feedback) {
            return $feedback->get_grade();
        }
        return false;
    }

    /**
     * @param allocatable $allocatable
     * @return bool
     */
    protected function allocatable_is_not_yet_graded($allocatable) {
        $grade = $this->get_allocatable_final_grade($allocatable);
        return $grade === false || $grade === null;
    }
}
