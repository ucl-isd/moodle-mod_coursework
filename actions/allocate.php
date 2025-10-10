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

require_once(dirname(__FILE__).'/../../../config.php');

global $CFG, $OUTPUT, $DB, $PAGE;

require_once($CFG->dirroot.'/mod/coursework/lib.php');

/**
 * Handles all form submissions from the allocation page.
 *
 * @param coursework $coursework The coursework object.
 * @param \stdClass $coursemodule The coursemodule object.
 */
function coursework_process_form_submissions(coursework $coursework, $coursemodule) {
    global $DB, $PAGE, $CFG;

    $formsavebutton = optional_param('save', 0, PARAM_BOOL);
    $samplingformsavebutton = optional_param('save_sampling', 0, PARAM_BOOL);
    $assessorallocationstrategy = optional_param('assessorallocationstrategy', false, PARAM_TEXT);
    $deletemodsetrule = optional_param('delete-mod-set-rule', [], PARAM_RAW);
    $allocationsmanager = $coursework->get_allocation_manager();

    // SHAME.
    // Variable $_POST['allocatables'] comes as array of arrays which is not supported by optional_param_array.
    // However, we clean this later in process_data() function.
    $dirtyformdata = isset($_POST['allocatables']) ? $_POST['allocatables'] : [];

    if ($formsavebutton) {
        // Save allocation strategy settings if a strategy was submitted.
        if ($assessorallocationstrategy) {
            if ($assessorallocationstrategy != $coursework->assessorallocationstrategy) {
                $coursework->set_assessor_allocation_strategy($assessorallocationstrategy);
            }
            $coursework->save_allocation_strategy_options($assessorallocationstrategy);
        }
        $coursework->save();

        // Process manual allocations from the table.
        $processor = new \mod_coursework\allocation\table\processor($coursework);
        $processor->process_data($dirtyformdata);
        $allocationsmanager->auto_generate_sample_set();
    }

    if ($samplingformsavebutton && $coursework->sampling_enabled()) {
        $allocationsmanager->save_sample();
    }

    // TODO - Leon and Stuart think this is never used.
    if ($deletemodsetrule) {
        if (is_array($deletemodsetrule)) {
            reset($deletemodsetrule);
            $deleteruleid = key($deletemodsetrule); // Only one button can be clicked.
            if (is_numeric($deleteruleid)) {
                $DB->delete_records('coursework_mod_set_rules', ['id' => $deleteruleid]);
            }
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
}

/**
 * Renders the allocation page content.
 *
 * @param coursework $coursework The coursework object.
 * @param \mod_coursework_allocation_table $allocationtable The renderable table object.
 */
function coursework_render_page(coursework $coursework, \mod_coursework_allocation_table $allocationtable) {
    global $PAGE, $OUTPUT;

    // Prepare renderable objects.
    $allocationsmanager = $coursework->get_allocation_manager();
    $warnings = new \mod_coursework\warnings($coursework);
    $objectrenderer = $PAGE->get_renderer('mod_coursework', 'object');

    $template = new stdClass();
    $template->page_url_params = \html_writer::input_hidden_params($PAGE->url);

    // Warnings.
    $template->warnings = $warnings->percentage_allocations_not_complete();
    if ($coursework->allocation_enabled()) {
        $template->warnings .= $warnings->manual_allocation_not_completed();
        if ($coursework->use_groups == 1 || $coursework->assessorallocationstrategy == 'group_assessor') {
            $template->warnings .= $warnings->students_in_mutiple_groups();
        }
    }

    // Widgets.
    if ($coursework->sampling_enabled()) {
        $samplesetwidget = $allocationsmanager->get_sampling_set_widget();
        $template->samplingwidget = \html_writer::tag('form', $objectrenderer->render($samplesetwidget), ['id' => 'sampling_form', 'method' => 'post']);
    }
    if ($coursework->allocation_enabled()) {
        $allocationwidget = new \mod_coursework_allocation_widget(new widget($coursework));
        $template->allocationwidget = $objectrenderer->render($allocationwidget);
    }

    // Main allocation table.
    $template->table = $objectrenderer->render($allocationtable);

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('mod_coursework/allocate/main', $template);
    echo $OUTPUT->footer();
}

$coursemoduleid = required_param('id', PARAM_INT);
$coursemodule = get_coursemodule_from_id('coursework', $coursemoduleid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $coursemodule->course], '*', MUST_EXIST);
$coursework = $DB->get_record('coursework', ['id' => $coursemodule->instance], '*', MUST_EXIST);
$formsavebutton = optional_param('save', 0, PARAM_BOOL);
$samplingformsavebutton = optional_param('save_sampling', 0, PARAM_BOOL);
$allocateallbutton = optional_param('auto-allocate-all', 0, PARAM_BOOL);
$coursework = coursework::find($coursework);

// options used for pagination
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
$options = compact('sortby', 'sorthow', 'perpage', 'page');

require_login($course, true, $coursemodule);

require_capability('mod/coursework:allocate', $PAGE->context, null, true, "Can't allocate here - permission denied.");

$url = '/mod/coursework/actions/allocate.php';
$link = new \moodle_url($url, ['id' => $coursemoduleid]);
$PAGE->set_url($link);
$title = get_string('allocatefor', 'mod_coursework', $coursework->name);
$PAGE->set_title($title);
$PAGE->set_heading($title);

$PAGE->requires->jquery();

// Will set off the function that adds listeners for onclick/onchange etc.
$jsmodule = [
    'name' => 'mod_coursework',
    'fullpath' => '/mod/coursework/module.js',
    'requires' => ['base', 'node-base'],
];
$PAGE->requires->js_init_call(
        'M.mod_coursework.init_allocate_page',
    ['wwwroot' => $CFG->wwwroot, 'coursemoduleid' => $coursemoduleid],
    false,
    $jsmodule
);

$PAGE->requires->string_for_js('sameassessorerror', 'coursework');

// Process any form submissions. This may redirect away from the page.
coursework_process_form_submissions($coursework, $coursemodule);

// Prepare the main allocation table for rendering.
$allocationtable = new mod_coursework\allocation\table\builder($coursework, $options);
$allocationtable = new mod_coursework_allocation_table($allocationtable);

// Render the page.
coursework_render_page($coursework, $allocationtable);
