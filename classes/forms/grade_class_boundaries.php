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
 * Creates mform for grade class boundary editing.
 *
 * @package    mod_coursework
 * @copyright  2025 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\forms;

use mod_coursework\models\coursework;

use moodleform;

/**
 * Simple form providing a set of grade class boundary percentages.
 */
class grade_class_boundaries extends moodleform {

    /**
     * The coursework object
     * @var ?coursework $coursework
     */
    protected ?coursework $coursework = null;

    /**
     * Makes the form elements.
     */
    public function definition() {

        $mform =& $this->_form;

        $mform->addElement('hidden', 'courseworkid', $this->_customdata['courseworkid']);
        $mform->setType('courseworkid', PARAM_INT);

        $coursework = $this->get_coursework();
        $titleelement = $coursework ? $coursework->name : get_string('systemdefault', 'coursework');
        $mform->addElement(
            'html',
            \html_writer::tag('h1', get_string('gradeboundariessettingfor', 'coursework', $titleelement), ['class' => 'h2'])
        );

        $mform->addElement('html', \html_writer::tag(
            'p',
            get_string('automaticagreementgradebands_form_desc', 'coursework'))
        );

        $defaults = $this->get_default_boundaries();

        $maxbands = 6;
        for ($i = 1; $i <= $maxbands; $i++) {
            $mform->addElement('html', '<hr/><h2 class="h5">' . get_string('band', 'coursework') . '</h2>');
            $mform->addElement(
                'float',
                "grade_boundary_top_$i",
                get_string('gradeboundarytop', 'mod_coursework', $i),
                ['size' => '5']
            );
            $mform->setType("grade_boundary_top_$i", PARAM_TEXT);
            $mform->addRule(
                "grade_boundary_top_$i", get_string('err_valueoutofrange', 'mod_coursework'), 'numeric', null, 'client'
            );
            $default = $defaults[$i - 1][1] ?? null;
            if ($default !== null) {
                $mform->setDefault("grade_boundary_top_$i", number_format($default, 2));
            }

            $mform->addElement(
                'float',
                "grade_boundary_bottom_$i",
                get_string('gradeboundarybottom', 'mod_coursework', $i),
                ['size' => '5'],
            );
            $mform->setType("grade_boundary_bottom_$i", PARAM_TEXT);
            $default = $defaults[$i - 1][0] ?? null;
            if ($default !== null) {
                $mform->setDefault("grade_boundary_bottom_$i", number_format($default, 2));
            }
        }
        $mform->addElement('html', '<hr/>');

        $this->add_action_buttons();

    }

    /**
     * Get the default grade boundaries.
     * @return array[]
     */
    protected function get_default_boundaries(): array {
        return [
            [70.00, 100.00],
            [60.00, 69.99],
            [50.00, 59.99],
            [40.00, 49.99],
            [1.00, 39.99],
            [0.00, 0.99],
        ];
    }

    /**
     * Get the coursework for this form.  Null means we are setting default boundaries at system level.
     * @return coursework|null
     */
    protected function get_coursework(): ?coursework {
        if ($this->coursework === null && $this->_customdata['courseworkid']) {
            $this->coursework = coursework::find($this->_customdata['courseworkid']);
        }
        return $this->coursework;
    }

    /**
     * Validate submitted data.
     * @param $data
     * @param $files
     * @return array of errors
     */
    public function validation($data, $files): array {
        $errors = [];
        $data = (array)$data;
        $boundaries = self::parse_form_data($data);
        $gradeoptions = array_keys(make_grades_menu($this->get_coursework()->grade));
        foreach ($boundaries as $boundary) {
            if ($boundary['top'] && $boundary['bottom'] === '') {
                $errors["grade_boundary_bottom_" . $boundary['index']] = get_string('error');
            } else if ($boundary['bottom'] && $boundary['top'] === '') {
                $errors["grade_boundary_top_" . $boundary['index']] = get_string('required');
            }
            if (!is_numeric($boundary['top']) || $boundary['top'] < 0) {
                $errors["grade_boundary_top_" . $boundary['index']] = get_string('required');
            } else if (abs(number_format($boundary['top'], 2, '.', '') - $boundary['top']) > 0) {
                $errors["grade_boundary_top_" . $boundary['index']] =
                    get_string('gradeboundarydecimalprecisionwarning', 'coursework');
            }
            if (!is_numeric($boundary['bottom']) || $boundary['bottom'] < 0) {
                $errors["grade_boundary_bottom_" . $boundary['index']] = get_string('required');
            } else if (abs(number_format($boundary['bottom'], 2, '.', '') - $boundary['bottom']) > 0) {
                $errors["grade_boundary_bottom_" . $boundary['index']] =
                    get_string('gradeboundarydecimalprecisionwarning', 'coursework');
            }
            if ($boundary['bottom'] > $boundary['top']) {
                $errors["grade_boundary_bottom_" . $boundary['index']] = get_string('gradeboundarytopbottommismatch', 'coursework');
            }
            if ($boundary['bottom'] < min($gradeoptions)) {
                $errors["grade_boundary_bottom_" . $boundary['index']] = get_string('err_valueoutofrange', 'coursework');
            }
            if ($boundary['top'] > max($gradeoptions)) {
                $errors["grade_boundary_top_" . $boundary['index']] = get_string('err_valueoutofrange', 'coursework');
            }
        }
        return $errors;
    }

    /**
     * Parse the grade boundary figures from submitted form data into an object.
     * @param $data
     * @return array
     */
    public static function parse_form_data($data): array {
        $keys = array_keys($data);
        sort($keys);
        $boundaries = [];
        foreach ($keys as $key) {
            if (preg_match('/^grade_boundary_(top|bottom)_[\d]+$/', $key)) {
                $index = (int)filter_var($key, FILTER_SANITIZE_NUMBER_INT);
                $istop = str_starts_with($key, 'grade_boundary_top_');
                if (!isset($boundaries[$index])) {
                    $boundaries[$index] = [
                        'index' => $index,
                        'top' => $istop ? $data[$key] : null,
                        'bottom' => !$istop ? $data[$key] : null,
                    ];
                } else if ($istop) {
                    $boundaries[$index]['top'] = $data[$key];
                } else {
                    $boundaries[$index]['bottom'] = $data[$key];
                }
            }
        }
        return $boundaries;
    }
}
