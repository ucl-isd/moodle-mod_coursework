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
 * Renders the allocation page content.
 *
 * @param coursework $coursework
 * @param array $options
 */
function coursework_render_page(coursework $coursework, $options) {
    global $PAGE, $OUTPUT;

    // Prepare renderable objects.
    $allocationsmanager = $coursework->get_allocation_manager();
    $warnings = new \mod_coursework\warnings($coursework);
    $objectrenderer = $PAGE->get_renderer('mod_coursework', 'object');

    $template = new stdClass();
    $template->page_url_params = \html_writer::input_hidden_params($PAGE->url);

    // Display warnings.
    $template->warnings = $warnings->percentage_allocations_not_complete();
    if ($coursework->allocation_enabled()) {
        $template->warnings .= $warnings->manual_allocation_not_completed();
        if ($coursework->use_groups == 1 || $coursework->assessorallocationstrategy === 'group_assessor') {
            $template->warnings .= $warnings->students_in_mutiple_groups();
        }
    }

    // Render sampling widget if enabled.
    // TODO - this needs work...
    $template->sampling_enabled = $coursework->sampling_enabled();
    if ($coursework->sampling_enabled()) {
        $samplesetwidget = $allocationsmanager->get_sampling_set_widget();
        $template->sampling_widget = $objectrenderer->render($samplesetwidget);
    }

    // Render allocation strategy widget if enabled.
    $template->allocation_enabled = $coursework->allocation_enabled();
    if ($coursework->allocation_enabled()) {
        $allocationwidget = new \mod_coursework_allocation_widget(new \mod_coursework\allocation\widget($coursework));
        $template->allocation = $objectrenderer->render($allocationwidget);
    }

    // Render main allocation table.
    $template->table_heading = get_string('assessormoderatorgrades', 'mod_coursework');
    $template->pin_info = get_string('pininfo', 'mod_coursework');
    $allocationtable = new \mod_coursework_allocation_table(new \mod_coursework\allocation\table\builder($coursework, $options));
    $template->table = $objectrenderer->render($allocationtable);

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('mod_coursework/allocate/page', $template);
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

// 3. Get table options. Pagination is disabled, but sorting is still active.
$options = [
    'page' => 0,
    'perpage' => 0, // A value of 0 signifies 'all records'.
    'sortby' => optional_param('sortby', '', PARAM_ALPHA),
    'sorthow' => optional_param('sorthow', '', PARAM_ALPHA),
];

// 4. Process any form submissions. This may redirect away.
coursework_process_form_submissions($coursework, $coursemodule);

// 5. Render the page.
coursework_render_page($coursework, $options);
