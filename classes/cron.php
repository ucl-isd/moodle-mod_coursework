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

namespace mod_coursework;
use coding_exception;
use context;
use mod_coursework\models\coursework;
use mod_coursework\models\deadline_extension;
use mod_coursework\models\personaldeadline;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use stdClass;

/**
 * Class containing all logic for the coursework cron functionality
 */
class cron {
    /**
     * Email to be sent to a user
     */
    const EMAIL_TYPE_USER = 'user';

    /**
     * Email to be sent to an admin
     */
    const EMAIL_TYPE_ADMIN = 'admin';

    /**
     * Standard Moodle API function to get things going
     *
     * @return bool
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws coding_exception
     */
    public static function run() {
        if (!self::in_test_environment()) {
            echo "Starting coursework cron functions...\n";
        }
        self::finalise_any_submissions_where_the_deadline_has_passed();
        self::send_reminders_to_students();
        self::autorelease_feedbacks_where_the_release_date_has_passed();
        return true;
    }

    /**
     * Function to be run periodically according to the moodle cron
     * This function searches for things that need to be done, such
     * as sending out mail, toggling flags etc ...
     *
     * @return boolean
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws coding_exception
     * @todo this really needs refactoring :(
     */
    private static function send_reminders_to_students() {
        global $DB;

        $counts = [
            'emails' => 0,
            'users' => 0,
        ];

        $userswhoneedreminding = [];

        $rawcourseworks = $DB->get_records('coursework');
        foreach ($rawcourseworks as $rawcoursework) {
            /**
             * @var coursework $coursework
             */
            $coursework = coursework::find($rawcoursework);
            if (!$coursework || !$coursework->is_coursework_visible()) {// check if coursework exists and is not hidden
                continue;
            }

            // if cw doesn't have personal deadlines and deadline passed and cw doesnt have any individual extensions
            if (
                !$coursework->personaldeadlines_enabled() && (!$coursework->has_deadline()
                || $coursework->deadline_has_passed() && !$coursework->extension_exists())
            ) {
                continue;
            }

            $students = $coursework->get_students_who_have_not_yet_submitted();

            foreach ($students as $student) {
                $individualextension = false;
                $personaldeadline = false;

                if ($coursework->extensions_enabled()) {
                    $individualextension = deadline_extension::get_extension_for_student($student, $coursework);
                }
                if ($coursework->personaldeadlines_enabled()) {
                    $personaldeadline = personaldeadline::get_personaldeadline_for_student($student, $coursework);
                }

                $deadline = $personaldeadline ? $personaldeadline->personaldeadline : $coursework->deadline;

                if ($individualextension) {
                    // check if 1st reminder is due to be sent but has not been sent yet
                    if (
                        $coursework->due_to_send_first_reminders($individualextension->extended_deadline) &&
                        $student->has_not_been_sent_reminder($coursework, 1, $individualextension->extended_deadline)
                    ) {
                           $student->deadline = $individualextension->extended_deadline;
                           $student->extension = $individualextension->extended_deadline;
                           $student->courseworkid = $coursework->id;
                           $student->nextremindernumber = 1;
                           $userswhoneedreminding[$student->id() . '_' . $coursework->id] = $student;

                        // check if 2nd reminder is due to be sent but has not been sent yet
                    } else if (
                        $coursework->due_to_send_second_reminders($individualextension->extended_deadline) &&
                        $student->has_not_been_sent_reminder($coursework, 2, $individualextension->extended_deadline)
                    ) {
                           $student->deadline = $individualextension->extended_deadline;
                           $student->extension = $individualextension->extended_deadline;
                           $student->courseworkid = $coursework->id;
                           $student->nextremindernumber = 2;
                           $userswhoneedreminding[$student->id() . '_' . $coursework->id] = $student;
                    }
                } else if ($deadline > time()) { // coursework or personal deadline hasn't passed
                    // check if 1st reminder is due to be sent but has not been sent yet
                    if ($coursework->due_to_send_first_reminders($deadline) && $student->has_not_been_sent_reminder($coursework, 1)) {
                            $student->deadline = $deadline;
                            $student->courseworkid = $coursework->id;
                            $student->nextremindernumber = 1;
                            $userswhoneedreminding[$student->id() . '_' . $coursework->id] = $student;

                        // check if 2nd reminder is due to be sent but has not been sent yet
                    } else if ($coursework->due_to_send_second_reminders($deadline) && $student->has_not_been_sent_reminder($coursework, 2)) {
                            $student->deadline = $deadline;
                            $student->courseworkid = $coursework->id;
                            $student->nextremindernumber = 2;
                            $userswhoneedreminding[$student->id() . '_' . $coursework->id] = $student;
                    }
                }
            }
        }

        self::send_email_reminders_to_students($userswhoneedreminding, $counts, self::EMAIL_TYPE_USER);

        if (self::in_test_environment() && !defined('PHPUNIT_TEST')) {
            mtrace("cron coursework, sent {$counts['emails']} emails to {$counts['users']} users");
        }
        return true;
    }

    /**
     * This is not used for output, but just converts the parametrised query to one that
     * can be copy/pasted into an SQL GUI in order to debug SQL errors
     *
     * @param string $query
     * @param array $params
     * @return string
     */
    public static function coursework_debuggable_query($query, $params = []) {

        global $CFG;

        // Substitute all the {tablename} bits.
        $query = preg_replace('/\{/', $CFG->prefix, $query);
        $query = preg_replace('/}/', '', $query);

        // Now put all the params in place.
        foreach ($params as $name => $value) {
            $pattern = '/:' . $name . '/';
            $replacevalue = (is_numeric($value) ? $value : "'" . $value . "'");
            $query = preg_replace($pattern, $replacevalue, $query);
        }

        return $query;
    }

    /**
     * Reminds students that they need to submit work.
     *
     * @param array $users
     * @param array $counts user and email cumulative counts so we can set log messages.
     * @return void
     * @throws \dml_exception
     * @throws coding_exception
     */
    private static function send_email_reminders_to_students(array $users, array &$counts) {

        global $DB;

        $emailcounter = 0;
        $usercounter = [];

        foreach ($users as $user) {
            $courseworkinstance = coursework::find($user->courseworkid);

            $mailer = new mailer($courseworkinstance);

            if ($mailer->send_student_deadline_reminder($user)) {
                $emailcounter++;
                if (!isset($usercounter[$user->id])) {
                    $usercounter[$user->id] = 1;
                } else {
                    $usercounter[$user->id]++;
                }

                $extension = isset($user->extension) ? $user->extension : 0;
                $emailreminder = new stdClass();
                $emailreminder->userid = $user->id;
                $emailreminder->courseworkid = $user->courseworkid;
                $emailreminder->remindernumber = $user->nextremindernumber;
                $emailreminder->extension = $extension;
                $DB->insert_record('coursework_reminder', $emailreminder);
            }
        }

        $counts['emails'] += array_sum($usercounter);
        $counts['users'] += count($usercounter);
    }

    /**
     * Updates all DB columns where the deadline was before now - updates 'finalisedstatus' to submission::FINALISED_STATUS_FINALISED
     */
    public static function finalise_any_submissions_where_the_deadline_has_passed($courseworkid = null) {
        $submissions = submission::not_finalised_past_deadline($courseworkid);
        foreach ($submissions as $submission) {
            // Doing this one at a time so that the email will arrive with finalisation already
            // done. Would not want them to check straight away and then find they could still
            // edit it.
            $submission->update_attribute('finalisedstatus', submission::FINALISED_STATUS_FINALISED);
            submission::remove_cache($submission->courseworkid);
            // Slightly wasteful to keep re-fetching the coursework :-/
            $mailer = new mailer($submission->get_coursework());
            foreach ($submission->get_students() as $student) {
                $mailer->send_submission_receipt($student, true);
            }
        }
    }

    /**
     * @return bool
     */
    public static function in_test_environment() {
        $inphpunit = defined('PHPUNIT_TEST') ? PHPUNIT_TEST : false;
        $inbehat = defined('BEHAT_TEST') ? BEHAT_TEST : false;
        if (!empty($inphpunit) || !empty($inbehat)) {
            return true;
        }
        return false;
    }

    /**
     * Auto release feedback of marked submission if the coursework has individual feedback enabled
     * @throws \dml_exception
     * @throws coding_exception
     */

    private static function autorelease_feedbacks_where_the_release_date_has_passed() {
        global $DB;
        if (!self::in_test_environment()) {
            echo 'Auto releasing feedbacks for courseworks where the release date have passed...';
        }

        $sql = "SELECT *
                 FROM {coursework} c
                 JOIN {coursework_submissions} cs
                   ON c.id = cs.courseworkid
                WHERE c.individualfeedback <= :now
                  AND c.individualfeedback != 0
                  AND c.individualfeedback IS NOT NULL
                  AND cs.firstpublished IS NULL";

        $courseworksubmissions = $DB->get_records_sql($sql, ['now' => time()]);

        foreach ($courseworksubmissions as $courseworksubmission) {
            $submission = submission::find($courseworksubmission);
            $feedbackautoreleasedeadline = $submission->get_coursework()->get_individual_feedback_deadline();
            $allocatable = $submission->get_allocatable();
            if (empty($allocatable)) {
                continue;
            }

            if ($feedbackautoreleasedeadline < time() && $submission->ready_to_publish()) {
                $submission->publish();
            }
        }
    }

    /**
     * Get admins and teachers.
     * @param $context
     * @return array
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function get_admins_and_teachers($context): array {
        $result = [];

        $graders = get_enrolled_users($context, 'mod/coursework:addinitialgrade');
        foreach ($graders as $grader) {
            $result[$grader->id] = user::find($grader, false);
        }
        $managers = get_enrolled_users($context, 'mod/coursework:addagreedgrade');
        foreach ($managers as $manager) {
            if (isset($result[$manager->id])) {
                // Already have this user.
                continue;
            }
            $result[$manager->id] = user::find($manager, false);
        }
        return array_values($result);
    }
}
