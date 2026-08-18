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
use mod_coursework\forms\moderator_stats_form;
use mod_coursework\models\coursework;
use mod_coursework\models\submission;
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
     * @var moderator_stats_form Form object.
     */
    protected moderator_stats_form $form;

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
     * Set the form object.
     * @param moderator_stats_form $form
     * @return void
     */
    public function set_form(moderator_stats_form $form) {
        $this->form = $form;
    }

    /**
     * Get the HTML to print from the template.
     * @return string
     */
    public function report(): string {
        global $OUTPUT, $PAGE;
        // Information about the assessment.
        $assessmentinfo = $this->get_assessment_information_data();

        // Marking summary.
        $markingsummary = $this->get_marking_summary();

        // Stats per marker.
        $markerstats = $this->get_marker_statistics($markingsummary['submissions']);

        // Moderation data per moderator.
        $moderatorstats = $this->get_moderation_data();

        // Moderation summary.
        $moderationsummary = $this->get_moderation_summary($moderatorstats);

        // Moderation sample.
        $moderationsample = $this->get_moderation_sample($markerstats, $moderatorstats);

        // Merge all the data into one object for the template.
        $data = (object)array_merge(
            $assessmentinfo,
            $markingsummary,
            $markerstats,
            $moderatorstats,
            $moderationsummary,
            $moderationsample
        );

        if (isset($this->form)) {
            $data->form = $this->form->render();
        }

        return $OUTPUT->render_from_template('mod_coursework/audit/report', $data);
    }

    /**
     * Get the data for the moderation sample section.
     *
     * @param array $markerstats
     * @param array $moderatorstats
     * @return array
     */
    protected function get_moderation_sample(array $markerstats, array $moderatorstats): array {
        $data = ['moderation_sample' => [
            'marked' => [
                'boundaries' => [],
                'total' => 0,
            ],
            'moderated' => [
                'boundaries' => [],
                'total' => 0,
            ],
        ]];

        if (isset($markerstats['marker_stats_data'])) {
            foreach ($markerstats['marker_stats_data'] as $marker) {
                foreach ($marker['boundaries'] as $key => $boundary) {
                    if (!isset($data['moderation_sample']['marked']['boundaries'][$key])) {
                        $data['moderation_sample']['marked']['boundaries'][$key] = 0;
                    }
                    $data['moderation_sample']['marked']['boundaries'][$key] += $boundary;
                    $data['moderation_sample']['marked']['total'] += $boundary;
                }
            }
        }

        if (isset($moderatorstats['moderator_stats'])) {
            foreach ($moderatorstats['moderator_stats'] as $moderator) {
                foreach ($moderator['boundaries'] as $key => $boundary) {
                    if (!isset($data['moderation_sample']['moderated']['boundaries'][$key])) {
                        $data['moderation_sample']['moderated']['boundaries'][$key] = 0;
                    }
                    $data['moderation_sample']['moderated']['boundaries'][$key] += $boundary;
                    $data['moderation_sample']['moderated']['total'] += $boundary;
                }
            }
        }

        return $data;
    }

    /**
     * Get the data for the moderation summary section.
     * @param array $moderatorstats
     * @return array
     */
    protected function get_moderation_summary(array $moderatorstats): array {
        $data = ['moderation_stats' => [
            'total' => 0,
            'total_agreed' => 0,
            'total_disagreed' => 0,
        ]];

        // Loop through each moderator's stats to count up how many have been moderated.
        if (isset($moderatorstats['moderator_stats'])) {
            foreach ($moderatorstats['moderator_stats'] as $moderator) {
                $data['moderation_stats']['total'] += $moderator['total'];
                $data['moderation_stats']['total_agreed'] += $moderator['agreed'];
                $data['moderation_stats']['total_disagreed'] += $moderator['disagreed'];
            }
        }

        return $data;
    }

    /**
     * Get the data for the marking summary section.
     * @return array
     */
    protected function get_marking_summary(): array {
        // Get all submissions made on the activity.
        $submissions = submission::find_all(['courseworkid' => $this->coursework->id]);

        // Count the marked submissions by any assessor.
        $marked = $this->count_marked_submissions($submissions);

        return [
            'submissions' => $submissions,
            'total_submissions' => count($submissions),
            'marked' => $marked,
            'unmarked' => count($submissions) - $marked,
            'summary_stats' => $this->calculate_grade_statistics($this->get_grades()),
        ];
    }

    /**
     * Get the data we need to display the moderation information.
     * @return array
     */
    protected function get_moderation_data(): array {
        global $DB;

        $data = ['moderator_stats' => []];

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
            $boundaries = $this->get_boundaries();

            foreach ($permoderator as $row) {
                $mdata = [
                    'name' => $row['name'],
                    'boundaries' => [],
                    'agreed' => 0,
                    'disagreed' => 0,
                ];
                foreach ($boundaries as $boundary) {
                    $agreed = $this->count_agreements_within_boundary($row, $boundary, 'agreed');
                    $disagreed = $this->count_agreements_within_boundary($row, $boundary, 'disagreed');

                    // Increase the amount agreed or disagreed by this marker.
                    $mdata['agreed'] += $agreed;
                    $mdata['disagreed'] += $disagreed;

                    // How many were moderated in this boundary?
                    $mdata['boundaries'][] = $agreed + $disagreed;
                }
                $mdata['total'] = $mdata['agreed'] + $mdata['disagreed'];
                $data['moderator_stats'][] = $mdata;
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
        $agreements = $this->adjust_grades_to_percentages($row[$type]);
        // Filter out elements not within the boundaries.
        $min = $boundary[0];
        $max = $boundary[1];
        $filtered = array_filter($agreements, fn($grade) => $grade >= $min && $grade <= $max);
        return count($filtered);
    }

    /**
     * Get the grade boundaries in an array.
     * @return array
     */
    protected function get_boundaries(): array {
        $boundaries = average_grade_no_straddle::get_config_setting('autogradeclassboundaries') ?? [];
        // Reverse the array so it's lowest to highest (probably - if the config setting is correctly formatted).
        $boundaries = array_reverse($boundaries);
        return $boundaries;
    }

    /**
     * Get the data we need to display the per assessor information.
     * @param array $submissions Array of submissions
     * @return array
     */
    protected function get_marker_statistics(array $submissions): array {
        $boundaries = $this->get_boundaries();

        $data = [
            'marker_stats_headers' => [],
            'marker_stats_data' => [],
        ];
        foreach ($boundaries as $boundary) {
            $data['marker_stats_headers'][] = $boundary[0] . ' - ' . $boundary[1];
        }

        // Now let's calculate everything per marker.
        $assessors = $this->coursework->get_all_assessors();
        foreach ($assessors as $assessor) {
            // Get the grade data for just this assessor.
            $grades = $this->get_grades($assessor->id);

            $assessordata = [];
            $assessordata['name'] = fullname($assessor);

            // Boundary statistics.
            $assessordata['boundaries'] = [];
            foreach ($boundaries as $boundary) {
                $assessordata['boundaries'][] = $this->count_grades_within_boundary($grades, $boundary);
            }

            // Calculate their overall grade statistics.
            $stats = $this->calculate_grade_statistics($grades);
            $assessordata['mean'] = $stats['mean'];
            $assessordata['median'] = $stats['median'];
            $assessordata['sd'] = $stats['sd'];

            // How many of the submissions did this assessor mark?
            $marked = $this->count_marked_submissions($submissions, $assessor->id);
            $unmarked = count($submissions) - $marked;

            $assessordata['marked'] = $marked;
            $assessordata['unmarked'] = $unmarked;

            $data['marker_stats_data'][] = $assessordata;
        }

        return $data;
    }

    /**
     * Count how many of the submissions were marked.
     * @param array $submissions Array of submissions
     * @param int|null $assessorid If this is set, only count those marked by this user.
     * @return int
     */
    protected function count_marked_submissions(array $submissions, ?int $assessorid = null): int {
        $marked = 0;
        foreach ($submissions as $submission) {
            // Get all assessor stage feedbacks for this submission.
            $feedback = $submission->get_assessor_feedbacks();

            // If we specify an assessor, filter by just theirs.
            if (!is_null($assessorid)) {
                $feedback = array_filter($feedback, function ($f) use ($assessorid) {
                    return (int)$f->assessorid === $assessorid;
                });
            }

            // If any exist, yes this was marked.
            if ($feedback) {
                $marked++;
            }
        }
        return $marked;
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
     * Adjust grades in the array to percentages, based on maxgrade of the activity.
     * @param array $grades
     * @return array
     */
    protected function adjust_grades_to_percentages(array $grades): array {
        $maxgrade = $this->coursework->get_max_grade();
        if ($maxgrade === 100) {
            return $grades;
        }
        return array_map(fn($grade) => round(($grade / $maxgrade) * 100, 2), $grades);
    }

    /**
     * Get the data we need to display the summary information.
     * @return array
     */
    protected function get_assessment_information_data(): array {
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
            'grade_boundaries' => $this->get_boundaries(),
        ];
    }

    /**
     * Calculate grade statistics from supplied grades.
     *
     * @param array $grades Array of grades given
     * @return array
     */
    protected function calculate_grade_statistics(array $grades): array {
        $data = [];

        if (count($grades) < 1) {
            $data['mean'] = 0;
            $data['median'] = 0;
            $data['max'] = 0;
            $data['min'] = 0;
            $data['sd'] = 0;
            $data['flagged'] = $this->count_flagged_submissions();
            return $data;
        }

        // Mean grade.
        $data['mean'] = round((array_sum($grades) / count($grades)), 2);

        // Median grade.
        $midpoint = (int)floor(count($grades) / 2);
        // If there is an odd number of grades, the median will be the exact middle so use that.
        // If there is an even number of grades, average the 2 closest to the middle.
        $data['median'] = round((count($grades) % 2) ? $grades[$midpoint] : ($grades[$midpoint - 1] + $grades[$midpoint]) / 2, 2);

        // Highest grade.
        $data['max'] = max($grades);

        // Lowest grade.
        $data['min'] = min($grades);

        // Standard deviation.
        $data['sd'] = $this->calculate_standard_deviation($grades, (float)$data['mean']);

        // Plagiarism flag count.
        $data['flagged'] = $this->count_flagged_submissions();

        return $data;
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
        $result = 0;
        foreach ($values as $value) {
            $result += pow(($value - $mean), 2);
        }
        return round(sqrt($result / count($values)), 2);
    }

    /**
     * Get an array of all the unique individual grades given by markers.
     *
     * @param int|null $assessorid Assessor ID (if null, it will get everyone).
     * @return array
     */
    protected function get_grades(?int $assessorid = null): array {
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

        sort($grades);

        // If the max grade on the activity is not 100, then we need to adjust the grades into correct percentages.
        $grades = $this->adjust_grades_to_percentages($grades);

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
            SELECT f.id, f.stageidentifier, f.submissionid, f.grade
              FROM {coursework_feedbacks} f
              JOIN {coursework_submissions} s ON f.submissionid = s.id
             WHERE s.courseworkid = :id
               AND f.finalised = 1
               AND f.stageidentifier LIKE 'assessor_%'
               AND f.ismoderation = 0
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

    /**
     * Get appraisal record.
     * @param int $courseworkid
     * @return mixed
     */
    public static function get_moderator_appraisal(int $courseworkid) {
        global $DB;
        return $DB->get_record('coursework_moderator_appraisals', ['courseworkid' => $courseworkid]);
    }

    /**
     * Get the moderator appraisal record for this audit.
     * @return array
     */
    public function get_moderator_appraisal_form_data(): array {
        $data = static::get_moderator_appraisal($this->coursework->id);
        if (!$data) {
            return [];
        }
        if (!is_null($data->markingcriteriarecommendations)) {
            $data->markingcriteriarecommendations = ['text' => $data->markingcriteriarecommendations];
        }
        if (!is_null($data->markersmarkingrecommendations)) {
            $data->markersmarkingrecommendations = ['text' => $data->markersmarkingrecommendations];
        }
        if (!is_null($data->feedbackrecommendations)) {
            $data->feedbackrecommendations = ['text' => $data->feedbackrecommendations];
        }
        if (!is_null($data->goodpracticecomments)) {
            $data->goodpracticecomments = ['text' => $data->goodpracticecomments];
        }
        if (!is_null($data->generalcomments)) {
            $data->generalcomments = ['text' => $data->generalcomments];
        }
        // Get the file is there is one.
        $draftitemid = file_get_submitted_draft_itemid('file');
        $context = \core\context\module::instance($this->cmid);
        file_prepare_draft_area(
            $draftitemid,
            $context->id,
            'mod_coursework',
            'appraisal',
            $this->coursework->id,
        );
        $data->file = $draftitemid;
        return (array)$data;
    }
}
