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
 * File for a sampling rule that will include X students from between an upper and lower limit.
 *
 * @package    mod_coursework
 * @copyright  2015 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\sample_set_rule;

use mod_coursework\sample_set_rule;
use html_writer;
use mod_coursework\allocation\allocatable;
use mod_coursework\models\coursework;

defined('MOODLE_INTERNAL') || die();

/**
 * Defines a rule that will include all students above or below a particular percentage of
 * the total grade.
 */
class range_sample_type extends \mod_coursework\sample_set_rule\sample_base {

    public function adjust_set(array &$moderationset, array &$potentialallocatables, $stage) {

    }

    public function get_numeric_boundaries() {

    }

    public function get_default_rule_order() {

    }

    public function add_form_elements($assessornumber=0) {

        global $DB;

        $html = '';

        $sql = "SELECT     sr.*
                     FROM       {coursework_sample_set_rules}   sr,
                                {coursework_sample_set_plugin}  sp
                     WHERE      sr.sample_set_plugin_id = sp.id
                     AND        sr.courseworkid = {$this->coursework->id}
                     AND        sr.stage_identifier = 'assessor_{$assessornumber}'
                     AND        sp.rulename = 'range_sample_type'";

        $rulesfound = false;

        $samplerecords = $DB->get_records_sql($sql);

        if (!empty($samplerecords)) {
            $seq = 0;
            foreach ($samplerecords as $record) {
                $html .= $this->range_elements($assessornumber, $seq, $record);
                $seq++;
            }
        } else {
            $html .= $this->range_elements($assessornumber, 0, false);
        }

        $html  .= html_writer::link('#', get_string('addgraderule', 'mod_coursework'), ['id' => "assessor_{$assessornumber}_addgradderule", 'class' => 'addgradderule sample_set_rule']);
        $html  .= "  ";
        $html  .= html_writer::link('#', get_string('removegraderule', 'mod_coursework'), ['id' => "assessor_{$assessornumber}_removegradderule", 'class' => 'removegradderule sample_set_rule']);

        return $html;
    }

    public function range_elements($assessornumber, $sequence, $dbrecord=false) {

        $percentageoptions = [];

        for ($i = 0; $i < 110; $i = $i + 10) {
            $percentageoptions[$i] = "{$i}";
        }

        $scale = [];

        if ($this->coursework->grade > 0) {
            for ($i = 0; $i <= $this->coursework->grade; $i++) {
                $scale[] = $i;
            }
        } else {
            $gradescale = \grade_scale::fetch(['id' => abs($this->coursework->grade)]);
            $scale = explode(",", $gradescale->scale);
        }

        if ($dbrecord) {
            $selectedtype = [$dbrecord->ruletype => get_string($dbrecord->ruletype, 'mod_coursework')];
            $selectedto = ($dbrecord->ruletype == 'scale') ? [$dbrecord->upperlimit => $scale[$dbrecord->upperlimit]] : [$dbrecord->upperlimit => $dbrecord->upperlimit];

            $selectedfrom = ($dbrecord->ruletype == 'scale') ? [$dbrecord->lowerlimit => $scale[$dbrecord->lowerlimit]] : [$dbrecord->lowerlimit => $dbrecord->lowerlimit];

            $ruleschecked = ($dbrecord) ? true : false;

        } else {
            $selectedtype = ['percentage' => get_string('percentage', 'mod_coursework')];
            $selectedto = ['100' => '100'];
            $selectedfrom = ['0' => '0'];;
            $ruleschecked = false;
        }

        $html = html_writer::start_tag(
            'div',
            ['class' => "assessor_{$assessornumber}_grade_rules", 'id' => "assessor_{$assessornumber}_grade_rules_{$sequence}"]
        );

        $html .= html_writer::checkbox(
            "assessor_{$assessornumber}_samplerules[]", 1, $ruleschecked, '',
            ['id' => "assessor_{$assessornumber}_samplerules_{$sequence}", 'class' => "assessor_{$assessornumber} range_grade_checkbox sample_set_rule"]);

        $gradescaletext = ($this->coursework->grade < 0) ? get_string('scale', 'mod_coursework') : get_string('grade', 'mod_coursework');
        $gradescaleval = ($this->coursework->grade < 0) ? 'scale' : 'grade';

        $options = ['percentage' => get_string('percentage', 'mod_coursework'),
            $gradescaleval => $gradescaletext];

        $html .= html_writer::select($options,
            "assessor_{$assessornumber}_sampletype[]",
            "",
            $selectedtype,
            ['id' => "assessor_{$assessornumber}_sampletype_{$sequence}", 'class' => "grade_type  sample_set_rule"]);

        $html .= html_writer::label(get_string('from', 'mod_coursework'), 'assessortwo_samplefrom[0]');

        $ruleoptions = (!empty($selectedtype) && array_key_exists('percentage', $selectedtype)) ? $percentageoptions : $scale; //change this into a ternary statement that

        $html .= html_writer::select($ruleoptions,
            "assessor_{$assessornumber}_samplefrom[]",
            "",
            $selectedfrom,
            ['id' => "assessor_{$assessornumber}_samplefrom_{$sequence}", 'class' => " sample_set_rule range_drop_down range_samp_from"]);

        $html .= html_writer::label(get_string('to', 'mod_coursework'), "assessor_{$assessornumber}_sampleto[0]");

        $html .= html_writer::select(array_reverse($ruleoptions, true),
            "assessor_{$assessornumber}_sampleto[]",
            "",
            $selectedto,
            ['id' => "assessor_{$assessornumber}_sampleto_{$sequence}", 'class' => " sample_set_rule range_drop_down"]);

        $html .= html_writer::end_tag('div', '');

        return $html;

    }

    public function add_form_elements_js($assessornumber=0) {

        $jsscript = "

            var AUTOMATIC_SAMPLING = 1;

            //add grade rule buttons
            $('.addgradderule').each(function(e,element) {

                $(element).on('click',function (e) {
                    e.preventDefault();

                    var linkid = $(this).attr('id').split('_');

                    if ($('#'+linkid[0] + '_' + linkid[1] + '_samplingstrategy').val() == AUTOMATIC_SAMPLING) {

                        var spanClone = $('div.'+linkid[0] + '_' + linkid[1] +'_grade_rules').first().clone(true);

                        // Find out how many rule spans exist
                        var gradeSpans = $('div.'+linkid[0] + '_' + linkid[1] +'_grade_rules');

                        if (gradeSpans.length < 5) {
                            // Put a new line in

                            // Rename the select box ids
                            spanClone.find('select').each(function (n, ele) {
                                var elename = $(ele).attr('id').split('_');
                                $(ele).attr('id', elename[0] + '_' + elename[1] + '_' + elename[2] + '_' + gradeSpans.length);
                            });

                            // Rename the checkbox
                            spanClone.find('input').each(function (n, ele) {
                                var elename = $(ele).attr('id').split('_');
                                $(ele).attr('id', elename[0] + '_' + elename[1] + '_' + elename[2] + '_' + gradeSpans.length);
                            });

                            spanClone.attr('id', linkid[0] + '_' + linkid[1] + '_grade_rules_' + gradeSpans.length);

                            //add the cloned span
                            //spanClone.appendTo($('#'+linkid[0] + '_' + linkid[1]+'_grade_rules_0').parent());
                             spanClone.insertAfter($('div.'+linkid[0] + '_' + linkid[1] +'_grade_rules').last());

                            // Make sure the from and to selects are set to the correct type

                            change_options($('#'+linkid[0] + '_' + linkid[1] + '_sampletype_' + gradeSpans.length));

                        }
                    }
                });
            })

            // Remove grade rule buttons
            $('.removegradderule').each(function(e,element) {

                $(element).on('click',function (e) {
                    e.preventDefault();

                    var linkid = $(this).attr('id').split('_');

                    var spanclass = 'div.'+linkid[0] + '_' + linkid[1] +'_grade_rules';

                    if ($('#'+linkid[0] + '_' + linkid[1] + '_samplingstrategy').val() == AUTOMATIC_SAMPLING) {

                        // Find out how many rule spans exist
                        var gradeSpans = $(spanclass);
                        if (gradeSpans.length > 1) {
                            $(spanclass).last().remove();
                        }
                    }

                });

            });

            $('.range_grade_checkbox').each(function(e,element) {

            var ele_id = $(this).attr('id').split('_');
                        var sampletypeid = '#'+ele_id[0]+'_'+ele_id[1]+'_sampletype_'+ele_id[3];
                        var samplefromid = '#'+ele_id[0]+'_'+ele_id[1]+'_samplefrom_'+ele_id[3];
                        var sampletoid = '#'+ele_id[0]+'_'+ele_id[1]+'_sampleto_'+ele_id[3];

                       var disabled = !$(this).prop('checked');

                       $(sampletypeid).attr('disabled',disabled);
                       $(samplefromid).attr('disabled',disabled);
                       $(sampletoid).attr('disabled',disabled);

                    $(element).on('change',function() {

                        var ele_id = $(this).attr('id').split('_');
                        var sampletypeid = '#'+ele_id[0]+'_'+ele_id[1]+'_sampletype_'+ele_id[3];
                        var samplefromid = '#'+ele_id[0]+'_'+ele_id[1]+'_samplefrom_'+ele_id[3];
                        var sampletoid = '#'+ele_id[0]+'_'+ele_id[1]+'_sampleto_'+ele_id[3];

                       var disabled = !$(this).prop('checked');

                       $(sampletypeid).attr('disabled',disabled);
                       $(samplefromid).attr('disabled',disabled);
                       $(sampletoid).attr('disabled',disabled);

                    })
            });

            // Grade rule drop downs
            $('.grade_type').each(function(e,element) {

                $(element).on('change',function() {
                    change_options(this);

                })

            });

            function change_options(element) {
                    var PERCENT = 'percentage';

                    var ele_id = $(element).attr('id').split('_');

                    var samplefromid = '#'+ele_id[0]+'_'+ele_id[1]+'_samplefrom_'+ele_id[3];
                    var sampletoid = '#'+ele_id[0]+'_'+ele_id[1]+'_sampleto_'+ele_id[3];

                    // Remove the contents from the grade rule from and to drop downs
                    $(samplefromid).find('option').remove();
                    $(sampletoid).find('option').remove();

                    var selectValues = [];

                    var type = PERCENT;

                    if ($(element).val() == PERCENT ) {
                        for (var i = 0;i < 11; i++) {
                            selectValues[i] = (i*10);
                        }
                    } else {
                        var selectValues = $('#scale_values').val().split(',');
                        type = 1;
                    }

                    //change the values within the grade rule from and to drop downs
                    $.each(selectValues, function(i, val) {

                            var text = val;
                            var value = (type == PERCENT) ? val: i;

                            $(samplefromid).append($('<option>',{
                                value: value,
                                text: text
                            }));

                            $(sampletoid).append($('<option>',{
                                value: value,
                                text: text
                            }));

                        });

                     $(sampletoid).append($(sampletoid).children().toArray().reverse());
                     $(sampletoid).children().first().prop('selected', true);
                     $(samplefromid).children().first().prop('selected', true);

            }

            ";

        return  html_writer::script($jsscript, null);
    }

    public function save_form_data($assessornumber=0, &$order=0) {

            global $DB;

            $samplerules = optional_param_array("assessor_{$assessornumber}_samplerules", false, PARAM_RAW);
            $sampletype = optional_param_array("assessor_{$assessornumber}_sampletype", false, PARAM_RAW);
            $samplefrom = optional_param_array("assessor_{$assessornumber}_samplefrom", false, PARAM_RAW);
            $sampleto = optional_param_array("assessor_{$assessornumber}_sampleto", false, PARAM_RAW);

            $sampleplugin = $DB->get_record('coursework_sample_set_plugin', ['rulename' => 'range_sample_type']);

        if ($samplerules) {
            foreach ($samplerules as $i => $val) {

                $dbrecord = new \stdClass();

                $dbrecord->ruletype = $sampletype[$i];
                $dbrecord->lowerlimit = $samplefrom[$i];
                $dbrecord->upperlimit = $sampleto[$i];
                $dbrecord->sample_set_plugin_id = $sampleplugin->id;
                $dbrecord->courseworkid = $this->coursework->id;
                $dbrecord->ruleorder = $order;
                $dbrecord->stage_identifier = "assessor_{$assessornumber}";

                $DB->insert_record("coursework_sample_set_rules", $dbrecord);
                $order++;
            }
        }

    }

    public function adjust_sample_set($stagenumber, &$allocatables, &$manualsampleset, &$autosampleset) {

        global  $DB;

        $stage = "assessor_" . $stagenumber;

        $sql = "SELECT         r.*,p.rulename
                         FROM           {coursework_sample_set_plugin} p,
                                        {coursework_sample_set_rules} r
                         WHERE          p.id = r.sample_set_plugin_id
                         AND            r.courseworkid = :courseworkid
                         AND            p.rulename = 'range_sample_type'
                         AND            stage_identifier = :stage
                         ORDER BY       ruleorder";

        $ruleinstance = $DB->get_records_sql($sql, ['courseworkid' => $this->coursework->id, 'stage' => $stage]);

        foreach ($ruleinstance as $ri) {

            $limit = $this->rationalise($ri->ruletype, $ri->lowerlimit, $ri->upperlimit);

            // all allocatables that are within specified range based on previous stage
            $previousstage = $stagenumber - 1;
            $allocatablesinrange = $this->get_allocatables_in_range("assessor_".$previousstage, $limit[0], $limit[1]);

            $finalised = $this->finalised_submissions();
            $published = $this->released_submissions();

            foreach ($allocatablesinrange as $awf) {
                if (!isset($published[$awf->allocatableid]) && !isset($finalised[$awf->allocatableid])
                    && !isset($autosampleset[$awf->allocatableid]) && !isset($manualsampleset[$awf->allocatableid])
                    && isset($allocatables[$awf->allocatableid])) {
                        $autosampleset[$awf->allocatableid] = $allocatables[$awf->allocatableid];
                }
            }
        }
    }

    private function rationalise($ruletype, $limit1, $limit2) {

        global  $DB;

        $limits = [];

            $limits[0] = ($limit1 > $limit2) ? $limit2 : $limit1;
            $limits[1] = ($limit1 > $limit2) ? $limit1 : $limit2;

        if ($ruletype == 'scale') {
            ++$limits[0];
            ++$limits[1];
        }

        if ($ruletype == 'percentage') {
            if ($this->coursework->grade > 0) {
                $limits[0] = $this->coursework->grade * $limits[0] / 100;
                $limits[1] = $this->coursework->grade * $limits[1] / 100;
            } else {
                $scale = $DB->get_record("scale", ['id' => abs($this->coursework->grade)]);

                if ($scale) {

                    $courseworkscale = explode(",", $scale->scale);

                    $numberofitems = count($courseworkscale);

                    $weighting = 100 / $numberofitems; // shall we round it????

                    $limits[0] = ceil($limits[0] / $weighting); // element of array
                    $limits[1] = ceil($limits[1] / $weighting); // element of array

                    // Note we have to add one as the values are not stored in there element positions

                }

            }
        }

        return $limits;
    }

    private function get_allocatables_in_range($stage, $limit1, $limit2) {
        global $CFG, $DB;

        $gradesql = ($CFG->dbtype == 'pgsql') ? " CAST(grade AS integer) " : " grade ";

            $sql = "SELECT *
                    FROM {coursework_submissions} cs,
                         {coursework_feedbacks} cf
                    WHERE cs.id = cf.submissionid
                    AND courseworkid = :courseworkid
                    AND stage_identifier = :stage
                    AND $gradesql BETWEEN {$limit1} AND {$limit2}";

        // Note as things stand limit1 and limit2 can not be params as the type of the grade field (varchar)
        //means the values are cast as strings

        return $DB->get_records_sql($sql,
            ['courseworkid' => $this->coursework->id, 'stage' => $stage]
        );
    }

}
