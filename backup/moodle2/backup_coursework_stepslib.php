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
 * @copyright  2017 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class backup_coursework_activity_structure_step extends backup_activity_structure_step {
    protected function define_structure() {
        global $DB;

        foreach (['coursework_submissions',
                      'coursework_allocation_pairs',
                      'coursework_mod_set_members',
                      'coursework_sample_set_mbrs',
                      'coursework_extensions',
                      'coursework_person_deadlines'] as $tablename) {
            $DB->execute("update {{$tablename}} set allocatableuser=0, allocatablegroup=0");
            $DB->execute("update {{$tablename}} set allocatableuser=allocatableid where allocatabletype='user'");
            $DB->execute("update {{$tablename}} set allocatablegroup=allocatableid where allocatabletype='group'");
        }

        $userinfo = $this->get_setting_value('userinfo');

        $coursework = new backup_nested_element('coursework', ['id'],
                                              ['formid',
                                                    'course',
                                                    'name',
                                                    'intro',
                                                    'introformat',
                                                    'timecreated',
                                                    'timemodified',
                                                    'grade',
                                                    'deadline',
                                                    'srsinclude',
                                                    'numberofmarkers',
                                                    'blindmarking',
                                                    'maxbytes',
                                                    'generalfeedback',
                                                    'individualfeedback',
                                                    'feedbackcomment',
                                                    'feedbackcommentformat',
                                                    'generalfeedbacktimepublished',
                                                    'courseworktype',
                                                    'assessorallocationstrategy',
                                                    'moderationenabled',
                                                    'allocationenabled',
                                                    'moderatorallocationstrategy',
                                                    'viewothersfeedback',
                                                    'autoreleasefeedback',
                                                    'retrospectivemoderation',
                                                    'studentviewcomponentfeedbacks',
                                                    'studentviewmoderatorfeedbacks',
                                                    'strictanonymity',
                                                    'studentviewfinalfeedback',
                                                    'studentviewcomponentgrades',
                                                    'studentviewfinalgrade',
                                                    'studentviewmoderatorgrade',
                                                    'strictanonymitymoderator',
                                                    'allowlatesubmissions',
                                                    'mitigationenabled',
                                                    'enablegeneralfeedback',
                                                    'maxfiles',
                                                    'filetypes',
                                                    'use_groups',
                                                    'grouping_id',
                                                    'allowearlyfinalisation',
                                                    'showallfeedbacks',
                                                    'startdate',
                                                    'samplingenabled',
                                                    'extensionsenabled',
                                                    'assessoranonymity',
                                                    'viewinitialgradeenabled',
                                                    'automaticagreement',
                                                    'automaticagreementrange',
                                                    'automaticagreementstrategy',
                                                    'averagerounding',
                                                    'feedbackreleaseemail',
                                                    'gradeeditingtime',
                                                    'markingdeadlineenabled',
                                                    'initialmarkingdeadline',
                                                    'agreedgrademarkingdeadline',
                                                    'markingreminderenabled',
                                                    'submissionnotification',
                                                    'personaldeadlineenabled',
                                                    'relativeinitialmarkingdeadline',
                                                    'relativeagreedmarkingdeadline',
                                                    'autopopulatefeedbackcomment',
                                                    'moderationagreementenabled',
                                                    'draftfeedbackenabled',
                                                    'processenrol',
                                                    'processunenrol',
                                                    'plagiarismflagenabled',
                                                  ]);

            $samplestrategies = new backup_nested_element('coursework_sample_set_rules');

            $samplestrategy = new backup_nested_element('coursework_sample_set_rule', ['id'],
                                                                ['courseworkid',
                                                                     'sample_set_plugin_id',
                                                                     'ruleorder',
                                                                     'ruletype',
                                                                     'upperlimit',
                                                                     'lowerlimit',
                                                                     'stage_identifier']);

            $coursework->add_child($samplestrategies);

            $samplestrategies->add_child($samplestrategy);

            $samplestrategy->set_source_table('coursework_sample_set_rules',
                                        ['courseworkid' => backup::VAR_PARENTID]);

        if ($userinfo) {

            $plagiarismflags = new backup_nested_element('coursework_plagiarism_flags');

            $plagiarismflag = new backup_nested_element('coursework_plagiarism_flag', ['id'],
                                                            [
                                                                    "courseworkid",
                                                                    "submissiond",
                                                                    "status",
                                                                    "comment",
                                                                    "comment_format",
                                                                    "createdby",
                                                                    "timecreated",
                                                                    "lastmodifiedby",
                                                                    "timemodified",
                                                            ]);

            $moderationagreements = new backup_nested_element('coursework_mod_agreements');

            $moderationagreement = new backup_nested_element('coursework_mod_agreement', ['id'],
                                                    [
                                                        "feedbackid",
                                                        "moderatorid",
                                                        "agreement",
                                                        "timecreated",
                                                        "timemodified",
                                                        "lasteditedby",
                                                        "modcomment",
                                                        "modecommentformat",
                                                    ]);

            $feedbacks = new backup_nested_element('coursework_feedbacks');

            $feedback = new backup_nested_element('coursework_feedback', ['id'],
                                                 [
                                                     "submissionid",
                                                     "assessorid",
                                                     "timecreated",
                                                     "timemodified",
                                                     "grade",
                                                     "cappedgrade",
                                                     "feedbackcomment",
                                                     "timepublished",
                                                     "lasteditedbyuser",
                                                     "isfinalgrade",
                                                     "ismoderation",
                                                     "feedbackcommentformat",
                                                     "entry_id",
                                                     "markernumber",
                                                     "stage_identifier",
                                                     "finalised",
                                                 ]);

            $submissions = new backup_nested_element('coursework_submissions');

            $submission = new backup_nested_element('coursework_submission', ['id'],
                                                  [
                                                      "courseworkid",
                                                      "userid",
                                                      "authorid",
                                                      "timecreated",
                                                      "timemodified",
                                                      "finalised",
                                                      "manualsrscode",
                                                      "createdby",
                                                      "lastupdatedby",
                                                      "allocatableid",
                                                      "allocatabletype",
                                                      'allocatableuser',
                                                      'allocatablegroup',
                                                      "firstpublished",
                                                      "lastpublished",
                                                      "timesubmitted",
                                                  ]);
            $reminders = new backup_nested_element('coursework_reminders');

            $reminder = new backup_nested_element('coursework_reminder', ['id'],
                                                [
                                                    "userid",
                                                    "coursework_id",
                                                    "remindernumber",
                                                    "extension",
                                                ]);

            $pairs = new backup_nested_element('coursework_allocation_pairs');

            $pair = new backup_nested_element('coursework_allocation_pair', ['id'],
                                            [
                                                "courseworkid",
                                                "assessorid",
                                                "manual",
                                                "moderator",
                                                "timelocked",
                                                "stage_identifier",
                                                "allocatableid",
                                                "allocatabletype",
                                                'allocatableuser',
                                                'allocatablegroup',
                                            ]);

            $modsetrules = new backup_nested_element('coursework_mod_set_rules');

            $modsetrule = new backup_nested_element('coursework_mod_set_rule', ['id'],
                                                  [
                                                      "courseworkid",
                                                      "rulename",
                                                      "ruleorder",
                                                      "upperlimit",
                                                      "lowerlimit",
                                                      "minimum",
                                                  ]);

            $allocationconfigs = new backup_nested_element('coursework_allocation_configs');

            $allocationconfig = new backup_nested_element('coursework_allocation_config', ['id'],
                                                         [
                                                             "courseworkid",
                                                             "allocationstrategy",
                                                             "assessorid",
                                                             "value",
                                                             "purpose",
                                                         ]);

            $modsetmembers = new backup_nested_element('coursework_mod_set_members');

            $modsetmember = new backup_nested_element('coursework_mod_set_member', ['id'],
                                                    [
                                                        "courseworkid",
                                                        "allocatableid",
                                                        "allocatabletype",
                                                        'allocatableuser',
                                                        'allocatablegroup',
                                                        "stage_identifier",
                                                    ]);

            $extensions = new backup_nested_element('coursework_extensions');

            $extension = new backup_nested_element('coursework_extension', ['id'],
                                                 [
                                                     "allocatableid",
                                                     "allocatabletype",
                                                     'allocatableuser',
                                                     'allocatablegroup',
                                                     "courseworkid",
                                                     "extended_deadline",
                                                     "pre_defined_reason",
                                                     "createdbyid",
                                                     "extra_information_text",
                                                     "extra_information_format",
                                                 ]);

            $personaldeadlines = new backup_nested_element('coursework_person_deadlines');

            $personaldeadline = new backup_nested_element('coursework_person_deadline', ['id'],
                                                [
                                                    "allocatableid",
                                                    'allocatableuser',
                                                    'allocatablegroup',
                                                    "allocatabletype",
                                                    "courseworkid",
                                                    "personal_deadline",
                                                    "createdbyid",
                                                    "timecreated",
                                                    "timemodified",
                                                    "lastmodifiedbyid",
                                                ]);

            $samplemembers = new backup_nested_element('coursework_sample_set_mbrs');

            $samplemember = new backup_nested_element('coursework_sample_set_mbr', ['id'],
                                                [
                                                        "courseworkid",
                                                        "allocatableid",
                                                        "allocatabletype",
                                                        'allocatableuser',
                                                        'allocatablegroup',
                                                        "stage_identifier",
                                                        "selectiontype",
                                                ]);

            // A coursework instance has submissions.
            $coursework->add_child($submissions);
            // Each coursework may have reminders
            $coursework->add_child($reminders);
            // And allocations pairs
            $coursework->add_child($pairs);
            // And moderation sets
            $coursework->add_child($modsetrules);
            // And a set of extensionsenabled
            $coursework->add_child($extensions);
            // And a set of personaldeadlines
            $coursework->add_child($personaldeadlines);
            // And a set of moderation rule sets
            $coursework->add_child($modsetmembers);
            // And allocation configs
            $coursework->add_child($allocationconfigs);
            // And sample members
            $coursework->add_child($samplemembers);

            // And submissions are made up from individual submission instances
            $submissions->add_child($submission);
            // Submissions have multiple feedback items
            $submission->add_child($feedbacks);

            // Feedbacks is a set of individual items
            $feedbacks->add_child($feedback);

            $feedback->add_child($moderationagreements);
            $moderationagreements->add_child($moderationagreement);

            $submission->add_child($plagiarismflags);
            $plagiarismflags->add_child($plagiarismflag);

            // as are reminders, pairs, extensions, modsets and modsetrules,
            // and allocation configs
            $reminders->add_child($reminder);
            $pairs->add_child($pair);
            $extensions->add_child($extension);
            $personaldeadlines->add_child($personaldeadline);
            $modsetrules->add_child($modsetrule);
            $modsetmembers->add_child($modsetmember);
            $samplemembers->add_child($samplemember);
            $allocationconfigs->add_child($allocationconfig);

            $submission->set_source_table('coursework_submissions',
                                          ['courseworkid' => backup::VAR_PARENTID]);

            $feedback->set_source_table('coursework_feedbacks',
                                        ['submissionid' => backup::VAR_PARENTID]);

            $plagiarismflag->set_source_table('coursework_plagiarism_flags',
                                         ['submissionid' => backup::VAR_PARENTID]);

            $moderationagreement->set_source_table('coursework_mod_agreements',
                                        ['feedbackid' => backup::VAR_PARENTID]);

            $reminder->set_source_table('coursework_reminder',
                                        ['coursework_id' => backup::VAR_PARENTID]);

            $pair->set_source_table('coursework_allocation_pairs',
                                    ['courseworkid' => backup::VAR_PARENTID]);

            $modsetrule->set_source_table('coursework_mod_set_rules',
                                          ['courseworkid' => backup::VAR_PARENTID]);

            $extension->set_source_table('coursework_extensions',
                                         ['courseworkid' => backup::VAR_PARENTID]);

            $personaldeadline->set_source_table('coursework_person_deadlines',
                                        ['courseworkid' => backup::VAR_PARENTID]);

            $modsetmember->set_source_table('coursework_mod_set_members',
                ['courseworkid' => backup::VAR_PARENTID]);

            $samplemember->set_source_table('coursework_sample_set_mbrs',
                                ['courseworkid' => backup::VAR_PARENTID]);

            $allocationconfig->set_source_table('coursework_allocation_config',
                                                 ['courseworkid' => backup::VAR_PARENTID]);

            // Mark important foreign keys
            $feedback->annotate_ids('user', 'assessorid');
            $feedback->annotate_ids('user', 'lasteditedbyuser');
            $feedback->annotate_ids('user', 'markernumber');

            $submission->annotate_ids('user', 'userid');
            $submission->annotate_ids('user', 'createdby');
            $submission->annotate_ids('user', 'lastupdatedby');
            $submission->annotate_ids('user', 'allocatableuser');
            $submission->annotate_ids('group', 'allocatablegroup');

            $reminder->annotate_ids('user', 'userid');

            $pair->annotate_ids('user', 'assessorid');
            $pair->annotate_ids('user', 'allocatableuser');
            $pair->annotate_ids('group', 'allocatablegroup');

            $allocationconfig->annotate_ids('user', 'assessorid');

            $modsetmember->annotate_ids('user', 'allocatableuser');
            $modsetmember->annotate_ids('group', 'allocatablegroup');

            $extension->annotate_ids('user', 'allocatableuser');
            $extension->annotate_ids('group', 'allocatablegroup');

            $personaldeadline->annotate_ids('user', 'allocatableuser');
            $personaldeadline->annotate_ids('group', 'allocatablegroup');

            $samplemember->annotate_ids('user', 'allocatableuser');
            $samplemember->annotate_ids('group', 'allocatablegroup');

            $moderationagreement->annotate_ids('user', 'moderatorid');
            $moderationagreement->annotate_ids('user', 'lasteditedby');

            $plagiarismflag->annotate_ids('user', 'createdby');
            $plagiarismflag->annotate_ids('user', 'lastmodifiedby');

            $coursework->annotate_files('mod_coursework', 'feedback', null);
            $coursework->annotate_files('mod_coursework', 'submission', null);

        }

        $coursework->annotate_ids('grouping', 'grouping_id');

        $coursework->set_source_table('coursework', ['id' => backup::VAR_ACTIVITYID]);

        return $this->prepare_activity_structure($coursework);

    }
}
