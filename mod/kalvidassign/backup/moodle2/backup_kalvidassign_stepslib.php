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
 * @package moodlecore
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the backup steps that will be used by the backup_kalvidassign_activity_task
 */

/**
 * Define the complete kalvidassign structure for backup, with file and id annotations
 */
class backup_kalvidassign_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $kalvidassign = new backup_nested_element('kalvidassign', array('id'), array(
            'course','name', 'intro', 'introformat', 'timeavailable', 'timedue',
            'preventlate', 'resubmit', 'emailteachers', 'grade', 'timecreated', 'timemodified'));

        $issues = new backup_nested_element('submissions');

        $issue = new backup_nested_element('submission', array('id'), array(
            'userid', 'entry_id', 'grade', 'submissioncomment', 'format',
            'teacher', 'mailed', 'timemarked', 'timecreated', 'timemodified'));


        // Build the tree
        $kalvidassign->add_child($issues);
        $issues->add_child($issue);

        // Define sources
        $kalvidassign->set_source_table('kalvidassign', array('id' => backup::VAR_ACTIVITYID));

        // All the rest of elements only happen if we are including user info
        if ($userinfo) {
           $issue->set_source_table('kalvidassign_submission', array('vidassignid' => backup::VAR_PARENTID));
        }

        // Annotate the user id's where required.
        $issue->annotate_ids('user', 'userid');

        // Annotate the file areas in use
        $issue->annotate_files('mod_kalvidassign', 'submission', 'id'); // By issue->id

        // Return the root element, wrapped into standard activity structure
        return $this->prepare_activity_structure($kalvidassign);
    }
}
