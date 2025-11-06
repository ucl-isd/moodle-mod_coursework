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

namespace mod_coursework;
use coding_exception;
use moodle_url;

/**
 * This holds the routes that the Coursework module uses, so that we don't have to keep manually entering them
 * in multiple places. The routes should be RESTful:
 *
 * Index
 * Show
 * New - show form
 * Create - save form
 * Edit - show form
 * Update - save form
 * Destroy
 */
class router {

    /**
     * @var router
     */
    private static $instance;

    /**
     * Singleton.
     */
    private function __construct() {
    }

    /**
     * Singleton accessor.
     *
     * @return router
     */
    public static function instance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @param string $pathname e.g. 'edit feedback'
     * @param array $items named array keys so we can construct meaningful paths.
     * @param bool $asurlobject return the moodle_url or a string of the path?
     * @param bool $escaped
     * @throws coding_exception
     * @return moodle_url|string url
     */
    public function get_path($pathname, $items = [], $asurlobject = false, $escaped = true) {

        global $CFG;

        $contextid = false;
        $coursemoduleid = false;

        if (array_key_exists('coursework', $items)) {
            /**
             * @var coursework $coursework
             */
            $coursework = $items['coursework'];
            $contextid = $coursework->get_context_id();
            $coursemoduleid = $coursework->get_coursemodule_id();
        }

        $url = false;

        switch ($pathname) {

            case 'create feedback':
                $url = new moodle_url('/mod/coursework/actions/feedbacks/create.php');
                break;

            case 'course':
                $url = new moodle_url('/course/view.php', ['id' => $items['course']->id]);
                break;

            case 'edit coursework':
                $url = new moodle_url('/mod/edit.php');
                break;

            case 'coursework settings':
                $url = new moodle_url('/course/modedit.php', ['update' => $coursemoduleid]);
                break;

            case 'coursework':
                $url = new moodle_url('/mod/coursework/view.php', ['id' => $coursemoduleid]);
                break;

            case 'allocations':
                $url = new moodle_url('/mod/coursework/actions/allocate.php',
                                      ['id' => $coursemoduleid]);
                break;

            case 'assessor grading':

            case 'new feedback':
                $url = new moodle_url('/mod/coursework/actions/feedbacks/new.php',
                                          ['submissionid' => $items['submission']->id,
                                                'stageidentifier' => $items['stage']->identifier(),
                                                'assessorid' => $items['assessor']->id]);
                break;

            case 'new final feedback':
                $params = ['submissionid' => $items['submission']->id,
                                'stageidentifier' => $items['stage']->identifier(),
                                'isfinalgrade' => 1];
                $url = new moodle_url('/mod/coursework/actions/feedbacks/new.php', $params);
                break;

            case 'new submission':
                $url = new moodle_url('/mod/coursework/actions/submissions/new.php',
                                      [
                                          'allocatableid' => $items['submission']->allocatableid,
                                          'allocatabletype' => $items['submission']->allocatabletype,
                                          'courseworkid' => $items['submission']->courseworkid,
                                      ]);
                break;

            case 'edit feedback':
                $url = new moodle_url('/mod/coursework/actions/feedbacks/edit.php',
                                      ['feedbackid' => $items['feedback']->id]);
                break;

            case 'update feedback':
                $url = new moodle_url('/mod/coursework/actions/feedbacks/update.php',
                                      ['feedbackid' => $items['feedback']->id]);
                break;

            case 'new deadline extension':
                $url = new moodle_url('/mod/coursework/actions/deadline_extensions/new.php',
                                      $items);
                break;

            case 'edit deadline extension':
                $url = new moodle_url('/mod/coursework/actions/deadline_extensions/edit.php',
                                      $items);
                break;

            case 'edit personal deadline':
                $url = new moodle_url('/mod/coursework/actions/personaldeadline.php',
                    $items);
                break;

            case 'set personal deadlines':
                $url = new moodle_url('/mod/coursework/actions/set_personaldeadlines.php',
                    ['id' => $coursemoduleid]);
                break;

            case 'new moderations':
                $params = ['submissionid' => $items['submission']->id,
                                'stageidentifier' => $items['stage']->identifier(),
                                'feedbackid' => $items['feedbackid']];
                $url = new moodle_url('/mod/coursework/actions/moderations/new.php', $params);
                break;

            case 'create moderation agreement':
                $url = new moodle_url('/mod/coursework/actions/moderations/create.php');
                break;

            case 'edit moderation':
                $url = new moodle_url('/mod/coursework/actions/moderations/edit.php',
                                      ['moderationid' => $items['moderation']->id,
                                           'feedbackid' => $items['moderation']->feedbackid]);
                break;

            case 'update moderation':
                $url = new moodle_url('/mod/coursework/actions/moderations/update.php');
                break;

            case 'show moderation':
                $url = new moodle_url('/mod/coursework/actions/moderations/show.php',
                                        ['moderationid' => $items['moderation']->id,
                                        'feedbackid' => $items['moderation']->feedbackid]);

                break;

            case 'new plagiarism flag':
                $url = new moodle_url('/mod/coursework/actions/plagiarism_flagging/new.php',
                                        ['submissionid' => $items['submission']->id ]);

                break;

            case 'create plagiarism flag':
                $url = new moodle_url('/mod/coursework/actions/plagiarism_flagging/create.php');

                break;

            case 'edit plagiarism flag':
                $url = new moodle_url('/mod/coursework/actions/plagiarism_flagging/edit.php',
                                        ['flagid' => $items['flag']->id ]);

                break;

            case 'update plagiarism flag':
                $url = new moodle_url('/mod/coursework/actions/plagiarism_flagging/update.php',
                                        ['flagid' => $items['flag']->id]);
                break;

        }

        if (!$url) {

            // Try to auto construct it.
            $bits = explode(' ', $pathname);
            $action = array_shift($bits);
            $type = implode('_', $bits);

            $autopath = '/mod/coursework/actions/' . $this->pluralise($type) . '/' . $action . '.php';
            if (file_exists($CFG->dirroot . $autopath)) {

                $params = [];
                if (array_key_exists($type, $items)) {
                    $params[$type.'id'] = $items[$type]->id;
                } else if (array_key_exists('coursework', $items)) {
                    $params['courseworkid'] = $items['coursework']->id;
                } else if (array_key_exists('courseworkid', $items)) {
                    $params['courseworkid'] = $items['courseworkid'];
                }

                $url = new moodle_url($autopath, $params);
            }
        }

        if (!$url) {
            throw new coding_exception("No target file for path: '{$pathname}'");
        }

        return $asurlobject ? $url : $url->out($escaped);
    }

    /**
     * Might need more complex pluralisation rules later.
     *
     * @param $string
     * @return mixed
     */
    protected function pluralise($string) {
        return $string . 's';
    }

}
