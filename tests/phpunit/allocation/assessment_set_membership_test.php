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

namespace mod_coursework;

/**
 * @package    mod_coursework
 * @copyright  2025 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Unit tests for the assessment_set_membership class.
 * @group mod_coursework
 */

use core_cache\cache;
use mod_coursework\models\assessment_set_membership;

final class assessment_set_membership_test extends \advanced_testcase {
    use test_helpers\factory_mixin;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Two students but only one is double marked and should have agreed grade, extension not enabled
     */
    public function test_create_and_count(): void {
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_coursework');

        $params = [
            'grade' => 100,
            'numberofmarkers' => 2,
            'samplingenabled' => 1,
            'deadline' => time() + 86400,
        ];
        $coursework = $this->create_a_coursework($params);
        $student1 = $this->create_a_student();
        $submission1 = new \stdClass();
        $submission1->userid = $student1->id;
        $submission1->allocatableid = $student1->id;
        $submission1 = $generator->create_submission($submission1, $coursework);

        $this->assertEquals(
            0,
            assessment_set_membership::membership_count(
                $coursework->id,
                $submission1->allocatabletype,
                $submission1->allocatableid,
            )
        );
        $setmembersdata = new \stdClass();
        $setmembersdata->courseworkid = $coursework->id;
        $setmembersdata->allocatableid = $submission1->allocatableid;
        $setmembersdata->allocatabletype = 'user';
        $setmembersdata->stageidentifier = 'assessor_1';
        $membership = assessment_set_membership::create($setmembersdata);
        $this->assertEquals(
            1,
            assessment_set_membership::membership_count(
                $coursework->id,
                $submission1->allocatabletype,
                $submission1->allocatableid,
            )
        );

        // Test accessing cached count directly.
        $cache = cache::make('mod_coursework', assessment_set_membership::CACHE_AREA_MEMBER_COUNT);
        $cachekey = "{$coursework->id}_{$submission1->allocatabletype}_{$submission1->allocatableid}";
        $this->assertEquals(1, $cache->get($cachekey));

        $membership->membership_count_clear_cache_for_coursework();
        $this->assertTrue($cache->get($cachekey) === false);
        // Count of 1 is re-populated after cache just cleared.
        $this->assertEquals(
            1,
            assessment_set_membership::membership_count(
                $coursework->id,
                $submission1->allocatabletype,
                $submission1->allocatableid,
            )
        );
        $this->assertEquals(1, $cache->get($cachekey));

        $this->assertEquals(
            $membership,
            assessment_set_membership::get_from_id($membership->id)
        );

        $this->assertEquals(
            $membership,
            assessment_set_membership::find($setmembersdata)
        );

        $membership->destroy();
        $this->assertEquals(
            0,
            assessment_set_membership::membership_count(
                $coursework->id,
                $submission1->allocatabletype,
                $submission1->allocatableid,
            )
        );
    }
}
