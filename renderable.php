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

// phpcs:disable PSR1.Classes.ClassDeclaration

/**
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This file contains all of the renderable class definitions. They are separate from the main
 * autoloaded class definitions in /classes because the renderer cannot deal with namespaces.
 */

use mod_coursework\framework\decorator;
use mod_coursework\models\coursework;

defined('MOODLE_INTERNAL') || die();

/**
 * Class mod_coursework_renderable
 *
 * Acts as a decorator around a class. Remember to add the @ mixin property so that PHPStorm will
 * provide autocompletion of methods and properties in the renderer. We only need this because feeding
 * a namespaced class to the renderer borks it.
 */
class mod_coursework_renderable extends decorator implements renderable {
}

class mod_coursework_allocation_table extends mod_coursework_renderable {
}

class mod_coursework_allocation_table_row extends mod_coursework_renderable {
}

class mod_coursework_allocation_widget extends mod_coursework_renderable {
}

class mod_coursework_assessor_feedback_row extends mod_coursework_renderable {
}

class mod_coursework_assessor_feedback_table extends mod_coursework_renderable {
}

class mod_coursework_coursework extends mod_coursework_renderable {
    /**
     * Return coursework object not parent.
     * @return coursework
     */
    // phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod
    public function wrapped_object(): coursework {
        return parent::wrapped_object();
    }
}

class mod_coursework_sampling_set_widget extends mod_coursework_renderable {
}

class mod_coursework_submission_files extends mod_coursework_renderable {
}

class mod_coursework_feedback_files extends mod_coursework_renderable {
}

class mod_coursework_personaldeadlines_table extends mod_coursework_renderable {
}
