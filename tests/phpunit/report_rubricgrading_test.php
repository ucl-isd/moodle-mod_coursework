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
 * Tests for coursework rubricgrading implementation.
 *
 * @package    mod_coursework
 * @author     Conn Warwicker <conn.warwicker@catalyst-eu.net>
 * @copyright  2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_coursework;

use advanced_testcase;
use cm_info;
use core\lang_string;
use core_reportbuilder\local\report\column;
use mod_coursework\local\report_rubricgrading\coursework;
use mod_coursework\models\feedback;
use stdClass;
use xmldb_table;

/**
 * Tests for coursework rubricgrading implementation.
 *
 * @covers \mod_coursework\local\report_rubricgrading\coursework
 */
final class report_rubricgrading_test extends advanced_testcase {
    use test_helpers\factory_mixin;

    private function check_report_rubricgrading_installed(): bool {
        $path = \core_component::get_component_directory('report_rubricgrading');
        return !is_null($path);
    }

    public function test_get_row_key_uses_userid_and_markernumber(): void {
        if (!$this->check_report_rubricgrading_installed()) {
            $this->markTestSkipped('report_rubricgrading plugin is not installed.');
        }
        $this->resetAfterTest();

        $plugin = new coursework($this->get_coursework_cm_info());

        $rowkey = $plugin->get_row_key((object)[
            'userid' => 42,
            'feedbackid' => 3,
        ]);

        $this->assertSame('42_3', $rowkey);
    }

    public function test_add_report_fields_adds_stageidentifier_field(): void {
        if (!$this->check_report_rubricgrading_installed()) {
            $this->markTestSkipped('report_rubricgrading plugin is not installed.');
        }
        $this->resetAfterTest();

        $plugin = new coursework($this->get_coursework_cm_info());
        $table = new xmldb_table('report_rubricgrading_tmp');

        $plugin->add_report_fields($table);

        $field = $table->getField('stageidentifier');
        $this->assertNotNull($field);
        $this->assertSame(XMLDB_TYPE_CHAR, $field->getType());
        $this->assertSame('100', $field->getLength());
    }

    public function test_add_report_columns_returns_stageidentifier_column(): void {
        if (!$this->check_report_rubricgrading_installed()) {
            $this->markTestSkipped('report_rubricgrading plugin is not installed.');
        }
        $this->resetAfterTest();

        $plugin = new coursework($this->get_coursework_cm_info());

        $columns = $plugin->add_report_columns();

        $this->assertCount(1, $columns);
        $this->assertSame('stageidentifier', $columns[0][0]);
        $this->assertInstanceOf(lang_string::class, $columns[0][1]);
        $this->assertSame(column::TYPE_TEXT, $columns[0][2]);
    }

    public function test_add_row_data_formats_assessor_stageidentifier(): void {
        if (!$this->check_report_rubricgrading_installed()) {
            $this->markTestSkipped('report_rubricgrading plugin is not installed.');
        }
        $this->resetAfterTest();

        $plugin = new coursework($this->get_coursework_cm_info());
        $pivotrow = new stdClass();

        $plugin->add_row_data((object)['stageidentifier' => 'assessor_2'], $pivotrow);

        $this->assertSame(get_string('markernumber', 'mod_coursework', 2), $pivotrow->stageidentifier);
    }

    public function test_add_row_data_formats_final_agreed_stageidentifier(): void {
        if (!$this->check_report_rubricgrading_installed()) {
            $this->markTestSkipped('report_rubricgrading plugin is not installed.');
        }
        $this->resetAfterTest();

        $plugin = new coursework($this->get_coursework_cm_info());
        $pivotrow = new stdClass();

        $plugin->add_row_data((object)['stageidentifier' => 'final_agreed_1'], $pivotrow);

        $this->assertSame(get_string('finalagreed', 'mod_coursework'), $pivotrow->stageidentifier);
    }

    public function test_add_row_data_preserves_other_stageidentifier(): void {
        if (!$this->check_report_rubricgrading_installed()) {
            $this->markTestSkipped('report_rubricgrading plugin is not installed.');
        }
        $this->resetAfterTest();

        $plugin = new coursework($this->get_coursework_cm_info());
        $pivotrow = new stdClass();

        $plugin->add_row_data((object)['stageidentifier' => 'moderation'], $pivotrow);

        $this->assertSame('moderation', $pivotrow->stageidentifier);
    }

    public function test_alter_maps_group_member_fields_to_student_fields(): void {
        if (!$this->check_report_rubricgrading_installed()) {
            $this->markTestSkipped('report_rubricgrading plugin is not installed.');
        }
        $this->resetAfterTest();

        $plugin = new coursework($this->get_coursework_cm_info());
        $row = (object)[
            'allocatabletype' => 'group',
            'userid' => null,
            'firstname' => null,
            'lastname' => null,
            'email' => null,
            'username' => null,
            'idnumber' => null,
            'firstnamephonetic' => null,
            'lastnamephonetic' => null,
            'middlename' => null,
            'alternatename' => null,
            'group_member_id' => 99,
            'group_member_firstname' => 'Ada',
            'group_member_lastname' => 'Lovelace',
            'group_member_email' => 'ada@example.invalid',
            'group_member_username' => 'adal',
            'group_member_idnumber' => 'S123',
            'group_member_firstnamephonetic' => 'A-da',
            'group_member_lastnamephonetic' => 'Love-lace',
            'group_member_middlename' => 'Byron',
            'group_member_alternatename' => 'Countess',
        ];

        $plugin->alter($row);

        $this->assertSame(99, $row->userid);
        $this->assertSame('Ada', $row->firstname);
        $this->assertSame('Lovelace', $row->lastname);
        $this->assertSame('ada@example.invalid', $row->email);
        $this->assertSame('adal', $row->username);
        $this->assertSame('S123', $row->idnumber);
        $this->assertSame('A-da', $row->firstnamephonetic);
        $this->assertSame('Love-lace', $row->lastnamephonetic);
        $this->assertSame('Byron', $row->middlename);
        $this->assertSame('Countess', $row->alternatename);
    }

    public function test_alter_leaves_user_rows_unchanged(): void {
        if (!$this->check_report_rubricgrading_installed()) {
            $this->markTestSkipped('report_rubricgrading plugin is not installed.');
        }
        $this->resetAfterTest();

        $plugin = new coursework($this->get_coursework_cm_info());
        $row = (object)[
            'allocatabletype' => 'user',
            'userid' => 13,
            'firstname' => 'Grace',
            'lastname' => 'Hopper',
            'email' => 'grace@example.invalid',
            'username' => 'graceh',
            'idnumber' => 'S456',
            'firstnamephonetic' => 'Grace',
            'lastnamephonetic' => 'Hopper',
            'middlename' => 'Brewster',
            'alternatename' => 'Amazing Grace',
            'group_member_id' => 99,
            'group_member_firstname' => 'Ada',
            'group_member_lastname' => 'Lovelace',
            'group_member_email' => 'ada@example.invalid',
            'group_member_username' => 'adal',
            'group_member_idnumber' => 'S123',
            'group_member_firstnamephonetic' => 'A-da',
            'group_member_lastnamephonetic' => 'Love-lace',
            'group_member_middlename' => 'Byron',
            'group_member_alternatename' => 'Countess',
        ];

        $plugin->alter($row);

        $this->assertSame(13, $row->userid);
        $this->assertSame('Grace', $row->firstname);
        $this->assertSame('Hopper', $row->lastname);
        $this->assertSame('grace@example.invalid', $row->email);
        $this->assertSame('graceh', $row->username);
        $this->assertSame('S456', $row->idnumber);
        $this->assertSame('Grace', $row->firstnamephonetic);
        $this->assertSame('Hopper', $row->lastnamephonetic);
        $this->assertSame('Brewster', $row->middlename);
        $this->assertSame('Amazing Grace', $row->alternatename);
    }

    public function test_override_column_ignores_other_columns(): void {
        if (!$this->check_report_rubricgrading_installed()) {
            $this->markTestSkipped('report_rubricgrading plugin is not installed.');
        }
        $this->resetAfterTest();

        $plugin = new coursework($this->get_coursework_cm_info());
        $column = (new column('timegraded', null, 'rubric'))->add_field('rp.timegraded');

        $this->assertFalse($plugin->override_column('timegraded', $column, 'rp'));
        $this->assertSame(['rp.timegraded AS c_timegraded'], $column->get_fields());
    }

    public function test_grade_column_shows_mark_to_the_marker_who_created_it(): void {
        if (!$this->check_report_rubricgrading_installed()) {
            $this->markTestSkipped('report_rubricgrading plugin is not installed.');
        }
        $this->resetAfterTest();
        $feedback = $this->setup_coursework_with_one_of_two_marks();

        $this->setUser($this->teacher->id);

        $this->assertSame('45', $this->format_grade_column($feedback, 45.0));
    }

    public function test_grade_column_shows_mark_to_admin(): void {
        if (!$this->check_report_rubricgrading_installed()) {
            $this->markTestSkipped('report_rubricgrading plugin is not installed.');
        }
        $this->resetAfterTest();
        $feedback = $this->setup_coursework_with_one_of_two_marks();

        $this->setAdminUser();

        $this->assertSame('45', $this->format_grade_column($feedback, 45.0));
    }

    public function test_grade_column_hides_mark_from_agreed_grade_marker_until_initial_marks_complete(): void {
        if (!$this->check_report_rubricgrading_installed()) {
            $this->markTestSkipped('report_rubricgrading plugin is not installed.');
        }
        $this->resetAfterTest();
        $feedback = $this->setup_coursework_with_one_of_two_marks();
        $this->allow_teachers_to_add_agreed_grades();

        $this->setUser($this->otherteacher->id);

        $this->assertSame(
            get_string('mark_hidden_manager', 'mod_coursework'),
            $this->format_grade_column($feedback, 45.0)
        );
    }

    public function test_crit_score_column_hides_mark_from_agreed_grade_marker_until_initial_marks_complete(): void {
        if (!$this->check_report_rubricgrading_installed()) {
            $this->markTestSkipped('report_rubricgrading plugin is not installed.');
        }
        $this->resetAfterTest();
        $feedback = $this->setup_coursework_with_one_of_two_marks();
        $this->allow_teachers_to_add_agreed_grades();

        $this->setUser($this->otherteacher->id);

        $this->assertSame(
            get_string('mark_hidden_manager', 'mod_coursework'),
            $this->format_grade_column($feedback, 3.0, 'crit1_score')
        );
    }

    public function test_grade_column_hides_mark_from_other_initial_marker(): void {
        if (!$this->check_report_rubricgrading_installed()) {
            $this->markTestSkipped('report_rubricgrading plugin is not installed.');
        }
        $this->resetAfterTest();
        $feedback = $this->setup_coursework_with_one_of_two_marks();

        $this->setUser($this->otherteacher->id);

        $this->assertSame(
            get_string('mark_hidden_teacher', 'mod_coursework'),
            $this->format_grade_column($feedback, 45.0)
        );
    }

    public function test_grade_column_shows_mark_to_agreed_grade_marker_once_initial_marks_complete(): void {
        if (!$this->check_report_rubricgrading_installed()) {
            $this->markTestSkipped('report_rubricgrading plugin is not installed.');
        }
        $this->resetAfterTest();
        $feedback = $this->setup_coursework_with_one_of_two_marks();
        $this->create_an_assessor_feedback_for_the_submission($this->otherteacher);
        $this->allow_teachers_to_add_agreed_grades();

        // A third teacher who hasn't marked, but can add the agreed grade.
        $agreedmarker = $this->getDataGenerator()->create_user();
        $this->enrol_as_teacher($agreedmarker);
        $this->setUser($agreedmarker);

        $this->assertSame('45', $this->format_grade_column($feedback, 45.0));
    }

    /**
     * Create a double-marked coursework where only the first marker ($this->teacher) has marked the submission.
     *
     * @return feedback The first marker's feedback.
     */
    private function setup_coursework_with_one_of_two_marks(): feedback {
        $this->setAdminUser();
        $this->create_a_coursework(['numberofmarkers' => 2, 'allocationenabled' => 0, 'viewinitialgradeenabled' => 0]);
        $this->create_a_student();
        $this->create_a_teacher();
        $this->create_another_teacher();
        $this->create_a_submission_for_the_student();
        $feedback = $this->create_an_assessor_feedback_for_the_submission($this->teacher);
        return feedback::get_from_id($feedback->id);
    }

    /**
     * Give the (non-editing) teacher role the capability to add agreed grades in the coursework.
     */
    private function allow_teachers_to_add_agreed_grades(): void {
        global $DB;
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability('mod/coursework:addagreedgrade', CAP_ALLOW, $roleid, $this->get_coursework()->get_context());
    }

    /**
     * Run a pivot table row through a report column as altered by the coursework plugin.
     *
     * @param feedback $feedback
     * @param float $value Column value
     * @param string $name Column name
     * @return string The formatted value
     */
    private function format_grade_column(feedback $feedback, float $value, string $name = 'grade'): string {
        $cm = get_coursemodule_from_instance('coursework', $this->get_coursework()->id);
        $plugin = new coursework(get_fast_modinfo($this->get_course()->id)->get_cm($cm->id));

        $column = (new column($name, null, 'rubric'))
            ->set_index(0)
            ->add_field("rp.{$name}");
        $this->assertTrue($plugin->override_column($name, $column, 'rp'));

        return $column->format_value([
            "c0_{$name}" => $value,
            'c0_submissionid' => $feedback->submissionid,
            'c0_feedbackid' => $feedback->id,
            'c0_realstageidentifier' => $feedback->stageidentifier,
        ]);
    }

    private function get_coursework_cm_info(): cm_info {
        $this->setAdminUser();
        $generator = $this->get_coursework_generator();
        $params['course'] = $this->get_course()->id;
        $module = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('coursework', $module->id);
        return get_fast_modinfo($this->get_course()->id)->get_cm($cm->id);
    }
}
