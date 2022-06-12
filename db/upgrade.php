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
 * The upgrade file for Personalised Study Guide.
 *
 * @package format_psg
 * @author Ted Krahn
 * @copyright 2022 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The upgrade function.
 *
 * @param int $oldversion The current version in the database.
 */
function xmldb_format_psg_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2022010401) {

        // Define table format_psg_module_ls to be created.
        $table = new xmldb_table('format_psg_module_ls');

        // Adding fields to table format_psg_module_ls.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('moduleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('lsc', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table format_psg_module_ls.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for format_psg_module_ls.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Psg savepoint reached.
        upgrade_plugin_savepoint(true, 2022010401, 'format', 'psg');
    }

    if ($oldversion < 2022021100) {

        // Define table format_psg_student_module_ls to be created.
        $table = new xmldb_table('format_psg_student_module_ls');

        // Adding fields to table format_psg_student_module_ls.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('moduleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('score', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table format_psg_student_module_ls.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for format_psg_student_module_ls.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Psg savepoint reached.
        upgrade_plugin_savepoint(true, 2022021100, 'format', 'psg');
    }

    if ($oldversion < 2022021600) {

        $DB->delete_records_select('format_psg_student_module_ls', 'id > 0');

        // Define field scoretype to be added to format_psg_student_module_ls.
        $table = new xmldb_table('format_psg_student_module_ls');
        $field = new xmldb_field('scoretype', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null, 'score');

        // Conditionally launch add field scoretype.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Psg savepoint reached.
        upgrade_plugin_savepoint(true, 2022021600, 'format', 'psg');
    }

    return true;
}
