<?php

/**
 * Class coursework_user_test
 * @group mod_coursework
 */
class coursework_moderation_set_membership_test extends advanced_testcase {

    public function setUp() {
        $this->resetAfterTest();
    }

    public function test_find() {
        global $DB;

        $record = new stdClass();
        $record->allocatableid = 22;
        $record->allocatabletype = 'user';
        $record->courseworkid = 44;
        $record->id = $DB->insert_record('coursework_sample_set_mbrs', $record);

        $this->assertEquals(22, \mod_coursework\models\assessment_set_membership::find($record->id)->allocatableid);
    }
}