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
 * Kaltura video assignment
 *
 * @package    mod_kalvidassign
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/locallib.php');
require_once(dirname(__FILE__) . '/locallib.php');

$id = optional_param('id', 0, PARAM_INT);           // Course Module ID

// Retrieve module instance
if (empty($id)) {
    print_error('invalidid', 'kalvidassign');
}

if (!empty($id)) {

    if (! $cm = get_coursemodule_from_id('kalvidassign', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('coursemisconf');
    }

    if (! $kalvidassign = $DB->get_record('kalvidassign', array("id"=>$cm->instance))) {
        print_error('invalidid', 'kalvidassign');
    }
}

require_course_login($course->id, true, $cm);

global $SESSION, $CFG;

// Connect to Kaltura
$kaltura        = new kaltura_connection();
$connection     = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);
$partner_id     = '';
$sr_unconf_id   = '';
$host           = '';

if ($connection) {

    // If a connection is made then include the JS libraries
    $partner_id    = local_kaltura_get_partner_id();
    $sr_unconf_id  = local_kaltura_get_player_uiconf('mymedia_screen_recorder');
    $host = local_kaltura_get_host();
    $url = new moodle_url("{$host}/p/{$partner_id}/sp/{$partner_id}/ksr/uiconfId/{$sr_unconf_id}");
    $PAGE->requires->js($url, true);
    $PAGE->requires->js('/local/kaltura/js/screenrecorder.js', true);
    
    $PAGE->requires->js('/local/kaltura/js/jquery.js', true);
    $PAGE->requires->js('/local/kaltura/js/swfobject.js', true);
    $PAGE->requires->js('/local/kaltura/js/kcwcallback.js', true);
}


$PAGE->set_url('/mod/kalvidassign/view.php', array('id'=>$id));
$PAGE->set_title(format_string($kalvidassign->name));
$PAGE->set_heading($course->fullname);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);

add_to_log($course->id, 'kalvidassign', 'view assignment details', 'view.php?id='.$cm->id, $kalvidassign->id, $cm->id);

// Update 'viewed' state if required by completion system
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

if (local_kaltura_has_mobile_flavor_enabled() && local_kaltura_get_enable_html5()) {
    $uiconf_id = local_kaltura_get_player_uiconf('player');
    $url = new moodle_url(local_kaltura_htm5_javascript_url($uiconf_id));
    $PAGE->requires->js($url, true);
    $url = new moodle_url('/local/kaltura/js/frameapi.js');
    $PAGE->requires->js($url, true);
}

echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('mod_kalvidassign');

echo $OUTPUT->box_start('generalbox');

echo $renderer->display_mod_info($kalvidassign, $context);

echo format_module_intro('kalvidassign', $kalvidassign, $cm->id);
echo $OUTPUT->box_end();

$entry_object   = null;
$disabled       = false;

if (empty($connection)) {

    echo $OUTPUT->notification(get_string('conn_failed_alt', 'local_kaltura'));
    $disabled = true;

}

if (!has_capability('mod/kalvidassign:gradesubmission', $context)) {

    $param = array('vidassignid' => $kalvidassign->id, 'userid' => $USER->id);
    $submission = $DB->get_record('kalvidassign_submission', $param);

    if (!empty($submission->entry_id)) {
        $entry_object = local_kaltura_get_ready_entry_object($submission->entry_id, false);
    }

    echo $renderer->display_submission($cm, $USER->id, $entry_object);


    if (kalvidassign_assignemnt_submission_expired($kalvidassign)) {
        $disabled = true;
    }

    if (empty($submission->entry_id) && empty($submission->timecreated)) {

        echo $renderer->display_student_submit_buttons($cm, $USER->id, $disabled);

        echo $renderer->render_progress_bar();

        echo $renderer->display_grade_feedback($kalvidassign, $context);
    } else {

        if ($disabled || !$kalvidassign->resubmit) {
            $disabled = true;
        }

        echo $renderer->display_student_resubmit_buttons($cm, $USER->id, $disabled);

        echo $renderer->render_progress_bar();

        echo $renderer->display_grade_feedback($kalvidassign, $context);

        // Check if the repository plug-in exists.  Add Kaltura video to
        // the Kaltura category
        if (!empty($submission->entry_id)) {

            $category = false;
            $enabled = local_kaltura_kaltura_repository_enabled();

            if ($enabled && $connection) {
                require_once($CFG->dirroot.'/repository/kaltura/locallib.php');

                // Create the course category
                $category = repository_kaltura_create_course_category($connection, $course->id);
            }

            if (!empty($category) && $enabled) {
                repository_kaltura_add_video_course_reference($connection, $course->id, array($submission->entry_id));
            }
        }

    }

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
                array('upload_successful', 'local_kaltura'),
                array('video_converting', 'kalvidassign'),
                array('previewvideo', 'kalvidassign'),
                array('javanotenabled', 'kalvidassign'),
                array('checkingforjava', 'kalvidassign')
        )
    );

    $courseid               = get_courseid_from_context($PAGE->context);
    $conversion_script      = '';
    $kcw                    = local_kaltura_get_kcw('assign_uploader', true);
    $markup                 = $renderer->display_all_panel_markup();
    $properties             = kalvidassign_get_video_properties();
    $conversion_script      = "../../local/kaltura/check_conversion.php?courseid={$courseid}&entry_id=";
    $login_session          = '';
    $modalwidth             = 0;
    $modalheight            = 0;
    
    if ($connection) {
        $login_session      = $connection->getKs();
    }

    list($modalwidth, $modalheight) = kalvidassign_get_player_dimensions();

    $properties['width'] = $modalwidth - KALTURA_POPUP_WIDTH_ADJUSTMENT;
    $properties['height'] = $modalheight - KALTURA_POPUP_HEIGHT_ADJUSTMENT;
    $PAGE->requires->js_init_call('M.local_kaltura.video_assignment', array($conversion_script, $markup, $properties, $kcw, $login_session, $partner_id,
            $conversion_script, $modalwidth, $modalheight), false, $jsmodule);

} else {
    echo $renderer->display_instructor_buttons($cm, $USER->id);
}


echo $OUTPUT->footer();
