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
 * Admin setting for auto grade class boundaries.
 * @package    mod_coursework
 * @copyright  2025 UCL.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework;

/**
 * Admin setting for auto grade class boundaries.
 * @package    mod_coursework
 * @copyright  2025 UCL.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_autogradeboundaries extends \admin_setting_configtextarea {
    /**
     * How many lines (grade classes) are required to be entered. Less than 2 means it cannot work.
     */
    const MIN_LINES_REQUIRED = 2;

    /** How many values (bottom and top) are expected per line. */
    const EXPECTED_VALUES_PER_LINE = 2;

    /** Maximum number of decimal places allowed in a value. */
    const MAX_DECIMAL_PLACES = 2;

    /**
     * Expected increment between grade classes (i.e. one band must immediately follow the other).
     */
    const EXPECTED_INCREMENT_BETWEEN_BANDS = 0.01;

    /**
     * Override parent method to validate data before storage.
     * @param string $data textarea content
     * @return bool|string true if ok, string if error found.
     */
    public function validate($data): bool|string {
        if (empty($data)) {
            return true;
        }
        $lines = explode("\n", $data);
        if (count($lines) < self::MIN_LINES_REQUIRED) {
            return get_string('gradeboundaryerrornotenoughbands', 'mod_coursework');
        }
        $previousbottom = null;
        foreach ($lines as $index => $line) {
            $parts = explode('|', $line);
            if (count($parts) != self::EXPECTED_VALUES_PER_LINE) {
                return get_string('gradeboundaryerrorpartcount', 'mod_coursework', $index + 1);
            }
            foreach ($parts as $part) {
                $part = clean_param($part, PARAM_LOCALISEDFLOAT);
                if ($part === false) {
                    return get_string(
                        'gradeboundaryerrorinvalidvalue',
                        'mod_coursework',
                        ['value' => $part, 'line' => $index + 1]
                    );
                }
                if ($part < 0) {
                    return get_string('gradeboundaryerrornegativevalue', 'mod_coursework', $index + 1);
                }
                if (str_contains((string)$part, '.') && strlen(explode('.', $part)[1]) > self::MAX_DECIMAL_PLACES) {
                    return get_string(
                        'gradeboundaryerrorinvalidvalue',
                        'mod_coursework',
                        ['value' => $part, 'line' => $index + 1]
                    );
                }
            }
            $bottom = clean_param($parts[0], PARAM_LOCALISEDFLOAT);
            $top = clean_param($parts[1], PARAM_LOCALISEDFLOAT);
            if ($bottom >= $top) {
                return get_string('gradeboundaryerrorinvalidrange', 'mod_coursework', $index + 1);
            }
            if (
                $previousbottom !== null
                && round($previousbottom - $top, self::MAX_DECIMAL_PLACES) !== self::EXPECTED_INCREMENT_BETWEEN_BANDS
            ) {
                return get_string(
                    'gradeboundaryerrorinvalidincrement',
                    'mod_coursework',
                    [
                        'previousbottom' => $previousbottom,
                        'thistop' => $top,
                        'line' => $index + 1,
                        'increment' => self::EXPECTED_INCREMENT_BETWEEN_BANDS,
                    ]
                );
            }
            $previousbottom = $bottom;
        }
        return parent::validate($data);
    }
}
