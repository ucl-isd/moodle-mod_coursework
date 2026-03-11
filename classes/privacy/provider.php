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
 * @copyright  2017 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use coursework;
use mod_coursework\models\submission;
use stdClass;

/**
 * Privacy Subsystem implementation for coursework.
 *
 * @package    mod_coursework
 * @category   privacy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements core_userlist_provider, \core_privacy\local\metadata\provider, \core_privacy\local\request\plugin\provider {
    /**
     * Provides meta data that is stored about a user with mod_coursework
     *
     * @param  collection $collection A collection of meta data items to be added to.
     * @return  collection Returns the collection of metadata.
     */
    public static function get_metadata(collection $collection): collection {
        $feedbacks = [
            'assessorid' => 'privacy:metadata:assessorid',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'timemodified',
            'grade' => 'privacy:metadata:grade',
            'submissionid' => 'privacy:metadata:submissionid',
            'feedbackcomment' => 'privacy:metadata:feedbackcomment',
        ];
        $submissions = [
            'authorid' => 'privacy:metadata:authorid',
            'userid' => 'privacy:metadata:userid',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'timemodified',
            'createdby' => 'createdby',
            'timesubmitted' => 'timesubmitted',
        ];
        $extensions = [
            'allocatableid' => 'privacy:metadata:allocatableid',
            'createdbyid' => 'privacy:metadata:createdbyid',
            'extrainformationtext' => 'privacy:metadata:extrainformationtext',
            'extended_deadline' => 'privacy:metadata:extended_deadline',
            'allocatableuser' => 'privacy:metadata:userid',
            'allocatablegroup' => 'privacy:metadata:groupid',
        ];
        $persondeadlines = [
            'allocatableid' => 'privacy:metadata:allocatableid',
            'createdbyid' => 'privacy:metadata:createdbyid',
            'personaldeadline' => 'privacy:metadata:personaldeadline',
            'allocatableuser' => 'privacy:metadata:userid',
            'allocatablegroup' => 'privacy:metadata:groupid',
        ];
        $modagreements = [
            'moderatorid' => 'privacy:metadata:moderatorid',
            'agreement' => 'privacy:metadata:agreement',
            'modcomment' => 'privacy:metadata:modcomment',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'timemodified',
        ];
        $plagiarismflags = [
            'createdby' => 'privacy:metadata:createdby',
            'comment' => 'privacy:metadata:comment',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'timemodified',
        ];
        $collection->add_database_table('coursework_feedbacks', $feedbacks, 'privacy:metadata:feedbacks');
        $collection->add_database_table('coursework_submissions', $submissions, 'privacy:metadata:submissions');
        $collection->add_database_table('coursework_extensions', $extensions, 'privacy:metadata:extensions');
        $collection->add_database_table('coursework_person_deadlines', $persondeadlines, 'privacy:metadata:persondeadlines');
        $collection->add_database_table('coursework_mod_agreements', $modagreements, 'privacy:metadata:modagreements');
        $collection->add_database_table('coursework_plagiarism_flags', $plagiarismflags, 'privacy:metadata:plagiarismflags');
        return $collection;
    }
    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $params = [
            'modulename' => 'coursework',
            'contextid' => $context->id,
            'contextlevel' => CONTEXT_MODULE,
        ];
        $sql = "SELECT cwf.assessorid
                    FROM {context} ctx
                    JOIN {course_modules} cm ON cm.id = ctx.instanceid
                    JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                    JOIN {coursework} cw ON cw.id = cm.instance
                    JOIN {coursework_submissions} cws ON cw.id = cws.courseworkid
                    JOIN {coursework_feedbacks} cwf ON cws.id = cwf.submissionid
                WHERE ctx.id = :contextid AND ctx.contextlevel = :contextlevel";
        $userlist->add_from_sql('assessorid', $sql, $params);
        $sql = "SELECT cws.userid, cws.authorid
                    FROM {context} ctx
                    JOIN {course_modules} cm ON cm.id = ctx.instanceid
                    JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                    JOIN {coursework} cw ON cw.id = cm.instance
                    JOIN {coursework_submissions} cws ON cw.id = cws.courseworkid
                WHERE ctx.id = :contextid AND ctx.contextlevel = :contextlevel";
        $userlist->add_from_sql('userid', $sql, $params);
        $userlist->add_from_sql('authorid', $sql, $params);
        $sql = "SELECT cwe.allocatableid, cwe.allocatableuser, cwe.allocatablegroup
                    FROM {context} ctx
                    JOIN {course_modules} cm ON cm.id = ctx.instanceid
                    JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                    JOIN {coursework} cw ON cw.id = cm.instance
                    JOIN {coursework_extensions} cwe ON cw.id = cwe.courseworkid
                WHERE ctx.id = :contextid AND ctx.contextlevel = :contextlevel";
        $userlist->add_from_sql('allocatableid', $sql, $params);
        $userlist->add_from_sql('allocatableuser', $sql, $params);
        $userlist->add_from_sql('allocatablegroup', $sql, $params);
        $sql = "SELECT cwpd.allocatableid, cwpd.allocatableuser, cwpd.allocatablegroup
                    FROM {context} ctx
                    JOIN {course_modules} cm ON cm.id = ctx.instanceid
                    JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                    JOIN {coursework} cw ON cw.id = cm.instance
                    JOIN {coursework_person_deadlines} cwpd ON cw.id = cwpd.courseworkid
                WHERE ctx.id = :contextid AND ctx.contextlevel = :contextlevel";
        $userlist->add_from_sql('allocatableid', $sql, $params);
        $userlist->add_from_sql('allocatableuser', $sql, $params);
        $userlist->add_from_sql('allocatablegroup', $sql, $params);
        $sql = "SELECT cwma.moderatorid
                    FROM {context} ctx
                    JOIN {course_modules} cm ON cm.id = ctx.instanceid
                    JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                    JOIN {coursework} cw ON cw.id = cm.instance
                    JOIN {coursework_submissions} cws ON cw.id = cws.courseworkid
                    JOIN {coursework_feedbacks} cwf ON cws.id = cwf.submissionid
                    JOIN {coursework_mod_agreements} cwma ON cwf.id = cwma.feedbackid
                WHERE ctx.id = :contextid AND ctx.contextlevel = :contextlevel";
        $userlist->add_from_sql('moderatorid', $sql, $params);

        $sql = "SELECT cwpf.createdby
                    FROM {context} ctx
                    JOIN {course_modules} cm ON cm.id = ctx.instanceid
                    JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                    JOIN {coursework} cw ON cw.id = cm.instance
                    JOIN {coursework_submissions} cws ON cw.id = cws.courseworkid
                    JOIN {coursework_plagiarism_flags} cwpf ON cws.id = cwpf.submissionid
                WHERE ctx.id = :contextid AND ctx.contextlevel = :contextlevel";
        $userlist->add_from_sql('createdby', $sql, $params);
    }
    /**
     * Returns all of the contexts that has information relating to the userid.
     *
     * @param  int $userid The user ID.
     * @return contextlist an object with the contexts related to a userid.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $params = [
            'modulename' => 'coursework',
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
            'authorid' => $userid,
            'assessorid' => $userid,
            'allocatableid' => $userid,
            'allocatableuser' => $userid,
            'allocatablegroup' => $userid,
            'moderatorid' => $userid,
            'createdby' => $userid,
        ];
        $sql = "SELECT ctx.id
                    FROM {course_modules} cm
                    JOIN {modules} m ON cm.module = m.id AND m.name = :modulename
                    JOIN {coursework} cw ON cm.instance = cw.id
                    JOIN {context} ctx ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                    JOIN {coursework_submissions} cws ON cw.id = cws.courseworkid AND cws.authorid = :authorid";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);
        $sql = "SELECT ctx.id
                    FROM {course_modules} cm
                    JOIN {modules} m ON cm.module = m.id AND m.name = :modulename
                    JOIN {coursework} cw ON cm.instance = cw.id
                    JOIN {context} ctx ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                    JOIN {coursework_submissions} cws ON cw.id = cws.courseworkid
                    JOIN {coursework_feedbacks} cwf ON cws.id = cwf.submissionid AND cwf.assessorid = :assessorid";
        $contextlist->add_from_sql($sql, $params);
        $sql = "SELECT ctx.id
                    FROM {course_modules} cm
                    JOIN {modules} m ON cm.module = m.id AND m.name = :modulename
                    JOIN {coursework} cw ON cm.instance = cw.id
                    JOIN {context} ctx ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                    JOIN {coursework_extensions} cwx ON cwx.courseworkid = cw.id AND (cwx.allocatableid = :userid OR cwx.allocatableuser = :allocatableuser OR cwx.allocatablegroup = :allocatablegroup)";
        $contextlist->add_from_sql($sql, $params);
        $sql = "SELECT ctx.id
                    FROM {course_modules} cm
                    JOIN {modules} m ON cm.module = m.id AND m.name = :modulename
                    JOIN {coursework} cw ON cm.instance = cw.id
                    JOIN {context} ctx ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                    JOIN {coursework_person_deadlines} cw_pd ON cw_pd.courseworkid = cw.id AND (cw_pd.allocatableid = :userid OR cw_pd.allocatableuser = :allocatableuser OR cw_pd.allocatablegroup = :allocatablegroup)";
        $contextlist->add_from_sql($sql, $params);
        $sql = "SELECT ctx.id
                    FROM {course_modules} cm
                    JOIN {modules} m ON cm.module = m.id AND m.name = :modulename
                    JOIN {coursework} cw ON cm.instance = cw.id
                    JOIN {context} ctx ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                    JOIN {coursework_submissions} cws ON cw.id = cws.courseworkid
                    JOIN {coursework_feedbacks} cwf ON cws.id = cwf.submissionid
                    JOIN {coursework_mod_agreements} cw_ag ON cw_ag.feedbackid = cwf.id AND cw_ag.moderatorid = :moderatorid";
        $contextlist->add_from_sql($sql, $params);
        $sql = "SELECT ctx.id
                    FROM {course_modules} cm
                    JOIN {modules} m ON cm.module = m.id AND m.name = :modulename
                    JOIN {coursework} cw ON cm.instance = cw.id
                    JOIN {context} ctx ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                    JOIN {coursework_submissions} cws ON cw.id = cws.courseworkid
                    JOIN {coursework_plagiarism_flags} cw_pf ON cws.id = cw_pf.submissionid
                    WHERE cw_pf.courseworkid = cw.id AND cw_pf.createdby = :createdby";
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }
    /**
     * Write out the user data filtered by contexts.
     *
     * @param approved_contextlist $contextlist contexts that we are writing data out from.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        foreach ($contextlist->get_contexts() as $context) {
            // Check that the context is a module context.
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $user = $contextlist->get_user();
            $courseworkdata = helper::get_context_data($context, $user);
            helper::export_context_files($context, $user);
            writer::with_context($context)->export_data([], $courseworkdata);
            $coursework = self::get_coursework_instance($context);
            static::export_coursework_submissions($coursework, $user, $context, []);
            static::export_coursework_extension($coursework->id, $user->id, $context, []);
            static::export_person_deadlines($coursework->id, $user->id, $context, []);
            static::export_plagiarism_flags($coursework->id, $context, []);
        }
    }
    public static function delete_data_for_all_users_in_context(context $context) {
        if ($context->contextlevel == CONTEXT_MODULE) {
            $cm = get_coursemodule_from_id('coursework', $context->instanceid);
            if ($cm) {
                // Get the coursework related to this context.
                $coursework = self::get_coursework_instance($context);
                // Retrieve all submissions by this coursework to remove relating data
                $submissions = $coursework->retrieve_submissions_by_coursework();
                foreach ($submissions as $submission) {
                    // Remove all plagiarisms of the current submission
                    $coursework->remove_plagiarisms_by_submission($submission->id);
                    // remove corresponding file of this submission
                    $coursework->remove_corresponding_file($context->id, $submission->id, 'submission');
                    // Retrieve all feedbacks for this current submission
                    $feedbacks = $coursework->retrieve_feedbacks_by_submission($submission->id);
                    foreach ($feedbacks as $feedback) {
                        // Remove all agreements for a feedback
                        $coursework->remove_agreements_by_feedback($feedback->id);
                        // remove corresponding file of this feedback
                        $coursework->remove_corresponding_file($context->id, $feedback->id, 'feedback');
                    }
                    // Remove all feedbacks for this submission
                    $coursework->remove_feedbacks_by_submission($submission->id);
                }
                // Remove all submissions by this coursework
                $coursework->remove_submissions_by_coursework();
                // Remove all deadline extensions by coursework
                $coursework->remove_deadline_extensions_by_coursework();
                // Remove all personal deadlines by coursework
                $coursework->remove_personaldeadlines_by_coursework();
            }
        }
    }
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $user = $contextlist->get_user();
        foreach ($contextlist as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            // Get the coursework related to this context.
            $coursework = self::get_coursework_instance($context);
            // Retrieve all submissions by user-id to remove relating data
            $submissions = $coursework->retrieve_submissions_by_user($user->id);
            foreach ($submissions as $submission) {
                // Remove all plagiarisms of the current submission
                $coursework->remove_plagiarisms_by_submission($submission->id);
                // remove corresponding file of this submission
                $coursework->remove_corresponding_file($context->id, $submission->id, 'submission');
                // Retrieve all feedbacks for this current submission
                $feedbacks = $coursework->retrieve_feedbacks_by_submission($submission->id);
                foreach ($feedbacks as $feedback) {
                    // Remove all agreements for a feedback
                    $coursework->remove_agreements_by_feedback($feedback->id);
                    // remove corresponding file of this feedback
                    $coursework->remove_corresponding_file($context->id, $feedback->id, 'feedback');
                }
                // Remove all feedbacks for this submission
                $coursework->remove_feedbacks_by_submission($submission->id);
            }
            // Remove all submissions submitted by this user
            $coursework->remove_submissions_by_user($user->id);
            // Remove all deadline extensions
            $coursework->remove_deadline_extensions_by_user($user->id);
            // Remove all personal deadlines
            $coursework->remove_personaldeadlines_by_user($user->id);
        }
    }
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        // Get the coursework related to this context.
        $coursework = self::get_coursework_instance($context);
        $userids = $userlist->get_userids();
        foreach ($userids as $userid) {
            // Retrieve all submissions by user-id to remove relating data
            $submissions = $coursework->retrieve_submissions_by_user($userid);
            foreach ($submissions as $submission) {
                // Remove all plagiarisms of the current submission
                $coursework->remove_plagiarisms_by_submission($submission->id);
                // remove corresponding file of this submission
                $coursework->remove_corresponding_file($context->id, $submission->id, 'submission');
                // Retrieve all feedbacks for this current submission
                $feedbacks = $coursework->retrieve_feedbacks_by_submission($submission->id);
                foreach ($feedbacks as $feedback) {
                    // Remove all agreements for a feedback
                    $coursework->remove_agreements_by_feedback($feedback->id);
                    // remove corresponding file of this feedback
                    $coursework->remove_corresponding_file($context->id, $feedback->id, 'feedback');
                }
                // Remove all feedbacks for this submission
                $coursework->remove_feedbacks_by_submission($submission->id);
            }
            // Remove all submissions submitted by this user
            $coursework->remove_submissions_by_user($userid);
            // Remove all deadline extensions
            $coursework->remove_deadline_extensions_by_user($userid);
            // Remove all personal deadlines
            $coursework->remove_personaldeadlines_by_user($userid);
        }
    }
    protected static function get_coursework_instance(context $context) {
        global $DB;
        $courseid = ['id' => $context->get_course_context()->instanceid];
        $course = $DB->get_record('course', $courseid, '*', MUST_EXIST);
        $modinfo = get_fast_modinfo($course);
        $coursemodule = $modinfo->get_cm($context->instanceid);
        $courseworkid = ['id' => $coursemodule->instance];
        return new \mod_coursework\models\coursework($courseworkid);
    }
    /**
     * Exports coursework submission data for a user.
     *
     * @param  coursework     $coursework       The coursework object
     * @param  stdClass       $user             The user object
     * @param  context_module $context          The context
     * @param  array           $path             The path for exporting data
     * @param  bool $exportforteacher A flag for if this is exporting data as a teacher.
     */
    protected static function export_coursework_submissions($coursework, $user, $context, $path, $exportforteacher = false) {
        $submissions = self::get_user_submissions($user->id, $coursework->id);
        $teacher = ($exportforteacher) ? $user : null;
        foreach ($submissions as $submission) {
            self::export_submission_files($context, $submission->id, $path);
            if (!isset($teacher)) {
                self::export_coursework_submission($submission, $context, $path);
            }
            // Export feedbacks for each submission, feedbacks include user's grade
            $feedbacks = self::get_submission_feedbacks($submission->id);
            if ($feedbacks) {
                self::export_submissions_feedbacks($feedbacks, $context, $path);
            }
        }
    }
    protected static function export_submission_files($context, $submissionid, $currentpath) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_coursework', 'submission', $submissionid);
        if (!empty($files)) {
            foreach ($files as $file) {
                writer::with_context($context)->export_file($currentpath, $file);
            }
        }
    }

    /**
     * Gets all the submissions at once for user.
     * @param $userid
     * @param int $courseworkid
     * @return submissions[]
     * @throws \dml_exception
     */
    protected static function get_user_submissions($userid, $courseworkid) {
        global $DB;
        $params = ['courseworkid' => $courseworkid, 'authorid' => $userid];
        return $DB->get_records('coursework_submissions', $params);
    }

    /**
     * Formats and then exports the user's submission data.
     *
     * @param stdClass $submission The coursework submission DB record.
     * @param context $context The context object
     * @param array $currentpath Current directory path that we are exporting to.
     * @throws \coding_exception
     */
    protected static function export_coursework_submission(stdClass $submission, context $context, array $currentpath) {
        $status = self::get_submissions_status($submission->id);
        $submissiondata = (object)[
            'userid' => $submission->userid,
            'status' => $status ? $status : '',
            'timecreated' => transform::datetime($submission->timecreated),
            'timemodified' => transform::datetime($submission->timemodified),
            'timesubmitted' => transform::datetime($submission->timesubmitted),
            'createdby' => $submission->createdby,
            'finalised' => transform::yesno($submission->finalisedstatus == submission::FINALISED_STATUS_FINALISED),
        ];
        writer::with_context($context)
            ->export_data(array_merge($currentpath, [get_string('privacy:submissionpath', 'mod_coursework')]), $submissiondata);
    }
    protected static function get_submissions_status($submissionid) {
        $submission = new submission($submissionid);
        $status = $submission->get_status_text();
        if (!empty($status)) {
            return $status;
        }
    }
    protected static function get_submission_feedbacks($submissionid) {
        global $DB;
        $params = ['submissionid' => $submissionid];
        return $DB->get_records('coursework_feedbacks', $params);
    }
    protected static function export_submissions_feedbacks($feedbacks, $context, $path) {
        $feedbacksdata = [];
        foreach ($feedbacks as $feedback) {
            $feedbackdataformatted = self::format_submissions_feedback($feedback);
            if ($feedbackdataformatted) {
                array_push($feedbacksdata, $feedbackdataformatted);
            }
            if ($feedback->ismoderation) {
                self::export_mod_agreements($feedback->id, $context, $path);
            }
            self::export_feedback_files($context, $feedback->id, $path);
        }
        // coursework_feedbacks table contains all grading information
        if (!empty($feedbacksdata)) {
            writer::with_context($context)
                ->export_data(array_merge($path, [get_string('privacy:feedbackspath', 'mod_coursework')]), (object) $feedbacksdata);
        }
    }
    protected static function export_feedback_files($context, $feedbackid, $currentpath) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_coursework', 'feedback', $feedbackid);
        if (!empty($files)) {
            foreach ($files as $file) {
                writer::with_context($context)->export_file($currentpath, $file);
            }
        }
    }
    /**
     * Formats the user's submission grade data.
     *
     * @param  stdClass $feedback The coursework submission grade object
     */
    protected static function format_submissions_feedback(stdClass $feedback) {
        return [
            'assessorid' => $feedback->assessorid,
            'timecreated' => transform::datetime($feedback->timecreated),
            'timemodified' => transform::datetime($feedback->timemodified),
            'grade' => $feedback->grade,
            'feedbackcomment' => $feedback->feedbackcomment,
            'stageidentifier' => $feedback->stageidentifier,
            'finalised' => transform::yesno($feedback->finalised),
        ];
    }
    protected static function export_coursework_extension($courseworkid, $userid, $context, $path) {
        $extension = self::get_coursework_extension($courseworkid, $userid);
        if ($extension) {
            self::export_coursework_extension_data($extension, $context, $path);
        }
    }
    protected static function get_coursework_extension($courseworkid, $userid) {
        global $DB;
        $params = ['courseworkid' => $courseworkid, 'allocatableid' => $userid];
        return $DB->get_record('coursework_extensions', $params);
    }
    protected static function export_coursework_extension_data($extension, $context, $path) {
        $extensiondata = [
            'extended_deadline' => transform::datetime($extension->extended_deadline),
            'extrainformationtext' => $extension->extrainformationtext,
            'createdbyid' => $extension->createdbyid,
        ];
        writer::with_context($context)
            ->export_data(array_merge($path, [get_string('privacy:extensionpath', 'mod_coursework')]), (object) $extensiondata);
    }
    protected static function export_person_deadlines($courseworkid, $userid, $context, $path) {
        $persondeadline = self::get_person_deadline($courseworkid, $userid);
        if ($persondeadline) {
            self::export_person_deadline_data($persondeadline, $context, $path);
        }
    }
    protected static function get_person_deadline($courseworkid, $userid) {
        global $DB;
        $params = ['courseworkid' => $courseworkid, 'allocatableid' => $userid];
        return $DB->get_record('coursework_person_deadlines', $params);
    }
    protected static function export_person_deadline_data($persondeadline, $context, $path) {
        $persondeadlinedata = [
            'personaldeadline' => transform::datetime($persondeadline->personaldeadline),
            'timecreated' => transform::datetime($persondeadline->timecreated),
            'timemodified' => transform::datetime($persondeadline->timemodified),
            'createdbyid' => $persondeadline->createdbyid,
        ];
        writer::with_context($context)
            ->export_data(array_merge($path, [get_string('privacy:person_deadlines', 'mod_coursework')]), (object) $persondeadlinedata);
    }

    protected static function export_plagiarism_flags($courseworkid, $context, $path) {
        $plagiarism = self::get_plagiarism_flags($courseworkid);
        if ($plagiarism) {
            self::export_plagiarism_flags_data($plagiarism, $context, $path);
        }
    }

    protected static function get_plagiarism_flags($courseworkid) {
        global $DB;

        $param = ['courseworkid' => $courseworkid];

        return $DB->get_record('coursework_plagiarism_flags', $param);
    }

    protected static function export_plagiarism_flags_data($plagiarism, $context, $path) {

        $status = self::format_plagiarism_status($plagiarism->status);

        $plagiarismdata = [
            'comment' => transform::datetime($plagiarism->comment),
            'status' => $status,
            'timecreated' => transform::datetime($plagiarism->timecreated),
            'timemodified' => transform::datetime($plagiarism->timemodified),
            'createdby' => $plagiarism->createdby,
        ];

        writer::with_context($context)
                ->export_data(array_merge($path, [get_string('privacy:plagiarism_alert', 'mod_coursework')]), (object) $plagiarismdata);
    }

    protected static function format_plagiarism_status($status) {
        switch ($status) {
            case 0:
                $statusstring = get_string('plagiarism_0', 'mod_coursework');
                break;
            case 1:
                $statusstring = get_string('plagiarism_1', 'mod_coursework');
                break;
            case 2:
                $statusstring = get_string('plagiarism_2', 'mod_coursework');
                break;
            case 3:
                $statusstring = get_string('plagiarism_3', 'mod_coursework');
                break;
            default:
                $statusstring = '';
                break;
        }

        return $statusstring;
    }

    protected static function export_mod_agreements($feedbackid, $context, $path) {
        $agreement = self::get_mod_agreement($feedbackid);
        if ($agreement) {
            self::export_mod_agreement_data($agreement, $context, $path);
        }
    }
    protected static function get_mod_agreement($feedbackid) {
        global $DB;
        $param = ['feedbackid' => $feedbackid];
        return $DB->get_record('coursework_mod_agreements', $param);
    }
    protected static function export_mod_agreement_data($agreement, $context, $path) {
        $agreementdata = [
            'moderatorid' => $agreement->moderatorid,
            'agreement' => $agreement->agreement,
            'modcomment' => $agreement->modcomment,
            'timecreated' => transform::datetime($agreement->timecreated),
            'timemodified' => transform::datetime($agreement->timemodified),
        ];
        writer::with_context($context)
            ->export_data(array_merge($path, [get_string('privacy:moderator', 'mod_coursework')]), (object) $agreementdata);
    }
}
