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

namespace mod_coursework\task;
use mod_coursework\mailer;
use mod_coursework\models\coursework;
use mod_coursework\models\submission;
use mod_coursework\models\user;

/**
 * Adhoc task to process emails from coursework.
 *
 * @package   mod_coursework
 * @author    Conn Warwicker <conn.warwicker@catalyst-eu.net>
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mail_task extends \core\task\adhoc_task {
    /**
     * Execute the task.
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        if (!isset($data->type, $data->coursework)) {
            mtrace('Task is missing required custom data');
            return;
        }
        $coursework = coursework::get_from_id($data->coursework);
        if (!$coursework) {
            mtrace("Coursework {$data->coursework} no longer exists, skipping {$data->type} notification.");
            return;
        }
        switch ($data->type) {
            case 'submission_receipt':
                if (!isset($data->submission, $data->user)) {
                    mtrace('Task is missing required custom data');
                    return;
                }
                $submission = $this->load_submission($data->submission);
                $user = $this->load_user($data->user);
                if (!$submission || !$user) {
                    return;
                }
                $this->send_submission_receipt($coursework, $submission, $user);
                break;
            case 'feedback':
                if (!isset($data->submission)) {
                    mtrace('Task is missing required custom data');
                    return;
                }
                $submission = $this->load_submission($data->submission);
                if (!$submission) {
                    return;
                }
                $this->send_feedback_notification($coursework, $submission);
                break;
            case 'deadline_reminder':
                if (!isset($data->user, $data->extra->deadline)) {
                    mtrace('Task is missing required custom data');
                    return;
                }
                $user = $this->load_user($data->user);
                if (!$user) {
                    return;
                }
                $user->deadline = $data->extra->deadline;
                $this->send_deadline_reminder($coursework, $user, $data);
                break;
            case 'submission_notification':
                if (!isset($data->user)) {
                    mtrace('Task is missing required custom data');
                    return;
                }
                $user = $this->load_user($data->user);
                if (!$user) {
                    return;
                }
                $this->send_submission_notification($coursework, $user);
                break;
            default:
                throw new \coding_exception('Unknown type');
        }
    }

    /**
     * Load the user.
     * @param int $userid
     * @return user
     */
    private function load_user(int $userid): user {
        $user = user::get_from_id($userid);
        if (!$user) {
            mtrace("User {$userid} no longer exists, skipping notification.");
        }
        return $user;
    }

    /**
     * Load the submission.
     * @param int $submissionid
     * @return submission
     */
    private function load_submission(int $submissionid): submission {
        $submission = submission::get_from_id($submissionid);
        if (!$submission) {
            mtrace("Submission {$submissionid} no longer exists, skipping notification.");
        }
        return $submission;
    }

    /**
     * Send the submission receipt email.
     * @param coursework $coursework
     * @param submission $submission
     * @param user $user
     */
    private function send_submission_receipt(coursework $coursework, submission $submission, user $user): void {
        $notify = $this->get_custom_data()->notify ?? $submission->is_finalised();
        $mailer = new mailer($coursework);
        $mailer->send_submission_receipt($user, $submission, $notify);
    }

    /**
     * Send the feedback notification email.
     * @param coursework $coursework
     * @param submission $submission
     */
    private function send_feedback_notification(coursework $coursework, submission $submission): void {
        $mailer = new mailer($coursework);
        $mailer->send_feedback_notification($submission);
    }

    /**
     * Send the deadline reminder email.
     * @param coursework $coursework
     * @param user $user
     */
    private function send_deadline_reminder(coursework $coursework, user $user, \stdClass $data): void {
        global $DB;
        $mailer = new mailer($coursework);
        if (!$mailer->send_student_deadline_reminder($user)) {
            mtrace("Failed to send deadline reminder to user {$user->id}");
            return;
        }
        $emailreminder = new \stdClass();
        $emailreminder->userid = $user->id;
        $emailreminder->courseworkid = $data->courseworkid;
        $emailreminder->remindernumber = $data->nextremindernumber;
        $emailreminder->extension = $data->extension;
        $DB->insert_record('coursework_reminder', $emailreminder);
    }

    /**
     * Send the submission notification email.
     * @param coursework $coursework
     * @param user $user
     */
    private function send_submission_notification(coursework $coursework, user $user): void {
        $mailer = new mailer($coursework);
        $mailer->send_submission_notification($user->id());
    }
}
