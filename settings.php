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
 * @copyright  2011 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\candidateprovider_manager;

defined('MOODLE_INTERNAL') || die;

global $CFG, $DB, $PAGE;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/coursework/lib.php');

    $settingsheader = new admin_setting_heading('settings_header', '', get_string('settings_header', 'mod_coursework'));
    $settings->add($settingsheader);

    // Set site-wide option for late submission
    $availabilityheader = new admin_setting_heading('availability_header', get_string('availability', 'mod_coursework'), '');
    $settings->add($availabilityheader);
    $allowlatesubmissionname = get_string('allowlatesubmissions', 'coursework');
    $allowlatesubmissiondescription = get_string('allowlatesubmissions_desc', 'coursework');
    $options = [ 0 => get_string('no'), 1 => get_string('yes')];
    $settings->add(new admin_setting_configselect('coursework_allowlatesubmissions',
                   $allowlatesubmissionname, $allowlatesubmissiondescription, 0, $options));

    // Set site-wide limit on submissions sizes.
    if (isset($CFG->maxbytes)) {
        $submissionsheader = new admin_setting_heading('submissions_header', get_string('submissions', 'mod_coursework'), '');
        $settings->add($submissionsheader);
        $configmaxbytesstring = get_string('configmaxbytes', 'coursework');
        $maximumsizestring = get_string('maximumsize', 'coursework');
        $maxbytessetting = new admin_setting_configselect('coursework_maxbytes',
                                                          $maximumsizestring,
                                                          $configmaxbytesstring,
                                                          1048576,
                                                          get_max_upload_sizes($CFG->maxbytes));
        $settings->add($maxbytessetting);
    }

    // Submissions
    $submissionsheader = new admin_setting_heading('submissions_header', get_string('submissions', 'mod_coursework'), '');
    $settings->add($submissionsheader);
    $options = [ 0 => get_string('no'), 1 => get_string('yes')];
    $settings->add(new admin_setting_configselect('coursework_plagiarismflag', get_string('plagiarism_flag_enable', 'mod_coursework'), get_string('plagiarism_flag_enable_desc', 'mod_coursework'), 0, $options));

    // Submission receipt
    $submissionreceiptheader = new admin_setting_heading('submissionreceipt_header', get_string('submissionreceipt', 'mod_coursework'), '');
    $settings->add($submissionreceiptheader);
    $options = [ 0 => get_string('no'), 1 => get_string('yes')];
    $settings->add(new admin_setting_configselect('coursework_allsubmissionreceipt', get_string('allsubmission', 'mod_coursework'), get_string('allsubmission_desc', 'mod_coursework'), 0, $options));

    // Blind marking
    $blindmarkingheader = new admin_setting_heading('blindmarking_header', get_string('blindmarking', 'mod_coursework'), '');
    $settings->add($blindmarkingheader);
    $blindmarkingname = get_string('blindmarking', 'coursework');
    $blindmarkingdescription = get_string('blindmarking_desc', 'coursework');
    $options = [ 0 => get_string('no'), 1 => get_string('yes')];
    $settings->add(new admin_setting_configselect('coursework_blindmarking', $blindmarkingname, $blindmarkingdescription, 0, $options));
    $settings->add(new admin_setting_configcheckbox('coursework_forceblindmarking', get_string('forceblindmarking', 'mod_coursework'), get_string('forceblindmarking_desc', 'mod_coursework'), 0));

    // Candidate number provider
    $candidateproviderheader = new admin_setting_heading('candidateprovider_header', get_string('candidate_number_provider', 'mod_coursework'), '');
    $settings->add($candidateproviderheader);

    // Get available providers
    $provideroptions = ['' => get_string('no_provider_selected', 'mod_coursework')];
    $availableproviders = candidateprovider_manager::instance()->get_available_providers();
    foreach ($availableproviders as $component => $name) {
        $provideroptions[$component] = $name;
    }

    if (count($availableproviders) > 0) {
        $candidateprovidername = get_string('candidate_number_provider', 'mod_coursework');
        $candidateproviderdescription = get_string('candidate_number_provider_desc', 'mod_coursework');
        $settings->add(new admin_setting_configselect('mod_coursework/candidate_provider',
                                                      $candidateprovidername,
                                                      $candidateproviderdescription,
                                                      '',
                                                      $provideroptions));

        $usecandidatenumbersname = get_string('use_candidate_numbers_for_hidden_name', 'mod_coursework');
        $usecandidatenumbersdescription = get_string('use_candidate_numbers_for_hidden_name_desc', 'mod_coursework');
        $currentprovider = get_config('mod_coursework', 'candidate_provider');
        if (empty($currentprovider)) {
            $usecandidatenumbersdescription .= ' ' . get_string('use_candidate_numbers_requires_provider', 'mod_coursework');
        }
        $settings->add(new admin_setting_configcheckbox('mod_coursework/use_candidate_numbers_for_hidden_name',
                                                        $usecandidatenumbersname,
                                                        $usecandidatenumbersdescription,
                                                        '0'));
    } else {
        // Show a message when no providers are available
        $noprovidersetting = new admin_setting_description('coursework_candidate_provider_none',
                                                          get_string('candidate_number_provider', 'mod_coursework'),
                                                          get_string('no_candidate_provider_available', 'mod_coursework'));
        $settings->add($noprovidersetting);
    }

    // Assessor anonymity
    $assessoranonymityheader = new admin_setting_heading('assessoranonymity_header', get_string('assessoranonymity', 'mod_coursework'), '');
    $settings->add($assessoranonymityheader);
    $assessoranonymityname = get_string('assessoranonymity', 'coursework');
    $assessoranonymitydescription = get_string('assessoranonymity_desc', 'coursework');
    $options = [ 0 => get_string('no'), 1 => get_string('yes')];
    $settings->add(new admin_setting_configselect('coursework_assessoranonymity', $assessoranonymityname, $assessoranonymitydescription, 0, $options));

    // Set site-wide options for when feedback is due.
    $weeks = [];
    for ($i = 1; $i <= 10; $i++) {
        $weeks[$i] = $i;
    }
    $feedbacktypesheader = new admin_setting_heading('feedbacktypes_header', get_string('feedbacktypes', 'mod_coursework'), '');
    $settings->add($feedbacktypesheader);
    $generalfeedbackstring = get_string('generalfeedback', 'coursework');
    $configgeneralfeedbackstring = get_string('configgeneralfeedback', 'coursework');
    $generalfeedbacksetting = new admin_setting_configselect('coursework_generalfeedback',
                                                             $generalfeedbackstring,
                                                             $configgeneralfeedbackstring,
                                                             2, $weeks);
    $settings->add($generalfeedbacksetting);

    // enable auto-release of individual feedback
    $individualfeedbackautoreleasename = get_string('individual_feedback_auto_release', 'coursework');
    $individualfeedbackautoreleasenamedescription = get_string('individual_feedback_auto_release_desc', 'coursework');
    $options = [ 0 => get_string('no'), 1 => get_string('yes')];
    $settings->add(new admin_setting_configselect('coursework_auto_release_individual_feedback', $individualfeedbackautoreleasename, $individualfeedbackautoreleasenamedescription, 0, $options));
    $settings->add(new admin_setting_configcheckbox('coursework_forceauto_release_individual_feedback', get_string('forceautoauto_release_individual_feedback', 'mod_coursework'), get_string('forceautoauto_release_individual_feedback_desc', 'mod_coursework'), 0));

    $individualfeedbackstring = get_string('individualfeedback', 'coursework');
    $configindfeedbackstring = get_string('configindividualfeedback', 'coursework');
    $individualfeedbacksetting = new admin_setting_configselect('coursework_individualfeedback',
                                                                $individualfeedbackstring,
                                                                $configindfeedbackstring,
                                                                4, $weeks);
    $settings->add($individualfeedbacksetting);

    // Feedback release email
    $feedbackreleaseemailname = get_string('feedbackreleaseemail', 'coursework');
    $feedbackreleaseemaildescription = get_string('feedbackreleaseemail_help', 'coursework');
    $options = [ 0 => get_string('no'), 1 => get_string('yes')];
    $settings->add(new admin_setting_configselect('coursework_feedbackreleaseemail', $feedbackreleaseemailname, $feedbackreleaseemaildescription, 1, $options));

    $dayreminder = [];
    for ($i = 2; $i <= 7; $i++) {
        $dayreminder[$i] = $i;
    }
    $studentreminderheader = new admin_setting_heading('studentreminder_header', get_string('studentreminder', 'mod_coursework'), '');
    $settings->add($studentreminderheader);
    $reminderstring = get_string('coursework_reminder', 'coursework');
    $confreminderstring = get_string('config_coursework_reminder', 'coursework');
    $settings->add(new admin_setting_configselect('coursework_day_reminder', $reminderstring,
                       $confreminderstring, 7, $dayreminder));

    $secondreminderstring = get_string('second_reminder', 'coursework');
    $confsecondreminderstring = get_string('config_second_reminder', 'coursework');
    $settings->add(new admin_setting_configselect('coursework_day_second_reminder', $secondreminderstring,
                                                  $confsecondreminderstring, 3, $dayreminder));

    // Sitewide message that students will see and agree to before submitting or editing.
    $termsagreementheader = new admin_setting_heading('termsagreement_header', get_string('termsagreement', 'mod_coursework'), '');
    $settings->add($termsagreementheader);
    $agreetermsname = get_string('agreeterms', 'coursework');
    $agreetermsdescription = get_string('agreetermsdescription', 'coursework');
    $options = [ 0 => get_string('no'), 1 => get_string('yes')];
    $settings->add(new admin_setting_configselect('coursework_agree_terms',
                                                    $agreetermsname, $agreetermsdescription, 0, $options));

    $agreetermstext = get_string('agreetermstext', 'coursework');
    $settings->add(new admin_setting_confightmleditor('coursework_agree_terms_text',
                                                      $agreetermstext, '', ''));

    // Extensions
    $extensionsheader =
        new admin_setting_heading('extensions_header', get_string('extensions', 'mod_coursework'), '');
    $settings->add($extensionsheader);

    // Enable coursework individual extensions
    $individualextensionname = get_string('individual_extension', 'coursework');
    $individualextensiondescription = get_string('individual_extension_desc', 'coursework');
    $options = [ 0 => get_string('no'), 1 => get_string('yes')];
    $settings->add(new admin_setting_configselect('coursework_individual_extension', $individualextensionname, $individualextensiondescription, 1, $options));

    // Allow people to specify a list of extension reasons here so that they can be quickly chosen
    $extensionlistlabel = get_string('extension_reasons', 'coursework');
    $extensionlistdescription = get_string('extension_reasons_desc', 'coursework');
    $settings->add(new admin_setting_configtextarea('coursework_extension_reasons_list',
                                                    $extensionlistlabel, $extensionlistdescription, ''));

    // maximum extension deadline
    $settings->add(new admin_setting_configtext('coursework_max_extension_deadline', get_string('maximum_extension_deadline', 'coursework'),
                                                                                    get_string('maximum_extension_deadline_desc', 'coursework'),
                                                                                    18, PARAM_INT, 2));

    // Default per page

    $options = ['3' => '3', '10' => '10', '20' => '20', '30' => '30', '40' => '40', '50' => '50', '100' => '100'];

    $gradingpageheader = new admin_setting_heading('grading_page_header', get_string('grading_page', 'mod_coursework'), '');
    $settings->add($gradingpageheader);

    $perpage = get_string('per_page', 'coursework');
    $perpagedescription = get_string('per_page_desc', 'coursework');
    $settings->add(new admin_setting_configselect('coursework_per_page', $perpage, $perpagedescription, '10', $options));

    $gradeeditingheader = new admin_setting_heading('grade_editing_header', get_string('grade_editing', 'mod_coursework'), '');
    $settings->add($gradeeditingheader);

    // Deadline defaults
    $deadlinedefaultsheader = new admin_setting_heading('deadline_defaults_header', get_string('deadline_defaults', 'mod_coursework'), '');
    $settings->add($deadlinedefaultsheader);

    // Marking deadline
    $options = ['0' => get_string('disabled', 'mod_coursework')];
    $options['7'] = get_string('oneweekoption', 'mod_coursework');
    $options['14'] = get_string('twoweeksoption', 'mod_coursework');
    $options['21'] = get_string('threeweeksoption', 'mod_coursework');
    $options['28'] = get_string('fourweeksoption', 'mod_coursework');
    $options['35'] = get_string('fiveweeksoption', 'mod_coursework');
    $options['42'] = get_string('sixweeksoption', 'mod_coursework');
    $markingdeadlinename = get_string('marking_deadline_default', 'coursework');
    $markingdeadlinedescription = get_string('marking_deadline_enabled_desc', 'coursework');
    $settings->add(new admin_setting_configselect('coursework_marking_deadline', $markingdeadlinename, $markingdeadlinedescription, '0', $options));

    // Marking deadline
    $options = ['0' => get_string('disabled', 'mod_coursework')];
    $options['7'] = get_string('oneweekoption', 'mod_coursework');
    $options['14'] = get_string('twoweeksoption', 'mod_coursework');
    $options['21'] = get_string('threeweeksoption', 'mod_coursework');
    $options['28'] = get_string('fourweeksoption', 'mod_coursework');
    $options['35'] = get_string('fiveweeksoption', 'mod_coursework');
    $options['42'] = get_string('sixweeksoption', 'mod_coursework');

    $agreedmarkingdeadlinename = get_string('agreed_marking_deadline_default', 'coursework');
    $agreedmarkingdeadlinedescription = get_string('agreed_marking_deadline_default_desc', 'coursework');
    $settings->add(new admin_setting_configselect('coursework_agreed_marking_deadline', $agreedmarkingdeadlinename, $agreedmarkingdeadlinedescription, '0', $options));

    // Start date
    $options = ['0' => get_string('disabled', 'mod_coursework')];
    $options['1'] = get_string('today', 'mod_coursework');

    $startdatename = get_string('startdate', 'coursework');
    $startdatedescription = get_string('start_date_enabled_desc', 'coursework');
    $settings->add(new admin_setting_configselect('coursework_start_date', $startdatename, $startdatedescription, '0', $options));

    // Submission deadline
    $options = ['0' => get_string('disabled', 'mod_coursework')];
    $options['1'] = get_string('today', 'mod_coursework');
    $options['7'] = get_string('sevendays', 'mod_coursework');
    $options['14'] = get_string('fourteendays', 'mod_coursework');
    $options['31'] = get_string('onemonth', 'mod_coursework');

    $submissiondeadlinename = get_string('submissiondeadline', 'coursework');
    $submissiondeadlinedescription = get_string('submission_deadline_enabled_desc', 'coursework');
    $settings->add(new admin_setting_configselect('coursework_submission_deadline', $submissiondeadlinename, $submissiondeadlinedescription, '0', $options));

    // Assessor allocations
    $assessorallocationsheader = new admin_setting_heading('assessor_allocations_header_header', get_string('assessorallocations', 'mod_coursework'), '');
    $settings->add($assessorallocationsheader);

    $options = [ 'username' => get_string('username'), 'email' => get_string('email')];
    $settings->add(new admin_setting_configselect('coursework_allocation_identifier',  get_string('allocationidentifier', 'coursework'), get_string('allocationidentifier_desc', 'coursework'), 'username', $options));

}
