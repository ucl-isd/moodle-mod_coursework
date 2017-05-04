<?php

namespace mod_coursework\controllers;

use coding_exception;
use context;
use mod_coursework\framework\table_base;
use mod_coursework\router;
use mod_coursework\models\coursework;
use mod_coursework\models\submission;
use moodle_exception;

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
     * @var \stdClass
     */
    protected $coursemodule;

    /**
     * @var coursework
     */
    protected $coursework;

    /**
     * @var \stdClass
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
            $model_class = $this->model_class();
            $model_name = $this->model_name();
            $this->$model_name = $model_class::find($this->params['id']);
            $this->coursework = $this->$model_name->get_coursework();
        }

        if (!empty($this->params['courseworkid'])) {
            $coursework = $DB->get_record('coursework', array('id' => $this->params['courseworkid']), '*', MUST_EXIST);
            $this->coursework = coursework::find($coursework);

            $this->coursemodule = get_coursemodule_from_instance('coursework', $this->coursework->id);

            $this->params['courseid'] = $this->coursework->course;
        }

        // Not always clear if we will get cmid or courseworkid, so we have to be OK with either/or.
        if (empty($this->coursemodule) && !empty($this->params['cmid'])) {
            $this->coursemodule = get_coursemodule_from_id('coursework', $this->params['cmid'], 0, false, MUST_EXIST);
            if (empty($this->coursework)) {
                $this->coursework = coursework::find($this->coursemodule->instance);
            }
            $this->params['courseid'] = $this->coursemodule->course;
        }

        if (empty($this->course)) {

            if (!empty($this->params['courseid'])) {
                $this->course = $DB->get_record('course', array('id' => $this->params['courseid']), '*', MUST_EXIST);
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
     * @param $method_name
     * @param $arguments
     * @throws coding_exception
     */
    public function __call($method_name, $arguments) {

        if (method_exists($this, $method_name)) {
            $this->prepare_environment();
            call_user_func(array($this,
                                 $method_name));
        } else {
            throw new coding_exception('No page defined in the controller called "'.$method_name.'"');
        }
    }

    /**
     * @return context|mixed
     */
    protected function get_context() {
        return $this->coursework->get_context();
    }

    /**
     * This centralises the paths that we will use. It's the beginning of a router.
     *
     * @param string $path_name
     * @param $items
     * @return string
     */
    protected function get_path($path_name, $items) {

        return call_user_func_array(array($this->get_router(), 'get_path'), func_get_args());

    }

    /**
     * @return router
     */
    protected function get_router() {

        return router::instance();
    }

    /**
     * @return \mod_coursework_object_renderer
     */
    protected function get_object_renderer() {
        global $PAGE;

        return $PAGE->get_renderer('mod_coursework', 'object');
    }

    /**
     * @return \mod_coursework_page_renderer
     */
    protected function get_page_renderer() {
        global $PAGE;

        return $PAGE->get_renderer('mod_coursework', 'page');
    }

    /**
     * @param string $page_name
     */
    protected function render_page($page_name) {
        $renderer_class = $this->renderer_class();
        $renderer = new $renderer_class;
        $function_name = $page_name . '_page';
        $renderer->$function_name(get_object_vars($this));
        $this->page_rendered = true;
    }

    /**
     * @return string
     */
    public function model_name() {
        $class_name = get_class($this);
        $bits = explode('\\', $class_name);
        $controller_name = end($bits);
        return str_replace('s_controller', '', $controller_name);
    }

    /**
     * Tells us whether the user pressed the cancel button in a moodle form
     *
     * @return bool
     * @throws \coding_exception
     */
    protected function cancel_button_was_pressed() {
        return !!optional_param('cancel', false, PARAM_ALPHA);
    }

    /**
     * @return table_base
     */
    protected function model_class() {
        $renderer_class_name = "\\mod_coursework\\models\\{$this->model_name()}";
        return $renderer_class_name;
    }

    /**
     * @return string
     */
    protected function renderer_class() {
        $renderer_class_name = "\\mod_coursework\\renderers\\{$this->model_name()}_renderer";
        return $renderer_class_name;
    }
}