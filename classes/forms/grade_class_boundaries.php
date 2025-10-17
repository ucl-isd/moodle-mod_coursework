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
use mod_coursework\auto_grader\average_grade_no_straddle;

use moodleform;

// If we are at system level, forms lib needs to be required.
require_once("$CFG->libdir/formslib.php");

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

        $mform->addElement('hidden', 'courseworkid', $this->_customdata['courseworkid'] ?? 0);
        $mform->setType('courseworkid', PARAM_INT);

        $coursework = $this->get_coursework();
        $titleelement = $coursework ? $coursework->name : get_string('systemdefault', 'coursework');
        $mform->addElement(
            'html',
            \html_writer::tag('h1', get_string('gradeboundariessettingfor', 'coursework', $titleelement), ['class' => 'h5'])
        );

        $mform->addElement('html', \html_writer::tag(
            'p',
            get_string('automaticagreementgradebands_form_desc', 'coursework'))
        );

        $hascustomboundaries = $coursework && average_grade_no_straddle::has_grade_class_boundaries_db($coursework->id);
        if (!$coursework) {
            $mform->addElement(
                'html',
                \html_writer::div(
                    '<i class="fa fa-fw fa-exclamation-triangle mr-1"></i>' .
                    get_string('gradeclasssetboundariessettingsystem', 'coursework'),
                    'alert alert-warning'
                )
            );
        } else if (!$hascustomboundaries) {
            $mform->addElement(
                'html',
                \html_writer::div(
                    '<i class="fa fa-fw fa-info-circle mr-1"></i>' .
                    get_string('gradeclasssetboundariesnotyetset', 'coursework'),
                    'alert alert-info'
                )
            );
        }
        $bands = average_grade_no_straddle::get_grade_class_boundaries($hascustomboundaries ? $coursework->id : 0);
        foreach ($bands as $index => $band) {
            $mform->addElement('html', '<hr/><h2 class="h5">' . get_string('band', 'coursework') . '</h2>');
            $mform->addElement(
                'float',
                "grade_boundary_top_$index",
                get_string('gradeboundarytop', 'mod_coursework', $index),
                ['size' => '5']
            );
            $mform->setType("grade_boundary_top_$index", PARAM_TEXT);
            $mform->addRule(
                "grade_boundary_top_$index", get_string('err_valueoutofrange', 'mod_coursework'), 'numeric', null, 'client'
            );
            $default = $band['top'] ?? null;
            if ($default !== null) {
                $mform->setDefault("grade_boundary_top_$index", number_format($default, 2));
            }

            $mform->addElement(
                'float',
                "grade_boundary_bottom_$index",
                get_string('gradeboundarybottom', 'mod_coursework', $index),
                ['size' => '5'],
            );
            $mform->setType("grade_boundary_bottom_$index", PARAM_TEXT);
            $default = $band['bottom'] ?? null;
            if ($default !== null) {
                $mform->setDefault("grade_boundary_bottom_$index", number_format($default, 2));
            }
        }
        $mform->addElement('html', '<hr/>');

        $this->add_action_buttons();

    }

    /**
     * Get the coursework for this form.  Null means we are setting default boundaries at system level.
     * @return coursework|null
     */
    protected function get_coursework(): ?coursework {
        if ($this->coursework === null && ($this->_customdata['courseworkid'] ?? 0)) {
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
        $gradeoptions = $this->get_coursework()
            ? array_keys(make_grades_menu($this->get_coursework()->grade))
            : null;
        foreach ($boundaries as $boundary) {
            if ($boundary['top'] === '' && $boundary['bottom'] === '') {
                // We allow both top and bottom to be null as this indicates that this band is not in use.
                continue;
            }
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
            if ($boundary['bottom'] < 0 || ($gradeoptions && $boundary['bottom'] < min($gradeoptions))) {
                $errors["grade_boundary_bottom_" . $boundary['index']] = get_string('err_valueoutofrange', 'coursework');
            }
            if ($gradeoptions && $boundary['top'] > max($gradeoptions)) {
                $errors["grade_boundary_top_" . $boundary['index']] = get_string('err_valueoutofrange', 'coursework');
            }

            // Now check if any of the entered bands overlap.
            if (self::count_bands($boundary['bottom'], $boundary['top'], $boundaries) > 1) {
                // Band should only "overlap" with itself (i.e. count = 1) and not other bands (count > 1).
                $errors["grade_boundary_top_" . $boundary['index']]
                    = $errors["grade_boundary_bottom_" . $boundary['index']]
                    = get_string('automaticagreementgradebandsoverlap', 'coursework');
            }
        }

        // Now check if there are any gaps in the boundaries.
        // (Boundaries sorted in descending order).
        $bottomprevious = max($gradeoptions);
        foreach ($boundaries as $boundary) {
            // Check if there is a gap between previous bottom and current top.
            if (number_format(abs($bottomprevious - $boundary['top']), 2) > 0.01) {
                $errors["grade_boundary_top_" . $boundary['index']] =
                    get_string(
                        'gradeclasssetboundariesgapwarning',
                        'coursework',
                        ['top' => $boundary['top'], 'bottom' => $bottomprevious]
                    );
            }
            $bottomprevious = min($bottomprevious, $boundary['bottom']);
        }

        // Finally check bottom boundary covers min possible mark.
        $bottomboundaryitem = end($boundaries);
        if (min($gradeoptions) != $bottomboundaryitem['bottom']) {
            $errors["grade_boundary_bottom_" . $bottomboundaryitem['index']] =
                get_string(
                    'gradeclasssetboundariesgapwarning',
                    'coursework',
                    ['bottom' => $bottomboundaryitem['bottom'], 'top' => min($gradeoptions)]
                );
        }

        return $errors;
    }

    /**
     * Count how many bands the current start and end overlap with.
     * @param $rangestart
     * @param $rangeend
     * @param $bands
     * @return int
     */
    public static function count_bands($rangestart, $rangeend, $bands): int {
        $count = 0;
        foreach ($bands as $band) {
            // Check if there is any overlap between the range and the band.
            if ($rangestart <= $band['top'] && $rangeend >= $band['bottom']) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Parse the grade boundary figures from submitted form data into an object.
     * @param array $data
     * @return array
     */
    public static function parse_form_data(array $data): array {
        $keys = array_keys($data);
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
        usort($boundaries, function ($a, $b) {
            return $a['index'] <=> $b['index'];
        });
        return $boundaries;
    }
}
