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
 * This file keeps track of upgrades to the ipal module
 *
 * Sometimes, changes between versions involve alterations to database
 * structures and other major things that may break installations. The upgrade
 * function in this file will attempt to perform all the necessary actions to
 * upgrade your older installation to the current version. If there's something
 * it cannot do itself, it will tell you what you need to do.  The commands in
 * here will all be database-neutral, using the functions defined in DLL libraries.
 *
 * @package   mod_ipal
 * @copyright 2012 W. F. Junkin Eckerd College (http://www.eckerd.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * xmldb_ipal_upgrade
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_ipal_upgrade($oldversion) {

    global $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2012052900) {

        // Define field shortname to be added to ipal_answered_archive.
        $table = new xmldb_table('ipal_answered_archive');
        $field = new xmldb_field('shortname', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'a_text');

        // Conditionally launch add field shortname.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('sent', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'time_created');

        // Conditionally launch add field sent.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('instructor', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'shortname');

        // Conditionally launch add field instructor.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

    }

    if ($oldversion < 2012072300) {
        $table = new xmldb_table('ipal');
        $field = new xmldb_field('anonymous', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'name');
        // Conditionally launch add field anonymous.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }

    if ($oldversion < 2012081611) {
        $table = new xmldb_table('ipal');
        $field = new xmldb_field('mobile', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'name');
        // Conditionally launch add field mobile.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('ipal_mobile');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);

            $table->add_field('user_id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');

            $table->add_field('reg_id', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, ' ');
            $table->add_field('device_code', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, ' ');
            $table->add_field('clicker_type', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, ' ');
            $table->add_field('course_id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
            $table->add_field('time_created', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

            // Conditionally launch add table ipal_mobile.
            $dbman->create_table($table);
        }

        // Ipal savepoint reached.
        upgrade_mod_savepoint(true, 2012081611, 'ipal');
    }

    // Final return of upgrade result (true, all went good) to Moodle.
    return true;
}