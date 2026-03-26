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
use core\message\message;
use core_user;
use html_writer;
use mod_coursework\models\coursework;
use mod_coursework\models\submission;
use mod_coursework\models\user;
use stdClass;

/**
 * This is where all emails are sent from. The methods need to be OK to run from a separate cron process,
 * so do not pass in entire objects. Just DB row ids, which the methods then retrieve.
 *
 * @package mod_coursework
 */
class mailer {
    /**
     * @var models\coursework
     */
    protected $coursework;

    /**
     * @param coursework $coursework
     */
    public function __construct($coursework) {
        $this->coursework = $coursework;
    }

    /**
     * This ought to only be triggered when the submission is finalised, not when the draft is uploaded.
     *
     * @param user $user
     * @param bool $finalised
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws coding_exception
     */

    public function send_submission_receipt($user, $finalised = false) {
        global $CFG;

        $submission = $this->coursework->get_user_submission($user);

        if ($this->coursework && $this->coursework->is_coursework_visible()) {// check if coursework exists and is not hidden
            $emaildata = new stdClass();
            $emaildata->name = $user->name();
            $dateformat = '%a, %d %b %Y, %H:%M';
            $emaildata->submittedtime = userdate($submission->time_submitted(), $dateformat);
            $emaildata->coursework_name = $this->coursework->name;
            $emaildata->submissionid = $submission->id;
            if ($finalised) {
                $emaildata->finalised = get_string('save_email_finalised', 'coursework');
            } else {
                $emaildata->finalised = '';
            }

            $subject = get_string('save_email_subject', 'coursework');
            $textbody = get_string('save_email_text', 'coursework', $emaildata);
            $htmlbody = get_string('save_email_html', 'coursework', $emaildata);

            // New approach.
            $eventdata = new message();
            $eventdata->component = 'mod_coursework';
            $eventdata->name = 'submission_receipt';
            $eventdata->userfrom = core_user::get_noreply_user();
            $eventdata->userto = $user->get_raw_record();
            $eventdata->subject = $subject;
            $eventdata->fullmessage = $textbody;
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml = $htmlbody;
            $eventdata->smallmessage = $textbody;
            $eventdata->notification = 1;
            $eventdata->contexturl = $CFG->wwwroot . '/mod/coursework/view.php?id=' . $submission->get_coursework()->get_coursemodule_id();
            $eventdata->contexturlname = 'View your submission here';
            $eventdata->courseid = $this->coursework->course;

            message_send($eventdata);
        }
    }

    /**
     * @param submission $submission
     * @throws coding_exception
     */
    public function send_late_submission_notification($submission) {
        global $CFG;

        $coursework = $submission->get_coursework();
        $studentorgroup = $submission->get_allocatable();
        $recipients = $coursework->initial_assessors($studentorgroup);
        foreach ($recipients as $recipient) {
            // New approach.
            $eventdata = new message();
            $eventdata->component = 'mod_coursework';
            $eventdata->name = 'submission_receipt';
            $eventdata->userfrom = core_user::get_noreply_user();
            $eventdata->userto = $recipient;
            $eventdata->subject = 'Late submission for ' . $coursework->name;
            $messagetext =
                'A late submission was just submitted for ' . $studentorgroup->type() . ' ' . $studentorgroup->name();
            $eventdata->fullmessage = $messagetext;
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml = $messagetext;
            $eventdata->smallmessage = $messagetext;
            $eventdata->notification = 1;
            $eventdata->contexturl =
                $CFG->wwwroot . '/mod/coursework/view.php?id=' . $coursework->get_coursemodule_id();
            $eventdata->contexturlname = 'View the submission here';
            $eventdata->courseid = $this->coursework->course;

            message_send($eventdata);
        }
    }

    /**
     * Send feedback notifications to users whose feedback was released
     *
     * @param submission $submission
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws coding_exception
     */
    public function send_feedback_notification($submission) {
        global $CFG, $DB;

        // Check if coursework exists and is not hidden.
        if ($this->coursework && $this->coursework->is_coursework_visible()) {
            $emaildata = new stdClass();
            $emaildata->coursework_name = $this->coursework->name;

            $subject = get_string('feedback_released_email_subject', 'coursework');

            // Get a student or all students from a group.
            $students = $submission->students_for_gradebook();

            foreach ($students as $student) {
                $user = $DB->get_record('user', ['id' => $student->id, 'deleted' => 0, 'suspended' => 0]);
                if (!$user) {
                    debugging("User $student->id not found", DEBUG_DEVELOPER);
                    continue;
                }

                $emaildata->name = core_user::get_fullname($user);
                $textbody = get_string('feedback_released_email_text', 'coursework', $emaildata);
                $htmlbody = get_string('feedback_released_email_html', 'coursework', $emaildata);

                $eventdata = new message();
                $eventdata->component = 'mod_coursework';
                $eventdata->name = 'feedback_released';
                $eventdata->userfrom = core_user::get_noreply_user();
                $eventdata->userto = $user;
                $eventdata->subject = $subject;
                $eventdata->fullmessage = $textbody;
                $eventdata->fullmessageformat = FORMAT_PLAIN;
                $eventdata->fullmessagehtml = $htmlbody;
                $eventdata->smallmessage = $textbody;
                $eventdata->notification = 1;
                $eventdata->contexturl =
                    $CFG->wwwroot . '/mod/coursework/view.php?id=' . $submission->get_coursework()->get_coursemodule_id();
                $eventdata->contexturlname = 'View your submission here';
                $eventdata->courseid = $this->coursework->course;

                message_send($eventdata);
            }
        }
    }

    /**
     *  Send deadline reminder notifications to users who haven't submitted yet
     *
     * @param $user
     * @return mixed
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws coding_exception
     */
    public function send_student_deadline_reminder($user) {

        global $CFG;

        if (!$this->coursework || !$this->coursework->is_coursework_visible()) {// check if coursework exists and is not hidden
            return false;
        }
        $emaildata = new stdClass();
        $emaildata->coursework_name = $this->coursework->name;
        $emaildata->coursework_name_with_link = html_writer::link($CFG->wwwroot . '/mod/coursework/view.php?id=' . $this->coursework->get_coursemodule_id(), $this->coursework->name);
        $emaildata->deadline = $user->deadline;
        $emaildata->human_deadline = userdate($user->deadline, '%a, %d %b %Y, %H:%M');

        $secondstodeadline = $user->deadline - time();
        $days = floor($secondstodeadline / 86400);
        $hours = floor($secondstodeadline / 3600) % 24;
        $daystodeadline = '';
        if ($days > 0) {
            $daystodeadline = $days . ' days and ';
        }
        $daystodeadline .= $hours . ' hours';
        $emaildata->day_hour = $daystodeadline;

        $subject = get_string('cron_email_subject', 'mod_coursework', $emaildata);

        $student = user::get_from_id($user->id);

        $emaildata->name = $student->name();
        $textbody = get_string('cron_email_text', 'mod_coursework', $emaildata);
        $htmlbody = get_string('cron_email_html', 'mod_coursework', $emaildata);

        $eventdata = new message();
        $eventdata->component = 'mod_coursework';
        $eventdata->name = 'student_deadline_reminder';
        $eventdata->userfrom = core_user::get_noreply_user();
        $eventdata->userto = $student->get_raw_record();
        $eventdata->subject = $subject;
        $eventdata->fullmessage = $textbody;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml = $htmlbody;
        $eventdata->smallmessage = $textbody;
        $eventdata->notification = 1;
        $eventdata->contexturl = $CFG->wwwroot . '/mod/coursework/view.php?id=' . $this->coursework->get_coursemodule_id();
        $eventdata->contexturlname = 'View the coursework here';
        $eventdata->courseid = $this->coursework->course;

        return message_send($eventdata);
    }

    public function send_submission_notification(int $useridtonotify) {

        global $CFG;

        if ($this->coursework && $this->coursework->is_coursework_visible()) {// check if coursework exists and is not hidden
            $emaildata = new stdClass();
            $emaildata->coursework_name = $this->coursework->name;

            $subject = get_string('submission_notification_subject', 'coursework', $emaildata->coursework_name);

            $userstonotify = user::get_from_id($useridtonotify);
            if (!$userstonotify) {
                return;
            }
            $emaildata->name = $userstonotify->name();
            $textbody = get_string('submission_notification_text', 'coursework', $emaildata);
            $htmlbody = get_string('submission_notification_html', 'coursework', $emaildata);

            $eventdata = new message();
            $eventdata->component = 'mod_coursework';
            $eventdata->name = 'coursework_submission';
            $eventdata->userfrom = core_user::get_noreply_user();
            $eventdata->userto = $userstonotify->get_raw_record();
            $eventdata->subject = $subject;
            $eventdata->fullmessage = $textbody;
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml = $htmlbody;
            $eventdata->smallmessage = $textbody;
            $eventdata->notification = 1;
            $eventdata->contexturl = $CFG->wwwroot . '/mod/coursework/view.php?id=' . $this->coursework->id();
            $eventdata->contexturlname = 'coursework submission';
            $eventdata->courseid = $this->coursework->course;

            message_send($eventdata);
        }
    }
}
