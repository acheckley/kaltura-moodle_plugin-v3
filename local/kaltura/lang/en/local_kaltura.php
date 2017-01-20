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
 * Kaltura video assignment locallib
 *
 * @package    local_kaltura
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


$string['pluginname'] = 'Kaltura package libraries';
$string['hostedconn'] = 'Kaltura Hosted Solution (SaaS)';
$string['ceconn'] = 'Kaltura CE';
$string['conn_heading_title'] = 'Kaltura hosted and CE settings';
$string['conn_heading_desc'] = 'Input the type of connection you would like to use (KalturaCE or the hosted Kaltura server) and enter in the'.
                               ' credentials related to the connection you are using.';
$string['conn_server'] = 'Connection Server';
$string['conn_server_desc'] = 'Select whether you are connecting to a hosted account on whether you are connect to your own Kaltura CE server';
$string['server_uri'] = 'Server URI';
$string['server_uri_desc'] = 'Type in the server URI you want to connect to.  Otherwise just type in the default settings';
$string['hosted_login'] = 'Hosted account login';
$string['hosted_login_desc'] = 'Login username that is used to log into the Kaltura site';
$string['hosted_password'] = 'Hosted account password';
$string['hosted_password_desc'] = 'Password that is used to log into the Kaltura site';
$string['player_regular_light'] = 'Player light (6709421)';
$string['player_regular_dark'] = 'Player dark (6709411)';
$string['custom_player'] = 'Custom player';
$string['kaltura_player_resource'] = 'Kaltura resource player';
$string['kaltura_player_resource_desc'] = 'Player used to play back video for Video Resources.  This player will appear as the default player for new Video Resources.'.
' However it may be overridden if the <b>Override video resource player setting</b> is unchecked';
$string['kaltura_player_resource_custom'] = 'Custom UIConf';
$string['kaltura_player_resource_custom_desc'] = 'Only used if you have a custom resource player UIConf ID';
$string['kaltura_player'] = 'Kaltura player';
$string['kaltura_player_desc'] = 'Player used to play back video for Video Assignments';
$string['kaltura_player_custom'] = 'Custom UIConf';
$string['kaltura_player_custom_desc'] = 'Only used if you have a custom assignment player UIConf ID';
$string['player_mymedia_uploader'] = 'Default upload widget (8464961)';
$string['player_uploader'] = 'Default upload widget (6709401)';
$string['kaltura_uploader_custom'] = 'Custom KCW UIConf ID';
$string['kaltura_uploader_custom_desc'] = 'Only used if you have a custom KCW UIConf ID';
$string['player_presentation'] = 'Video presentation (4860481)';
$string['kaltura_presentation'] = 'Presentation player';
$string['kaltura_presentation_desc'] = 'Player used to play back videos synchronized with documents';
$string['kaltura_presentation_custom'] = 'Custom video presentation UIConf ID';
$string['kaltura_presentation_custom_desc'] = 'Only used if you have a custom Presentation Widget UIConf ID';
$string['conn_status_title'] = 'Connection status';
$string['conn_success'] = 'Successfully logged in with credentials';
$string['conn_failed'] = 'Unable to login.  Please verify your credentials and connection settings and try again';
$string['test_connection'] = 'Test connection to Kaltura server';
$string['click_test_button'] = 'Click button to test the connection';
$string['start'] = 'Start';
$string['flashminimum'] = 'Flash player version 9 and above is required. '.
                          '<a href=\"http://get.adobe.com/flashplayer/\">Upgrade your flash version</a>';
$string['upload_successful'] = 'Video uploaded successfully.  Remember to save/submit.';
$string['video_converting'] = 'The video is still converting.  Please check the status of the video at a later time.';
$string['conn_failed_alt'] = 'Connection with Kaltura is currently unavailable.  Please inform your administrator.';
$string['player_resource_override'] = 'Override video resource player setting';
$string['player_resource_override_desc'] = 'Check this option to override the individual Video Resource player selection.  This will force all Video Resource activities to use the selected <b>Kaltura resource player</b>';
$string['enable_html5'] = 'Enable HTML5 flavour';
$string['enable_html5_desc'] = 'Check this setting to enable to use of the HTML5 flavours when viewing videos';
$string['video_error'] = 'There was an error processing this video.  Please try another video';
$string['video_bad'] = 'Please do not use this video';
$string['kaltura_general'] = 'General Settings';
$string['kaltura_kalvidpres_title'] = 'Kaltura Video Presentation Settings';
$string['kaltura_kcw_title'] = 'Kaltura Content Wizard (KCW) Settings';
$string['kaltura_kalvidres_title'] = 'Kaltura Video Resource Settings';
$string['kaltura_kalvidassign_title'] = 'Kaltura Video Assignment Settings';
$string['filter_player_width'] = 'Embedded player width';
$string['filter_player_width_desc'] = 'Width of the embedded player';
$string['filter_player_height'] = 'Embedded player height';
$string['filter_player_height_desc'] = 'Height of the embedded player';
$string['filter_player'] = 'Embedded player height';
$string['filter_player_desc'] = 'Height of the embedded player';
$string['filter_custom'] = 'Custom embedded UIConf ID';
$string['filter_custom_desc'] = 'Only used if you have a custom player for embedding video';
$string['player_filter'] =  'Embedded player';
$string['player_filter_desc'] =  'Player used by the Kaltura filter plug-in when embedding video on the page';
$string['kaltura_filter_title'] = 'Embedded player used via the File Picker (Kaltura Filter) settings';
$string['assign_uploader_desc'] = 'Uploader widget when a user is uploading a submission';
$string['res_uploader_desc'] = 'Uploader widget when a user is uploading a video resource';
$string['pres_uploader_desc'] = 'Uploader widget when a user is uploading a video resource';
$string['mymedia_uploader_desc'] = 'Uploader widget when a user is uploading from the My Media page';
$string['kaltura_mymedia_title'] = 'My Media Settings';
$string['assign_uploader'] = 'Video Assignment KCW uploader';
$string['res_uploader'] = 'Video Resource KCW uploader';
$string['pres_uploader'] = 'Video Presentation KCW uploader';
$string['mymedia_uploader'] = 'My Media KCW uploader';
$string['custom_player_upload'] = 'Custom upload widget';
$string['nine'] = '9';
$string['eighteen'] = '18';
$string['twentyone'] = '21';
$string['twentyfour'] = '24';
$string['twentyseven'] = '27';
$string['thirty'] = '30';
$string['mymedia_items_per_page'] = 'Videos per page';
$string['mymedia_items_per_page_desc'] = 'The number of videos to display on a single page';
$string['application_name'] = 'Application name';
$string['application_name_desc'] = 'The application name is the name reported back to the Kaltura server to be used for aggregation by application in the user level reports';
$string['player_mymedia_screen_recorder'] = 'Default screen recorder widget (9780761)';
$string['mymedia_screen_recorder'] = 'Screen Recoder UIConf ID';
$string['mymedia_screen_recorder_desc'] = 'Screen recorder widget when recording a video from the My Media page';
$string['custom_screen_recorder'] = 'Custom screen recorder widget';
$string['kaltura_screen_recorder_custom'] = 'Custom KSR UIConf ID';
$string['kaltura_screen_recorder_custom_desc'] = 'Only used if you have a custom KSR UIConf ID';
$string['kaltura_reports'] = 'Kaltura reports';
$string['kaltura_kalreports_heading'] = 'Kaltura Reports Settings';
$string['report_server_uri'] = 'Reports server URI';
$string['report_server_uri_desc'] = 'Type in the Kaltura reporting server URI you want to connect to';
$string['kaltura_simple_uploader'] = 'Simple Uploader';
$string['kaltura_simple_uploader_desc'] = 'Widget used to upload a document when adding a video presentation';
$string['kaltura_simple_uploader'] = 'Simple Uploader';
$string['kaltura_simple_uploader_desc'] = 'Widget used to upload a document when adding a video presentation';
$string['kaltura_simple_uploader_cust_desc'] = 'Only used if you have a custom simple uploader widget';
$string['kaltura_simple_uploader_cust'] = 'Custom Kaltura Simple Uploader UIConf ID';
$string['kalvidassign_player_height'] = 'Player height';
$string['kalvidassign_player_height_desc'] = 'Adjust this setting if your Kaltura player is being cut off when submitting and/or viewing video submissions.';
$string['kalvidassign_player_width'] = 'Player width';
$string['kalvidassign_player_width_desc'] = 'Adjust this setting if your Kaltura player is being cut off when submitting and/or viewing video submissions.';
$string['simple_uploader'] = 'Simple Uploader (1002613)';
$string['enable_reports'] = 'Enable reports';
$string['enable_reports_desc'] = 'Kaltura reports are only compatible with Kaltura version Falcon or above';
$string['enable_screen_recorder'] = 'Enable screen recorder';
$string['enable_screen_recorder_desc'] = 'A global setting to enable the screen recorder across all Kaltura plug-ins (My Media, Video resource, Video presentation and Video assignment).  This option overrides any specific capability settings.';

// Screen recorder
$string['screenrecorder'] = 'Screen Recording';
$string['loadingwait'] = 'Loading. This may take a few minutes.';

// Capabilities
$string['kaltura:view_report'] = 'View Kaltura reports';

// Kaltura reports
$string['kaltura_report_navbar'] = 'Kaltura Course Media Reports';
$string['header_kaltura_reports'] = 'Kaltura Reports';
$string['no_capability'] = 'You don\'t have the capability to view this report';
$string['report_disabled'] = 'Kaltura reporting has been disabled.  Please enable it in the Kaltura Package Libraries local plug-in';
$string['kaltura_course_reports'] = 'Kaltura Course Media Reports';
$string['clear'] = 'Clear';
$string['course_name'] = 'Course name:';
$string['found_course'] = 'Found {$a} course(s):';
$string['no_recent_course'] = 'There are currently no recently visited courses to display';
$string['no_course_result'] = 'No course results for "{$a}"';
$string['recent_course_view'] = 'Recently viewed course(s):';
$string['recent_courses_display_limit'] = 'Recent courses display limit';
$string['recent_courses_display_limit_desc'] = 'The limit of the number of recently viewed courses to display';
$string['search'] = 'Search';
$string['search_courses_display_limit'] = 'Course search display limit';
$string['search_courses_display_limit_desc'] = 'The limit of the number of courses to display from a search';
$string['repo_not_installed'] = 'The Kaltura repository needs to be installed to view this file';
