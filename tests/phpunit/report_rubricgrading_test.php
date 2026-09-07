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
use ReflectionMethod;
use stdClass;
use xmldb_table;

final class report_rubricgrading_test extends advanced_testcase {
    use test_helpers\factory_mixin;

    public function test_get_row_key_uses_userid_and_markernumber(): void {
        $this->resetAfterTest();

        $plugin = new coursework($this->get_coursework_cm_info());

        $rowkey = $plugin->get_row_key((object)[
            'userid' => 42,
            'markernumber' => 3,
        ]);

        $this->assertSame('42_3', $rowkey);
    }

    public function test_add_report_fields_adds_stageidentifier_field(): void {
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
        $this->resetAfterTest();

        $plugin = new coursework($this->get_coursework_cm_info());

        $columns = $plugin->add_report_columns();

        $this->assertCount(1, $columns);
        $this->assertSame('stageidentifier', $columns[0][0]);
        $this->assertInstanceOf(lang_string::class, $columns[0][1]);
        $this->assertSame(column::TYPE_TEXT, $columns[0][2]);
    }

    public function test_add_row_data_formats_assessor_stageidentifier(): void {
        $this->resetAfterTest();

        $plugin = new coursework($this->get_coursework_cm_info());
        $pivotrow = new stdClass();

        $plugin->add_row_data((object)['stageidentifier' => 'assessor_2'], $pivotrow);

        $this->assertSame(get_string('markernumber', 'mod_coursework', 2), $pivotrow->stageidentifier);
    }

    public function test_add_row_data_formats_final_agreed_stageidentifier(): void {
        $this->resetAfterTest();

        $plugin = new coursework($this->get_coursework_cm_info());
        $pivotrow = new stdClass();

        $plugin->add_row_data((object)['stageidentifier' => 'final_agreed_1'], $pivotrow);

        $this->assertSame(get_string('finalagreed', 'mod_coursework'), $pivotrow->stageidentifier);
    }

    public function test_add_row_data_preserves_other_stageidentifier(): void {
        $this->resetAfterTest();

        $plugin = new coursework($this->get_coursework_cm_info());
        $pivotrow = new stdClass();

        $plugin->add_row_data((object)['stageidentifier' => 'moderation'], $pivotrow);

        $this->assertSame('moderation', $pivotrow->stageidentifier);
    }

    public function test_fiddle_maps_group_member_fields_to_student_fields(): void {
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

        $plugin->fiddle($row);

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

    public function test_fiddle_leaves_user_rows_unchanged(): void {
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

        $plugin->fiddle($row);

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

    private function get_coursework_cm_info(): cm_info {
        $this->setAdminUser();
        $this->resetAfterTest(false);
        $generator = $this->get_coursework_generator();
        $params['course'] = $this->get_course()->id;
        $module = $generator->create_instance($params);
        $cm = get_coursemodule_from_instance('coursework', $module->id);
        return get_fast_modinfo($this->get_course()->id)->get_cm($cm->id);
    }
}
