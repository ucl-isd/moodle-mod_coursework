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
 * @copyright  2017 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\framework;

/**
 * Class decorator
 *
 * Acts as a decorator around a class. Remember to add the @ mixin property so that PHPStorm will
 * provide autocompletion of methods and properties.
 */
class decorator {
    /**
     * @var
     */
    protected $wrappedobject;

    /**
     * @param $wrappedobject
     */
    public function __construct($wrappedobject) {
        $this->wrappedobject = $wrappedobject;
    }

    /**
     * Delegate everything to the wrapped object by default.
     *
     * @param $method
     * @param $args
     * @return mixed
     */
    public function __call($method, $args) {
        return call_user_func_array(
            [$this->wrappedobject,
                                          $method],
            $args
        );
    }

    /**
     * Delegate everything to the wrapped object by default.
     *
     * @param $name
     * @return mixed
     */
    public function __get($name) {
        return $this->wrappedobject->$name;
    }

    /**
     * Delegate everything to the wrapped object by default.
     *
     * @param $name
     * @param $value
     * @return mixed
     */
    public function __set($name, $value) {
        return $this->wrappedobject->$name = $value;
    }

    /**
     * @return mixed
     */
    public function wrapped_object() {
        return $this->wrappedobject;
    }
}
