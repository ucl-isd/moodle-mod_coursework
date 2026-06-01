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

use mod_coursework\models\submission;

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
     * Some rules make no sense when there are multiple e.g. 'include at least x% of the total number'.
     *
     * @return true
     */
    public static function allow_multiple() {
        return true;
    }

    /**
     * Generate form elements and return as html string
     * Each rule may have different form elements that we need to add in order for a new one to be created
     *
     * @param int $assessornumber the stage identifier numeric component e.g. assessor_x where x = 1
     * @return string the html of the added elements
     */
    abstract public function add_form_elements(int $assessornumber = 0): string;

    /**
     * Generate form elements and return as js string.
     *
     * @param int $assessornumber the stage identifier numeric component e.g. assessor_x where x = 1
     * @return string the html of the added elements
     */
    abstract public function add_form_elements_js(int $assessornumber = 0): string;

    /**
     * Saves the form data
     *
     * @param int $assessornumber the stage identifier numeric component e.g. assessor_x where x = 1
     * @param int $order value to store on coursework_sample_set_rules.ruleorder. Increments inside function.
     * @return void
     */
    abstract public function save_form_data(int $assessornumber = 0, int &$order = 0): void;

    /**
     * Given a marking stage number and three arrays passed by reference, the autosampleset array is modified based on conditions
     *
     * The autosampleset is modified so that assessment_set_membership {coursework_sample_set_mbrs} records
     * can be created by the calling function
     *
     * @param int $stagenumber the numeric marking stage
     * @param allocatable[] $allocatables an array implementing the allocatable interface.
     * @param \stdClass[] $manualsampleset
     * @param array $autosampleset
     * @return void
     */
    abstract public function adjust_sample_set(
        int $stagenumber,
        array &$allocatables,
        array &$manualsampleset,
        array &$autosampleset
    ): void;

    /**
     * Retrieves the finalised submissions based on provided $stage
     *
     * @param string $stage the stage identifier
     * @return array of db objects (submission x feedback)
     */
    protected function finalised_submissions(string $stage): array {
        global $DB;

        $sql = "SELECT allocatableid
                  FROM {coursework_submissions} s
                  JOIN {coursework_feedbacks} f
                    ON f.submissionid = s.id AND f.stageidentifier = :stage
                 WHERE s.courseworkid = :courseworkid
                   AND s.finalisedstatus = :finalised";

        $params = [
            'stage' => $stage,
            'courseworkid' => $this->coursework->id,
            'finalised' => submission::FINALISED_STATUS_FINALISED,
        ];
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * @return array
     * @throws \dml_exception
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
