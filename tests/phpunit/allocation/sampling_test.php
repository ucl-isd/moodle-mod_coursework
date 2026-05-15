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
 * Unit tests for creating a sample set and its allocation
 *
 * @package    mod_coursework
 * @copyright  2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework;

use mod_coursework\models\coursework;
use mod_coursework\models\submission;
use function PHPUnit\Framework\assertEquals;

/**
 * Class test_auto_allocator
 * @property coursework coursework
 * @property \stdClass student
 * @property \stdClass teacher
 * @property \stdClass course
 * @group mod_coursework
 */
final class sampling_test extends \advanced_testcase {
    use test_helpers\factory_mixin;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();

        $this->course = $generator->create_course();

        // NB: deadline in the future to test draft submissions, as draft submissions are auto-finalised past deadline.
        $params = [
            'grade' => 100,
            'numberofmarkers' => 3,
            'samplingenabled' => 1,
            'deadline' => time() + 86400,
            'allocationenabled' => 1,
            'assessorallocationstrategy' => 'equal',
        ];

        $this->coursework = $this->create_a_coursework($params);

        $this->create_a_student();
        $this->create_another_student();
        $this->create_a_teacher();
        $this->create_another_teacher();
    }

    /**
     *
     * @covers \mod_coursework\sample_set_rule\range_sample_type::adjust_sample_set
     */
    public function test_sampling_not_applied_to_draft_feedback(): void {
        global $DB;

        $allocationparams = [
            'courseworkid' => $this->coursework->id,
            'assessorid' => $this->teacher->id,
            'ismanual' => 0,
            'moderator' => 0,
            'timelocked' => 0,
            'stageidentifier' => 'assessor_1',
            'allocatableid' => $this->student->id,
            'allocatableuser' => 0,
            'allocatablegroup' => 0,
            'allocatabletype' => 'user',
        ];
        $allocation = \mod_coursework\models\allocation::build($allocationparams);
        $allocation->save();

        $submission = new \mod_coursework\models\submission();
        $submission->courseworkid = $this->coursework->id;
        $submission->allocatableid = $this->student->id;
        $submission->allocatabletype = 'user';
        $submission->finalisedstatus = submission::FINALISED_STATUS_NOT_FINALISED;
        $submission->save();

        $this->assertEquals(\mod_coursework\models\submission::FINALISED_STATUS_NOT_FINALISED, $submission->finalisedstatus);
        $this->assertFalse($submission->is_finalised());

        $feedbackparams = [
            'submissionid' => $submission->id,
            'assessorid' => $this->teacher->id,
            'stageidentifier' => 'assessor_1',
            'grade' => 12,
            'isfinalgrade' => 0,
            'ismoderation' => 0,
            'markernumber' => 1,
            'isfinalised' => 0,
        ];
        $feedback = \mod_coursework\models\feedback::create($feedbackparams);
        $this->assertTrue(\mod_coursework\models\allocation::exists($allocationparams));

        $allocator = new \mod_coursework\allocation\auto_allocator($this->coursework);
        $allocator->process_allocations();
        $this->assertTrue(\mod_coursework\models\allocation::exists($allocationparams));

        $this->create_sample_ruleset((object) [
            'type' => 'range_sample_type',
            'stage' => 2,
            'rules' => [
                0 => (object)['type' => 'grade', 'lowerlimit' => 0, 'upperlimit' => 45],
                1 => (object)['type' => 'grade', 'lowerlimit' => 49, 'upperlimit' => 49],
                2 => (object)['type' => 'grade', 'lowerlimit' => 59, 'upperlimit' => 59],
            ],
        ]);

        $rangesampleplugin = $DB->get_record('coursework_sample_set_plugin', ['rulename' => 'range_sample_type']);

        $expectedsamplesetrules = [
            0 => (object)[
                'courseworkid' => $this->coursework->id,
                'samplesetpluginid' => $rangesampleplugin->id,
                'ruleorder' => 0,
                'upperlimit' => 45,
                'lowerlimit' => 0,
                'ruletype' => 'grade',
                'stageidentifier' => 'assessor_2',
            ],
            1 => (object)[
                'courseworkid' => $this->coursework->id,
                'samplesetpluginid' => $rangesampleplugin->id,
                'ruleorder' => 1,
                'upperlimit' => 49,
                'lowerlimit' => 49,
                'ruletype' => 'grade',
                'stageidentifier' => 'assessor_2',
            ],
            2 => (object)[
                'courseworkid' => $this->coursework->id,
                'samplesetpluginid' => $rangesampleplugin->id,
                'ruleorder' => 2,
                'upperlimit' => 59,
                'lowerlimit' => 59,
                'ruletype' => 'grade',
                'stageidentifier' => 'assessor_2',
            ],
        ];
        $sql = "SELECT * FROM {coursework_sample_set_rules} WHERE courseworkid = :courseworkid ORDER BY ruleorder ASC";
        $params = ['courseworkid' => $this->coursework->id];
        $rules = $DB->get_records_sql($sql, $params);

        $this->assertEquals(3, count($rules));
        foreach ($rules as $rule) {
            $expectedsamplesetrule = $expectedsamplesetrules[$rule->ruleorder];
            $this->assertEquals($expectedsamplesetrule->courseworkid, $rule->courseworkid);
            $this->assertEquals($expectedsamplesetrule->samplesetpluginid, $rule->samplesetpluginid);
            $this->assertEquals($expectedsamplesetrule->ruleorder, $rule->ruleorder);
            $this->assertEquals($expectedsamplesetrule->upperlimit, $rule->upperlimit);
            $this->assertEquals($expectedsamplesetrule->lowerlimit, $rule->lowerlimit);
            $this->assertEquals($expectedsamplesetrule->ruletype, $rule->ruletype);
            $this->assertEquals($expectedsamplesetrule->stageidentifier, $rule->stageidentifier);
        }

        $this->assertTrue($this->coursework->has_automatic_sampling_at_stage('assessor_2'));
        $this->coursework->save();

        // Stage 2. Draft allocation should not work.
        $manager = $this->coursework->get_allocation_manager();
        $manager->auto_generate_sample_set();
        $warnings = new warnings($this->coursework);
        $this->assertEmpty($warnings->get_warnings());

        $sql = "SELECT * FROM {coursework_sample_set_mbrs}
                WHERE courseworkid = :courseworkid";
        $params = ['courseworkid' => $this->coursework->id];
        $samplemembership = $DB->get_records_sql($sql, $params);
        $this->assertEmpty($samplemembership);

        $submission->finalisedstatus = submission::FINALISED_STATUS_FINALISED;
        $submission->save();
        $this->assertEquals(submission::FINALISED_STATUS_FINALISED, $submission->finalisedstatus);
        $this->assertTrue($submission->is_finalised());

        // Stage 2. Draft allocation should work.
        $manager = $this->coursework->get_allocation_manager();
        $manager->auto_generate_sample_set();
        $warnings = new warnings($this->coursework);
        $this->assertEmpty($warnings->get_warnings());

        $sql = "SELECT * FROM {coursework_sample_set_mbrs}
                WHERE courseworkid = :courseworkid";
        $params = ['courseworkid' => $this->coursework->id];
        $samplemembership = $DB->get_records_sql($sql, $params);
        $this->assertNotEmpty($samplemembership);
    }

    /**
     *
     * @covers \mod_coursework\sample_set_rule\range_sample_type::adjust_sample_set
     */
    public function test_sampling_then_equal_auto_allocation(): void {
        global $DB;

        $allocationparams = [
            'courseworkid' => $this->coursework->id,
            'assessorid' => $this->teacher->id,
            'ismanual' => 0,
            'moderator' => 0,
            'timelocked' => 0,
            'stageidentifier' => 'assessor_1',
            'allocatableid' => $this->student->id,
            'allocatableuser' => 0,
            'allocatablegroup' => 0,
            'allocatabletype' => 'user',
        ];
        $allocation = \mod_coursework\models\allocation::build($allocationparams);
        $allocation->save();

        $submission = new \mod_coursework\models\submission();
        $submission->courseworkid = $this->coursework->id;
        $submission->allocatableid = $this->student->id;
        $submission->allocatabletype = 'user';
        $submission->save();

        $this->assertEquals(\mod_coursework\models\submission::FINALISED_STATUS_NOT_FINALISED, $submission->finalisedstatus);
        $this->assertFalse($submission->is_finalised());

        $feedbackparams = [
            'submissionid' => $submission->id,
            'assessorid' => $this->teacher->id,
            'stageidentifier' => 'assessor_1',
            'grade' => 12,
            'isfinalgrade' => 0,
            'ismoderation' => 0,
            'markernumber' => 1,
            'isfinalised' => 1,
        ];
        $feedback = \mod_coursework\models\feedback::create($feedbackparams);
        $this->assertTrue(\mod_coursework\models\allocation::exists($allocationparams));

        $allocator = new \mod_coursework\allocation\auto_allocator($this->coursework);
        $allocator->process_allocations();

        $this->assertTrue(\mod_coursework\models\allocation::exists($allocationparams));

        $this->create_sample_ruleset((object) [
            'type' => 'range_sample_type',
            'stage' => 2,
            'rules' => [
                0 => (object)['type' => 'grade', 'lowerlimit' => 0, 'upperlimit' => 45],
                1 => (object)['type' => 'grade', 'lowerlimit' => 49, 'upperlimit' => 49],
                2 => (object)['type' => 'grade', 'lowerlimit' => 59, 'upperlimit' => 59],
            ],
        ]);
        $this->coursework->save();

        $manager = $this->coursework->get_allocation_manager();
        $manager->auto_generate_sample_set();

        $warnings = new warnings($this->coursework);

        $this->assertEmpty($warnings->get_warnings());
        $sql = "SELECT * FROM {coursework_sample_set_mbrs}
                WHERE courseworkid = :courseworkid";
        $params = ['courseworkid' => $this->coursework->id];
        $samplemembership = $DB->get_records_sql($sql, $params);
        $this->assertEmpty($samplemembership);

        $allocator = new \mod_coursework\allocation\auto_allocator($this->coursework);
        $allocator->process_allocations();

        $submission = $this->coursework->get_user_submission($this->student);
        $allocatableid = $submission->get_allocatable()->id();
        $allocatabletype = $submission->get_allocatable()->type();

        $this->coursework->get_allocatable_submission($submission->get_allocatable());
    }

    /**
     *
     * Given a collection of sample set rules, this function creates coursework_sample_set_rules record =s for phpunit testing
     *
     * @param $ruleset
     * @return void
     * @throws \dml_exception
     */
    private function create_sample_ruleset($ruleset) {
        global $DB;

        $sampleplugin = $DB->get_record('coursework_sample_set_plugin', ['rulename' => $ruleset->type]);

        foreach ($ruleset->rules as $ruleorder => $rule) {
            $record = new \stdClass();
            $record->courseworkid = $this->coursework->id;
            $record->samplesetpluginid = $sampleplugin->id;
            $record->ruleorder = $ruleorder;
            $record->upperlimit = $rule->upperlimit;
            $record->lowerlimit = $rule->lowerlimit;
            $record->ruletype = $rule->type;
            $record->stageidentifier = 'assessor_' . $ruleset->stage;
            $DB->insert_record("coursework_sample_set_rules", $record);
        }
    }
}
