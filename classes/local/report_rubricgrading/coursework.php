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
 * Implementation of coursework support for rubricgrading report.
 *
 * This assumes the rubricgrading plugin is installed, but this class won't ever be loaded if that plugin is not
 * installed, so we don't need a hard dependency on it.
 *
 * @package    report_rubricgrading
 * @copyright  2025 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\local\report_rubricgrading;

use core\lang_string;
use core_reportbuilder\local\report\column;
use stdClass;
use xmldb_table;

// Not included when exporting a reportbuilder report.
require_once $CFG->dirroot . '/grade/grading/lib.php';

class coursework extends \report_rubricgrading\local\plugin_base {
    #[\Override]
    protected function load_grading_manager(): void {
        $this->gradingmanager = get_grading_manager($this->cm->context, 'mod_coursework', 'submissions');
    }

    #[\Override]
    protected function get_sql_rubric(): string {
        return "SELECT grf.id,
                      cws.authorid as userid,
                      cwf.grade,
                      cw.grade               AS gradeoutof,
                      cwf.feedbackcomment    AS overallfeedback,
                      cwf.markernumber,
                      cwf.stageidentifier,
                      cws.timemodified       AS timegraded,
                      stu.firstname,
                      stu.lastname,
                      stu.email,
                      stu.username,
                      stu.idnumber,
                      stu.firstnamephonetic,
                      stu.lastnamephonetic,
                      stu.middlename,
                      stu.alternatename,
                      grdr.firstname         AS grader_firstname,
                      grdr.lastname          AS grader_lastname,
                      grdr.firstnamephonetic AS grader_firstnamephonetic,
                      grdr.lastnamephonetic  AS grader_lastnamephonetic,
                      grdr.middlename        AS grader_middlename,
                      grdr.alternatename     AS grader_alternatename,
                      grc.id                 AS criterionid,
                      gl.score,
                      gl.definition          AS leveldef,
                      grf.remark
                 FROM {gradingform_rubric_fillings}  grf
                 JOIN {gradingform_rubric_criteria}  grc  ON grc.id  = grf.criterionid
                 JOIN {gradingform_rubric_levels}    gl   ON gl.id   = grf.levelid
                 JOIN {grading_instances}            gin  ON gin.id  = grf.instanceid AND gin.status = 1
                 JOIN {coursework_feedbacks}         cwf  ON cwf.id   = gin.itemid
                 JOIN {coursework_submissions}       cws  ON cws.id = cwf.submissionid
                 JOIN {grading_definitions}          gd   ON gd.id   = gin.definitionid
                 JOIN {grading_areas}                ga   ON ga.id   = gd.areaid
                 JOIN {context}                      ctx  ON ctx.id  = ga.contextid
                 JOIN {course_modules}               cm   ON cm.id   = ctx.instanceid AND cm.id  = :cmid
                 JOIN {coursework}                   cw   ON cw.id  = cm.instance
                 JOIN {user}                         stu  ON stu.id  = cws.authorid AND stu.deleted = 0
                 JOIN {user}                         grdr ON grdr.id = cwf.assessorid
             ORDER BY stu.lastname, stu.firstname, cwf.stageidentifier, grc.sortorder";
    }

    #[\Override]
    protected function get_sql_rubric_ranges(): string {
        return "SELECT grf.id,
                      cws.authorid as userid,
                      cwf.grade,
                      cw.grade               AS gradeoutof,
                      cwf.feedbackcomment    AS overallfeedback,
                      cwf.markernumber,
                      cwf.stageidentifier,
                      cws.timemodified       AS timegraded,
                      stu.firstname,
                      stu.lastname,
                      stu.email,
                      stu.username,
                      stu.idnumber,
                      stu.firstnamephonetic,
                      stu.lastnamephonetic,
                      stu.middlename,
                      stu.alternatename,
                      grdr.firstname         AS grader_firstname,
                      grdr.lastname          AS grader_lastname,
                      grdr.firstnamephonetic AS grader_firstnamephonetic,
                      grdr.lastnamephonetic  AS grader_lastnamephonetic,
                      grdr.middlename        AS grader_middlename,
                      grdr.alternatename     AS grader_alternatename,
                      grc.id                 AS criterionid,
                      gl.score,
                      gl.definition          AS leveldef,
                      grf.remark
                 FROM {gradingform_rubric_ranges_f}  grf
                 JOIN {gradingform_rubric_ranges_c}  grc  ON grc.id  = grf.criterionid
                 JOIN {gradingform_rubric_ranges_l}  gl   ON gl.id   = grf.levelid
                 JOIN {grading_instances}            gin  ON gin.id  = grf.instanceid AND gin.status = 1
                 JOIN {coursework_feedbacks}         cwf  ON cwf.id   = gin.itemid
                 JOIN {coursework_submissions}       cws  ON cws.id = cwf.submissionid
                 JOIN {grading_definitions}          gd   ON gd.id   = gin.definitionid
                 JOIN {grading_areas}                ga   ON ga.id   = gd.areaid
                 JOIN {context}                      ctx  ON ctx.id  = ga.contextid
                 JOIN {course_modules}               cm   ON cm.id   = ctx.instanceid AND cm.id  = :cmid
                 JOIN {coursework}                   cw   ON cw.id  = cm.instance
                 JOIN {user}                         stu  ON stu.id  = cws.authorid AND stu.deleted = 0
                 JOIN {user}                         grdr ON grdr.id = cwf.assessorid
             ORDER BY stu.lastname, stu.firstname, cwf.stageidentifier, grc.sortorder";
    }

    protected function get_sql_guide(): string {
        return "SELECT grf.id,
                      cws.authorid as userid,
                      cwf.grade,
                      cw.grade               AS gradeoutof,
                      cwf.feedbackcomment    AS overallfeedback,
                      cwf.markernumber,
                      cwf.stageidentifier,
                      cws.timemodified       AS timegraded,
                      stu.firstname,
                      stu.lastname,
                      stu.email,
                      stu.username,
                      stu.idnumber,
                      stu.firstnamephonetic,
                      stu.lastnamephonetic,
                      stu.middlename,
                      stu.alternatename,
                      grdr.firstname         AS grader_firstname,
                      grdr.lastname          AS grader_lastname,
                      grdr.firstnamephonetic AS grader_firstnamephonetic,
                      grdr.lastnamephonetic  AS grader_lastnamephonetic,
                      grdr.middlename        AS grader_middlename,
                      grdr.alternatename     AS grader_alternatename,
                      grc.id                 AS criterionid,
                      grf.score,
                      grf.remark
                 FROM {gradingform_guide_fillings}   grf
                 JOIN {gradingform_guide_criteria}   grc  ON grc.id  = grf.criterionid 
                 JOIN {grading_instances}            gin  ON gin.id  = grf.instanceid AND gin.status = 1
                 JOIN {coursework_feedbacks}         cwf  ON cwf.id   = gin.itemid
                 JOIN {coursework_submissions}       cws  ON cws.id = cwf.submissionid
                 JOIN {grading_definitions}          gd   ON gd.id   = gin.definitionid
                 JOIN {grading_areas}                ga   ON ga.id   = gd.areaid
                 JOIN {context}                      ctx  ON ctx.id  = ga.contextid
                 JOIN {course_modules}               cm   ON cm.id   = ctx.instanceid AND cm.id  = :cmid
                 JOIN {coursework}                   cw   ON cw.id  = cm.instance
                 JOIN {user}                         stu  ON stu.id  = cws.authorid AND stu.deleted = 0
                 JOIN {user}                         grdr ON grdr.id = cwf.assessorid
             ORDER BY stu.lastname, stu.firstname, cwf.stageidentifier, grc.sortorder";
    }

    #[\Override]
    public function get_row_key(stdClass $row): mixed {
        return $row->userid . '_' . $row->markernumber;
    }

    #[\Override]
    public function add_report_fields(xmldb_table $xmldbtable): void {
        $xmldbtable->add_field('stageidentifier', XMLDB_TYPE_CHAR, '100');
    }

    #[\Override]
    public function add_row_data(stdClass $row, stdClass &$pivotrow): void {
        if (preg_match('/^assessor_(\d+)$/', $row->stageidentifier, $matches)) {
            $pivotrow->stageidentifier = get_string('markernumber', 'mod_coursework', $matches[1]);
        } else if (preg_match('/^final_agreed_(\d+)$/', $row->stageidentifier)) {
            $pivotrow->stageidentifier = get_string('finalagreed', 'mod_coursework');
        } else {
            $pivotrow->stageidentifier = $row->stageidentifier;
        }
    }

    #[\Override]
    public function add_report_columns(): array {
        return [
            [
                'stageidentifier',
                new lang_string('type', 'mod_coursework'),
                column::TYPE_TEXT,
            ]
        ];
    }

}
