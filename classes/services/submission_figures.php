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
 * Services for submission and grading figures.
 *
 * @package    mod_coursework
 * @copyright  2025 UCL <m.opitz@ucl.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\services;

use mod_coursework\models\coursework;
use mod_coursework\models\submission;

/**
 * Class to provide figures around submissions and gradings.
 */
class submission_figures {

    /**
     * Get the submissions for current user as assessor.
     *
     * @param int $instance
     * @return array
     */
    public static function get_submissions_for_assessor(int $instance): array {
        global $USER;

        $coursework = coursework::find($instance);
        $assessorsubmissions = [];
        $context = $coursework->get_context();
        $submissions = $coursework->get_all_submissions();

        if (!$coursework->has_multiple_markers()
                && has_capability('mod/coursework:addagreedgrade', $context)
                && !has_capability('mod/coursework:addinitialgrade', $context)) {

            foreach ($submissions as $sub) {
                $submission = submission::find($sub);

                if (count($submission->get_assessor_feedbacks()) < $submission->max_number_of_feedbacks()) {
                    continue;
                }

                if ($submission->final_grade_agreed()) {
                    $submission->submissiondatetime = $submission->timesubmitted; // We need that for the feedback tracker.
                }

                $assessorsubmissions[] = $submission;
            }
        } else if (is_siteadmin($USER) ||
            !$coursework->allocation_enabled() ||
            has_capability('mod/coursework:administergrades', $context)) {
            foreach ($submissions as $sub) {
                $submission = submission::find($sub);
                $assessorsubmissions[$submission->id] = $submission;
            }

        } else {
            foreach ($submissions as $sub) {
                $submission = submission::find($sub);
                if (empty($submission)) {
                    continue;
                }

                if ($coursework->assessor_has_any_allocation_for_student($submission->reload()->get_allocatable())
                    || (has_capability('mod/coursework:addagreedgrade', $context)
                        && (($submission->all_inital_graded() && !$submission->get_coursework()->sampling_enabled())
                            || ($submission->get_coursework()->sampling_enabled()
                                && $submission->all_inital_graded()
                                && ($submission->max_number_of_feedbacks() > 1)
                            )
                        )
                    )
                ) {
                    $assessorsubmissions[$submission->id] = $submission;
                }
            }
        }

        return $assessorsubmissions;
    }

    /**
     * Calculate the needed gradings for the current user as assessor.
     *
     * @param int $instance
     * @return int
     */
    public static function calculate_needsgrading_for_assessor(int $instance): int {
        $coursework = coursework::find($instance);
        $context = $coursework->get_context();

        if (!$coursework->has_multiple_markers()
                && !$coursework->allocation_enabled()
                && !has_capability('mod/coursework:addinitialgrade', $context)
                && has_capability('mod/coursework:addagreedgrade', $context)) {
            return 0;
        }

        $needsgrading = 0;
        $submissions = self::get_submissions_for_assessor($instance);

        // Remove unwanted submissions.
        $submissions = self::remove_ungradable_submissions($submissions);

        $canfinalgrade = has_any_capability([
                'mod/coursework:addagreedgrade',
                'mod/coursework:administergrades',
            ], $context) || (
                has_capability('mod/coursework:addinitialgrade', $context)
                    && has_capability('mod/coursework:addallocatedagreedgrade', $context)
            );

        if ($canfinalgrade) {
            $remaining = self::remove_final_gradable_submissions($submissions);
            $needsgrading = count($submissions) - count($remaining);
        }

        $caninitialgrade = has_any_capability([
            'mod/coursework:addinitialgrade',
            'mod/coursework:administergrades',
        ], $context);

        if ($caninitialgrade) {
            $submissions = self::remove_final_gradable_submissions($submissions);
            $needsgrading += count(self::get_assessor_initial_graded_submissions($submissions));
        }

        return $needsgrading;
    }

    /**
     * Remove final unfinalised, ungradeable and graded submissions from an array of submissions.
     *
     * @param array $submissions
     * @return array
     */
    private static function remove_ungradable_submissions(array $submissions): array {
        foreach ($submissions as $submission) {

            if (empty($submission->finalised)
                || !empty($submission->get_final_grade())
                || (has_capability('mod/coursework:addallocatedagreedgrade', $submission->get_coursework()->get_context())
                    && !$submission->is_assessor_initial_grader() && $submission->all_inital_graded())) {
                unset($submissions[$submission->id]);
            }
        }
        return $submissions;
    }

    /**
     * Remove final gradable submissions from an array of submissions.
     *
     * @param array $submissions
     * @return array
     */
    private static function remove_final_gradable_submissions(array $submissions): array {
        foreach ($submissions as $submission) {
            if (!empty($submission->all_inital_graded()) ) {
                unset($submissions[$submission->id]);
            }
        }
        return $submissions;
    }

    /**
     * Get the initial graded submissions for the assessor.
     *
     * @param array $submissions
     * @return array
     */
    private static function get_assessor_initial_graded_submissions(array $submissions): array {
        global $USER;

        foreach ($submissions as $submission) {
            $hasallfeedbacks = count($submission->get_assessor_feedbacks()) >= $submission->max_number_of_feedbacks();
            $isinitialgrader = $submission->is_assessor_initial_grader();
            $context = $submission->get_coursework()->get_context();
            $isadmin = has_capability('mod/coursework:administergrades', $context) || is_siteadmin($USER->id);

            // Remove submissions that are not assessable by the current user at this stage.
            if ($hasallfeedbacks || ($isinitialgrader && !$isadmin)) {
                unset($submissions[$submission->id]);
            }
        }
        return $submissions;
    }
}
