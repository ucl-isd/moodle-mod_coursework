<?php

/**
 * This file contains all of the renderable class definitions. They are separate from the main
 * autoloaded class definitions in /classes because the renderer cannot deal with namespaces.
 */
use mod_coursework\render_helpers\grading_report\cells\cell_interface;
use mod_coursework\render_helpers\grading_report\component_factory_interface;
use mod_coursework\render_helpers\grading_report\sub_rows\sub_rows_interface;
use mod_coursework\user_row;
use mod_coursework\framework;

/**
 * Class mod_coursework_renderable
 *
 * Acts as a decorator around a class. Remember to add the '@mixin' property so that PHPStorm will
 * provide autocompletion of methods and properties in the renderer. We only need this because feeding
 * a namespaced class to the renderer borks it.
 */
class mod_coursework_renderable extends \mod_coursework\framework\decorator implements renderable {

}

/**
 * @mixin \mod_coursework\allocation\table\builder
 */
class mod_coursework_allocation_table extends mod_coursework_renderable {

}

/**
 * @mixin \mod_coursework\allocation\table\row\builder
 */
class mod_coursework_allocation_table_row extends mod_coursework_renderable {

}

/**
 * @mixin \mod_coursework\allocation\widget
 */
class mod_coursework_allocation_widget extends mod_coursework_renderable {
}

/**
 * @mixin \mod_coursework\assessor_feedback_row
 */
class mod_coursework_assessor_feedback_row extends mod_coursework_renderable {
}

/**
 * @mixin \mod_coursework\assessor_feedback_table
 */
class mod_coursework_assessor_feedback_table extends mod_coursework_renderable {
}

/**
 * @mixin \mod_coursework\models\coursework
 */
class mod_coursework_coursework extends mod_coursework_renderable  { }


/**
 * @mixin \mod_coursework\grading_table_row_multi
 */
class mod_coursework_grading_table_row_multi extends mod_coursework_renderable {
}

/**
 * @mixin \mod_coursework\grading_table_row_single
 */
class mod_coursework_grading_table_row_single extends mod_coursework_renderable {
}

/**
 * @mixin \mod_coursework\moderation_set_widget
 */
class mod_coursework_moderation_set_widget extends mod_coursework_renderable {
}

/**
 * @mixin \mod_coursework\sampling_set_widget
 */
class mod_coursework_sampling_set_widget extends mod_coursework_renderable {
}

/**
 * @mixin \mod_coursework\submission_files
 */
class mod_coursework_submission_files extends mod_coursework_renderable {
}

/**
 * @mixin \mod_coursework\feedback_files
 */
class mod_coursework_feedback_files extends mod_coursework_renderable {
}

/**
 * @mixin \mod_coursework\personal_deadline\table\builder
 */
class mod_coursework_personal_deadlines_table extends mod_coursework_renderable {
}