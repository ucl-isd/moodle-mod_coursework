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

namespace mod_coursework\controllers;

use coding_exception;
use context;
use core\exception\invalid_parameter_exception;
use mod_coursework\framework\table_base;
use mod_coursework\models\coursework;
use mod_coursework\models\submission;
use mod_coursework\router;
use mod_coursework_object_renderer;
use mod_coursework_page_renderer;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL' || die());

global $CFG;

require_once($CFG->dirroot . '/lib/adminlib.php');
require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/mod/coursework/renderer.php');

/**
 * Class mod_coursework_controller controls the page generation for all of the pages in the coursework module.
 *
 * It is the beginning of the process of tidying things up to make them a bit more MVC where possible.
 *
 * @property bool page_rendered
 */
class controller_base {
    /**
     * From the HTTP request
     *
     * @var array
     */
    protected $params;

    /**
     * @var submission
     */
    protected $submission;

    /**
     * @var stdClass
     */
    protected $coursemodule;

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @var stdClass
     */
    protected $course;

    /**
     * @var router
     */
    protected $router;

    /**
     * @param array $params
     */
    public function __construct($params) {
        $this->params = $params;
    }

    /**
     * This is intended to be a single point of entry, which does all of the boring stuff like check require_login etc.
     *
     * It will use any of the supplies parameters to make sure that the record exists and then assign the retrieved object
     * as $this->$classname. It then finds a method with the same name as $page_name and builds it.
     *
     * Currently fetches
     * $this->coursework
     * $this->coursemodule
     * $this->submission
     * $this->course
     */
    protected function prepare_environment() {

        global $DB;

        // if there's an id, lets's assume it's an edit or update and we should just get the main model
        if (!empty($this->params['id'])) {
            $modelclass = $this->model_class();
            $modelname = $this->model_name();
            $this->$modelname = $modelclass::get_from_id($this->params['id']);
            if (!$this->$modelname) {
                throw new invalid_parameter_exception("Cannot find $modelclass ID " . $this->params['id']);
            }
            $this->coursework = $this->$modelname->get_coursework();
        }
        if (!empty($this->params['courseworkid'])) {
            $this->coursework = coursework::get_from_id($this->params['courseworkid']);

            $this->coursemodule = get_coursemodule_from_instance('coursework', $this->coursework->id);

            $this->params['courseid'] = $this->coursework->course;
        }

        // Not always clear if we will get cmid or courseworkid, so we have to be OK with either/or.
        if (empty($this->coursemodule) && !empty($this->params['cmid'])) {
            $this->coursemodule = get_coursemodule_from_id('coursework', $this->params['cmid'], 0, false, MUST_EXIST);
            if (empty($this->coursework)) {
                $this->coursework = coursework::get_from_id($this->coursemodule->instance);
            }
            $this->params['courseid'] = $this->coursemodule->course;
        }

        if (empty($this->course)) {
            if (!empty($this->params['courseid'])) {
                $this->course = $DB->get_record('course', ['id' => $this->params['courseid']], '*', MUST_EXIST);
            }

            if (!empty($this->coursework)) {
                $this->course = $this->coursework->get_course();
            }

            if (empty($this->course)) {
                throw new moodle_exception('Not enough params to retrieve course');
            }
        }

        if (empty($this->coursemodule)) {
            if (!empty($this->coursework)) {
                $this->coursemodule = $this->coursework->get_course_module();
            }

            if (empty($this->coursemodule)) {
                throw new moodle_exception('Not enough params to retrieve coursemodule');
            }
        }

        if (empty($this->coursework)) {
            throw new moodle_exception('Not enough params to retrieve coursework');
        }

        require_login($this->course, false, $this->coursemodule);
    }

    /**
     * Single accessible method that look for a private method and uses it if its there, after preparing the environment.
     *
     * @param $methodname
     * @param $arguments
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public function __call($methodname, $arguments) {

        if (method_exists($this, $methodname)) {
            $this->prepare_environment();
            call_user_func([$this,
                                 $methodname]);
        } else {
            throw new coding_exception('No page defined in the controller called "' . $methodname . '"');
        }
    }

    /**
     * @return context
     */
    protected function get_context() {
        return $this->coursework->get_context();
    }

    /**
     * This centralises the paths that we will use. It's the beginning of a router.
     *
     * @param string $pathname
     * @param $items
     * @return string
     */
    protected function get_path($pathname, $items) {

        return call_user_func_array([$this->get_router(), 'get_path'], func_get_args());
    }

    /**
     * @return router
     */
    protected function get_router() {

        return router::instance();
    }

    /**
     * @return \renderer_base
     */
    protected function get_object_renderer() {
        global $PAGE;

        return $PAGE->get_renderer('mod_coursework', 'object');
    }

    /**
     * @return \renderer_base
     */
    protected function get_page_renderer() {
        global $PAGE;

        return $PAGE->get_renderer('mod_coursework', 'page');
    }

    /**
     * @param string $pagename
     */
    protected function render_page($pagename) {
        $rendererclass = $this->renderer_class();
        $renderer = new $rendererclass();
        $functionname = $pagename . '_page';
        $renderer->$functionname(get_object_vars($this));
        $this->page_rendered = true;
    }

    /**
     * @return string
     */
    public function model_name() {
        $classname = get_class($this);
        $bits = explode('\\', $classname);
        $controllername = end($bits);
        return str_replace('s_controller', '', $controllername);
    }

    /**
     * Tells us whether the user pressed the cancel button in a moodle form
     *
     * @return bool
     * @throws coding_exception
     */
    protected function cancel_button_was_pressed() {
        return (bool)optional_param('cancel', false, PARAM_ALPHA);
    }

    /**
     * @return string
     */
    protected function model_class() {
        return "\\mod_coursework\\models\\{$this->model_name()}";
    }

    /**
     * @return string
     */
    protected function renderer_class() {
        return "\\mod_coursework\\renderers\\{$this->model_name()}_renderer";
    }
}
