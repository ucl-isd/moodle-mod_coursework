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
 * mod_coursework data generator
 *
 * @package    mod_coursework
 * @copyright  2012 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursework\models\allocation;
use mod_coursework\models\coursework;
use mod_coursework\models\feedback;
use mod_coursework\models\submission;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . "/mod/coursework/lib.php");

/**
 * Coursework module PHPUnit data generator class
 *
 * @package    mod_forum
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_coursework_generator extends testing_module_generator {
    /**
     * Create new coursework module instance.
     *
     * @param array|stdClass $record represents the data that would normally come from mod creation form:
     *                               - course is essential
     *                               - all else is optional and defaults will be supplied
     * @param array|null $options extra stuff for the coursemodule. idnumber, section, visible, etc.
     * @return coursework
     * @throws coding_exception
     */
    public function create_instance($record = null, ?array $options = null) {

        $this->instancecount++;
        $i = $this->instancecount;

        $record = (object)(array)$record;
        $options = (array)$options;

        if (empty($record->course)) {
            throw new coding_exception('module generator requires $record->course');
        }
        if (!isset($record->name)) {
            $record->name = get_string('pluginname', 'coursework') . ' ' . $i;
        }
        if (!isset($record->intro)) {
            $record->intro = 'Test coursework ' . $i;
        }
        if (!isset($record->idnumber)) {
            $record->idnumber = 'coursework' . $i;
        }
        if (!isset($record->introformat)) {
            $record->introformat = FORMAT_MOODLE;
        }

        // Coursework specific stuff.
        if (!isset($record->grade)) {
            $record->grade = 100;
        }
        if (!isset($record->deadline)) {
            $record->deadline = strtotime('+2 weeks');
        }
        if (!isset($record->numberofmarkers)) {
            $record->numberofmarkers = 2;
        }
        if (!isset($record->blindmarking)) {
            $record->blindmarking = 0;
        }
        if (!isset($record->maxbytes)) {
            $record->maxbytes = 1048576;
        }
        if (!isset($record->generalfeedback)) {
            $record->generalfeedback = strtotime('+2 weeks', $record->deadline);
        }
        if (!isset($record->individualfeedback)) {
            $record->individualfeedback = strtotime('+3 weeks', $record->deadline);
        }
        if (!isset($record->feedbackcommentformat)) {
            $record->feedbackcommentformat = FORMAT_HTML;
        }
        if (!isset($record->generalfeedbacktimepublished)) {
            $record->generalfeedbacktimepublished = 0;
        }

        if (empty($record->moderatorallocationstrategy)) {
            $record->moderatorallocationstrategy = 'none';
        }

        $instance = parent::create_instance($record, $options);
        return coursework::find($instance);
    }

    /**
     * Makes an allocation in the DB so we can then test with it.
     *
     * @param stdClass $allocation
     * @return stdClass
     * @throws coding_exception
     */
    public function create_allocation($allocation) {

        global $USER, $DB;

        if (empty($allocation->allocatableid) || !is_numeric($allocation->allocatableid)) {
            throw new coding_exception('Coursework generator needs an allocatableid for a new allocation');
        }

        if (empty($allocation->stageidentifier)) {
            throw new coding_exception('Coursework generator needs a stage identifier for a new allocation');
        }
        if (empty($allocation->courseworkid) || !is_numeric($allocation->courseworkid)) {
            throw new coding_exception('Coursework generator needs a courseworkid for a new allocation');
        }
        if (empty($allocation->assessorid) || !is_numeric($allocation->assessorid)) {
            if (!empty($USER->id)) {
                $allocation->assessorid = $USER->id;
            } else {
                throw new coding_exception('Coursework generator needs an assessorid for a new allocation');
            }
        }
        $allocation->ismanual = !empty($allocation->ismanual) ? 1 : 0;
        if (empty($allocation->allocatabletype)) {
            $allocation->allocatabletype = 'user';
        }
        $allocation = allocation::build((array)$allocation);
        $allocation->save();
        return $allocation;
    }

    /**
     * Makes a feedback for testing.
     *
     * @param stdClass $feedback
     * @return feedback
     * @throws coding_exception
     */
    public function create_feedback($feedback) {
        global $USER;

        $feedback = (object)$feedback;

        if (!is_numeric($feedback->assessorid ?? null)) {
            if (!empty($USER->id)) {
                $feedback->assessorid = $USER->id;
            } else {
                throw new coding_exception('Coursework generator needs a assessorid for a new feedback');
            }
        }

        if (empty($feedback->lasteditedbyuser)) {
            $feedback->lasteditedbyuser = $feedback->assessorid;
        }

        if (!is_numeric($feedback->submissionid ?? null) && !empty($feedback->allocatableid)) {
            $submission = submission::find([
                'courseworkid' => $feedback->courseworkid,
                'allocatableid' => $feedback->allocatableid,
            ]);

            if (!empty($submission->id())) {
                $feedback->submissionid = $submission->id();
                unset($feedback->allocatableid);
            } else {
                throw new coding_exception('Cannot resolve submission from allocatable');
            }
        } else if (!is_numeric($feedback->submissionid ?? null)) {
            throw new coding_exception('Coursework generator needs a submissionid for a new feedback');
        }

        $feedback = \mod_coursework\models\feedback::create($feedback);
        if (!isset($feedback->timecreated)) {
            $feedback->timecreated = time();
        }
        if (!isset($feedback->timemodified)) {
            $feedback->timemodified = time();
        }
        if (!isset($feedback->grade)) {
            $feedback->grade = null;
        }
        if (!isset($feedback->isfinalgrade)) {
            $feedback->isfinalgrade = 0;
        }
        if (!isset($feedback->ismoderation)) {
            $feedback->ismoderation = 0;
        }
        if (!isset($feedback->finalised)) {
            $feedback->finalised = 1;
        }

        $feedback->save();

        return $feedback;
    }

    /**
     * Makes a submission for testing.
     *
     * @param stdClass|array $submission
     * @param ?coursework $coursework
     * @return stdClass
     * @throws coding_exception
     */
    public function create_submission($submission, $coursework = null) {

        global $USER;

        $submission = submission::build($submission);

        if (!isset($submission->courseworkid) && isset($coursework)) {
            $submission->courseworkid = $coursework->id;
        } else if ($submission->courseworkid && !isset($coursework)) {
            $coursework = coursework::find($submission->courseworkid);
        } else if (!isset($submission->courseworkid) && !isset($coursework)) {
            throw new \core\exception\coding_exception('Coursework generator needs a courseworkid');
        }

        if (!isset($submission->allocatableid) || !is_numeric($submission->allocatableid) || empty($submission->allocatableid)) {
            if (!empty($USER->id)) {
                $submission->allocatableid = $USER->id;
            } else {
                throw new coding_exception('Coursework generator needs an allocatableid for a new submission');
            }
        }
        if (!isset($submission->timecreated)) {
            $submission->timecreated = time();
        }
        if (!isset($submission->timesubmitted)) {
            $submission->timesubmitted = time();
        }

        if (!isset($submission->timemodified)) {
            $submission->timemodified = time();
        }
        $submission->finalisedstatus = $submission->finalisedstatus ?: submission::FINALISED_STATUS_NOT_FINALISED;

        $submission->save();

        $fs = get_file_storage();
        // Prepare file record object
        $fileinfo = [
            'contextid' => $coursework->get_context_id(),
            // ID of context
            'component' => 'mod_coursework',
            // usually = table name
            'filearea' => 'submission',
            // usually = table name
            'itemid' => $submission->id,
            // usually = ID of row in table
            'filepath' => '/',
            // any path beginning and ending in /
            'filename' => 'myfile.txt']; // any filename
        // Create file containing text 'hello world'
        $fs->create_file_from_string($fileinfo, 'hello world');

        return $submission;
    }
}
