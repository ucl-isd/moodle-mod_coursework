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
 * Custom number form element
 *
 * @package    mod_coursework
 * @copyright  2026 UCL {@link https://www.ucl.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_coursework\forms;

use MoodleQuickForm_text;
use renderer_base;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/form/text.php');

/**
 * Number type form element.
 *
 * Extends the text element to render as HTML5 input type="number"
 * with support for min, max, and step attributes.
 *
 * @package    mod_coursework
 * @copyright  2026 UCL {@link https://www.ucl.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class number_element extends MoodleQuickForm_text {
    /**
     * Constructor.
     *
     * @param string $elementname Element name
     * @param mixed $elementlabel Label(s) for an element
     * @param array $attributes Element attributes.
     */
    public function __construct($elementname = null, $elementlabel = null, $attributes = null) {
        parent::__construct($elementname, $elementlabel, $attributes);
        $this->_type = 'number';
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $context = parent::export_for_template($output);
        $context = array_merge(
            $context,
            [
                'name' => $this->getName(),
                'id' => $this->getAttribute('id'),
                'value' => $this->getValue(),
                'min' => $this->getAttribute('min'),
                'max' => $this->getAttribute('max'),
                'step' => $this->getAttribute('step') ?: 'any',
                'required' => $this->getAttribute('required'),
            ]
        );
        return $context;
    }

    /**
     * Returns the HTML for this form element.
     *
     * @return string
     */
    public function toHtml(): string { // @codingStandardsIgnoreLine
        global $OUTPUT;
        $context = $this->export_for_template($OUTPUT);
        return $OUTPUT->render_from_template('mod_coursework/form_number', $context);
    }
}
