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
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\exceptions;

use mod_coursework\models\coursework;
use mod_coursework\router;

/**
 * Class access_denied is used when we have a user who is sometimes allowed to do something (has_capability()
 * returns true for this context), but is prevented by the business rules of the plugin. e.g. cannot access the
 * new submission page because they have already submitted. This should only be used in controller actions that
 * the user would not normally see a link to.
 *
 * @package mod_coursework
 */
class late_submission extends \moodle_exception {

    /**
     * @param coursework $coursework
     * @param string $message'
     * @throws \coding_exception
     */
    public function __construct($coursework, $message = null) {

        $link = router::instance()->get_path('coursework', array('coursework' => $coursework));

        parent::__construct('latesubmissionsnotallowed', 'mod_coursework', $link, null, $message);
    }
}
