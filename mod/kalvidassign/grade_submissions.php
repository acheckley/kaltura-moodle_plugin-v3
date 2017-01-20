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
 * Kaltura video assignment grade submission page
 *
 * @package    mod_kalvidassign
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(__FILE__) . '/renderer.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once(dirname(__FILE__) . '/grade_preferences_form.php');

$id      = required_param('cmid', PARAM_INT);           // Course Module ID
$mode    = optional_param('mode', 0, PARAM_TEXT);
$tifirst = optional_param('tifirst', '', PARAM_TEXT);
$tilast  = optional_param('tilast', '', PARAM_TEXT);
$page    = optional_param('page', 0, PARAM_INT);

$url = new moodle_url('/mod/kalvidassign/grade_submissions.php');
$url->param('cmid', $id);

if (!empty($mode)) {
    if (!confirm_sesskey()) {
        print_error('confirmsesskeybad', 'error');
    }
}

if (! $cm = get_coursemodule_from_id('kalvidassign', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
    print_error('coursemisconf');
}

if (! $kalvidassignobj = $DB->get_record('kalvidassign', array('id' => $cm->instance))) {
    print_error('invalidid', 'kalvidassign');
}

require_login($course->id, false, $cm);

global $PAGE, $OUTPUT, $USER;

$currentcrumb = get_string('singlesubmissionheader', 'kalvidassign');
$PAGE->set_url($url);
$PAGE->set_title(format_string($kalvidassignobj->name));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($currentcrumb);

$renderer = $PAGE->get_renderer('mod_kalvidassign');

if (local_kaltura_has_mobile_flavor_enabled() && local_kaltura_get_enable_html5()) {
    $uiconf_id = local_kaltura_get_player_uiconf('player');
    $url = new moodle_url(local_kaltura_htm5_javascript_url($uiconf_id));
    $PAGE->requires->js($url, true);
    $url = new moodle_url('/local/kaltura/js/frameapi.js');
    $PAGE->requires->js($url, true);
}

/*  js_init_call must be executed before the header is output to this page otherwise
 *  the extra YUI libraries required to display Panel will not work
 */
$jsmodule = array(
    'name'     => 'local_kaltura',
    'fullpath' => '/local/kaltura/js/kaltura.js',
    'requires' => array('yui2-yahoo-dom-event',
                        'yui2-container',
                        'yui2-dragdrop',
                        'yui2-animation',
                        'base',
                        'dom',
                        'node',
                        'io-base',
                        'json-parse',
                        ),
    'strings' => array(
            array('video_converting', 'kalvidassign'),
            array('previewvideo', 'kalvidassign'),
            )
);


$courseid               = get_courseid_from_context($PAGE->context);
$conversion_script      = "../../local/kaltura/check_conversion.php?courseid={$courseid}&entry_id=";
$markup                 = $renderer->display_video_preview_markup();
$markup                 .= $renderer->display_loading_markup();
$uiconf_id              = local_kaltura_get_player_uiconf('player');
$modalwidth             = 0;
$modalheight            = 0;
$videowidth             = 0;
$videoheight            = 0;

list($modalwidth, $modalheight) = kalvidassign_get_player_dimensions();
$videowidth = $modalwidth - KALTURA_POPUP_WIDTH_ADJUSTMENT;
$videoheight = $modalheight - KALTURA_POPUP_HEIGHT_ADJUSTMENT;

$PAGE->requires->js_init_call('M.local_kaltura.video_asignment_submission_view', array($conversion_script, $markup, $uiconf_id, $modalwidth, $modalheight,
        $videowidth, $videoheight), true, $jsmodule);

echo $OUTPUT->header();

require_capability('mod/kalvidassign:gradesubmission', get_context_instance(CONTEXT_MODULE, $cm->id));

add_to_log($course->id, 'kalvidassign', 'view submissions page', 'grade_submissions.php?cmid='.$cm->id, $kalvidassignobj->id, $cm->id);

$pref_form =  new kalvidassign_gradepreferences_form(null, array('cmid' => $cm->id, 'groupmode' => $cm->groupmode));
$data = null;

if ($data = $pref_form->get_data()) {
    set_user_preference('kalvidassign_group_filter', $data->group_filter);

    set_user_preference('kalvidassign_filter', $data->filter);

    if ($data->perpage > 0) {
        set_user_preference('kalvidassign_perpage', $data->perpage);
    }

    if (isset($data->quickgrade)) {
        set_user_preference('kalvidassign_quickgrade', $data->quickgrade);
    } else {
        set_user_preference('kalvidassign_quickgrade', '0');
    }

}

if (empty($data)) {
    $data = new stdClass();
}

$data->filter       = get_user_preferences('kalvidassign_filter', 0);
$data->perpage      = get_user_preferences('kalvidassign_perpage', 10);
$data->quickgrade   = get_user_preferences('kalvidassign_quickgrade', 0);
$data->group_filter = get_user_preferences('kalvidassign_group_filter', 0);

$grade_data = data_submitted();

// Check if fast grading was passed to the form and process the data
if (!empty($grade_data->mode)) {

    $usersubmission = array();
    $time = time();
    $updated = false;

    foreach ($grade_data->users as $userid => $val) {

        $param = array('vidassignid' => $kalvidassignobj->id,
                       'userid' => $userid);

        $usersubmissions = $DB->get_record('kalvidassign_submission', $param);

        if ($usersubmissions) {

            if (array_key_exists($userid, $grade_data->menu)) {

                // Update grade
                if (($grade_data->menu[$userid] != $usersubmissions->grade)) {

                    $usersubmissions->grade = $grade_data->menu[$userid];
                    $usersubmissions->timemarked = $time;
                    $usersubmissions->teacher = $USER->id;

                    $updated = true;
                }
            }

            if (array_key_exists($userid, $grade_data->submissioncomment)) {

                if (0 != strcmp($usersubmissions->submissioncomment, $grade_data->submissioncomment[$userid])) {
                    $usersubmissions->submissioncomment = $grade_data->submissioncomment[$userid];

                    $updated = true;

                }
            }


            // trigger grade event
            if ($DB->update_record('kalvidassign_submission', $usersubmissions)) {

                $grade = new stdClass();
                $grade->userid = $userid;
                $grade = kalvidassign_get_submission_grade_object($kalvidassignobj->id, $userid);

                $kalvidassignobj->cmidnumber = $cm->idnumber;

                kalvidassign_grade_item_update($kalvidassignobj, $grade);

                //add to log only if updating
                add_to_log($kalvidassignobj->course, 'kalvidassign', 'update grades',
                           'grade_submissions.php?cmid='.$cm->id, $cm->id);

            }

        } else {
            // No user submission however the instructor has submitted grade data
            $usersubmissions                = new stdClass();
            $usersubmissions->vidassignid   = $cm->instance;
            $usersubmissions->userid        = $userid;
            $usersubmissions->entry_id      = '';
            $usersubmissions->teacher       = $USER->id;
            $usersubmissions->timemarked    = $time;
            //$usersubmissions->timecreated   = $time;
            //$usersubmissions->timemodified  = $time;

            // Need to prevent completely empty submissions from getting entered
            // into the video submissions' table
            // Check for unchanged grade value and an empty feedback value
            $empty_grade = array_key_exists($userid, $grade_data->menu) &&
                           '-1' == $grade_data->menu[$userid];

            $empty_comment = array_key_exists($userid, $grade_data->submissioncomment) &&
                             empty($grade_data->submissioncomment[$userid]);

            if ( $empty_grade && $empty_comment ) {
                continue;
            }

            if (array_key_exists($userid, $grade_data->menu)) {
                $usersubmissions->grade = $grade_data->menu[$userid];
            }

            if (array_key_exists($userid, $grade_data->submissioncomment)) {
                $usersubmissions->submissioncomment = $grade_data->submissioncomment[$userid];
            }


            // trigger grade event
            if ($DB->insert_record('kalvidassign_submission', $usersubmissions)) {

                $grade = new stdClass();
                $grade->userid = $userid;
                $grade = kalvidassign_get_submission_grade_object($kalvidassignobj->id, $userid);

                $kalvidassignobj->cmidnumber = $cm->idnumber;

                kalvidassign_grade_item_update($kalvidassignobj, $grade);

                //add to log only if updating
                add_to_log($kalvidassignobj->course, 'kalvidassign', 'update grades',
                           'grade_submissions.php?cmid='.$cm->id, $cm->id);

            }

        }

        $updated = false;
    }
}

$renderer->display_submissions_table($cm, $data->group_filter, $data->filter, $data->perpage,
                                     $data->quickgrade, $tifirst, $tilast, $page);

$pref_form->set_data($data);
$pref_form->display();

echo $OUTPUT->footer();