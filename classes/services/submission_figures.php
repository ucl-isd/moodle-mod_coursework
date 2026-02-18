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

use mod_coursework\models\allocation;
use mod_coursework\models\coursework;

/**
 * Class to provide figures around submissions and gradings.
 */
class submission_figures {
    /**
     * Get the submissions for current user as assessor.
     *
     * @param int $instance
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function get_submissions_for_assessor(int $instance): array {

        $coursework = coursework::find($instance);
        $context = $coursework->get_context();
        $submissions = $coursework->get_all_submissions();

        $singlemarker = !$coursework->has_multiple_markers();
        $canaddagreed = has_capability('mod/coursework:addagreedgrade', $context);
        $canaddinitial = has_capability('mod/coursework:addinitialgrade', $context);
        $allocationdisabled = !$coursework->allocation_enabled();
        $canoverride = has_capability('mod/coursework:administergrades', $context);

        $assessorsubmissions = [];

        foreach ($submissions as $submission) {
            if (empty($submission)) {
                continue;
            }

            $submission->submissiondatetime = $submission->timesubmitted;

            // Case 1: Agreed grade only, and not all markers done yet.
            if ($singlemarker && $canaddagreed && !$canaddinitial) {
                if (count($submission->get_assessor_feedbacks()) === $submission->max_number_of_feedbacks()) {
                    $assessorsubmissions[$submission->id] = $submission;
                }

                // Case 2: Admin or no allocation or grading override.
            } else if ($allocationdisabled || $canoverride) {
                $assessorsubmissions[$submission->id] = $submission;

                // Case 3: Allocated assessor or allowed to add agreed grade after initial grading (and sampling if enabled).
            } else {
                $allocatable = $submission->reload()->get_allocatable();
                $allocated = allocation::allocatable_is_allocated_to_assessor($coursework->id, $allocatable->id(), $allocatable->type());
                $sampled = $submission->get_coursework()->sampling_enabled();
                $requiresamplingcheck = $sampled && ($submission->max_number_of_feedbacks() > 1);

                if ($allocated || ($canaddagreed && $submission->all_initial_graded() && (!$sampled || $requiresamplingcheck))) {
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
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function calculate_needsgrading_for_assessor(int $instance): int {
        $coursework = coursework::find($instance);
        $context = $coursework->get_context();

        $addagreedgrade = has_capability('mod/coursework:addagreedgrade', $context);
        $addinitialgrade = has_capability('mod/coursework:addinitialgrade', $context);
        $administergrades = has_capability('mod/coursework:administergrades', $context);

        if (
            !$coursework->has_multiple_markers()
            && !$coursework->allocation_enabled()
            && !$addinitialgrade
            && $addagreedgrade
        ) {
            return 0;
        }

        $needsgrading = 0;
        $submissions = self::get_submissions_for_assessor($instance);

        // Remove unwanted submissions.
        $submissions = self::remove_ungradable_submissions($submissions);

        $canfinalgrade =
            $addagreedgrade
            || $administergrades
            || (
                $addinitialgrade
                &&
                has_capability('mod/coursework:addallocatedagreedgrade', $context)
            );

        if ($canfinalgrade) {
            $remaining = self::remove_final_gradable_submissions($submissions);
            $needsgrading = count($submissions) - count($remaining);
        }

        if ($addinitialgrade || $administergrades) {
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
     * @throws \coding_exception
     */
    private static function remove_ungradable_submissions(array $submissions): array {
        foreach ($submissions as $submission) {
            if (
                !$submission->is_finalised()
                || !empty($submission->get_final_grade())
                || (has_capability('mod/coursework:addallocatedagreedgrade', $submission->get_coursework()->get_context())
                    && !$submission->is_assessor_initial_grader() && $submission->all_initial_graded())
            ) {
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
            if (!empty($submission->all_initial_graded())) {
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
     * @throws \coding_exception
     */
    private static function get_assessor_initial_graded_submissions(array $submissions): array {

        foreach ($submissions as $submission) {
            $hasallfeedbacks = count($submission->get_assessor_feedbacks()) >= $submission->max_number_of_feedbacks();
            $isinitialgrader = $submission->is_assessor_initial_grader();
            $context = $submission->get_coursework()->get_context();
            $isadmin = has_capability('mod/coursework:administergrades', $context);

            // Remove submissions that are not assessable by the current user at this stage.
            if ($hasallfeedbacks || ($isinitialgrader && !$isadmin)) {
                unset($submissions[$submission->id]);
            }
        }
        return $submissions;
    }
}
