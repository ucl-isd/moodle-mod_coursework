<?php
/**
 * Created by PhpStorm.
 * User: Nigel.Daley
 * Date: 08/10/2015
 * Time: 17:19
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_coursework_install() {
    global $DB;


    //install the plugins used by sampling in the correct order
    $plugins =   array('range_sample_type','total_sample_type');

    $i  =   1;

    foreach($plugins as $p) {
        $dbrecord = new \stdClass();

        $dbrecord->rulename = $p;
        $dbrecord->pluginorder = $i;

        $DB->insert_record('coursework_sample_set_plugin',$dbrecord);
        $i++;
    }

}