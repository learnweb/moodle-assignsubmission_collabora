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
 * Upgrade code for install
 *
 * @package   assignsubmission_collabora
 * @copyright Andreas Grabs <moodle@grabs-edv.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Stub for upgrade code
 * @param int $oldversion
 * @return bool
 */
function xmldb_assignsubmission_collabora_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2022030604) {

        // Define table assignsubmission_collabora to be created.
        $table = new xmldb_table('assignsubmission_collabora');

        // Adding fields to table assignsubmission_collabora.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('assignment', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('submission', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('numfiles', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table assignsubmission_collabora.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('assignment', XMLDB_KEY_FOREIGN, ['assignment'], 'assign', ['id']);
        $table->add_key('submission', XMLDB_KEY_FOREIGN, ['submission'], 'assign_submission', ['id']);

        // Conditionally launch create table for assignsubmission_collabora.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Now upgrade the old submission files.
        \assignsubmission_collabora\convert::run();

        // Collabora savepoint reached.
        upgrade_plugin_savepoint(true, 2022030604, 'assignsubmission', 'collabora');
    }

    return true;
}
