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
 * Manager object for the Grading Audit report.
 *
 * @package    mod_coursework
 * @copyright  2026 onwards Catalyst IT {@link http://www.catalyst-eu.net/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Conn Warwicker <conn.warwicker@catalyst-eu.net>
 */

namespace mod_coursework;

use mod_coursework\auto_grader\average_grade_no_straddle;
use mod_coursework\models\coursework;
use stdClass;

/**
 * Audit class.
 */
class audit {
    /**
     * @var int Course Module ID.
     */
    protected int $cmid;

    /**
     * @var coursework Coursework instance.
     */
    protected coursework $coursework;

    /**
     * @var stdClass Course object.
     */
    protected stdClass $course;

    /**
     * Build the audit object from the course module id.
     * @param int $cmid
     */
    public function __construct(int $cmid) {
        // Get the course and course module if it's a valid coursework cmid.
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'coursework');
        $this->cmid = $cmid;
        $this->course = $course;
        $this->coursework = coursework::get_from_id($cm->instance, MUST_EXIST);
    }

    /**
     * Get the HTML to print from the template.
     * @return string
     */
    public function report(): string {
        global $OUTPUT;
        $data = (object)array_merge(
            $this->get_summary_data(),
            $this->get_overall_data(),
            $this->get_assessor_data(),
            $this->get_moderation_data(),
        );
        return $OUTPUT->render_from_template('mod_coursework/audit/report', $data);
    }

    /**
     * Get the data we need to display the moderation information.
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function get_moderation_data(): array {
        global $DB;

        $data = [];

        // First, count how many submissions have been moderated.
        $feedbacks = $this->get_feedback();
        if ($feedbacks) {
            $feedbackids = array_keys($feedbacks);
            [$insql, $inparams] = $DB->get_in_or_equal($feedbackids);

            // Get all moderation agreement records for any of these submissions.
            $agreements = $DB->get_records_sql(
                "SELECT a.id, a.moderatorid, a.agreement, f.grade,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
                       FROM {coursework_mod_agreements} a
                       JOIN {coursework_feedbacks} f ON f.id = a.feedbackid
                       JOIN {coursework_submissions} s ON s.id = f.submissionid
                       JOIN {user} u ON u.id = a.moderatorid
                      WHERE f.id {$insql}",
                $inparams,
            );

            // Loop through the records and split into data per moderator.
            $permoderator = [];
            if ($agreements) {
                foreach ($agreements as $agreement) {
                    if (!isset($permoderator[$agreement->moderatorid])) {
                        $permoderator[$agreement->moderatorid] = [
                            'name' => fullname($agreement),
                            'agreed' => [],
                            'disagreed' => [],
                        ];
                    }
                    $permoderator[$agreement->moderatorid][$agreement->agreement][] = $agreement->grade;
                }
            }

            // Now we've got agree and disagree arrays with every mark they agreed/disagreed with.
            // We need to go through the grade boundaries and work out how many they disagreed with for each.
            $boundaries = average_grade_no_straddle::get_config_setting('autogradeclassboundaries');
            if (!is_array($boundaries)) {
                $boundaries = [];
            }

            // Reverse the array so it's lowest to highest (probably - if the config setting is correctly formatted).
            $boundaries = array_reverse($boundaries);

            $data['moderators_data'] = [];
            foreach ($permoderator as $row) {
                $mdata = [];
                $mdata['name'] = $row['name'];
                $mdata['boundaries'] = [];
                foreach ($boundaries as $boundary) {
                    $mdata['boundaries'][] = $this->count_agreements_within_boundary($row, $boundary, 'disagreed');
                }
                $data['moderators_data'][] = $mdata;
            }
        }
        return $data;
    }

    /**
     * Count agreements of a given type, within a grade boundary.
     * @param array $row Array of data, with 'agreed' and 'disagreed' as keys, and arrays of grades as values.
     * @param array $boundary Min and Max grade for the boundary.
     * @param string $type 'agreed' or 'disagreed'.
     * @return int
     */
    protected function count_agreements_within_boundary(array $row, array $boundary, string $type): int {
        $agreements = $row[$type];
        // Filter out elements not within the boundaries.
        $min = $boundary[0];
        $max = $boundary[1];
        $filtered = array_filter($agreements, fn($grade) => $grade >= $min && $grade <= $max);
        return count($filtered);
    }

    /**
     * Get the data we need to display the per assessor information.
     *
     * @return array
     */
    protected function get_assessor_data(): array {
        // First work out what headers we need, based on the grade boundaries.
        $boundaries = average_grade_no_straddle::get_config_setting('autogradeclassboundaries');
        if (!is_array($boundaries)) {
            $boundaries = [];
        }

        // Reverse the array so it's lowest to highest (probably - if the config setting is correctly formatted).
        $boundaries = array_reverse($boundaries);

        $data = [];
        $data['assessor_headers'] = [];
        foreach ($boundaries as $boundary) {
            $data['assessor_headers'][] = $boundary[0] . ' - ' . $boundary[1];
        }

        // Now let's calculate everything per marker.
        $data['assessors_data'] = [];
        $assessors = $this->coursework->get_all_assessors();
        foreach ($assessors as $id => $assessor) {
            // Get the grade data for just this assessor.
            $grades = $this->get_grades($assessor->id);

            $assdata = [];
            $assdata['name'] = fullname($assessor);

            // Boundary statistics.
            $assdata['boundaries'] = [];
            foreach ($boundaries as $boundary) {
                $assdata['boundaries'][] = $this->count_grades_within_boundary($grades, $boundary);
            }

            // Calculate their overall statistics.
            $stats = $this->calculate_statistics($grades);
            $assdata['mean'] = $stats['stats_mean'];
            $assdata['median'] = $stats['stats_median'];
            $assdata['sd'] = $stats['stats_sd'];
            $data['assessors_data'][] = $assdata;
        }

        return $data;
    }

    /**
     * Given a set of grades and an upper/lower boundary, count how many exist within the boundary.
     *
     * @param array $grades Array of grade values.
     * @param array $boundary Boundary array [min, max].
     * @return int
     */
    protected function count_grades_within_boundary(array $grades, array $boundary): int {
        $min = $boundary[0];
        $max = $boundary[1];
        $filtered = array_filter($grades, fn($grade) => $grade >= $min && $grade <= $max);
        return count($filtered);
    }

    /**
     * Get the data we need to display the summary information.
     *
     * @return array
     */
    protected function get_summary_data(): array {
        // Get the assessors assigned on the activity.
        $uniqueassessors = $this->coursework->get_all_assessors();
        $assessors = [];
        foreach ($uniqueassessors as $assessor) {
            $assessors[$assessor->id] = fullname($assessor);
        }

        // Get the moderators for this activity.
        $uniquemoderators = get_users_by_capability(
            \core\context\module::instance($this->cmid),
            'mod/coursework:moderate',
        );
        $moderators = [];
        foreach ($uniquemoderators as $moderator) {
            $moderators[$moderator->id] = fullname($moderator);
        }

        return [
            'course' => $this->course->fullname,
            'coursework' => $this->coursework->name,
            'assessors' => implode(', ', $assessors),
            'moderators' => implode(', ', $moderators),
            'submissions' => count($this->coursework->retrieve_submissions_by_coursework()),
        ];
    }

    /**
     * Calculate grade statistics from supplied grades.
     *
     * @param array $grades
     * @return array
     */
    protected function calculate_statistics(array $grades): array {
        $data = [];

        if (count($grades) < 1) {
            $data['stats_mean'] = 0;
            $data['stats_median'] = 0;
            $data['stats_max'] = 0;
            $data['stats_min'] = 0;
            $data['stats_sd'] = 0;
            $data['stats_flagged'] = $this->count_flagged_submissions();
            return $data;
        }

        // Mean grade.
        $data['stats_mean'] = round((array_sum($grades) / count($grades)), 2);

        // Median grade.
        $midpoint = (int)floor(count($grades) / 2);
        // If there is an odd number of grades, the median will be the exact middle so use that.
        // If there is an even number of grades, average the 2 closest to the middle.
        $data['stats_median'] = round((count($grades) % 2) ? $grades[$midpoint] : ($grades[$midpoint - 1] + $grades[$midpoint]) / 2, 2);

        // Highest grade.
        $data['stats_max'] = max($grades);

        // Lowest grade.
        $data['stats_min'] = min($grades);

        // Standard deviation.
        $data['stats_sd'] = $this->calculate_standard_deviation($grades, (float)$data['stats_mean']);

        // Plagiarism flag count.
        $data['stats_flagged'] = $this->count_flagged_submissions();

        return $data;
    }

    /**
     * Get the data we need to display the overall statistics information.
     *
     * @return array
     */
    protected function get_overall_data(): array {
        return $this->calculate_statistics($this->get_grades());
    }

    /**
     * Count the number of plagiarism flags on the submissions for this activity.
     * @return int
     */
    protected function count_flagged_submissions(): int {
        global $DB;
        $feedback = $this->get_feedback();
        $ids = array_unique(array_column($feedback, 'submissionid'));
        if ($ids) {
            [$insql, $inparams] = $DB->get_in_or_equal($ids);
            return $DB->count_records_select(
                'coursework_plagiarism_flags',
                "submissionid {$insql}",
                $inparams
            );
        } else {
            return 0;
        }
    }

    /**
     * Calculate the standard deviation of the supplied grades.
     *
     * @param array $values Array of values
     * @param float $mean Mean of the the values
     * @return float
     */
    private function calculate_standard_deviation(array $values, float $mean): float {
        $a = 0;
        foreach ($values as $value) {
            $a += pow(($value - $mean), 2);
        }
        return round(sqrt($a / count($values)), 2);
    }

    /**
     * Get an array of all the unique individual grades given by markers.
     *
     * @param int|null $assessorid Assessor ID (if null, it will get everyone).
     * @return array
     */
    protected function get_grades(int $assessorid = null): array {
        $feedbacks = $this->get_feedback($assessorid);
        $grades = [];
        foreach ($feedbacks as $feedback) {
            // Only interested in the individual marks, not the agreed mark.
            if (strpos($feedback->stageidentifier, 'assessor_') !== false) {
                if (!is_null($feedback->grade) && $feedback->grade !== '') {
                    // I noticed the actual DB field is a varchar. Will this always be a number? Testing with rubrics
                    // still produces a number, so I'm not sure if it's possible for it to be a string somehow.
                    $grades[] = (float)$feedback->grade;
                }
            }
        }

        // Make sure we don't have any weird empty values.
        // We still need 0 as a valid value though, so can't just do basic array_filter().
        $grades = array_filter($grades, fn($val) => !is_null($val) && $val !== '');

        sort($grades);

        return $grades;
    }

    /**
     * Get all the submitted feedback marks for this activity.
     *
     * @param int|null $assessorid Assessor ID (if null it will get everyone).
     * @return array
     */
    protected function get_feedback(int $assessorid = null): array {
        global $DB;
        $sql = "
            SELECT f.*
              FROM {coursework_feedbacks} f
              JOIN {coursework_submissions} s ON f.submissionid = s.id
             WHERE s.courseworkid = :id
        ";
        $params = [
            'id' => $this->coursework->id,
        ];

        if (!is_null($assessorid)) {
            $sql .= " AND f.assessorid = :assessorid";
            $params['assessorid'] = $assessorid;
        }

        return $DB->get_records_sql($sql, $params);
    }
}
