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
 * Behat data generator for mod_assign.
 *
 * @package   mod_assign
 * @category  test
 * @copyright 2021 Andrew Lyons
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_coursework_generator extends behat_generator_base
{
    /**
     * Get a list of the entities that Behat can create using the generator step.
     *
     * @return array
     */
    protected function get_creatable_entities(): array
    {
        return [
            'submissions' => [
                'singular' => 'submission',
                'datagenerator' => 'submission',
                'required' => ['assign', 'user'],
                'switchids' => ['assign' => 'assignid', 'user' => 'userid'],
            ],
            'extensions' => [
                'singular' => 'extension',
                'datagenerator' => 'extension',
                'required' => ['assign', 'user', 'extensionduedate'],
                'switchids' => ['assign' => 'cmid', 'user' => 'userid'],
            ],
        ];
    }

    /**
     *
     * @Given :count :entitytype exist with the following data:
     *
     * @param string $entitytype The name of the type entity to add
     * @param int $count
     * @param TableNode $data
     *
     */
    /**
     * Creates/updates a moderation agreement for a student's submission in the coursework module.
     *
     * Expected keys in $table:
     *  - Agreement: (string) moderator's agreement text
     *  - Comment:   (string) moderator's comment
     *
     * @param string $studentfullname Full name of the student (as used in your helpers)
     * @param string $courseworkname Coursework name
     * @param string $moderatorfullname Full name of the moderator
     * @param TableNode $table Behat table with "Agreement" and "Comment" keys
     *
     * @throws coding_exception If any entity cannot be resolved.
     */
    public function create_moderation_agreement(
        string    $studentfullname,
        string    $courseworkname,
        string    $moderatorfullname,
        TableNode $table
    ): void
    {
        global $DB;

        // Resolve users (student & moderator).
        // NOTE: If your generator provides different helpers,
        //       swap these calls accordingly (e.g., get_user_by_fullname()).
        $student = $this->get_user_from_username($studentfullname);
        if (!$student || empty($student->id)) {
            throw new \coding_exception("Student '{$studentfullname}' not found");
        }

        $moderator = $this->get_user_from_username($moderatorfullname);
        if (!$moderator || empty($moderator->id)) {
            throw new \coding_exception("Moderator '{$moderatorfullname}' not found");
        }

        // Resolve coursework module by its name.
        $coursework = $DB->get_record('coursework', ['name' => $courseworkname], '*', IGNORE_MISSING);
        if (!$coursework) {
            throw new \coding_exception("Coursework '{$courseworkname}' not found");
        }

        // Resolve the student's submission within this coursework.
        $submission = $DB->get_record('coursework_submissions', [
            'courseworkid' => $coursework->id,
            'allocatableid' => $student->id,
        ], '*', IGNORE_MISSING);
        if (!$submission) {
            throw new \coding_exception("Submission for '{$studentfullname}' not found in '{$courseworkname}'");
        }

        // Ensure the moderator allocation exists for this student and stage.
        $allocation = $DB->get_record('coursework_allocation_pairs', [
            'courseworkid' => $coursework->id,
            'assessorid' => $moderator->id,
            'allocatableid' => $student->id,
            'stageidentifier' => 'moderator',
        ], '*', IGNORE_MISSING);
        if (!$allocation) {
            throw new \coding_exception("Moderator '{$moderatorfullname}' is not allocated to '{$studentfullname}' for '{$courseworkname}'");
        }

        // Resolve feedback created by an assessor stage (e.g., assessor_1, assessor_2).
        $feedback = $DB->get_record_sql(
            "SELECT *
           FROM {coursework_feedbacks}
          WHERE submissionid = :submissionid
            AND stageidentifier LIKE 'assessor_%'",
            ['submissionid' => $submission->id]
        );
        if (!$feedback) {
            throw new \coding_exception("Assessor feedback for '{$studentfullname}' not found in '{$courseworkname}'");
        }

        // Extract provided table values (Agreement + Comment).
        $data = $table->getRowsHash();
        $agreementtext = $data['Agreement'] ?? '';
        $comment = $data['Comment'] ?? '';

        // Check if an agreement already exists for this moderator+feedback.
        $existing = $DB->get_record('coursework_mod_agreements', [
            'feedbackid' => $feedback->id,
            'moderatorid' => $moderator->id,
        ], '*', IGNORE_MISSING);

        // Prepare the agreement record for insert or update.
        $agreement = new \stdClass();
        $agreement->feedbackid = $feedback->id;
        $agreement->moderatorid = $moderator->id;
        $agreement->agreement = $agreementtext;
        $agreement->modcomment = $comment;
        $agreement->modcommentformat = FORMAT_HTML; // or FORMAT_MOODLE/FORMAT_PLAIN per your usage
        $agreement->lasteditedby = $moderator->id;
        $agreement->timecreated = time();
        $agreement->timemodified = time();

        if ($existing) {
            // Update existing record.
            $agreement->id = $existing->id;
            $DB->update_record('coursework_mod_agreements', $agreement);
        } else {
            // Insert new record.
            $DB->insert_record('coursework_mod_agreements', $agreement);
        }

    }
}