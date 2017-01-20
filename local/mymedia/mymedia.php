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
 * My Media main page
 *
 * @package    local
 * @subpackage mymedia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/locallib.php');
require_once('lib.php');

require_login();

global $SESSION, $USER;

$page          = optional_param('page', 0, PARAM_INT);
$sort          = optional_param('sort', 'recent', PARAM_TEXT);
$simple_search = '';
$videos        = 0;

$enabled = local_kaltura_kaltura_repository_enabled();

if ($enabled) {
    require_once(dirname(dirname(dirname(__FILE__))) . '/repository/kaltura/locallib.php');
}

$mymedia = get_string('heading_mymedia', 'local_mymedia');
$PAGE->set_context(get_system_context());
$header  = format_string($SITE->shortname).": $mymedia";

$PAGE->set_url('/local/mymedia/mymedia.php');
$PAGE->set_course($SITE);

$PAGE->set_pagetype('mymedia-index');
$PAGE->set_pagelayout('frontpage');
$PAGE->set_title($header);
$PAGE->set_heading($header);
$PAGE->add_body_class('mymedia-index');

$PAGE->requires->js('/local/kaltura/js/jquery.js', true);
$PAGE->requires->js('/local/kaltura/js/swfobject.js', true);
$PAGE->requires->js('/local/kaltura/js/kcwcallback.js', true);
$PAGE->requires->css('/local/mymedia/css/mymedia.css');

// Connect to Kaltura
$kaltura = new kaltura_connection();
$connection = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);

if (!$connection) {
    $url = new moodle_url('/admin/settings.php', array('section' => 'local_kaltura'));
    print_error('conn_failed', 'local_kaltura', $url);
}

$partner_id    = local_kaltura_get_partner_id();
$login_session = '';

// Include javascript for screen recording widget
$uiconf_id  = local_kaltura_get_player_uiconf('mymedia_screen_recorder');
$host = local_kaltura_get_host();
$url = new moodle_url("{$host}/p/{$partner_id}/sp/{$partner_id}/ksr/uiconfId/{$uiconf_id}");
$PAGE->requires->js($url, true);
$PAGE->requires->js('/local/kaltura/js/screenrecorder.js', true);

$courseid = get_courseid_from_context($PAGE->context);

if (local_kaltura_has_mobile_flavor_enabled() && local_kaltura_get_enable_html5()) {
    $uiconf_id = local_kaltura_get_player_uiconf('player_resource');
    $url = new moodle_url(local_kaltura_htm5_javascript_url($uiconf_id));
    $PAGE->requires->js($url, true);
    $url = new moodle_url('/local/kaltura/js/frameapi.js');
    $PAGE->requires->js($url, true);
}

echo $OUTPUT->header();


if ($data = data_submitted() and confirm_sesskey()) {

    // Make sure the user has the capability to search, and if the required parameter is set
    if (has_capability('local/mymedia:search', $PAGE->context, $USER) && isset($data->simple_search_name)) {

        $data->simple_search_name = clean_param($data->simple_search_name, PARAM_NOTAGS);

        if (isset($data->simple_search_btn_name)) {
            $SESSION->mymedia = $data->simple_search_name;
        } else if (isset($data->clear_simple_search_btn_name)) {
            $SESSION->mymedia = '';
        }
    } else {
        // Clear the session variable in case the user's permissions were revoked during a search
        $SESSION->mymedia = '';
    }
}

$context = get_context_instance(CONTEXT_USER, $USER->id);

require_capability('local/mymedia:view', $context, $USER);


$renderer = $PAGE->get_renderer('local_mymedia');

if ($enabled) {
    try {

        if (!$connection) {
            throw new Exception("Unable to connect");
        }
 
        // Required by screen recorder
        $login_session = $connection->getKs();

        $per_page = get_config(KALTURA_PLUGIN_NAME, 'mymedia_items_per_page');

        if (empty($per_page)) {
            $per_page = MYMEDIA_ITEMS_PER_PAGE;
        }

        $SESSION->mymediasort = $sort;

        // Check if the sesison data is set
        if (isset($SESSION->mymedia) && !empty($SESSION->mymedia)) {
            $videos = repository_kaltura_search_mymedia_videos($connection, $SESSION->mymedia, $page + 1, $per_page, $sort);
        } else {
            $videos = repository_kaltura_search_mymedia_videos($connection, '', $page + 1, $per_page, $sort);
        }

        $total = $videos->totalCount;

        if ($videos instanceof KalturaMediaListResponse &&  0 < $videos->totalCount ) {
            $videos = $videos->objects;

            // totalcount, current page number, number of items per page
            // Remember to check the session if a search has been performed
            $page = $OUTPUT->paging_bar($total,
                                        $page,
                                        $per_page,
                                        new moodle_url('/local/mymedia/mymedia.php', array('sort' => $sort)));


            echo $renderer->create_options_table_upper($page, $partner_id, $login_session);

            echo $renderer->create_vidoes_table($videos);

            echo $renderer->create_options_table_lower($page);

        } else {
            add_to_log(SITEID, 'local_mymedia', 'View - no videos', '', 'no videos');

            echo $renderer->create_options_table_upper($page, $partner_id, $login_session);

            echo '<center>'. get_string('no_videos', 'local_mymedia') . '</center>';

            echo $renderer->create_vidoes_table(array());
        }

        // Get Video detail panel markup
        $courses = enrol_get_my_courses(array('id','fullname'), 'visible DESC,sortorder ASC');

        $video_details = $renderer->video_details_markup($courses);
        $dialog        = $renderer->create_simple_dialog_markup();

        // Load YUI modules
        $jsmodule = array(
            'name'     => 'local_mymedia',
            'fullpath' => '/local/mymedia/js/mymedia.js',
            'requires' => array('base', 'dom', 'node',
                                'event-delegate', 'yui2-container', 'yui2-animation',
                                'yui2-dragdrop', 'tabview',
                                'collection', 'io-base', 'json-parse',

                                ),
            'strings' => array(array('video_converting',   'local_mymedia'),
                               array('loading',            'local_mymedia'),
                               array('error_saving',       'local_mymedia'),
                               array('missing_required',   'local_mymedia'),
                               array('error_not_owner',    'local_mymedia'),
                               array('failure_saved_hdr',  'local_mymedia'),
                               array('success_saving',     'local_mymedia'),
                               array('success_saving_hdr', 'local_mymedia'),
                               array('upload_success_hdr', 'local_mymedia'),
                               array('upload_success',     'local_mymedia'),
                               array('continue',           'local_mymedia')
                               )

            );

        $edit_meta = has_capability('local/mymedia:editmetadata', $context, $USER) ? 1 : 0;
        $edit_course = local_mymedia_check_capability('local/mymedia:sharecourse');
        $edit_site = local_mymedia_check_capability('local/mymedia:sharesite');

        $save_video_script = "../../local/mymedia/save_video_details.php?entry_id=";
        $conversion_script = "../../local/mymedia/check_conversion.php?courseid={$courseid}&entry_id=";
        $kcw_markup        = local_kaltura_get_kcw('mymedia_uploader', false);
        $kcw_panel_markup  = $renderer->create_kcw_panel_markup();
        $loading_markup    = $renderer->create_loading_screen_markup();
        $uiconf_id         = local_kaltura_get_player_uiconf('player_filter');



        $PAGE->requires->js_init_call('M.local_mymedia.init_config', array($video_details, $dialog, $conversion_script,
                                                                           $save_video_script, $uiconf_id,
                                                                           $kcw_panel_markup, $kcw_markup,
                                                                           $loading_markup, $edit_meta, $edit_course,
                                                                           $edit_site
                                                                          ), true, $jsmodule);

    } catch (Exception $exp) {
        add_to_log(SITEID, 'local_mymedia', 'View - error main page', '', $exp->getMessage());
        echo get_string('problem_viewing', 'local_mymedia');
    }

} else {
    echo get_string('repository_enable', 'local_mymedia');
}


echo $OUTPUT->footer();
