<?php
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
 * Kaltura repository upgrade.php script
 *
 * @package    repository
 * @subpackage kaltura
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_repository_kaltura_upgrade($oldversion = 0) {

    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2012061302) {

        // Define table repo_kaltura_videos to be created
        $table = new xmldb_table('repo_kaltura_videos');

        // Adding fields to table repo_kaltura_videos
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_field('entryid', XMLDB_TYPE_CHAR, '15', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table repo_kaltura_videos
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table repo_kaltura_videos
        $table->add_index('course_entry_idx', XMLDB_INDEX_UNIQUE, array('courseid', 'entryid'));

        // Conditionally launch create table for repo_kaltura_videos
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // kaltura savepoint reached
        upgrade_plugin_savepoint(true, 2012061302, 'repository', 'kaltura');
    }

    if ($oldversion < 2012061303) {

        // Define field timecreated to be added to repo_kaltura_videos
        $table = new xmldb_table('repo_kaltura_videos');
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '20', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'entryid');

        // Conditionally launch add field timecreated
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // kaltura savepoint reached
        upgrade_plugin_savepoint(true, 2012061303, 'repository', 'kaltura');
    }

    if ($oldversion < 2012112701) {

        global $DB;

        $type_id = $DB->get_field('repository', 'id', array('type' => 'kaltura'));


        $role_id = $DB->get_field('role', 'id', array('shortname' => 'user'));

        if (!empty($role_id)) {

            $context = get_system_context();

            assign_capability('repository/kaltura:view', CAP_ALLOW, $role_id, $context);
            assign_capability('repository/kaltura:sharedvideovisibility', CAP_ALLOW, $role_id, $context);
            assign_capability('repository/kaltura:systemvisibility', CAP_PREVENT, $role_id, $context);

            $context->mark_dirty();
        }

        // kaltura savepoint reached
        upgrade_plugin_savepoint(true, 2012112701, 'repository', 'kaltura');

    }

    return true;

}