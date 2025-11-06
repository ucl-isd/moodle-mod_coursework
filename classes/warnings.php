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

namespace mod_coursework;
use coding_exception;
use core\output\notification;
use mod_coursework\models\coursework;
use stdClass;

/**
 * Class warnings is responsible for detecting and displaying warnings to users based on
 * system conditions and configuration.
 *
 * @package mod_coursework
 */
class warnings {

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @var string[] $warnings
     */
    private $warnings = [];

    /**
     * @param coursework $coursework
     */
    public function __construct($coursework) {
        $this->coursework = $coursework;
    }

    /**
     * If the coursework is set to need three teachers, but there are only two enrolled, then we
     * need to notify the managers.
     *
     * @return string
     */
    public function not_enough_assessors() {

        $html = '';
        $firststage = $this->coursework->get_stage('assessor_1');
        $actualnumber = count($firststage->get_teachers());
        $numberofinitialassessors = $actualnumber;

        if ($numberofinitialassessors < $this->coursework->numberofmarkers) {
            // Problem!

            $strings = new stdClass();
            $strings->actual_number = $actualnumber;
            $strings->required_number = $this->coursework->numberofmarkers;

            $html .= get_string('not_enough_teachers', 'mod_coursework', $strings);
            $html = $this->alert_div($html);
        }

        return $html;

    }

    /**
     * @return bool|string
     * @throws coding_exception
     */
    public function students_in_mutiple_groups() {

        global $DB;
        $message = '';

        if ($this->coursework->grouping_id) {
            $sql = "SELECT * FROM (
                          SELECT gm.userid,
                                 count(gm.userid) as noofgroups,
                                 groupings.groupingid,
                                 u.firstname,
                                 u.lastname
                           FROM {groups} g
                     INNER JOIN {groups_members} gm
                             ON g.id = gm.groupid
                     INNER JOIN {groupings_groups} groupings
                             ON g.id=groupings.groupid
                     INNER JOIN {user} u
                             ON u.id = gm.userid
                          WHERE g.courseid = :courseid
                            AND groupings.groupingid = :groupingid
                       GROUP BY gm.userid, groupings.groupingid, u.firstname, u.lastname)a
                          WHERE noofgroups > 1";

            $params = ['courseid' => $this->coursework->get_course()->id,
                            'groupingid' => $this->coursework->grouping_id];
        } else {
            $sql = "SELECT * FROM (
                            SELECT gm.userid,
                                   count(gm.userid) as noofgroups,
                                   u.firstname,
                                   u.lastname
                              FROM {groups} g
                        INNER JOIN {groups_members} gm
                                ON gm.groupid = g.id
                        INNER JOIN {user} u
                                ON u.id = gm.userid
                             WHERE g.courseid = :courseid
                          GROUP BY gm.userid, u.firstname, u.lastname) a
                    WHERE noofgroups > 1";

            $params = ['courseid' => $this->coursework->get_course()->id];
        }

        // get all students that are in more than a one group
        $studentsinmultigroups = $DB->get_records_sql($sql, $params);

        if ($studentsinmultigroups) {
            $studentmessage = '';
            foreach ($studentsinmultigroups as $student) {

                if (!has_capability('mod/coursework:addinitialgrade', $this->coursework->get_context(), $student->userid)) {
                    $studentmessage .= '<li>' . $student->firstname . ' ' . $student->lastname;

                    // Get group ids of these students
                    if ($this->coursework->grouping_id) {

                        $sql = "SELECT g.id, g.name
                               FROM {groups} g
                         INNER JOIN {groupings_groups} groupings
                                 ON g.id = groupings.groupid
                         INNER JOIN {groups_members} gm
                                 ON gm.groupid = g.id
                              WHERE g.courseid = :courseid
                                AND gm.userid = :userid
                                AND groupings.groupingid =:grouping_id";

                        $params = [
                            'grouping_id' => $this->coursework->grouping_id,
                            'courseid' => $this->coursework->get_course()->id,
                            'userid' => $student->userid];
                    } else {

                        $sql = "SELECT g.id, g.name
                                FROM {groups} g
                          INNER JOIN {groups_members} gm
                                  ON gm.groupid = g.id
                               WHERE g.courseid = :courseid
		                         AND gm.userid = :userid";

                        $params = [
                            'courseid' => $this->coursework->get_course()->id,
                            'userid' => $student->userid];
                    }
                    $studentmessage .= '<ul>';
                    $groups = $DB->get_records_sql($sql, $params);

                    foreach ($groups as $group) {
                        $studentmessage .= '<li>';
                        $studentmessage .= $group->name;
                        $studentmessage .= '</li>';
                    }
                    $studentmessage .= '</ul></li>';
                }
            }

            if (!empty($studentmessage)) {
                $message = '<div class = "multiple_groups_warning">';
                $message .= '<p>' . get_string('studentsinmultiplegroups', 'mod_coursework') . '</p>';
                $message .= '<ul>';
                $message .= $studentmessage;
                $message .= '</ul></div>';
            }
        }

        if (!empty($message)) {
            return $this->alert_div($message);
        }

        return false;
    }

    /**
     * Warns us if percentage allocations are enabled and so not add up to 100%
     *
     * @return string
     */
    public function percentage_allocations_not_complete() {
        global $DB;

        if ($this->coursework->percentage_allocations_enabled()) {
            $sql = "SELECT COALESCE(SUM(value), 0)
                      FROM {coursework_allocation_config}
                      WHERE courseworkid = ?
                      AND allocationstrategy = 'percentages'
                      ";
            $totalpercentages = $DB->count_records_sql($sql, [$this->coursework->id]);

            if ($totalpercentages < 100) {
                return $this->alert_div(get_string('percentages_do_not_add_up', 'mod_coursework', $totalpercentages));
            }
        }

        return '';
    }

    /** Warning if allocation is selected but no assessor is chosen
     * @return string
     * @throws coding_exception
     */
    public function manual_allocation_not_completed() {
        global $DB;

        $coursework = $this->coursework;

        $courseworkstages = $coursework->numberofmarkers;
        for ($i = 1; $i <= $courseworkstages; $i++) {
             $assessor = 'assessor_'.$i;

            if ($coursework->samplingenabled == 0 || $assessor == 'assessor_1') {
                $allocatables = $coursework->get_allocatables();

                foreach ($allocatables as $allocatable) {

                    $params = ['courseworkid' => $coursework->id,
                                    'stageidentifier' => $assessor,
                                    'allocatableid' => $allocatable->id];

                    $existingallocations = $this->check_existing_allocations($params);

                    if ($existingallocations == false) {
                        return $this->alert_div(get_string('assessors_no_allocated_warning', 'mod_coursework'));
                    }
                }
            } else {

                $params = ['courseworkid' => $coursework->id];
                $sql = "SELECT id, stageidentifier, allocatableid
                         FROM {coursework_sample_set_mbrs}
                         WHERE courseworkid = :courseworkid";

                $stageidentifiers = $DB->get_records_sql($sql, $params);
                foreach ($stageidentifiers as $stageidentifier) {
                    $params = ['courseworkid' => $coursework->id,
                                    'stageidentifier' => $stageidentifier->stageidentifier,
                                    'allocatableid' => $stageidentifier->allocatableid];

                    $existingallocations = $this->check_existing_allocations($params);

                    if ($existingallocations == false) {
                        return $this->alert_div(get_string('assessors_no_allocated_warning', 'mod_coursework'));
                    }
                }
            }
        }
        return '';
    }

    /** Function to check if allocation exists
     * @param $params
     * @return array
     */
    public function check_existing_allocations($params) {
        global $DB;
        $sql = "SELECT 1
                FROM {coursework_allocation_pairs}
                WHERE courseworkid = :courseworkid
                AND stageidentifier = :stageidentifier
                AND allocatableid = :allocatableid";

        return $existingallocations = $DB->get_records_sql($sql, $params);

    }

    /**
     * Alerts teachers if there is a students who is not in any group and who will therefore
     * not be able to submit anything.
     *
     * @return string
     */
    public function student_in_no_group() {
        global $DB;

        if (!$this->coursework->is_configured_to_have_group_submissions()) {
            return '';
        }

        $studentids = array_keys(get_enrolled_users($this->coursework->get_context(), 'mod/coursework:submit'));

        if (empty($studentids)) {
            return '';
        }

        list($studentsql, $studentparams) = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED);

        if ($this->coursework->grouping_id != 0) {
            $students =
                $this->students_who_are_not_in_any_grouping_group($studentsql, $studentparams);
            if ($students) {
                $names = $this->make_list_of_student_names($students);
                return $this->alert_div(get_string('students_in_no_group_warning', 'mod_coursework').$names);
            }
        } else {

            $students = $this->students_who_are_not_in_any_group($studentsql, $studentparams);

            if ($students) {
                $names = $this->make_list_of_student_names($students);
                return $this->alert_div(get_string('students_in_no_group_warning', 'mod_coursework'). $names);
            }
        }

        return '';
    }

    /**
     * Common wrapper for alerts.
     *
     * @param string $message
     * @return string
     */
    private function alert_div($message) {
        global $OUTPUT;
        $notification = $OUTPUT->render(new notification($message, notification::NOTIFY_WARNING));
        $this->warnings[] = $notification;
        return $notification;
    }

    /**
     * @param $students
     * @return string
     */
    protected function make_list_of_student_names($students) {
        $names = '<ul>';
        foreach ($students as $student) {
            $names .= '<li>' . fullname($student) . '</li>';
        }
        $names .= '</ul>';
        return $names;
    }

    /**
     * @param $studentsql
     * @param $studentparams
     * @return mixed
     */
    private function students_who_are_not_in_any_group($studentsql, $studentparams) {
        global $DB;

        $sql = "SELECT u.*
                     FROM {user} u
                     WHERE NOT EXISTS (
                        SELECT 1
                          FROM {groups_members} m
                    INNER JOIN {groups} g
                            ON g.id = m.groupid
                         WHERE m.userid = u.id
                           AND g.courseid = :courseid
                           )
                      AND u.id $studentsql

                ";

        $params = array_merge($studentparams,
                              [
                                  'courseid' => $this->coursework->get_course()->id,
                              ]);
        $students = $DB->get_records_sql($sql, $params);
        return $students;
    }

    /**
     * @param $studentsql
     * @param $studentparams
     * @return mixed
     */
    private function students_who_are_not_in_any_grouping_group($studentsql, $studentparams) {
        global $DB;

        $sql = "SELECT u.*
                    FROM {user} u
                    WHERE NOT EXISTS (
                    SELECT 1
                      FROM {groups_members} m
                INNER JOIN {groups} g
                        ON g.id = m.groupid
                INNER JOIN {groupings_groups} gr
                        ON gr.groupid = g.id
                     WHERE m.userid = u.id
                       AND g.courseid = :courseid
                       AND gr.groupingid = :groupingid
                       )
                   AND u.id $studentsql
                ";

        $params = array_merge($studentparams,
                              [
                                  'courseid' => $this->coursework->get_course()->id,
                                  'groupingid' => $this->coursework->grouping_id,
                              ]);
        $students = $DB->get_records_sql($sql, $params);
        return $students;
    }

    /**
     * Alert markers that filter A to Z filter is on
     * @return string
     * @throws coding_exception
     */
    public function a_to_z_filter_on() {
        return $this->alert_div(get_string('namefilternon', 'mod_coursework'));
    }

    /**
     * Alert markers that there may be more submissions to grade
     * @return string
     * @throws coding_exception
     */
    public function filters_warning() {
        return $this->alert_div(get_string('filteronwarning', 'mod_coursework'));
    }

    /**
     * Alert markers there may be more submissions to grade due to group mode
     * settings.
     *
     * @return string
     */
    public function group_mode_chosen_warning(int $group): string {
        if (groups_get_activity_groupmode($this->coursework->get_course_module()) != 0 && $group != 0) {
            return $this->alert_div(get_string('groupmodechosenalert', 'mod_coursework'));
        }

        return "";
    }

    /**
     * Output buffered warnings for this instance.
     *
     * @return string[] HTML source code of each warning, for example:
     *   [
     *     '<div class="alert alert-warning">You may have ...</div>',
     *     '<div class="alert alert-warning">Some students are ...</div>',
     *   ]
     */
    public function get_warnings(): array {
        return $this->warnings;
    }
}
