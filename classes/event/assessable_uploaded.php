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



namespace mod_coursework\event;
use mod_coursework\models\submission;
defined('MOODLE_INTERNAL') || die();


class assessable_uploaded extends \core\event\assessable_uploaded {

    /**
     * Legacy event files.
     *
     * @var array
     */
    protected $legacyfiles = array();

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has uploaded a file to the submission with id '$this->objectid' " .
        "in the coursework activity with course module id '$this->contextinstanceid'.";
    }

    /**
     * Legacy event data if get_legacy_eventname() is not empty.
     *
     * @return \stdClass
     */
    protected function get_legacy_eventdata() {


        $submission = submission::find($this->objectid);
        $fs = get_file_storage();
        $files = $fs->get_area_files($submission->get_context_id(), 'mod_coursework', 'submission',
            $submission->id, "id", false);

        $eventdata = new \stdClass();
        $eventdata->modulename = 'coursework';
        $eventdata->cmid = $submission->get_course_module_id();
        $eventdata->itemid = $submission->id;
        $eventdata->courseid = $submission->get_course_id();
        $eventdata->userid = $submission->get_author_id();


        $eventdata->timeavailable = $submission->get_coursework()->timecreated;
        $eventdata->timedue = $submission->get_coursework()->get_user_deadline($submission->get_author_id());
        $eventdata->feedbackavailable = $submission->get_coursework()->get_individual_feedback_deadline();
        if ($files) {
            $eventdata->pathnamehashes = array();
            foreach ($files as $file) {
                $eventdata->pathnamehashes[] = $file->get_pathnamehash();
            }
            $eventdata->files = $files;
        }
        return $eventdata;
    }


    /**
     * Return the legacy event name.
     *
     * @return string
     */
    public static function get_legacy_eventname() {
        return 'assessable_file_uploaded';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventassessableuploaded', 'coursework');
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/coursework/view.php', array('id' => $this->contextinstanceid));
    }

    /**
     * Sets the legacy event data.
     *
     * @param stdClass $legacyfiles legacy event data.
     * @return void
     */
    public function set_legacy_files($legacyfiles) {
        $this->legacyfiles = $legacyfiles;
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        parent::init();
        $this->data['objecttable'] = 'coursework_submissions';
    }

    public static function get_objectid_mapping() {
        return array('db' => 'coursework_submissions', 'restore' => 'submission');
    }
}
