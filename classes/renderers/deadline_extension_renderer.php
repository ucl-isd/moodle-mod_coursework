<?php

namespace mod_coursework\renderers;

use mod_coursework\allocation\allocatable;
use mod_coursework\models\deadline_extension;

/**
 * Class deadline_extension_renderer is responsible for rendering pages and objects to do with
 * the deadline_extension model.
 *
 * @package mod_coursework\renderers
 */
class deadline_extension_renderer {

    /**
     * @param array $vars
     * @throws \coding_exception
     */
    public function show_page($vars) {
        global $PAGE, $SITE, $OUTPUT;

        /**
         * @var allocatable $allocatable
         */
        $allocatable = $vars['deadline_extension']->get_allocatable();

        $PAGE->set_pagelayout('standard');
        $heading = 'Deadline extension for ' . $allocatable->name();
        $PAGE->navbar->add($heading);
        $PAGE->set_title($SITE->fullname);
        $PAGE->set_heading($heading);


        $html = '';

        echo $OUTPUT->header();
        echo $html;
        echo $OUTPUT->footer();
    }

    /**
     * @param array $vars
     * @throws \coding_exception
     */
    public function new_page($vars) {
        global $PAGE, $SITE, $OUTPUT;

        $PAGE->set_pagelayout('standard');
        $PAGE->navbar->add('New deadline extension');
        $PAGE->set_title($SITE->fullname);
        $PAGE->set_heading($SITE->fullname);

        $allocatable = $vars['deadline_extension']->get_allocatable();

        $html = '<h1>Adding a new extension to the deadline for '.$allocatable->name().'</h1>';

        echo $OUTPUT->header();
        echo $html;
        $vars['form']->display();
        echo $OUTPUT->footer();

    }

    /**
     * @param array $vars
     * @throws \coding_exception
     */
    public function edit_page($vars) {
        global $PAGE, $SITE, $OUTPUT;

        $allocatable = $vars['deadline_extension']->get_allocatable();

        $PAGE->set_pagelayout('standard');
        $PAGE->navbar->add('Edit deadline extension for '.$allocatable->name());
        $PAGE->set_title($SITE->fullname);
        $PAGE->set_heading($SITE->fullname);

        $html = '<h1>Editing the extension to the deadline for ' . $allocatable->name() . '</h1>';

        echo $OUTPUT->header();
        echo $html;
        $vars['form']->display();
        echo $OUTPUT->footer();
    }

}