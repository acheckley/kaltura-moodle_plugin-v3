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
 * Kaltura video assignment grade preferences form
 *
 * @package    local_kaltura
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once(dirname(__FILE__) . '/locallib.php');

global $PAGE;

$param = optional_param('section', '', PARAM_TEXT);

/**
 * $enable_api_calls is a flag to enable the settings page to make API calls to
 * Kaltura.  This is done to reduce API calls when they are not needed/used.
 *
 * The API has to be called under the following criteria:
 * - Displaying the Kaltura settings page
 * - Upgrade settings page is displayed
 * (when a new plug-in is detected and is to be installed) -
 * - A global search is performed (searching from the administration block)
 */

// Check for specific reference to display the Kaltura settings page
$settings_page = !strcmp(KALTURA_PLUGIN_NAME, $param);

// Check if the upgrade page is being displayed
$upgrade_page = isset($_SERVER['REQUEST_URI']) ? strpos($_SERVER['REQUEST_URI'], "/admin/upgradesettings.php") : false;

// Check if a global search was performed
$global_search_page = isset($_SERVER['REQUEST_URI']) ? strpos($_SERVER['REQUEST_URI'], "/admin/search.php") : false;

$enable_api_calls = $settings_page || $upgrade_page || $global_search_page;

if ($hassiteconfig) {

    global $SESSION;

    // Add local plug-in configuration settings link to the navigation block
    $settings = new admin_settingpage('local_kaltura', get_string('pluginname', 'local_kaltura'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading('kaltura_conn_heading', get_string('conn_heading_title', 'local_kaltura'),
                       get_string('conn_heading_desc', 'local_kaltura')));

    // Connection status headers
    $initialized = false;

    // Check to see if the username, password or uri has changed
    $login              = get_config(KALTURA_PLUGIN_NAME, 'login');
    $login_previous     = get_config(KALTURA_PLUGIN_NAME, 'login_previous');
    $password           = get_config(KALTURA_PLUGIN_NAME, 'password');
    $password_previous  = get_config(KALTURA_PLUGIN_NAME, 'password_previous');
    $uri                = get_config(KALTURA_PLUGIN_NAME, 'uri');
    $uri_previous       = get_config(KALTURA_PLUGIN_NAME, 'uri_previous');

    // Check if a new URI has been entered
    $new_uri = ( ($uri && !$uri_previous) || (0 != strcmp($uri, $uri_previous)) ) ? true : false;

    // Must be the first time they saved data.  Retrieve Kaltura account and initiate becon to kaltura
    $new_login = ( ($login && !$login_previous) || (0 != strcmp($login, $login_previous)) ) ?
                    true : false;

    // If the login is the same check if the user updated the password
    $new_passwd = ( ($password && !$password_previous) || (0 != strcmp($password, $password_previous)) ) ?
                        true : false;

    if ($new_uri || $new_login || $new_passwd) {

        $uri = get_config(KALTURA_PLUGIN_NAME, 'uri');

        $initialized = local_kaltura_initialize_account($login, $password, $uri);

        if (empty($initialized)) {
            local_kaltura_uninitialize_account();
        }

        set_config('uri_previous', $uri, KALTURA_PLUGIN_NAME);
        set_config('login_previous', $login, KALTURA_PLUGIN_NAME);
        set_config('password_previous', $password, KALTURA_PLUGIN_NAME);

        unset($SESSION->kaltura_con);
        unset($SESSION->kaltura_con_timeout);
        unset($SESSION->kaltura_con_timestarted);

    } else {

        // May need to set the URI setting beause if was originally
        // disabled then the form will not submit the default URI
        $connection_type = get_config(KALTURA_PLUGIN_NAME, 'conn_server');

        if (0 == strcmp('hosted', $connection_type)) {

            set_config('uri', KALTURA_DEFAULT_URI, KALTURA_PLUGIN_NAME);
        }

    }

    if ($enable_api_calls) {

        $session = local_kaltura_login(true, '', KALTURA_SESSION_LENGTH, true);

        if (!empty($session)) {
            $settings->add(new admin_setting_heading('conn_status', get_string('conn_status_title', 'local_kaltura'),
                                                     get_string('conn_success', 'local_kaltura')));
        } else {
            $settings->add(new admin_setting_heading('conn_status', get_string('conn_status_title', 'local_kaltura'),
                                                     get_string('conn_failed', 'local_kaltura')));
        }
    }

    // Server Connection
    $choices = array('hosted' => get_string('hostedconn', 'local_kaltura'),
                     'ce' => get_string('ceconn', 'local_kaltura')
                     );

    $adminsetting = new admin_setting_configselect('conn_server', get_string('conn_server', 'local_kaltura'),
                                get_string('conn_server_desc', 'local_kaltura'), 'hosted', $choices);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    // Connection URI
    $adminsetting = new admin_setting_configtext('uri', get_string('server_uri', 'local_kaltura'),
                       get_string('server_uri_desc', 'local_kaltura'), KALTURA_DEFAULT_URI, PARAM_URL);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    // Kaltura login
    $adminsetting = new admin_setting_configtext('login', get_string('hosted_login', 'local_kaltura'),
                       get_string('hosted_login_desc', 'local_kaltura'), '', PARAM_TEXT);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    // Kaltura password
    $adminsetting = new admin_setting_configpasswordunmask('password', get_string('hosted_password', 'local_kaltura'),
                       get_string('hosted_password_desc', 'local_kaltura'), '');
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);


    // Kaltura reports section
    $settings->add(new admin_setting_heading('kaltura_kalreports_heading',
                   get_string('kaltura_kalreports_heading', 'local_kaltura'), ''));


    $adminsetting = new admin_setting_configtext('report_uri', get_string('report_server_uri', 'local_kaltura'),
                       get_string('report_server_uri_desc', 'local_kaltura'), KALTURA_REPORT_DEFAULT_URI, PARAM_URL);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configcheckbox('enable_reports', get_string('enable_reports', 'local_kaltura'),
                       get_string('enable_reports_desc', 'local_kaltura'), '0');
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configtext('recent_courses_display_limit', get_string('recent_courses_display_limit', 'local_kaltura'), get_string('recent_courses_display_limit_desc', 'local_kaltura'), 30, PARAM_INT);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configtext('search_courses_display_limit', get_string('search_courses_display_limit', 'local_kaltura'), get_string('search_courses_display_limit_desc', 'local_kaltura'), 100, PARAM_INT);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    // Kaltura regular player selection
    if ($enable_api_calls) {
        $players = local_kaltura_get_custom_players();
    }

    // Initialize KCW options
    $kcw_choices = array(KALTURA_PLAYER_UPLOADERREGULAR => get_string('player_uploader', 'local_kaltura'),
                 0 => get_string('custom_player_upload', 'local_kaltura'));

    // Kaltura regular player selection
    $settings->add(new admin_setting_heading('kaltura_kalvidassign_heading',
                   get_string('kaltura_kalvidassign_title', 'local_kaltura'), ''));


    $choices = array(KALTURA_PLAYER_PLAYERREGULARDARK  => get_string('player_regular_dark', 'local_kaltura'),
                     KALTURA_PLAYER_PLAYERREGULARLIGHT => get_string('player_regular_light', 'local_kaltura'),
                     );

    if (!empty($players)) {
        $choices = $choices + $players;
    }

    $choices[0] = get_string('custom_player', 'local_kaltura');

    $adminsetting = new admin_setting_configselect('player', get_string('kaltura_player', 'local_kaltura'),
                       get_string('kaltura_player_desc', 'local_kaltura'), KALTURA_PLAYER_PLAYERREGULARDARK, $choices);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configtext('player_custom', get_string('kaltura_player_custom', 'local_kaltura'),
                       get_string('kaltura_player_custom_desc', 'local_kaltura'), '', PARAM_INT);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configselect('assign_uploader', get_string('assign_uploader', 'local_kaltura'),
                       get_string('assign_uploader_desc', 'local_kaltura'), KALTURA_PLAYER_UPLOADERREGULAR, $kcw_choices);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configtext('assign_uploader_custom', get_string('kaltura_uploader_custom', 'local_kaltura'),
                       get_string('kaltura_uploader_custom_desc', 'local_kaltura'), '', PARAM_INT);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configtext('kalvidassign_player_width', get_string('kalvidassign_player_width', 'local_kaltura'),
            get_string('kalvidassign_player_width_desc', 'local_kaltura'), '400', PARAM_INT);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configtext('kalvidassign_player_height', get_string('kalvidassign_player_height', 'local_kaltura'),
            get_string('kalvidassign_player_height_desc', 'local_kaltura'), '365', PARAM_INT);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    // Kaltura resource regular player
    $settings->add(new admin_setting_heading('kaltura_kalvidres_heading',
                   get_string('kaltura_kalvidres_title', 'local_kaltura'), ''));

    $adminsetting = new admin_setting_configselect('player_resource', get_string('kaltura_player_resource', 'local_kaltura'),
                       get_string('kaltura_player_resource_desc', 'local_kaltura'), KALTURA_PLAYER_PLAYERREGULARDARK, $choices);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configtext('player_resource_custom', get_string('kaltura_player_resource_custom', 'local_kaltura'),
                       get_string('kaltura_player_resource_custom_desc', 'local_kaltura'), '', PARAM_INT);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    //// override checkbox
    $adminsetting = new admin_setting_configcheckbox('player_resource_override', get_string('player_resource_override', 'local_kaltura'),
                       get_string('player_resource_override_desc', 'local_kaltura'), '0');
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configselect('res_uploader', get_string('res_uploader', 'local_kaltura'),
                       get_string('res_uploader_desc', 'local_kaltura'), KALTURA_PLAYER_UPLOADERREGULAR, $kcw_choices);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configtext('res_uploader_custom', get_string('kaltura_uploader_custom', 'local_kaltura'),
                       get_string('kaltura_uploader_custom_desc', 'local_kaltura'), '', PARAM_INT);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    // Kaltura presentation player selection
    $settings->add(new admin_setting_heading('kaltura_kalvidpres_heading',
                   get_string('kaltura_kalvidpres_title', 'local_kaltura'), ''));

    $pres_choices = array(KALTURA_PLAYER_PLAYERVIDEOPRESENTATION => get_string('player_presentation', 'local_kaltura'),
                     0 => get_string('custom_player', 'local_kaltura'));

    $adminsetting = new admin_setting_configselect('presentation', get_string('kaltura_presentation', 'local_kaltura'),
                       get_string('kaltura_presentation_desc', 'local_kaltura'), KALTURA_PLAYER_PLAYERVIDEOPRESENTATION, $pres_choices);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configtext('presentation_custom', get_string('kaltura_presentation_custom', 'local_kaltura'),
                       get_string('kaltura_presentation_custom_desc', 'local_kaltura'), '', PARAM_INT);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configselect('pres_uploader', get_string('pres_uploader', 'local_kaltura'),
                       get_string('pres_uploader_desc', 'local_kaltura'), KALTURA_PLAYER_UPLOADERREGULAR, $kcw_choices);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configtext('pres_uploader_custom', get_string('kaltura_uploader_custom', 'local_kaltura'),
                       get_string('kaltura_uploader_custom_desc', 'local_kaltura'), '', PARAM_INT);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    // Kaltura simple uploader player selection
    $ksu_choices = array(KALTURA_PLAYER_KSU => get_string('simple_uploader', 'local_kaltura'),
                        0 => get_string('custom_player', 'local_kaltura'));

    $adminsetting = new admin_setting_configselect('simple_uploader', get_string('kaltura_simple_uploader', 'local_kaltura'),
                       get_string('kaltura_simple_uploader_desc', 'local_kaltura'), KALTURA_PLAYER_KSU, $ksu_choices);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configtext('simple_uploader_custom', get_string('kaltura_simple_uploader_cust', 'local_kaltura'),
                       get_string('kaltura_simple_uploader_cust_desc', 'local_kaltura'), '', PARAM_INT);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);


    // Kaltura My Media settings
    $settings->add(new admin_setting_heading('kaltura_mymedia_heading',
                   get_string('kaltura_mymedia_title', 'local_kaltura'), ''));

    $per_page  = array(9 => get_string('nine', 'local_kaltura'),
                       18 => get_string('eighteen', 'local_kaltura'),
                       21 => get_string('twentyone', 'local_kaltura'),
                       24 => get_string('twentyfour', 'local_kaltura'),
                       27 => get_string('twentyseven', 'local_kaltura'),
                       30 => get_string('thirty', 'local_kaltura'));

    $adminsetting = new admin_setting_configselect('mymedia_items_per_page', get_string('mymedia_items_per_page', 'local_kaltura'),
                       get_string('mymedia_items_per_page_desc', 'local_kaltura'), 9, $per_page);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $mymedia_choices = array(KALTURA_PLAYER_MYMEDIA_UPLOADER => get_string('player_mymedia_uploader', 'local_kaltura'),
                             0 => get_string('custom_player_upload', 'local_kaltura'));

    $adminsetting = new admin_setting_configselect('mymedia_uploader', get_string('mymedia_uploader', 'local_kaltura'),
                       get_string('mymedia_uploader_desc', 'local_kaltura'), KALTURA_PLAYER_MYMEDIA_UPLOADER, $mymedia_choices);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configtext('mymedia_uploader_custom', get_string('kaltura_uploader_custom', 'local_kaltura'),
                       get_string('kaltura_uploader_custom_desc', 'local_kaltura'), '', PARAM_INT);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $mymedia_scr_choices = array(KALTURA_PLAYER_MYMEDIA_SCREEN_RECORDER => get_string('player_mymedia_screen_recorder', 'local_kaltura'),
                             0 => get_string('custom_screen_recorder', 'local_kaltura'));

    $adminsetting = new admin_setting_configselect('mymedia_screen_recorder', get_string('mymedia_screen_recorder', 'local_kaltura'),
                       get_string('mymedia_screen_recorder_desc', 'local_kaltura'), KALTURA_PLAYER_MYMEDIA_SCREEN_RECORDER, $mymedia_scr_choices);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configtext('mymedia_screen_recorder_custom', get_string('kaltura_screen_recorder_custom', 'local_kaltura'),
                       get_string('kaltura_screen_recorder_custom_desc', 'local_kaltura'), '', PARAM_INT);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);


    // Kaltura Filter Plug-in settings
    $settings->add(new admin_setting_heading('kaltura_filter_heading',
                   get_string('kaltura_filter_title', 'local_kaltura'), ''));

    $adminsetting = new admin_setting_configtext('filter_player_width', get_string('filter_player_width', 'local_kaltura'),
                       get_string('filter_player_width_desc', 'local_kaltura'), KALTURA_FILTER_VIDEO_WIDTH, PARAM_INT);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configtext('filter_player_height', get_string('filter_player_height', 'local_kaltura'),
                       get_string('filter_player_height_desc', 'local_kaltura'), KALTURA_FILTER_VIDEO_HEIGHT, PARAM_INT);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configselect('player_filter', get_string('player_filter', 'local_kaltura'),
                       get_string('player_filter_desc', 'local_kaltura'), KALTURA_PLAYER_PLAYERREGULARDARK, $choices);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configtext('player_filter_custom', get_string('filter_custom', 'local_kaltura'),
                       get_string('filter_custom_desc', 'local_kaltura'), '', PARAM_INT);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $settings->add(new admin_setting_heading('kaltura_general_heading',
                   get_string('kaltura_general', 'local_kaltura'), ''));

    $adminsetting = new admin_setting_configcheckbox('enable_html5', get_string('enable_html5', 'local_kaltura'),
                       get_string('enable_html5_desc', 'local_kaltura'), '0');
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configcheckbox('enable_screen_recorder', get_string('enable_screen_recorder', 'local_kaltura'),
                       get_string('enable_screen_recorder_desc', 'local_kaltura'), '1');
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $adminsetting = new admin_setting_configtext('mymedia_application_name', get_string('application_name', 'local_kaltura'),
                       get_string('application_name_desc', 'local_kaltura'), 'Moodle', PARAM_NOTAGS);
    $adminsetting->plugin = KALTURA_PLUGIN_NAME;
    $settings->add($adminsetting);

    $jsmodule = array(
        'name'     => 'local_kaltura',
        'fullpath' => '/local/kaltura/js/kaltura.js',
        'requires' => array('base', 'dom', 'node'),
        );

    $test_script = $CFG->wwwroot . '/local/kaltura/test.php';
    $PAGE->requires->js_init_call('M.local_kaltura.init_config', array($test_script), true, $jsmodule);

}
