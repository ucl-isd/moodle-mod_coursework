<?php

namespace mod_coursework\test_helpers;

use coding_exception;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\models\group;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use phpunit_util;
use stdClass;
use testing_util;

/**
 * Class mod_coursework_factory_mixin
 *
 * @property mixed $teacher
 */
trait factory_mixin {

    /**
     * @var stdClass
     */
    protected $course;

    /**
     * @var submission
     */
    protected $submission;

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @var user
     */
    protected $other_student;

    /**
     * @var user
     */
    protected $student;

    /**
     * @var user
     */
    protected $teacher;

    /**
     * @var user
     */
    protected $other_teacher;

    /**
     * @return user
     */
    protected function create_a_student() {
        $generator = \testing_util::get_data_generator();

        $user = new \stdClass();
        $user->firstname = 'Student';
        $raw_student = $generator->create_user($user);
        $this->student = user::find($raw_student);
        $this->enrol_as_student($this->student);

        return $this->student;
    }

    /**
     * @return stdClass
     */
    protected function create_another_student() {
        $generator = testing_util::get_data_generator();

        $user = new stdClass();
        $user->firstname = 'Other Student';
        $this->other_student = user::find($generator->create_user($user));
        $this->enrol_as_student($this->other_student);

        return $this->other_student;
    }

    /**
     * @return user
     */
    protected function create_a_teacher() {
        $generator = testing_util::get_data_generator();

        $user = new stdClass();
        $user->firstname = 'Teacher';
        $db_record = $generator->create_user($user);
        $this->teacher = user::find($db_record);
        $this->enrol_as_teacher($this->teacher);

        return $this->teacher;
    }

    /**
     * @return stdClass
     */
    protected function create_another_teacher() {
        $generator = testing_util::get_data_generator();

        $user = new stdClass();
        $user->firstname = 'Other Teacher';
        $this->other_teacher = user::find($generator->create_user($user));
        $this->enrol_as_teacher($this->other_teacher);

        return $this->other_teacher;
    }

    /**
     */
    protected function create_a_group() {
        $generator = testing_util::get_data_generator();

        $group = new stdClass();
        $group->name = 'My group';
        $group->courseid = $this->get_course()->id;
        $this->group = group::find($generator->create_group($group));

        return $this->group;
    }

    /**
     */
    protected function create_a_grouping_and_add_the_group_to_it() {
        $generator = testing_util::get_data_generator();

        $grouping = new stdClass();
        $grouping->name = 'My group';
        $grouping->courseid = $this->get_course()->id;
        $this->grouping = $generator->create_grouping($grouping);

        $connector = new stdClass();
        $connector->groupingid = $this->grouping->id;
        $connector->groupid = $this->get_group()->id;
        $generator->create_grouping_group($connector);
    }

    /**
     */
    protected function add_student_to_the_group() {
        $generator = testing_util::get_data_generator();

        $membership = new stdClass();
        $membership->groupid = $this->get_group()->id;
        $membership->userid = $this->get_student()->id;
        $generator->create_group_member($membership);
    }

    /**
     */
    protected function add_the_other_student_to_the_group() {
        $generator = testing_util::get_data_generator();

        $membership = new stdClass();
        $membership->groupid = $this->get_group()->id;
        $membership->userid = $this->other_student->id;
        $generator->create_group_member($membership);
    }

    /**
     * @return \mod_coursework_generator
     */
    protected function get_coursework_generator() {
        return $this->getDataGenerator()->get_plugin_generator('mod_coursework');
    }

    /**
     * Makes a coursework and saves it as $this->coursework
     *
     * @throws coding_exception
     * @return coursework
     */
    protected function create_a_coursework() {
        $generator = $this->get_coursework_generator();
        $this->coursework = $generator->create_instance(array('course' => $this->get_course()->id));
        return $this->coursework;
    }

    /**
     * Makes a course and saves it as $this->course
     *
     * @throws coding_exception
     */
    protected function create_a_course() {
        $generator = phpunit_util::get_data_generator();
        $this->course = $generator->create_course();
    }

    /**
     * @return submission|stdClass
     * @throws coding_exception
     */
    public function create_a_submission_for_the_student() {
        $generator = $this->get_coursework_generator();
        $submission = new stdClass();
        $submission->courseworkid = $this->get_coursework()->id;
        $submission->userid = $this->get_student()->id;
        $submission->allocatableid = $this->get_student()->id;
        $submission->allocatabletype = 'user';
        $this->submission = $generator->create_submission($submission, $this->coursework);
        return $this->submission;
    }

    public function create_a_group_submission_for_the_student() {
        $generator = $this->get_coursework_generator();
        $submission = new stdClass();
        $submission->courseworkid = $this->get_coursework()->id;
        $submission->allocatableid = $this->get_group()->id;
        $submission->allocatabletype = 'group';
        $submission->lastupdatedby = $this->get_student()->id;
        $submission->createdby = $this->get_student()->id;
        $this->submission = $generator->create_submission($submission, $this->coursework);
    }

    /**
     * @return feedback
     * @throws coding_exception
     */
    public function create_a_final_feedback_for_the_submisison() {
        $generator = $this->get_coursework_generator();
        $feedback = new stdClass();
        $feedback->submissionid = $this->get_submission()->id;
        $feedback->assessorid = 11; // Dummy
        $feedback->stage_identifier = 'final_agreed_1';
        $feedback->grade = 45;
        $this->final_feedback = $generator->create_feedback($feedback);

        return $this->final_feedback;
    }

    /**
     * @param user $assessor
     * @return stdClass
     * @throws \coding_exception
     */
    public function create_an_assessor_feedback_for_the_submisison($assessor) {
        $count = $this->number_of_assessor_feedbacks();

        $generator = $this->get_coursework_generator();
        $feedback = new stdClass();
        $feedback->submissionid = $this->get_submission()->id;
        $feedback->assessorid = $assessor->id;
        $feedback->stage_identifier = 'assessor_'.($count+1);
        $feedback->grade = 45;
        return $generator->create_feedback($feedback);
    }

    /**
     * @return mixed
     */
    private function get_teacher_role_id() {
        global $DB;

        return $DB->get_field('role', 'id', array('shortname' => 'teacher'));
    }

    /**
     * @return mixed
     */
    private function get_student_role_id() {
        global $DB;

        return $DB->get_field('role', 'id', array('shortname' => 'student'));
    }

    /**
     * @return mixed
     */
    private function get_manager_role_id() {
        global $DB;

        return $DB->get_field('role', 'id', array('shortname' => 'manager'));
    }

    /**
     * @param $user
     */
    protected function enrol_as_teacher($user) {
        $generator = testing_util::get_data_generator();

        $generator->enrol_user($user->id, $this->get_course()->id, $this->get_teacher_role_id());
    }

    /**
     * @param $user
     */
    protected function enrol_as_student($user) {
        $generator = testing_util::get_data_generator();

        $generator->enrol_user($user->id, $this->get_course()->id, $this->get_student_role_id());
    }

    /**
     * @param $user
     */
    protected function enrol_as_manager($user) {
        $generator = testing_util::get_data_generator();

        $generator->enrol_user($user->id, $this->get_course()->id, $this->get_manager_role_id());
    }

    protected function enrol_the_other_teacher_as_a_manager() {
        $this->enrol_as_manager($this->other_teacher);
    }

    /**
     * @return int
     */
    protected function number_of_assessor_feedbacks() {
        $count = 0;
        for ($i = 1; $i <= 3; $i++) {
            $params = array('submissionid' => $this->get_submission()->id,
                            'stage_identifier' => 'assessor_' . $i);
            if (feedback::exists($params)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @return stdClass
     * @throws coding_exception
     */
    public function create_a_grouping() {
        $generator = testing_util::get_data_generator();
        $grouping = new stdClass();
        $grouping->courseid = $this->get_course()->id;
        return $generator->create_grouping($grouping);
    }

    /**
     * @return stdClass
     */
    protected function get_course() {
        if (!isset($this->course)) {
            $this->create_a_course();
        }
        return $this->course;
    }

    /**
     * @return stdClass
     */
    protected function get_group() {
        if (!isset($this->group)) {
            $this->create_a_group();
        }
        return $this->group;
    }

    /**
     * @return coursework
     */
    protected function get_coursework() {
        if (!isset($this->coursework)) {
            $this->create_a_coursework();
        }
        return $this->coursework;
    }

    /**
     * @return user
     */
    protected function get_student() {
        if (!isset($this->student)) {
            $this->create_a_student();
        }
        return $this->student;
    }

    /**
     * @return user
     */
    protected function get_submission() {
        if (!isset($this->submission)) {
            $this->create_a_submission_for_the_student();
        }
        return $this->submission;
    }
}