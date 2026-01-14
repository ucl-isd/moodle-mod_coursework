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

use mod_coursework\allocation\table\processor;
use mod_coursework\allocation\widget;
use mod_coursework\models\coursework;
use mod_coursework\warnings;

require_once(dirname(__FILE__) . '/../../../config.php');

global $CFG, $OUTPUT, $DB, $PAGE;

require_once($CFG->dirroot . '/mod/coursework/lib.php');

/**
 * Handles all form submissions from the allocation page.
 *
 * @param coursework $coursework The coursework object.
 * @param stdClass $coursemodule The coursemodule object.
 * @throws \core\exception\coding_exception
 * @throws \core\exception\moodle_exception
 * @throws coding_exception
 * @throws dml_exception
 * @throws invalid_parameter_exception
 * @throws moodle_exception
 */
function coursework_process_form_submissions(coursework $coursework, $coursemodule) {
    global $DB, $PAGE;

    $formsavebutton = optional_param('save', 0, PARAM_BOOL);
    $samplingformsavebutton = optional_param('save_sampling', 0, PARAM_BOOL);
    $assessorallocationstrategy = optional_param('assessorallocationstrategy', false, PARAM_TEXT);
    $deletemodsetrule = optional_param('delete-mod-set-rule', [], PARAM_RAW);
    $allocationsmanager = $coursework->get_allocation_manager();

    if ($formsavebutton) {
        // Save allocation strategy settings if a strategy was submitted.
        if ($assessorallocationstrategy) {
            if ($assessorallocationstrategy != $coursework->assessorallocationstrategy) {
                $coursework->set_assessor_allocation_strategy($assessorallocationstrategy);
            }
            $coursework->save_allocation_strategy_options($assessorallocationstrategy);
        }
        $coursework->save();

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
        $warnings = new warnings($coursework);
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
 * @throws \core\exception\coding_exception
 * @throws \core\exception\moodle_exception
 * @throws coding_exception
 * @throws dml_exception
 */
function coursework_render_page(coursework $coursework) {
    global $PAGE, $OUTPUT;

    // Prepare renderable objects.
    $allocationsmanager = $coursework->get_allocation_manager();
    $warnings = new warnings($coursework);
    $objectrenderer = $PAGE->get_renderer('mod_coursework', 'object');

    $template = new stdClass();
    $template->page_url_params = html_writer::input_hidden_params($PAGE->url);

    // Warnings.
    $template->warnings = $warnings->percentage_allocations_not_complete();
    if ($coursework->allocation_enabled()) {
        $template->warnings .= $warnings->manual_allocation_not_completed();
        if ($coursework->usegroups == 1 || $coursework->assessorallocationstrategy == 'group_assessor') {
            $template->warnings .= $warnings->students_in_mutiple_groups();
        }
    }

    // Widgets.
    if ($coursework->sampling_enabled()) {
        $samplesetwidget = $allocationsmanager->get_sampling_set_widget();
        $template->samplingwidget = html_writer::tag('form', $objectrenderer->render($samplesetwidget), ['id' => 'sampling_form', 'method' => 'post']);
    }
    if ($coursework->allocation_enabled()) {
        $allocationwidget = new mod_coursework_allocation_widget(new widget($coursework));
        $template->allocationwidget = $objectrenderer->render($allocationwidget);
    }

    $tablemodel = new stdClass();
    $tablemodel->headers = [get_string('student', 'mod_coursework')];
    foreach ($coursework->marking_stages() as $stage) {
        if (!$stage->uses_allocation()) {
            continue;
        }
        $tablemodel->headers[] = $stage->allocation_table_header();
    }

    $tablemodel->rows = [];

    foreach ($coursework->get_allocatables() as $allocatable) {
        $stages = [];
        foreach ($coursework->marking_stages() as $stage) {
            $feedback = $stage->get_feedback_for_allocatable($allocatable);
            $membership = $stage->get_assessment_set_membership($allocatable);

            $stagecell = [
                'stageidentifier' => $stage->identifier(),
            ];

            if ($feedback) {
                $stagecell['currentmarker'] = $feedback->assessor()->name();
                $stagecell['currentgrade'] = $feedback->get_grade();
            } else if ($stage->uses_allocation()) {
                $allocation = $stage->get_allocation($allocatable);
                $currentmarker = $allocation->assessorid ?? 0;

                $stagecell['potentialmarkers'] = array_values(
                    array_map(function ($marker) use ($currentmarker) {
                        return (object)['id' => $marker->id, 'name' => $marker->name(), 'selected' => $currentmarker == $marker->id];
                    }, $stage->get_teachers())
                );

                $stagecell['showmarkerselection'] = true;
                $stagecell['pinned'] = (!empty($allocation) && $allocation->is_pinned());
            }

            if ($stage->uses_sampling()) {
                if ($feedback || $stage->identifier() == 'assessor_1') {
                    $stagecell['samplingstate'] = get_string('includedinsample', 'mod_coursework');
                } else {
                    if ($membership && $membership->selectiontype == 'automatic') {
                        $stagecell['samplingstate'] = get_string('automaticallyinsample', 'mod_coursework');
                    } else {
                        $stagecell['samplingcheckboxdisplay'] = true;
                        $stagecell['samplingcheckboxvalue'] = !empty($membership);
                    }
                }
            }

            $stages[] = $stagecell;
        }

        $tablemodel->rows[] = [
            'allocatableid' => $allocatable->id,
            'courseworkid' => $coursework->id,
            'allocatablename' => $allocatable->name(),
            'stages' => $stages,
        ];
    }

    $template->table = $OUTPUT->render_from_template('mod_coursework/allocate/table', $tablemodel);

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

require_login($course, true, $coursemodule);

require_capability('mod/coursework:allocate', $PAGE->context);

$url = '/mod/coursework/actions/allocate.php';
$link = new moodle_url($url, ['id' => $coursemoduleid]);
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

$PAGE->requires->string_for_js('samemarkererror', 'coursework');

// Process any form submissions. This may redirect away from the page.
coursework_process_form_submissions($coursework, $coursemodule);

// Render the page.
coursework_render_page($coursework);
