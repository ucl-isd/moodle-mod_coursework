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

use core\exception\invalid_parameter_exception;
use mod_coursework\auto_grader\average_grade_no_straddle;

use moodleform;

defined('MOODLE_INTERNAL') || die();

// If we are at system level, forms lib needs to be required.
require_once("$CFG->libdir/formslib.php");

/**
 * Simple form providing a set of grade class boundary percentages.
 */
class grade_class_boundaries extends moodleform {
    /**
     * For these purposes we do not refer to max or min grades for an individual coursework, as we do not have a coursework object.
     * Instead it is explained to the user on the form that for validation, entered grades must cover the range 0 - 100.
     * For ease of implementation, it is assumed that this functionality is only used for courseworks graded 0 - 100.
     */
    const MIN_GRADE = 0;

    /**
     *  It is explained to the user on the form that for validation, entered grades must cover the range 0 - 100.
     */
    const MAX_GRADE = 100;

    /**
     * For these purposes, it is assumed that only 2 decimal places are used, even if course grade setting allows for more.
     */
    const DECIMAL_PLACES = 2;

    /**
     * We allow 6 bands though user may have left some empty when saving form.
     */
    const NUMBER_OF_BANDS = 6;

    /**
     * Makes the form elements.
     */
    public function definition() {
        global $DB;

        $templatename = $this->_customdata['templateid']
            ? $DB->get_field(
                'coursework_class_boundary_templates',
                'name',
                ['id' => $this->_customdata['templateid']],
            )
            : null;

        if ($this->_customdata['templateid'] && !$templatename) {
            throw new invalid_parameter_exception("Invalid template ID " . $this->_customdata['templateid']);
        }

        $mform =& $this->_form;

        $mform->addElement('hidden', 'templateid', $this->_customdata['templateid']);
        $mform->setType('templateid', PARAM_INT);

        $mform->addElement(
            'html',
            \html_writer::tag('h1', get_string('gradeboundariessettingfor', 'coursework', $templatename), ['class' => 'h2'])
        );

        $mform->addElement('html', \html_writer::tag(
            'p',
            get_string('automaticagreementgradebands_form_desc', 'coursework'))
        );

        $mform->addElement('text', 'templatename', get_string('gradeclassboundarytemplatename', 'mod_coursework'));

        $mform->setType('templatename', PARAM_TEXT);
        $mform->setDefault('templatename', $templatename);
        $mform->addRule('templatename', null, 'required');

        $bands = average_grade_no_straddle::get_grade_class_boundaries($this->_customdata['templateid']);
        if (count($bands) < self::NUMBER_OF_BANDS) {
            // Add some blank fields to form in case user wants to add more fields.
            for ($i = count($bands) + 1; $i <= self::NUMBER_OF_BANDS; $i++) {
                $bands[] = ['bottom' => null, 'top' => null];
            }
        }
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
                "grade_boundary_top_$index",
                get_string('gradeclassboundariesrangewarning', 'mod_coursework'),
                'numeric', null, 'client'
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

        if (is_siteadmin()) {
            // Only site admin can edit - markers can view form only (to save having a special page to view fields).
            $this->add_action_buttons();
        }

    }

    /**
     * Validate submitted data.
     * @param $data
     * @param $files
     * @return array of errors
     */
    public function validation($data, $files): array {
        if (!is_siteadmin()) {
            throw new \Exception("Only site admin allowed to edit");
        }
        $errors = [];
        $data = (array)$data;
        $boundaries = self::parse_form_data($data);
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
            if (!is_numeric($boundary['top']) || $boundary['top'] < self::MIN_GRADE) {
                $errors["grade_boundary_top_" . $boundary['index']] = get_string('error');
            } else if (abs(number_format($boundary['top'], self::DECIMAL_PLACES, '.', '') - $boundary['top']) > 0) {
                $errors["grade_boundary_top_" . $boundary['index']] =
                    get_string('gradeboundarydecimalprecisionwarning', 'coursework');
            }
            if (!is_numeric($boundary['bottom']) || $boundary['bottom'] < self::MIN_GRADE) {
                $errors["grade_boundary_bottom_" . $boundary['index']] = get_string('required');
            } else if (abs(number_format($boundary['bottom'], self::DECIMAL_PLACES, '.', '') - $boundary['bottom']) > 0) {
                $errors["grade_boundary_bottom_" . $boundary['index']] =
                    get_string('gradeboundarydecimalprecisionwarning', 'coursework');
            }
            if ($boundary['bottom'] > $boundary['top']) {
                $errors["grade_boundary_bottom_" . $boundary['index']] = get_string('gradeboundarytopbottommismatch', 'coursework');
            }
            if ($boundary['bottom'] < 0) {
                $errors["grade_boundary_bottom_" . $boundary['index']] =
                    get_string('gradeclassboundariesrangewarning', 'coursework');
            }
            if ($boundary['top'] > self::MAX_GRADE) {
                $errors["grade_boundary_top_" . $boundary['index']] =
                    get_string('gradeclassboundariesrangewarning', 'coursework');
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
        $bottomprevious = self::MAX_GRADE;
        $bottomboundaryitem = null;
        foreach ($boundaries as $boundary) {
            if ($boundary['top'] === '' && $boundary['bottom'] === '') {
                // We allow both top and bottom to be null as this indicates that this band is not in use.
                continue;
            }
            // Check if there is a gap between previous bottom and current top.
            if (!isset($errors["grade_boundary_top_" . $boundary['index']])) {
                if (number_format(abs($bottomprevious - $boundary['top']), self::DECIMAL_PLACES) > 0.01) {
                    $errors["grade_boundary_top_" . $boundary['index']] =
                        get_string(
                            'gradeclasssetboundariesgapwarning',
                            'coursework',
                            ['top' => $boundary['top'], 'bottom' => $bottomprevious]
                        );
                }
                $bottomprevious = min($bottomprevious, $boundary['bottom']);
            }
            $bottomboundaryitem = $boundary;
        }

        // Finally check bottom boundary covers min possible mark.
        if (self::MIN_GRADE != $bottomboundaryitem['bottom']) {
            $errors["grade_boundary_bottom_" . $bottomboundaryitem['index']] =
                get_string(
                    'gradeclasssetboundariesgapwarning',
                    'coursework',
                    ['bottom' => $bottomboundaryitem['bottom'], 'top' => self::MIN_GRADE]
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
