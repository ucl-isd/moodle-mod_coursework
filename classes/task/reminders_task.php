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

use core\task\scheduled_task;
use mod_coursework\mailer;
use mod_coursework\models\coursework;
use stdClass;

/**
 * Reminder notifications scheduled task.
 *
 * @package   mod_coursework
 * @author    Conn Warwicker <conn.warwicker@catalyst-eu.net>
 * @copyright 2026 onwards Catalyst IT EU {@link https://catalyst-eu.net}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reminders_task extends scheduled_task {
    /** @var string Reminder type for initial marking deadlines. */
    public const TYPE_INITIAL = 'initial';

    /** @var string Reminder type for agreed marking deadlines. */
    public const TYPE_AGREED = 'agreed';

    /** @var string Period key for deadlines due today (within the next 24 hours). */
    public const PERIOD_TODAY = 'today';

    /** @var string Period key for deadlines due within the next UPCOMING_DAYS days (excluding today). */
    public const PERIOD_UPCOMING = 'upcoming';

    /** @var int Number of days ahead to look for upcoming deadlines. */
    public const UPCOMING_DAYS = 5;

    #[\Override]
    public function get_name(): string {
        return get_string('task:reminders', 'mod_coursework');
    }

    #[\Override]
    public function execute(): void {
        $now = time();
        $endoftoday = strtotime('+1 day', $now);
        $endofupcoming = strtotime('+' . self::UPCOMING_DAYS . ' days', $now);

        // Windows are non-overlapping, so a deadline only ever appears once in the email.
        $windows = [
            self::PERIOD_TODAY => [$now, $endoftoday],
            self::PERIOD_UPCOMING => [$endoftoday + 1, $endofupcoming],
        ];

        // Build up all the reminder data per user, so each user only gets a single email.
        $data = [];
        foreach ([self::TYPE_INITIAL, self::TYPE_AGREED] as $type) {
            foreach ($windows as $period => [$from, $to]) {
                foreach ($this->get_reminder_data($type, $from, $to) as $userid => $activities) {
                    $data[$userid][$type][$period] = $activities;
                }
            }
        }

        mtrace('Found ' . count($data) . ' users who need marking reminders');

        foreach ($data as $userid => $userdata) {
            mtrace('Spawning marking_reminder task for user ' . $userid);
            mailer::queue(
                'marking_reminder',
                null,
                null,
                $userid,
                null,
                [
                    'data' => $userdata,
                    'upcomingdays' => self::UPCOMING_DAYS,
                ]
            );
        }
    }

    /**
     * Find the users who need reminding about a given type of marking deadline within a time window.
     *
     * @param string $type One of the TYPE_ constants.
     * @param int $from Start of the deadline window (inclusive).
     * @param int $to End of the deadline window (inclusive).
     * @return array Keyed by user id, then coursework id, containing ['unmarked' => int].
     */
    private function get_reminder_data(string $type, int $from, int $to): array {
        global $DB;

        if ($type === self::TYPE_AGREED) {
            $field = 'agreedgrademarkingdeadline';
            $capabilities = ['mod/coursework:addagreedgrade', 'mod/coursework:addallocatedagreedgrade'];
        } else {
            $field = 'initialmarkingdeadline';
            $capabilities = 'mod/coursework:addinitialgrade';
        }

        $activities = $DB->get_records_select(
            'coursework',
            "{$field} >= ? AND {$field} <= ?",
            [$from, $to],
            'id',
            'id, course'
        );

        mtrace('Found ' . count($activities) . " activities with {$type} marking deadlines between " .
            userdate($from) . ' and ' . userdate($to));

        $data = [];
        $courses = [];

        foreach ($activities as $activity) {
            $coursework = coursework::get_from_id($activity->id);
            if (!array_key_exists($activity->course, $courses)) {
                $courses[$activity->course] = get_course($activity->course);
            }

            $context = \core\context\module::instance($coursework->get_course_module()->id);
            $markers = get_users_by_capability($context, $capabilities);

            // If either the course or the users is empty for some reason, just skip this activity.
            if (!$courses[$activity->course] || !$markers) {
                mtrace('Skipping activity ' . $activity->id . ' as course or markers are empty');
                continue;
            }

            if ($type === self::TYPE_AGREED) {
                $activitydata = $this->get_data_for_agreed_marking_deadline_reminders(
                    $coursework,
                    $courses[$activity->course],
                    $markers
                );
            } else {
                $activitydata = $this->get_data_for_initial_marking_deadline_reminders(
                    $coursework,
                    $courses[$activity->course],
                    $markers
                );
            }

            foreach ($activitydata as $userid => $userdata) {
                if ($userdata['unmarked'] > 0) {
                    $data[$userid][$activity->id] = $userdata;
                }
            }
        }

        return $data;
    }

    /**
     * For a given activity on a course, find out which users need initial marking reminders, and for how many submissions.
     * @param coursework $coursework
     * @param stdClass $course
     * @param array $users
     * @return array
     */
    private function get_data_for_initial_marking_deadline_reminders(coursework $coursework, stdClass $course, array $users): array {
        global $DB;

        $return = [];

        // If we are using marker allocation, we want to specifically use those markers and their unmarked submissions.
        if ($coursework->allocationenabled) {
            // Find submissions still to be marked by each user.
            foreach ($users as $user) {
                $unmarked = $DB->count_records_sql("
                SELECT COUNT(s.id) as total
                  FROM {coursework_allocation_pairs} ap
                  JOIN {coursework_submissions} s ON (s.courseworkid = ap.courseworkid AND s.allocatabletype = ap.allocatabletype AND s.allocatableid = ap.allocatableid)
             LEFT JOIN {coursework_feedbacks} f ON (f.submissionid = s.id AND f.stageidentifier = ap.stageidentifier)
                 WHERE ap.courseworkid = :courseworkid
                       AND ap.assessorid = :assessorid
                       AND (f.id IS NULL OR f.finalised = 0)
                       AND s.finalisedstatus = :finalisedstatus
            ", [
                    'courseworkid' => $coursework->id,
                    'assessorid' => $user->id,
                    'finalisedstatus' => \mod_coursework\models\submission::FINALISED_STATUS_FINALISED,
                ]);
                $return[$user->id] = [
                    'unmarked' => $unmarked,
                ];
            }
        } else {
            // We can just use all the markers.
            $cm = get_coursemodule_from_instance('coursework', $coursework->id, $course->id);
            $audit = new \mod_coursework\audit($cm->id);
            $summary = $audit->get_marking_summary();
            foreach ($users as $user) {
                $return[$user->id] = [
                    'unmarked' => $summary['unmarked'],
                ];
            }
        }
        return $return;
    }

    /**
     * For a given activity on a course, find out which users need agreed-mark reminders, and for how many submissions.
     * @param coursework $coursework
     * @param stdClass $course
     * @param array $users
     * @return array
     */
    private function get_data_for_agreed_marking_deadline_reminders(coursework $coursework, stdClass $course, array $users): array {
        global $DB;

        $return = [];

        // If marker allocation is enabled, count only the ones the marker is allocated to without final agreed mark.
        if ($coursework->allocationenabled) {
            foreach ($users as $user) {
                $unmarked = $DB->count_records_sql("
                    SELECT COUNT(DISTINCT s.id) as total
                      FROM {coursework_allocation_pairs} ap
                      JOIN {coursework_submissions} s ON (
                           s.courseworkid = ap.courseworkid
                           AND s.allocatabletype = ap.allocatabletype
                           AND s.allocatableid = ap.allocatableid
                         )
                 LEFT JOIN {coursework_feedbacks} f ON (
                           f.submissionid = s.id
                           AND f.stageidentifier = :stageidentifier
                         )
                     WHERE ap.courseworkid = :courseworkid
                           AND ap.assessorid = :assessorid
                           AND (f.id IS NULL OR f.finalised = 0)
                           AND s.finalisedstatus = :finalisedstatus
                ", [
                    'courseworkid' => $coursework->id,
                    'assessorid' => $user->id,
                    'stageidentifier' => 'final_agreed_1',
                    'finalisedstatus' => \mod_coursework\models\submission::FINALISED_STATUS_FINALISED,
                ]);

                $return[$user->id] = ['unmarked' => $unmarked];
            }
            return $return;
        }

        // Otherwise, count any submissions on the coursework without the agreed final mark.
        $total = $DB->count_records_sql("
            SELECT COUNT(DISTINCT s.id)
              FROM {coursework_submissions} s
             WHERE s.courseworkid = :courseworkid
                   AND s.finalisedstatus = :finalisedstatus
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {coursework_feedbacks} f
                        WHERE f.submissionid = s.id
                          AND f.stageidentifier = :stageidentifier
                          AND f.finalised = :feedbackfinalised
                   )
        ", [
            'courseworkid' => $coursework->id,
            'finalisedstatus' => \mod_coursework\models\submission::FINALISED_STATUS_FINALISED,
            'stageidentifier' => 'final_agreed_1',
            'feedbackfinalised' => 1,
        ]);

        foreach ($users as $user) {
            $return[$user->id] = ['unmarked' => $total];
        }

        return $return;
    }
}
