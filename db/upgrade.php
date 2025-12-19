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
 * This file keeps track of upgrades to the eassessment module
 *
 * Sometimes, changes between versions involve alterations to database
 * structures and other major things that may break installations. The upgrade
 * function in this file will attempt to perform all the necessary actions to
 * upgrade your older installation to the current version. If there's something
 * it cannot do itself, it will tell you what you need to do.  The commands in
 * here will all be database-neutral, using the functions defined in DLL libraries.
 *
 * @package    mod_coursework
 * @copyright  2011 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/coursework/lib.php');

/**
 * xmldb_eassessment_upgrade
 *
 * @param int $oldversion
 * @return bool
 * @throws ddl_change_structure_exception
 * @throws ddl_exception
 * @throws ddl_field_missing_exception
 * @throws ddl_table_missing_exception
 */
function xmldb_coursework_upgrade($oldversion) {

    global $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2020111602) {
        throw new Exception("Unable to upgrade from versions 2020111602 and earlier");
        // Note this savepoint is 100% unreachable, but needed to pass the upgrade checks.
        upgrade_mod_savepoint(true, 2020111602, 'coursework');
    }

    if ($oldversion < 2024100700) {
        // CTP-3869 rename field manual on table coursework_allocation_pairs to ismanual.
        $table = new xmldb_table('coursework_allocation_pairs');
        $field = new xmldb_field(
            'manual',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            0,
            'assessorid'
        );

        // Launch rename field sourceid.
        $dbman->rename_field($table, $field, 'ismanual');

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2024100700, 'coursework');
    }

    if ($oldversion < 2025082800) {
        // Add usecandidate field to coursework table.
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('usecandidate', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0, 'renamefiles');

        // Conditionally launch add field usecandidate.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2025082800, 'coursework');
    }

    if ($oldversion < 2025100300) {
        $tablestoaddindex = ['coursework_submissions', 'coursework_extensions', 'coursework_person_deadlines'];
        foreach ($tablestoaddindex as $tablename) {
            // Define index courseworkid-allocatableid-allocatabletype (not unique) to be added to table.
            $table = new xmldb_table($tablename);
            $index = new xmldb_index(
                'courseworkid-allocatableid-allocatabletype',
                XMLDB_INDEX_NOTUNIQUE,
                ['courseworkid', 'allocatableid', 'allocatabletype']
            );

            // Conditionally launch add index courseworkid-allocatableid-allocatabletype.
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }
        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2025100300, 'coursework');
    }

    if ($oldversion < 2025100302) {
        $table = new xmldb_table('coursework');
        $upgradefield = new xmldb_field('draftfeedbackenabled');

        if (!$dbman->field_exists($table, $upgradefield)) {
            $dbman->drop_field($table, $upgradefield);
        }

        $upgradefield = new xmldb_field('gradeeditingtime');

        if (!$dbman->field_exists($table, $upgradefield)) {
            $dbman->drop_field($table, $upgradefield);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2025100302, 'coursework');
    }

    if ($oldversion < 2025110300) {
        // Rename field finalised on table coursework_submissions to finalisedstatus.
        $table = new xmldb_table('coursework_submissions');
        $field = new xmldb_field('finalised', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timemodified');

        if ($dbman->field_exists($table, $field)) {
            // Launch rename field finalisedstatus.
            $dbman->rename_field($table, $field, 'finalisedstatus');
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2025110300, 'coursework');
    }

    if ($oldversion < 2025110600) {
        // Align column names with coding standards.

        $table = new xmldb_table('coursework_feedbacks');
        $field = new xmldb_field('entry_id');

        // Conditionally launch drop field entry_id.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Conditionally rename stage_identifier to stageidentifier for various tables.
        $tables = [
            'coursework_feedbacks',
            'coursework_allocation_pairs',
            'coursework_mod_set_members',
            'coursework_sample_set_rules',
            'coursework_sample_set_mbrs',
        ];
        $field = new xmldb_field('stage_identifier', XMLDB_TYPE_CHAR, 20);
        $newname = 'stageidentifier';
        foreach ($tables as $table) {
            $table = new xmldb_table($table);

            if ($dbman->field_exists($table, $field) && !$dbman->field_exists($table, $newname)) {
                $dbman->rename_field($table, $field, $newname);
            }
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2025110600, 'coursework');
    }

    if ($oldversion < 2025110601) {
        $table = new xmldb_table('coursework');
        $field = new xmldb_field('use_groups', XMLDB_TYPE_INTEGER, 1);
        $newname = 'usegroups';

        if ($dbman->field_exists($table, $field) && !$dbman->field_exists($table, $newname)) {
            $dbman->rename_field($table, $field, $newname);
        }

        $table = new xmldb_table('coursework_reminder');
        $field = new xmldb_field('coursework_id', XMLDB_TYPE_INTEGER, 1);
        $newname = 'courseworkid';

        if ($dbman->field_exists($table, $field) && !$dbman->field_exists($table, $newname)) {
            $dbman->rename_field($table, $field, $newname);
        }

        $table = new xmldb_table('coursework_extensions');
        $field = new xmldb_field('extra_information_text', XMLDB_TYPE_TEXT);
        $newname = 'extrainformationtext';

        if ($dbman->field_exists($table, $field) && !$dbman->field_exists($table, $newname)) {
            $dbman->rename_field($table, $field, $newname);
        }

        $field = new xmldb_field('extra_information_format', XMLDB_TYPE_INTEGER, 2);
        $newname = 'extrainformationformat';

        if ($dbman->field_exists($table, $field) && !$dbman->field_exists($table, $newname)) {
            $dbman->rename_field($table, $field, $newname);
        }

        $table = new xmldb_table('coursework_sample_set_rules');
        $field = new xmldb_field('sample_set_plugin_id', XMLDB_TYPE_INTEGER, 10);
        $newname = 'samplesetpluginid';

        if ($dbman->field_exists($table, $field) && !$dbman->field_exists($table, $newname)) {
            $dbman->rename_field($table, $field, $newname);
        }

        $table = new xmldb_table('coursework_person_deadlines');
        $field = new xmldb_field('personal_deadline', XMLDB_TYPE_INTEGER, 10);
        $newname = 'personaldeadline';

        if ($dbman->field_exists($table, $field) && !$dbman->field_exists($table, $newname)) {
            $dbman->rename_field($table, $field, $newname);
        }

        $table = new xmldb_table('coursework_plagiarism_flags');
        $field = new xmldb_field('comment_format', XMLDB_TYPE_INTEGER, 2);
        $newname = 'commentformat';

        if ($dbman->field_exists($table, $field) && !$dbman->field_exists($table, $newname)) {
            $dbman->rename_field($table, $field, $newname);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2025110601, 'coursework');
    }

    if ($oldversion < 2025120401) {
        // Define key coursework_fk (foreign) to be added to coursework_allocation_pairs.
        $table = new xmldb_table('coursework_allocation_pairs');
        $key = new xmldb_key('coursework_fk', XMLDB_KEY_FOREIGN, ['courseworkid'], 'coursework', ['id']);

        // Launch add key coursework_fk.
        $dbman->add_key($table, $key);

        // Define key assessor_fk (foreign) to be added to coursework_allocation_pairs.
        $table = new xmldb_table('coursework_allocation_pairs');
        $key = new xmldb_key('assessor_fk', XMLDB_KEY_FOREIGN, ['assessorid'], 'user', ['id']);

        // Launch add key assessor_fk.
        $dbman->add_key($table, $key);

        // Define index courseworkid-ix (not unique) to be added to coursework_allocation_pairs.
        $table = new xmldb_table('coursework_allocation_pairs');
        $index = new xmldb_index('courseworkid-ix', XMLDB_INDEX_NOTUNIQUE, ['courseworkid']);

        // Conditionally launch add index courseworkid-ix.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index courseworkid-allocatableid-allocatabletype-ix (not unique) to be added to coursework_allocation_pairs.
        $table = new xmldb_table('coursework_allocation_pairs');
        $index = new xmldb_index('courseworkid-allocatableid-allocatabletype-ix', XMLDB_INDEX_NOTUNIQUE, ['courseworkid', 'allocatableid', 'allocatabletype']);

        // Conditionally launch add index courseworkid-allocatableid-allocatabletype-ix.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Coursework savepoint reached.
        upgrade_mod_savepoint(true, 2025120401, 'coursework');
    }

    // Always needs to return true.
    return true;
}
