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
 * @copyright  2026 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\render_helpers\grading_report\data;

use mod_coursework\models\coursework;

/**
 * Class to add notifications to grading report by row.
 */
class grading_report_notifier {
    /**
     * The coursework.
     * @var coursework
     */
    protected coursework $coursework;

    /**
     * Constructor.
     * @param coursework $coursework
     */
    public function __construct(coursework $coursework) {
        $this->coursework = $coursework;
    }

    /**
     * Add a notification to be shown in the marking table row.
     * @param int $submissionid
     * @param string $notification
     * @param string $notificationtype
     * @param int $lifetimeseconds
     * @return void
     */
    public function add_row_notification(int $submissionid, string $notification, string $notificationtype, int $lifetimeseconds = 10): void {
        global $SESSION;
        $key = $this->get_row_notifications_session_key();
        if (!isset($SESSION->$key)) {
            $SESSION->$key = [];
        }
        if (!isset($SESSION->{$key}[$submissionid])) {
            $SESSION->{$key}[$submissionid] = [];
        }
        $SESSION->{$key}[$submissionid][] = (object)[
            'submissionid' => $submissionid,
            'notification' => $notification,
            'notificationclass' => $notificationtype == \core\notification::ERROR ? 'danger' : $notificationtype,
            'expires' => time() + $lifetimeseconds,
        ];
    }

    /**
     * Redirect the user to the grading report page with a notification.
     * @param int $submissionid
     * @param string $notification
     * @param string $notificationtype
     * @param int $lifetimeseconds
     * @return void
     * @throws \core\exception\moodle_exception
     * @throws \moodle_exception
     */
    public function redirect_and_notify(int $submissionid, string $notification, string $notificationtype, int $lifetimeseconds = 10) {
        $this->add_row_notification($submissionid, $notification, $notificationtype, $lifetimeseconds);
        redirect(
            new \moodle_url(
                '/mod/coursework/view.php',
                ['id' => $this->coursework->get_coursemodule_id()],
                "submission-" . $submissionid
            )
        );
    }

    /**
     * Get all notifications to be shown in all marking table rows.
     * @return array
     */
    public function get_row_notifications(): array {
        global $SESSION;
        $key = $this->get_row_notifications_session_key();
        $rows = $SESSION->$key ?? [];
        foreach ($rows as $rowindex => $row) {
            foreach ($row as $notifindex => $notif) {
                if ($notif->expires < time()) {
                    unset($rows[$rowindex][$notifindex]);
                }
            }
        }
        // Empty cache now have sent all.
        $SESSION->$key = [];
        return $rows;
    }


    /**
     * Get the key used to store row notifications in $SESSION.
     * @return string
     */
    public function get_row_notifications_session_key(): string {
        return "coursework_grade_row_notifs_" . $this->coursework->id;
    }
}
