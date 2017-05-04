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

namespace mod_coursework;

use Countable;

defined('MOODLE_INTERNAL') || die();

/**
 * Renderable files list object.
 */
abstract class files implements countable {

    /**
     * @var \stored_file[]
     */
    protected $files;

    /**
     * Can be either submission or feedbackfiles.
     * @var string
     */

    /**
     * @param array $files
     */
    public function __construct($files = array()) {
        $this->files = $files;
    }

    /**
     * Getter for the files.
     *
     * @return array
     */
    public function get_files() {
        return $this->files;
    }

    /**
     * Do we have any actual files attached?
     *
     * @return bool
     */
    public function has_files() {
        return $this->files && count($this->files) > 0;
    }

    /**
     * Getter for type.
     *
     * @return string
     */
    abstract public function get_file_area_name();

    /**
     * So we know what lang strings to use, we need to know if the files are plural, so this tells us.
     *
     * @return bool
     */
    public function has_multiple_files() {
        return (count($this->files) > 1);
    }

    /**
     * @return int
     */
    public function count() {
        return count($this->files);
    }

}
