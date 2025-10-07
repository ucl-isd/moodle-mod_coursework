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
 * Page that prints a table of all students and all markers so that first marker, second marker, moderators
 * etc can be allocated manually or automatically.
 *
 * @package    mod_coursework
 * @copyright  2011 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\models\coursework;
use mod_coursework\allocation\widget;

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->dirroot . '/mod/coursework/lib.php');

/**
 * Handles all form submissions from the allocation page.
 *
 * @param coursework $coursework The coursework object.
 * @param stdClass $coursemodule The coursemodule object.
 * @return bool True if the main 'save' button was pressed.
 */
function coursework_process_form_submissions(coursework $coursework, $coursemodule) {
    global $DB, $PAGE;

    $formsavebutton = optional_param('save', 0, PARAM_BOOL);
    $samplingformsavebutton = optional_param('save_sampling', 0, PARAM_BOOL);
    $assessorallocationstrategy = optional_param('assessorallocationstrategy', false, PARAM_TEXT);
    $deletemodsetrule = optional_param('delete-mod-set-rule', [], PARAM_RAW);
    $dirtyformdata = $_POST['allocatables'] ?? [];

    $allocationsmanager = $coursework->get_allocation_manager();

    if ($formsavebutton) {
        // Save allocation strategy settings.
        if ($assessorallocationstrategy && $assessorallocationstrategy != $coursework->assessorallocationstrategy) {
            $coursework->set_assessor_allocation_strategy($assessorallocationstrategy);
        }
        $coursework->save_allocation_strategy_options($assessorallocationstrategy);
        $coursework->save();

        // Process manual allocations from the table.
        $processor = new \mod_coursework\allocation\table\processor($coursework);
        $processor->process_data($dirtyformdata);
        $allocationsmanager->auto_generate_sample_set();
    }

    if ($samplingformsavebutton && $coursework->sampling_enabled()) {
        $allocationsmanager->save_sample();
    }

    if ($deletemodsetrule && is_array($deletemodsetrule)) {
        $deleteruleid = key($deletemodsetrule);
        if (is_numeric($deleteruleid)) {
            $DB->delete_records('coursework_mod_set_rules', ['id' => $deleteruleid]);
        }
    }

    // Redirect on save.
    if ($formsavebutton) {
        $warnings = new \mod_coursework\warnings($coursework);
        $percentageallocationnotcomplete = $warnings->percentage_allocations_not_complete();
        $manualallocationnotcomplete = $coursework->allocation_enabled() ? $warnings->manual_allocation_not_completed() : '';

        if (empty($percentageallocationnotcomplete) && empty($manualallocationnotcomplete)) {
            redirect(new moodle_url('/mod/coursework/view.php', ['id' => $coursemodule->id]), get_string('changessaved', 'mod_coursework'));
        } else {
            redirect($PAGE->url);
        }
    }

    return $formsavebutton;
}

/**
 * Get and manage table pagination and sorting options from parameters and session.
 *
 * @param int $coursemoduleid
 * @return array An array of options for the table builder.
 */
function coursework_get_page_options($coursemoduleid) {
    global $SESSION, $CFG;

    // If a session variable holding page preference for the specific coursework is not set, set default value (0).
    if (isset($SESSION->allocate_perpage[$coursemoduleid]) && (isset($SESSION->perpage[$coursemoduleid]) && optional_param('per_page', 0, PARAM_INT) != $SESSION->perpage[$coursemoduleid])
        && optional_param('per_page', 0, PARAM_INT) != 0) { // prevent blank pages if not in correct page
        $page = 0;
        $SESSION->allocate_page[$coursemoduleid] = $page;
    } else if (!(isset($SESSION->allocate_page[$coursemoduleid]))) {
        $SESSION->allocate_page[$coursemoduleid] = optional_param('page', 0, PARAM_INT);
        $page = $SESSION->allocate_page[$coursemoduleid];
    } else {
        $page = optional_param('page', $SESSION->allocate_page[$coursemoduleid], PARAM_INT);
        $SESSION->allocate_page[$coursemoduleid] = $page;
    }

    // If a session variable holding perpage preference for the specific coursework is not set, set default value (10).
    if (!(isset($SESSION->allocate_perpage[$coursemoduleid]))) {
        $perpage = optional_param('per_page', 0, PARAM_INT);
        $perpage = $perpage ?: ($CFG->coursework_per_page ?? 10);
        $SESSION->allocate_perpage[$coursemoduleid] = $perpage;
    } else {
        $perpage = optional_param('per_page', $SESSION->allocate_perpage[$coursemoduleid], PARAM_INT);
        $SESSION->allocate_perpage[$coursemoduleid] = $perpage;
    }

    // SQL sort for allocation table.
    $sortby = optional_param('sortby', '', PARAM_ALPHA);
    $sorthow = optional_param('sorthow', '', PARAM_ALPHA);

    return compact('sortby', 'sorthow', 'perpage', 'page');
}

/**
 * Renders the allocation page content.
 *
 * @param coursework $coursework
 * @param array $options
 */
function coursework_render_page(coursework $coursework, $options) {
    global $PAGE, $OUTPUT;




    // Prepare renderable objects.
    $allocationsmanager = $coursework->get_allocation_manager();
    $allocationtable = new \mod_coursework_allocation_table(
        new \mod_coursework\allocation\table\builder($coursework, $options)
    );
    $allocationwidget = new \mod_coursework_allocation_widget(
        new \mod_coursework\allocation\widget($coursework)
    );
    $warnings = new \mod_coursework\warnings($coursework);
    $objectrenderer = $PAGE->get_renderer('mod_coursework', 'object');

    // Start rendering.
    echo $OUTPUT->header();



    // Add hidden params for forms.
    echo \html_writer::input_hidden_params($PAGE->url);

    echo '<div class="container">';
    echo '<h2>Allocate markers</h2>';

    // Display warnings.
    echo $warnings->percentage_allocations_not_complete();
    if ($coursework->allocation_enabled()) {
        echo $warnings->manual_allocation_not_completed();
        if ($coursework->use_groups == 1 || $coursework->assessorallocationstrategy == 'group_assessor') {
            echo $warnings->students_in_mutiple_grouos();
        }
    }

    echo '<div class="row">';

    echo '<div class="col-4">';

    // Render sampling widget if enabled.
    if ($coursework->sampling_enabled()) {
        echo \html_writer::start_tag('form', ['id' => 'sampling_form', 'method' => 'post']);
        $samplesetwidget = $allocationsmanager->get_sampling_set_widget();
        echo $objectrenderer->render($samplesetwidget);
        echo \html_writer::end_tag('form');
    }

    // Render allocation strategy widget if enabled.
    if ($coursework->allocation_enabled()) {
        echo $objectrenderer->render($allocationwidget);
    }

    echo '</div>';

    echo '<div class="col-8">';

    // Render main allocation table.
    echo \html_writer::div('', 'coursework_spacer');
    echo \html_writer::tag('h3', get_string('assessormoderatorgrades', 'mod_coursework'));
    echo \html_writer::tag('div', get_string('pininfo', 'mod_coursework'), ['class' => 'pininfo']);
    echo $objectrenderer->render($allocationtable);

    echo '</div>';

    echo '</div>';
    echo '</div>';

    echo $OUTPUT->footer();
}


// Main script execution starts here.

$coursemoduleid = required_param('id', PARAM_INT);

// 1. Initialise Moodle environment and get required objects.
$coursemodule = get_coursemodule_from_id('coursework', $coursemoduleid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $coursemodule->course], '*', MUST_EXIST);
$coursework = coursework::find($DB->get_record('coursework', ['id' => $coursemodule->instance], '*', MUST_EXIST));

require_login($course, true, $coursemodule);
require_capability('mod/coursework:allocate', $PAGE->context, null, true, "Can't allocate here - permission denied.");

// 2. Set up page parameters.
$PAGE->set_url('/mod/coursework/actions/allocate.php', ['id' => $coursemoduleid]);
$PAGE->set_title(get_string('allocatefor', 'mod_coursework', $coursework->name));
$PAGE->set_heading($PAGE->title);
$PAGE->requires->jquery();
$PAGE->requires->js_init_call(
        'M.mod_coursework.init_allocate_page',
    ['wwwroot' => $CFG->wwwroot, 'coursemoduleid' => $coursemoduleid],
    false,
    [
        'name' => 'mod_coursework',
        'fullpath' => '/mod/coursework/module.js',
        'requires' => ['base', 'node-base'],
    ]
);
$PAGE->requires->string_for_js('sameassessorerror', 'coursework');

// 3. Get pagination and sorting options.
$options = coursework_get_page_options($coursemoduleid);

// 4. Process any form submissions.
coursework_process_form_submissions($coursework, $coursemodule);

// 5. Render the page.
coursework_render_page($coursework, $options);
