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
 * Kaltura video assignment form
 *
 * @package    mod
 * @subpackage kalvidassign
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/locallib.php');

if (!confirm_sesskey()) {
    print_error('confirmsesskeybad', 'error');
}

$entry_id       = required_param('entry_id', PARAM_TEXT);
$cmid           = required_param('cmid', PARAM_INT);

global $USER, $OUTPUT, $DB, $PAGE;

if (! $cm = get_coursemodule_from_id('kalvidassign', $cmid)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
    print_error('coursemisconf');
}

if (! $kalvidassignobj = $DB->get_record('kalvidassign', array('id' => $cm->instance))) {
    print_error('invalidid', 'kalvidassign');
}

require_course_login($course->id, true, $cm);

$PAGE->set_url('/mod/kalvidassign/view.php', array('id' => $course->id));
$PAGE->set_title(format_string($kalvidassignobj->name));
$PAGE->set_heading($course->fullname);


if (kalvidassign_assignemnt_submission_expired($kalvidassignobj)) {
    print_error('assignmentexpired', 'kalvidassign', 'course/view.php?id='. $course->id);
}

echo $OUTPUT->header();

if (empty($entry_id)) {
    print_error('emptyentryid', 'kalvidassign', $CFG->wwwroot . '/mod/kalvidassign/view.php?id='.$cm->id);
}

$param = array('vidassignid' => $kalvidassignobj->id, 'userid' => $USER->id);
$submission = $DB->get_record('kalvidassign_submission', $param);

$time = time();

if ($submission) {

    $submission->entry_id = $entry_id;
    $submission->timemodified = $time;

    if (0 == $submission->timecreated) {
        $submission->timecreated = $time;
    }

    if ($DB->update_record('kalvidassign_submission', $submission)) {

        $message = get_string('assignmentsubmitted', 'kalvidassign');
        $continue = get_string('continue');

        echo $OUTPUT->notification($message, 'notifysuccess');

        echo html_writer::start_tag('center');

        $url = new moodle_url($CFG->wwwroot . '/mod/kalvidassign/view.php', array('id' => $cm->id));

        echo $OUTPUT->single_button($url, $continue, 'post');
        echo html_writer::end_tag('center');

        add_to_log($course->id, 'kalvidassign', 'submit', 'view.php?id='.$cm->id, $kalvidassignobj->id, $cm->id);
    } else {
        // TODO: print error message of failure to insert a new submission
    }

} else {
    $submission = new stdClass();
    $submission->entry_id = $entry_id;
    $submission->userid = $USER->id;
    $submission->vidassignid = $kalvidassignobj->id;
    $submission->grade = -1;
    $submission->timecreated = $time;
    $submission->timemodified = $time;

    if ($DB->insert_record('kalvidassign_submission', $submission)) {

        $message = get_string('assignmentsubmitted', 'kalvidassign');
        $continue = get_string('continue');

        echo $OUTPUT->notification($message, 'notifysuccess');

        //$param = array('id' => $cm->id);
        echo html_writer::start_tag('center');

        $url = new moodle_url($CFG->wwwroot . '/mod/kalvidassign/view.php', array('id' => $cm->id));

        echo $OUTPUT->single_button($url, $continue, 'post');
        echo html_writer::end_tag('center');

    } else {
        //TODO: print error message of failure to insert a new submission
    }

}

$context = $PAGE->context;

// Email an alert to the teacher
if ($kalvidassignobj->emailteachers) {
    kalvidassign_email_teachers($cm, $kalvidassignobj->name, $submission, $context);
}

echo $OUTPUT->footer();