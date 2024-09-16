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

/**
 * Class coursework_user_test
 * @group mod_coursework
 */
final class coursework_moderation_set_membership_test extends advanced_testcase {

    public function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_find(): void {
        global $DB;

        $record = new stdClass();
        $record->allocatableid = 22;
        $record->allocatabletype = 'user';
        $record->courseworkid = 44;
        $record->id = $DB->insert_record('coursework_sample_set_mbrs', $record);

        $this->assertEquals(22, \mod_coursework\models\assessment_set_membership::find($record->id)->allocatableid);
    }
}
