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
 * File for a sampling rule that will include X students from between an upper and lower limit.
 *
 * @package    mod_coursework
 * @copyright  2015 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\sample_set_rule;

use html_writer;
use mod_coursework\allocation\allocatable;
use mod_coursework\models\coursework;

defined('MOODLE_INTERNAL') || die();

/**
 * This base class is extended to make specific sampling rules strategies
 */
abstract class sample_base {

    /**
     * @var string DB table this class relates to.
     */
    protected static $tablename = 'coursework_mod_set_rules';

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $courseworkid;

    protected $coursework;

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
    protected $fields = [
        'id',
        'courseworkid',
        'rulename',
        'ruleorder',
        'upperlimit',
        'lowerlimit',
        'minimum',
    ];

    public function __construct($coursework) {
        $this->coursework = $coursework;
    }

    /**
     * Returns the name of the class without the 'coursework_moderation_set_rule_' prefix.
     */
    final public function get_name() {
        $fullname = get_class($this);
        $fullnamebits = explode('\\', $fullname);
        return end($fullnamebits);
    }

    /**
     * This will take the current set and the list of students who could potentially be added
     * and adjust them. e.g. if the rule says 'include all below 40% of total grade, it will
     * calculate what 40% is, then move any below it from the $potentialstudents array to
     * the $moderationset array.
     *
     * @abstract
     * @param array $moderationset
     * @param array $potentialallocatables
     * @param stage_base $stage
     * @return mixed
     */
    abstract public function adjust_set(array &$moderationset, array &$potentialallocatables, $stage);

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
    abstract public function add_form_elements($assessornumber);

    abstract public function add_form_elements_js($assessornumber);

    abstract public function save_form_data($assessornumber=0, &$order=0);

    abstract public function adjust_sample_set($ruleid, &$manualsampleset, &$allocatables, &$autosampleset);

    /**
     *
     * @return array
     */
    protected function finalised_submissions() {
        global $DB;

        $sql = "SELECT  allocatableid
                  FROM  {coursework_submissions} s
                  JOIN  {coursework_feedbacks} f
                    ON  f.submissionid = s.id
                 WHERE  s.courseworkid = :courseworkid
                   AND  f.stage_identifier = 'final_agreed_1'";

        return $DB->get_records_sql($sql, ['courseworkid' => $this->coursework->id]);
    }

    /**
     *
     * @return array
     */
    protected function released_submissions() {
        global $DB;

        $sql = "SELECT  allocatableid
                  FROM  {coursework_submissions}
                 WHERE  courseworkid = :courseworkid
                   AND  firstpublished IS NOT NULL";

        return $DB->get_records_sql($sql, ['courseworkid' => $this->coursework->id]);
    }

}
