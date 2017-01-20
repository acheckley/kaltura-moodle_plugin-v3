<?php
// This file keeps track of upgrades to
// the chat module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installation to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the methods of database_manager class
//
// Please do not forget to use upgrade_set_timeout()
// before any action that may take longer time to finish.

function xmldb_kalvidres_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2011110702) {

        // Changing type of field intro on table kalvidres to text
        $table = new xmldb_table('kalvidres');
        $field = new xmldb_field('intro', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'name');

        // Launch change of type for field intro
        $dbman->change_field_type($table, $field);
        
        // kalvidres savepoint reached
        upgrade_mod_savepoint(true, 2011110702, 'kalvidres');
    }

    
    return true;
}
    