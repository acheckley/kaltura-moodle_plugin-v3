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
 * @package    moodle-local_kaltura
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

define('KALTURA_PLUGIN_NAME', 'local_kaltura');
define('KALTURA_DEFAULT_URI', 'http://www.kaltura.com');
define('KALTURA_REPORT_DEFAULT_URI', 'http://apps.kaltura.com/hosted_pages');

define('KALTURA_PLAYER_UPLOADERREGULAR',                6709401); // KCW
define('KALTURA_PLAYER_PLAYERREGULARDARK',              6709411); // KDP dark
define('KALTURA_PLAYER_PLAYERREGULARLIGHT',             6709421); // KDP light
define('KALTURA_PLAYER_PLAYERVIDEOPRESENTATION',        4860481);
define('KALTURA_FILE_UPLAODER',                         6386311); // KSU
define('KALTURA_PLAYER_MYMEDIA_UPLOADER',               8464961); // KCW
define('KALTURA_PLAYER_MYMEDIA_SCREEN_RECORDER',        9780761); // KSR
define('KALTURA_PLAYER_KSU',                            1002613); // KSU

define('KALTURA_FILTER_VIDEO_WIDTH', 400);
define('KALTURA_FILTER_VIDEO_HEIGHT', 300);
define('KALTURA_ASSIGN_VIDEO_WIDTH', 400);
define('KALTURA_ASSIGN_VIDEO_HEIGHT', 365);

// YUI panel popup border adjustments to make the embedded video look centered
define('KALTURA_POPUP_WIDTH_ADJUSTMENT', 30);
define('KALTURA_POPUP_HEIGHT_ADJUSTMENT', 50);

define('KALTURA_SESSION_LENGTH', 10800); // 3 hours

require_once(dirname(__FILE__) . '/API/KalturaClient.php');
require_once(dirname(__FILE__) . '/kaltura_entries.class.php');

/**
 * Initialize the kaltura account and obtain the secrets and partner ID
 *
 * @param string - username (email)
 * @param string - password
 * @param string - URI
 *
 * Return boolean - true on success or false
 */
function local_kaltura_initialize_account($login, $password, $uri = '') {
    global $CFG;

    try {
        $config_obj = new KalturaConfiguration(0);

        $config_obj->clientTag = local_kaltura_create_client_tag();

        if (!empty($CFG->proxyhost)) {
            $config_obj->proxyHost = $CFG->proxyhost;
            $config_obj->proxyPort = $CFG->proxyport;
            $config_obj->proxyType = $CFG->proxytype;
            $config_obj->proxyUser = ($CFG->proxyuser)? $CFG->proxyuser : null;
            $config_obj->proxyPassword = ($CFG->proxypassword && $CFG->proxyuser)? $CFG->proxypassword: null;
        }

        if (!empty($uri)) {
            $config_obj->serviceUrl = $uri;
        }

        $client_obj = new KalturaClient($config_obj);

        if (empty($client_obj)) {
            return false;
        }

        $admin_session = $client_obj->adminUser->login($login, $password);

        $client_obj->setKs($admin_session);

        $user_info = $client_obj->partner->getInfo();

        if (isset($user_info->secret)) {
            set_config('secret', $user_info->secret, KALTURA_PLUGIN_NAME);
        }

        if (isset($user_info->adminSecret)) {
            set_config('adminsecret', $user_info->adminSecret, KALTURA_PLUGIN_NAME);
        }

        if (isset($user_info->id)) {
            set_config('partner_id', $user_info->id, KALTURA_PLUGIN_NAME);
        }

        // Check if this is a hosted initialization
        $connection_type = get_config(KALTURA_PLUGIN_NAME, 'conn_server');

        if (0 == strcmp('hosted', $connection_type)) {

            // May need to set the URI setting beause if was originally
            // disabled then the form will not submit the default URI
            set_config('uri', KALTURA_DEFAULT_URI, KALTURA_PLUGIN_NAME);

            local_kaltura_send_initialization($admin_session);

        }

        return true;

    } catch (KalturaException $e) {
        return false;

    } catch (KalturaClientException $e) {
        return false;
    }
}

function local_kaltura_uninitialize_account() {
    set_config('secret', '', KALTURA_PLUGIN_NAME);
    set_config('adminsecret', '', KALTURA_PLUGIN_NAME);
    set_config('partner_id', '', KALTURA_PLUGIN_NAME);
}

/**
 * Send initializations information to the Kaltura server
 *
 * @param string $session The Kaltura session string
 */
function local_kaltura_send_initialization($session) {

    global $CFG;

    $plugin = new stdClass();
    // We always want the version information even if it was already loaded by something else
    include(dirname(__FILE__).'/version.php');

    $ch = curl_init();

    $uri = "http://corp.kaltura.com/signup/activate_application?".
           "type=moodle&kaltura_version={$plugin->version}&system_version={$plugin->system_version}&ks={$session}";

    $options = array(CURLOPT_URL => $uri,
                     CURLOPT_POST => false,
                     CURLOPT_RETURNTRANSFER => true);

    curl_setopt_array($ch, $options);

    curl_exec($ch);
    curl_close($ch);
}


/**
 * Log in with the user's credentials.  General a kaltura session locally
 *
 * @param boolean - true to login as an administrator or false to login as user
 * @param string - privleges give to the user
 * @param int - number of seconds to keep the session alive
 *
 * @return obj - KalturaClient
 */
function local_kaltura_login($admin = false, $privileges = '', $expiry = 10800, $test_conn = false) {
    global $USER;

    list($login, $password) = local_kaltura_get_credentials();


    if (empty($login) || empty($password)) {
        return false;
    }

    $config_obj = local_kaltura_get_configuration_obj();

    if (empty($config_obj) || !($config_obj instanceof KalturaConfiguration)) {
        return false;
    }

    $client_obj = new KalturaClient($config_obj);

    if (empty($client_obj)) {
        return false;
    }

    $partner_id = $client_obj->getConfig()->partnerId;
    $secret     = get_config(KALTURA_PLUGIN_NAME, 'adminsecret');

    if (isloggedin()) {
        $username = $USER->username;
    } else {
        $username = null;
    }

    if ($admin) {

        $session = $client_obj->generateSession($secret, $username, KalturaSessionType::ADMIN,
                                     $partner_id, $expiry, $privileges);
    } else {

        $session = $client_obj->generateSession($secret, $username, KalturaSessionType::USER,
                                     $partner_id, $expiry, $privileges);
    }

    if (!empty($session)) {

        $client_obj->setKs($session);

        if ($test_conn) {
            $result = local_kaltura_test_connection($client_obj);

            if (empty($result)) {
                return false;
            }
        }

        return $client_obj;

    } else {
        return false;
    }

}

/**
 * Generate a weak Kaltura session
 *
 * @param int $courseid The id of the course
 * @param string $course_name The name of the course
 * @return string|bool The session ID string value or false on error
 */
function local_kaltura_generate_weak_kaltura_session($courseid, $course_name) {
    global $CFG, $USER, $DB;

    $config_obj = local_kaltura_get_report_configuration_obj();

    if (empty($config_obj) || !($config_obj instanceof KalturaConfiguration)) {
        return false;
    }

    $client_obj = new KalturaClient($config_obj);

    if (empty($client_obj)) {
        return false;
    }

    $kaltura = new kaltura_connection();
    $connection = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);

    if (empty($connection)) {
        return '';
    }

    $kaltura_version = get_config(KALTURA_PLUGIN_NAME, 'version');
    $kal_category    = repository_kaltura_create_course_category($connection, $courseid);

    $context     = get_context_instance(CONTEXT_COURSE, $courseid);
    $students    = count_enrolled_users($context, 'moodle/grade:view');

    $secret = get_config(KALTURA_PLUGIN_NAME, 'adminsecret');
    $partner_id = get_config(KALTURA_PLUGIN_NAME, 'partner_id');

    $username = null;

    if (isloggedin()) {
        $username = $USER->username;
    }
    // TODO: LOOK INTO THE CALLING OF A NON OBJECT
    // var_dump($kal_category);

    $privilege = array(
        'app' => 'moodle',
        'userId' => $username,
        'returnUrl' => $CFG->wwwroot . '/local/kaltura/reports.php?couseid=' . $courseid,
        'parentUrl' => $CFG->wwwroot,
        'appVersion' => $kaltura_version,
        'locale' => 'en',
        'frameWidth' => '800',
        'frameHeight' => '600',
        //'cssUrl' => '',
        'tz' => '300', // 60*5 = EST = GMT+5
        'categoryId' => $kal_category->id,
        'courseName' => $course_name,
        'totalUsersCount' => $students,
    );

    $priv_str  = '';

    foreach ($privilege as $key => $val) {
        $priv_str .= ','.$key.':'.$val;
    }

    $privilege = ltrim($priv_str, ',');

    if (function_exists('mcrypt_encrypt')) {
        $ks = $client_obj->generateSessionV2($secret, $username, KalturaSessionType::USER,
                                             $partner_id, 10800, $privilege);

    } else {
        $ks = $client_obj->generateSession($secret, $username, KalturaSessionType::USER,
                                             $partner_id, 10800, $privilege);
    }

    return $ks;

}

/**
 * This function is refactored code from @see login(). It only generates and
 * returns a user Kaltura session. The session value returned is mainly used for
 * inclusion into the video markup flashvars query string.
 *
 * @param string - privilege string
 * @return array - an array of Kaltura video entry ids
 */
function local_kaltura_generate_kaltura_session($video_list = array()) {
    global $USER;

    $config_obj = local_kaltura_get_configuration_obj();

    if (empty($config_obj) || !($config_obj instanceof KalturaConfiguration)) {
        return false;
    }

    $client_obj = new KalturaClient($config_obj);

    if (empty($client_obj) || empty($video_list)) {
        return false;
    }

    $privilege  = 'sview:' . implode(',sview:', $video_list);

    $secret     = get_config(KALTURA_PLUGIN_NAME, 'adminsecret');
    $partner_id = get_config(KALTURA_PLUGIN_NAME, 'partner_id');

    if (isloggedin()) {
        $username = $USER->username;
    } else {
        $username = null;
    }
    $session = $client_obj->generateSession($secret, $username, KalturaSessionType::USER,
                                            $partner_id, KALTURA_SESSION_LENGTH, $privilege);

    return $session;
}

/**
 * Returns an array with the login and password as values respectively
 *
 * @return array - login, password or an array of false values if none
 * were found
 */
function local_kaltura_get_credentials() {

    $login = false;
    $password = false;

    $login = get_config(KALTURA_PLUGIN_NAME, 'login');
    $password = get_config(KALTURA_PLUGIN_NAME, 'password');

    return array($login, $password);
}

/**
 * Retrieve an instance of the KalturaConfiguration class
 *
 * @return obj - KalturaConfiguration
 */
function local_kaltura_get_configuration_obj() {
    global $CFG;

    $partner_id = local_kaltura_get_partner_id();
    $uri        = local_kaltura_get_host();

    if (empty($partner_id)) {
        return false;
    }

    $config_obj = new KalturaConfiguration($partner_id);
    $config_obj->serviceUrl = $uri;
    $config_obj->clientTag = local_kaltura_create_client_tag();


    if (!empty($CFG->proxyhost)) {
        $config_obj->proxyHost = $CFG->proxyhost;
        $config_obj->proxyPort = $CFG->proxyport;
        $config_obj->proxyType = $CFG->proxytype;
        $config_obj->proxyUser = ($CFG->proxyuser)? $CFG->proxyuser : null;
        $config_obj->proxyPassword = ($CFG->proxypassword && $CFG->proxyuser)? $CFG->proxypassword: null;
    }
    return $config_obj;
}

/**
 * Retrieve an instance of the KalturaConfiguration class
 *
 * @return obj - KalturaConfiguration
 */
function local_kaltura_get_report_configuration_obj() {
    global $CFG;

    $partner_id = local_kaltura_get_partner_id();
    $uri        = get_config(KALTURA_PLUGIN_NAME, 'report_uri');

    if (empty($partner_id)) {
        return false;
    }

    $config_obj = new KalturaConfiguration($partner_id);
    $config_obj->serviceUrl = $uri;
    $config_obj->clientTag = local_kaltura_create_client_tag();


    if (!empty($CFG->proxyhost)) {
        $config_obj->proxyHost = $CFG->proxyhost;
        $config_obj->proxyPort = $CFG->proxyport;
        $config_obj->proxyType = $CFG->proxytype;
        $config_obj->proxyUser = ($CFG->proxyuser)? $CFG->proxyuser : null;
        $config_obj->proxyPassword = ($CFG->proxypassword && $CFG->proxyuser)? $CFG->proxypassword: null;
    }
    return $config_obj;
}

/**
 * Returns the test connection markup
 */
function local_kaltura_testconnection_markup() {

    $markup = '';

    $markup .= html_writer::start_tag('center');

    $attr = array('id' => 'open_button',
                  'value' => get_string('start', 'local_kaltura'),
                  'type' => 'button');
    $markup .= html_writer::tag('input', '', $attr);

    $markup .= html_writer::end_tag('center');

    return $markup;
}

/**
 * Retrieve a list of all the custom players available to the account
 */

function local_kaltura_get_custom_players() {


    try {
        $custom_players = array();

        $client_obj = local_kaltura_login(true, '');

        if (empty($client_obj)) {
            return $custom_players;
        }

        $allowed_types = array(KalturaUiConfObjType::PLAYER, KalturaUiConfObjType::PLAYER_V3);

        $filter = new KalturaUiConfFilter();
        $filter->objTypeEqual = implode(',', $allowed_types);
        $filter->tagsMultiLikeOr = 'player';

        $players = $client_obj->uiConf->listAction($filter, null);

        if ((!empty($players->objects)) && ($players instanceof KalturaUiConfListResponse)) {
            foreach ($players->objects as $uiconfid => $player) {

                $custom_players["{$player->id}"] = "{$player->name} ({$player->id})";
            }
        }

        return $custom_players;
    } catch (KalturaClientException $e) {
        //print_object($e);
        return false;
    } catch (KalturaException $e) {
        return false;
    }
}

/**
 * Retrieves the default player UIConf ID or the custom UIConf ID
 *
 * @param string - type of player uiconf to return, accepted values
 * are player, uploader and presentation
 *
 * @return int - uiconf id of the type of player requested
 */
function local_kaltura_get_player_uiconf($type = 'player') {

    $uiconf = 0;

    switch ($type) {
        case 'player':
        case 'player_resource':
        case 'res_uploader':
        case 'pres_uploader':
        case 'presentation':
        case 'mymedia_uploader':
        case 'mymedia_screen_recorder':
        case 'assign_uploader':
        case 'player_filter':
        case 'simple_uploader';

            $uiconf = get_config(KALTURA_PLUGIN_NAME, $type);

            if (empty($uiconf)) {
                $uiconf = get_config(KALTURA_PLUGIN_NAME, "{$type}_custom");
            }
            break;
        default:
            break;
    }


    return $uiconf;
}

/**
 * Retrives the player resource override configuration value
 *
 * @param nothing
 *
 * @return string - 1 if override is required, else 0
 */
function local_kaltura_get_player_override() {
    return get_config(KALTURA_PLUGIN_NAME, 'player_resource_override');
}

/**
 * Return the host URI and removes trailing slash
 *
 * @return string - host URI
 */
function local_kaltura_get_host() {

    $uri = get_config(KALTURA_PLUGIN_NAME, 'uri');

    // Remove trailing slash
    $trailing_slash = strrpos($uri, '/') + 1;
    $length         = strlen($uri);

    if ($trailing_slash == $length) {
        $uri = rtrim($uri, '/');
    }

    return $uri;
}

/**
 * Return the partner Id
 *
 * @return int - partner Id
 */
function local_kaltura_get_partner_id() {
    return get_config(KALTURA_PLUGIN_NAME, 'partner_id');
}

/**
 * Returns the javascript needed to initialize the KCW
 *
 * @param string - type of KCW to generate
 * @param bool - true if this is an admin using the kcw else false
 *
 * @return string - Javascript used by the KCW
 */
function local_kaltura_get_kcw($type = 'res_uploader', $admin = false) {

    $client_obj = local_kaltura_login(true);

    if (empty($client_obj)) {
        return '';
    }

    $kal_user   = local_kaltura_init_kaltura_user($admin);
    $vars       = local_kaltura_get_flashvars($client_obj, $kal_user);
    $uiconf     = local_kaltura_get_player_uiconf($type);
    $url        = local_kaltura_get_kcw_url($client_obj, $uiconf);

    return local_kaltura_get_kcw_code('', $url, $vars);
}

/**
 * Initialize the KalturaUser object
 *
 * @param bool - true to set isAdmin
 *
 * @return object - KalturaUser object
 */
function local_kaltura_init_kaltura_user($admin = false) {
    global $USER;

    $user               = new KalturaUser();
    $user->id           = $USER->id;
    $user->screenName   = $USER->username;
    $user->email        = $USER->email;
    $user->firstName    = $USER->firstname;
    $user->lastName     = $USER->lastname;
    $user->isAdmin      = $admin;

    return $user;
}

/**
 * Return the URL used by the SWFObject
 *
 * @param obj - a KalturaConfiguration object
 * @param int - uniconf Id of the widget to use
 *
 * @return string URL used by the SWFObject
 */
function local_kaltura_get_kcw_url($client_obj, $uiconf_id) {

    return $client_obj->getConfig()->serviceUrl . '/kcw/ui_conf_id/' . $uiconf_id;
}

/**
 * Return the URL used by the SWFObject
 *
 * @param obj - a KalturaConfiguration object
 * @param int - uniconf Id of the widget to use
 *
 * @return string URL used by the SWFObject
 */
function local_kaltura_get_kdp_presentation_url($client_obj, $uiconf_id) {

    return $client_obj->getConfig()->serviceUrl . '/kwidget/wid/_'.
           $client_obj->getConfig()->partnerId . '/uiconf_id/' . $uiconf_id;
}

/**
 * Retrieve the flashvars needed for the Kaltura SWF Uploader widget
 *
 * @param bool - login as an admin
 *
 * @return obj - flash vars as an object with properties
 */
function local_kaltura_get_uploader_flashvars($admin = false) {

    $client_obj = local_kaltura_login(true);

    if (empty($client_obj)) {
        return '';
    }

    $uiconf     = local_kaltura_get_player_uiconf('simple_uploader');
    $partner_id = local_kaltura_get_partner_id();
    $var_string = '';

    $vars = array();
    $vars['ks']          = $client_obj->getKs();
    $vars['partnerId']  = $partner_id;
    $vars['subPId'] = $partner_id * 100; // http://www.kaltura.org/kaltura-terminology#kaltura-sub-partner-id
    $vars['entryId'] = '-1';
    $vars['maxUploads'] = '10';
    $vars['maxFileSize'] = '128';
    $vars['maxTotalSize'] = '200';
    $vars['uiConfId'] = $uiconf;
    $vars['jsDelegate'] = 'delegate';

    foreach($vars as $key => $data) {
        $var_string .= $key . '=' . urlencode($data) . '&';
    }

    $var_string = rtrim($var_string, '&');

    return $var_string;

}

/**
 * Create flashvars to pass to SWFObject
 *
 * @param object - a KalturaConfiguration object
 * @param string - entry id of the video presentation
 * @param boolean - true to allow editing of the keypoints otherwise false
 *
 * @return string - querystring of flashvars
 */
function local_kaltura_get_swfdoc_flashvars($client_obj, $entry_id = '', $admin_mode = false) {

    global $USER;

    $var_string = '';

    $vars = array();
    $vars['showCloseButton'] = 'true';
    $vars['close'] = 'onContributionWizardClose';
    $vars['host']  = str_replace('http://', '', local_kaltura_get_host());
    $vars['partnerid']  = local_kaltura_get_partner_id();
    $vars['subpid']     = local_kaltura_get_partner_id() * 100; // http://www.kaltura.org/kaltura-terminology#kaltura-sub-partner-id
    $vars['uid']        = $USER->username;
    $vars['debugMode'] = '1';
    $vars['kshowId'] = '-1';
    $vars['pd_sync_entry'] = $entry_id;

    // If using admin mode adding these flashvars will display the edit buttons for adding sync points
    if ($admin_mode) {
        $vars['adminMode']  = 'true';
        $vars['ks']         = $client_obj->getKs();
    }

    foreach($vars as $key => $data) {
        $var_string .= $key . '=' . $data . '&';
    }

    $var_string = rtrim($var_string, '&');

    return $var_string;

}

/**
 * Create flashvars to pass to SWFObject
 *
 * @param object - a KalturaConfiguration object
 * @param object - a KalturaUser object
 * @param array - array of additional parameters key param name, value param
 * value
 *
 * @return string - querystring of flashvars
 */
function local_kaltura_get_flashvars($client_obj, $user_obj, $params = array()) {

    $var_string = '';

    $vars = array();
    $vars['partnerId']          = $client_obj->getConfig()->partnerId;
    $vars['kshow_id']           = -2;
    $vars['userId']             = $user_obj->screenName;
    $vars['sessionId']          = $client_obj->getKs();
    $vars['isAnonymous']        = 0;
    $vars['afterAddentry']      = 'onContributionWizardAfterAddEntry';
    $vars['close']              = 'onContributionWizardClose';
    //$vars['showCloseButton']    = 1;
    //$vars['partnerData'] = '';

    $vars = array_merge($vars, $params);

    foreach($vars as $key => $data) {
        $var_string .= $key . '=' . urlencode($data) . '&';
    }

    $var_string = rtrim($var_string, '&');

    return $var_string;
}

function local_kaltura_get_kcw_code($class = '', $swf_url, $flash_vars) {
    global $_SERVER;

    $output = '';
    $kcw_markup = '';

    $requirement = get_string('flashminimum', 'local_kaltura');

    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') === FALSE) {

        $attr = array('type' => 'application/x-shockwave-flash',
                      'src' => $swf_url,
                      'width' => '782',
                      'height' => '449',
                      'stype' => 'undefined',
                      'id' => 'kcw_div',
                      'name' => 'name',
                      'bgcolor' => '#ffffff',
                      'quality' => 'high',
                      'allowscriptaccess' => 'always',
                      'allowfullscreen' => 'TRUE',
                      'allownetworking' => 'all',
                      'flashvars' => $flash_vars,
                      );

        $kcw_markup = html_writer::empty_tag('embed', $attr);

    } else {
        $attr = array('id' => 'kcw_div',
                      'classid' => 'clsid:D27CDB6E-AE6D-11cf-96B8-444553540000',
                      'width' => '782',
                      'height' => '449'
                      );
        $kcw_markup .= html_writer::start_tag('object', $attr);

        $attr = array('NAME' => '_cx',
                       'VALUE' => '20690'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => '_cy',
                       'VALUE' => '11879'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'FlashVars',
                       'VALUE' => $flash_vars,
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'Movie',
                       'VALUE' => $swf_url
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'Src',
                       'VALUE' => $swf_url
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'WMode',
                       'VALUE' => 'Window'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'Play',
                       'VALUE' => '0'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'Loop',
                       'VALUE' => '-1'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'Quality',
                       'VALUE' => 'High'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'SAligh',
                       'VALUE' => 'LT'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'Menu',
                       'VALUE' => '-1'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'Base',
                       'VALUE' => ''
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'AllowScriptAccess',
                       'VALUE' => 'always'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'Scale',
                       'VALUE' => 'NoScale'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'DeviceFont',
                       'VALUE' => '0'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'EmbedMovie',
                       'VALUE' => '0'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'BGColor',
                       'VALUE' => 'FFFFFF'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'SWRemote',
                       'VALUE' => ''
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'MovieData',
                       'VALUE' => ''
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'SeamlessTabbing',
                       'VALUE' => '1'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'Profile',
                       'VALUE' => '0'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'ProfileAddress',
                       'VALUE' => ''
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'ProfilePort',
                       'VALUE' => '0'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'AllowNetworking',
                       'VALUE' => 'all'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'AllowFullScreen',
                       'VALUE' => 'true'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $kcw_markup .= html_writer::end_tag('object');

    }

    return $kcw_markup;

}

function local_kaltura_get_kdp_presentation_code($class = '', $swf_url, $flash_vars) {
    global $_SERVER;

    $output = '';
    $kcw_markup = '';

    $requirement = get_string('flashminimum', 'local_kaltura');

    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') === FALSE) {

        $attr = array('type' => 'application/x-shockwave-flash',
                      'src' => $swf_url,
                      'width' => '780',
                      'height' => '400',
                      'stype' => 'undefined',
                      'id' => 'video_presentation_player',
                      'name' => 'name',
                      'bgcolor' => '#ffffff',
                      'quality' => 'high',
                      'allowscriptaccess' => 'always',
                      'allowfullscreen' => 'TRUE',
                      'allownetworking' => 'all',
                      'flashvars' => $flash_vars,
                      );

        $kcw_markup = html_writer::empty_tag('embed', $attr);

    } else {
        $attr = array('id' => 'video_presentation_player',
                      'classid' => 'clsid:D27CDB6E-AE6D-11cf-96B8-444553540000',
                      'width' => '780',
                      'height' => '400'
                      );
        $kcw_markup .= html_writer::start_tag('object', $attr);

        $attr = array('NAME' => '_cx',
                       'VALUE' => '20637'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => '_cy',
                       'VALUE' => '10583'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'FlashVars',
                       'VALUE' => $flash_vars,
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'Movie',
                       'VALUE' => $swf_url
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'Src',
                       'VALUE' => $swf_url
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'WMode',
                       'VALUE' => 'Window'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'Play',
                       'VALUE' => '0'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'Loop',
                       'VALUE' => '-1'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'Quality',
                       'VALUE' => 'High'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'SAligh',
                       'VALUE' => 'LT'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'Menu',
                       'VALUE' => '-1'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'Base',
                       'VALUE' => ''
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'AllowScriptAccess',
                       'VALUE' => 'always'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'Scale',
                       'VALUE' => 'NoScale'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'DeviceFont',
                       'VALUE' => '0'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'EmbedMovie',
                       'VALUE' => '0'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'BGColor',
                       'VALUE' => 'FFFFFF'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'SWRemote',
                       'VALUE' => ''
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'MovieData',
                       'VALUE' => ''
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'SeamlessTabbing',
                       'VALUE' => '1'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'Profile',
                       'VALUE' => '0'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'ProfileAddress',
                       'VALUE' => ''
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'ProfilePort',
                       'VALUE' => '0'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'AllowNetworking',
                       'VALUE' => 'all'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $attr = array('NAME' => 'AllowFullScreen',
                       'VALUE' => 'true'
                       );
        $kcw_markup .= html_writer::empty_tag('PARAM', $attr);

        $kcw_markup .= html_writer::end_tag('object');

    }

    return $kcw_markup;

}

/**
 * This functions returns the HTML markup for the Kaltura dynamic player.
 *
 * @param obj - Kaltura video object
 * @param int - player ui_conf_id (optional).  If no value is specified the
 * default player will be used.
 * @param int - Moodle course id (optional).  This parameter is required in
 * order to generate Kaltura analytics data.
 * @param string - A kaltura session string
 * @param int - a unique identifier, this value is appented to 'kaltura_player_'
 * and is used as the id of the object tag
 *
 * @return string - HTML markup
 */
function local_kaltura_get_kdp_code($entry_obj, $uiconf_id = 0, $courseid = 0, $session = '', $uid = 0) {

    if (!local_kaltura_is_valid_entry_object($entry_obj)) {
        return 'Unable to play video ('. $entry_obj->id . ') please contact your site administrator.';
    }

    if (0 == $uid) {
        $uid  = floor(microtime(true));
        $uid .= '_' . mt_rand();
    }

    $host       = local_kaltura_get_host();
    $flash_vars = local_kaltura_get_kdp_flashvars($courseid, $session);

    if (empty($uiconf_id)) {
        $uiconf = local_kaltura_get_player_uiconf('player');
    } else {
        $uiconf = $uiconf_id;
    }

    $output =
        "<object id=\"kaltura_player_{$uid}\" name=\"kaltura_player_{$uid}\"
        type=\"application/x-shockwave-flash\" allowFullScreen=\"true\" allowNetworking=\"all\"
        allowScriptAccess=\"always\" height=\"{$entry_obj->height}\" width=\"{$entry_obj->width}\"
        xmlns:dc=\"http://purl.org/dc/terms/\" xmlns:media=\"http://search.yahoo.com/searchmonkey/media/\"
        rel=\"media:{$entry_obj->mediaType}\" resource=\"{$host}/index.php/kwidget/wid/_{$entry_obj->partnerId}/uiconf_id/{$uiconf}/entry_id/{$entry_obj->id}\"
        data=\"{$host}/index.php/kwidget/wid/_{$entry_obj->partnerId}/uiconf_id/{$uiconf}/entry_id/{$entry_obj->id}\">

        <param name=\"allowFullScreen\" value=\"true\" />
        <param name=\"allowNetworking\" value=\"all\" />
        <param name=\"allowScriptAccess\" value=\"always\" />
        <param name=\"bgcolor\" value=\"#000000\" />
        <param name=\"flashvars\" value=\"{$flash_vars}\" />
        <param name=\"wmode\" value=\"opaque\" />

        <param name=\"movie\" value=\"{$host}/index.php/kwidget/wid/_{$entry_obj->partnerId}/uiconf_id/{$uiconf}/entry_id/{$entry_obj->id}\" />

        <a rel=\"media:thumbnail\" href=\"{$entry_obj->thumbnailUrl}/width/120/height/90/bgcolor/000000/type/2\"></a>
        <span property=\"dc:description\" content=\"{$entry_obj->description}\"></span>
        <span property=\"media:title\" content=\"{$entry_obj->name}\"></span>
        <span property=\"media:width\" content=\"{$entry_obj->width}\"></span>
        <span property=\"media:height\" content=\"{$entry_obj->height}\"></span>
        <span property=\"media:type\" content=\"application/x-shockwave-flash\"></span>
        <span property=\"media:duration\" content=\"{$entry_obj->duration}\"></span>
        </object>";

    return $output;
}

/**
 * This function returns a string of flash variables required for Kaltura
 * analytics
 *
 * @param courseid
 * @param string - Kaltura session string
 * @return string - query string of flash variables
 *
 */
function local_kaltura_get_kdp_flashvars($courseid = 0, $session = '') {
    global $USER;

    if (isloggedin()) {
        $flash_vars = "userId={$USER->username}";
    } else {
        $flash_vars = '';
    }
    if (!empty($session)) {
       $flash_vars .= '&amp;ks='.$session;
    }

    $application_name = get_config(KALTURA_PLUGIN_NAME, 'mymedia_application_name');

    $application_name = empty($application_name) ? 'Moodle' : $application_name;

    $flash_vars .= '&amp;applicationName='.$application_name;

    $enabled = local_kaltura_kaltura_repository_enabled();

    if (!$enabled && 0 != $courseid) {
        return $flash_vars;
    }

    // Require the Repository library for category information
    require_once(dirname(dirname(dirname(__FILE__))) . '/repository/kaltura/locallib.php');

    $kaltura = new kaltura_connection();
    $connection = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);

    if (!$connection) {
        return '';
    }

    $category = repository_kaltura_create_course_category($connection, $courseid);

    if ($category) {
        $flash_vars .= '&amp;playbackContext=' . $category->id;
    }

    return $flash_vars;
}

/**
 * This function checks to see if the Kaltura repository plugin is enabled.
 * Because there is not easy wasy of verifying in the database and it would
 * require instanciating multiple classes, we're only going to check if the
 * required values are set in the database.
 *
 * @param none
 * @return bool - true if it exists and is enabled, otherwise false
 */
function local_kaltura_kaltura_repository_enabled() {
    global $CFG;

    $enabled = false;

    if (file_exists($CFG->dirroot . '/repository/kaltura/lib.php')) {
        require_once($CFG->dirroot . '/repository/kaltura/lib.php');

        if (!function_exists('repository_kaltura_create_course_category') ||
            !function_exists('repository_kaltura_add_video_course_reference')) {
            return $enabled;
        }

        $root_id   = get_config(REPOSITORY_KALTURA_PLUGIN_NAME, 'rootcategory_id');
        $root_path = get_config(REPOSITORY_KALTURA_PLUGIN_NAME, 'rootcategory');


        if (empty($root_id) || empty($root_path)) {
            return $enabled;
        }

        $enabled = true;
    }

    return $enabled;
}

/**
 * This function checks for the existance of required entry object
 * properties.  See KALDEV-28 for details
 *
 * @param object - entry object
 * @return bool - true if valid, else false
 */
function local_kaltura_is_valid_entry_object($entry_obj) {
    if (isset($entry_obj->mediaType)) {
        return true;
    } else {
        return false;
    }
}

/**
 * Returns the javascript needed to initialize the KDP for
 * the video presentation
 *
 * @param string - entry id of the video presentation
 * @param bool - true if this is an admin using the kcw else false
 *
 * @return string - Javascript used by the KCW
 */
function local_kaltura_get_kdp_presentation_player($entry_obj, $admin = false) {

    $client_obj = local_kaltura_login(true);

    if (empty($client_obj)) {
        return '';
    }

    $vars   = local_kaltura_get_swfdoc_flashvars($client_obj, $entry_obj->id, $admin);

    $uiconf = local_kaltura_get_player_uiconf('presentation');
    $url    = local_kaltura_get_kdp_presentation_url($client_obj, $uiconf);

    return local_kaltura_get_kdp_presentation_code('', $url, $vars);
}

/**
 * Retrieves an entry object and cache the object only if the entry status is
 * set to 'ready'.  If the second parameter is set to true, this function will
 * only return an entry object if the status is set to true.  Otherwise it will
 * return false.
 *
 * @param string - entry id
 * @param bool - true if this function is the only return an entry object when
 * the entry status is set to 'ready'.  False, to return the entry object
 * regardless of it's status
 *
 * @param mixed - entry object, or false (depending on the entry status and the
 * second prameter
 *
 * TODO: Change the name of this function (and all references) since it on
 * longer only returns an entry object when the status is set to 'ready'
 */
function local_kaltura_get_ready_entry_object($entry_id, $ready_only = true) {

    try {
        $client_obj = local_kaltura_login(true, '');

        if (empty($client_obj)) {
            return false;
        }

        // Check if the entry object is cached
        $entries = new KalturaStaticEntries();
        $entry_obj = KalturaStaticEntries::getEntry($entry_id, $client_obj->baseEntry, false);

        if (!empty($entry_obj)) {
            return $entry_obj;
        }

        // Check if the entry object is ready, by making an API call
        $entry_obj = $client_obj->baseEntry->get($entry_id);

        // If the entry object is ready then return it
        if (KalturaEntryStatus::READY == $entry_obj->status) {

            KalturaStaticEntries::addEntryObject($entry_obj);

            return $entry_obj;

        } else {
        // If it's not ready, check if the request is for a ready only object

            if (true === $ready_only) {
                $entry_obj = false;
            }
        }

        return $entry_obj;

    } catch (Exception $ex) {
        // Connection failed for some reason.  Maybe proxy settings?
        add_to_log(SITEID, 'local_kaltura', ' | check conversion', '', $ex->getMessage());
        return false;
    }
}

/**
 * This function generates the HTML markup for the File uploader widget
 *
 * @param array - array of parameters (currently it is not used)
 */
function local_kaltura_get_ksu_code($params = array()) {

    $flashvars      = local_kaltura_get_uploader_flashvars(true);
    $ksu_id         = local_kaltura_get_player_uiconf('simple_uploader');
    $uploader_url   = local_kaltura_get_host() . '/kupload/ui_conf_id/' . $ksu_id; //1002613
    $flashmin       = get_string('flashminimum', 'local_kaltura');
    $javascript =
        "\n<script type=\"text/javascript\">\n".
        "//<![CDATA[\n".
        "function add_ksu_uploader() {\n".
        "  var ksu = new SWFObject(\"{$uploader_url}\", \"uploader\", \"108\", \"21\", \"9.0.0\", \"#ffffff\");\n".
        "  ksu.addParam(\"flashVars\", \"{$flashvars}\");\n".
        "  ksu.addParam(\"allowScriptAccess\", \"always\");\n".
        "  ksu.addParam(\"allowFullScreen\", \"TRUE\");\n".
        "  ksu.addParam(\"allowNetworking\", \"all\");\n".
        "  ksu.addParam(\"wmode\", \"transparent\");\n".
        "  if (ksu.installedVer.major >= 9) {\n".
        "    ksu.write(\"ksu_tag\");\n".
        "  } else {\n".
        "    document.getElementById(\"ksu_tag\").innerHTML = \"{$flashmin}\";\n".
        "  }\n".
        "}\n".
        "//]]>\n".
        "</script>";

    return $javascript;
}

function local_kaltura_convert_ppt($entryId) {
    try {

        $client_obj = local_kaltura_login(true, '');

        $document_client = KalturaDocumentClientPlugin::get($client_obj);

        $document_url = $document_client->documents->convertPptToSwf($entryId);

////Debug
//$myFile = "/tmp/kcreate.txt";
//$fh = fopen($myFile, 'a');
//fwrite($fh, " -- convert_ppt  -- ");
//fwrite($fh, $document_url);
//fwrite($fh, ' ---- ');
//fclose($fh);

        return 'y:'. $document_url;
    } catch(Exception $exp) {
        return 'n:' . $exp->getMessage();
    }
}

function local_kaltura_check_document_status($document_id) {

    $client_obj = local_kaltura_login(true, '');

    $documentAssets = $client_obj->flavorAsset->getByEntryId($document_id);

    foreach($documentAssets as $asset) {

        if ($asset->fileExt != 'swf') {
            continue;
        }

        if ($asset->fileExt == 'swf' && $asset->status == KalturaFlavorAssetStatus::READY) {
            $params = array('entryId' => $document_id,
                            'flavorAssetId' => $asset->id,
                            'forceProxy' => true);

            $documents = new KalturaDocumentsService($client_obj);
            $url = $documents->serve($document_id, $asset->id);
            //$url = $client_obj->document->serve($document_id, $asset->id);
            $url = str_replace('&amp;', '&', $url);
            return 'y:' . $url;
        }
    }

    return 'n: not ready yet';
}

function local_kaltura_create_swfdoc($document_entry_id, $video_entry_id) {

////Debug
//$myFile = "/tmp/kcreate.txt";
//$fh = fopen($myFile, 'a');
//fwrite($fh, " -- create_swfdoc 1st -- ");
//fwrite($fh, ' ---- ');
//fclose($fh);
    $client_obj = local_kaltura_login(true, '');

    $url = local_kaltura_get_host() .
           '/index.php/extwidget/raw/entry_id/' .
           $document_entry_id . '/p/' . local_kaltura_get_partner_id() .
           '/sp/' . local_kaltura_get_partner_id() * 100 .
           '/type/download/format/swf/direct_serve/1';

    if (strpos($url, 'www.kaltura.com')) {
        $url = str_replace('www.kaltura.com', 'cdn.kaltura.com', $url);
    }

    $xml = '<sync><video><entryId>'.$video_entry_id.'</entryId></video><slide><path>'.$url.'</path></slide>';
    $xml .= '<times></times></sync>';

    $entry = new KalturaDataEntry();
    $entry->dataContent = $xml;
    $entry->mediaType = KalturaEntryType::DOCUMENT;
    $result = $client_obj->data->add($entry);

////Debug
//$myFile = "/tmp/kcreate.txt";
//$fh = fopen($myFile, 'a');
//fwrite($fh, " -- create_swfdoc -- ");
//$stringData = var_export($result, true);
//fwrite($fh, $stringData);
//fwrite($fh, ' ---- ');
//fclose($fh);

    return $result->id;
}


function local_kaltura_get_swfdoc_code($entry_id) {

    $flashvars      = local_kaltura_get_swfdoc_flashvars($entry_id, true);
    $uiconf         = local_kaltura_get_player_uiconf('presentation');
    $swf_url        = local_kaltura_get_host() . '/kwidget/wid/_' .
                      local_kaltura_get_partner_id() . '/uiconf_id/' . $uiconf ;
    $flashmin       = get_string('flashminimum', 'local_kaltura');

    $javascript =
        "//<![CDATA[\n".
        "var kdpp = new SWFObject(\"{$swf_url}\", \"video_presentation_player\", \"780\", \"400\", \"9.0.0\", \"#ffffff\");\n".
        "kdpp.addParam(\"flashVars\", \"{$flashvars}\");\n".
        "kdpp.addParam(\"allowScriptAccess\", \"always\");\n".
        "kdpp.addParam(\"allowFullScreen\", \"TRUE\");\n".
        "kdpp.addParam(\"allowNetworking\", \"all\");\n".
        "kdpp.addParam(\"wmode\", \"opaque\");\n".
        "if (kdpp.installedVer.major >= 9) {\n".
        "  kdpp.write(\"video_presentation_tag\");\n".
        "} else {\n".
        "  document.getElementById(\"video_presentation_tag\").innerHTML = \"{$flashmin}\";\n".
        "}\n";
        "//]]>\n";

    return $javascript;
}

/**
 * Check that the account has mobile flavours enabled
 *
 * @return bool - true if enabled, otherwise false
 */
function local_kaltura_has_mobile_flavor_enabled() {

    $filter = new KalturaPermissionFilter();
    $filter->nameEqual = 'FEATURE_MOBILE_FLAVORS';

    $pager = new KalturaFilterPager();
    $pager->pageSize = 30;
    $pager->pageIndex = 1;

    try {
        $client_obj = local_kaltura_login(true);

        if (empty($client_obj)) {
            throw new Exception("Unable to connect");
        }

        $results = $client_obj->permission->listAction($filter, $pager);

        if ( 0 == count($results->objects) ||
            $results->objects[0]->status != KalturaPermissionStatus::ACTIVE) {

            throw new Exception("partner doesn't have permission");

        }

        return true;

    } catch (Exception $ex) {
        add_to_log(SITEID, 'local_kaltura', ' | mobile flavor on', '', $ex->getMessage());
        return false;
    }
}

function local_kaltura_test_connection($client_obj) {
    $results = false;

    $filter = new KalturaPermissionFilter();
    $filter->nameEqual = 'KMC_READ_ONLY';

    $pager = new KalturaFilterPager();
    $pager->pageSize = 30;
    $pager->pageIndex = 1;

    try {

        $results = $client_obj->permission->listAction($filter, $pager);

        return $results;

    } catch (Exception $ex) {
        add_to_log(SITEID, 'local_kaltura', ' | test connection', '', $ex->getMessage());
        return false;
    }
}

/**
 * Return the Kaltura HTML5 javascript library URL
 * @param int - uiconf_id of the player to use
 *
 * @return string - url to the Kaltura HTML5 library URL
 */
function local_kaltura_htm5_javascript_url($uiconf_id) {

    $host       = local_kaltura_get_host();
    $partner_id = local_kaltura_get_partner_id();

    return "{$host}/p/{$partner_id}/sp/{$partner_id}00/embedIframeJs/uiconf_id/{$uiconf_id}/partner_id/{$partner_id}";

}

/**
 * Retrives the enable html 5 flavour configuration option
 *
 * @param nothing
 *
 * @return string - 1 if enabled, else 0
 */
function local_kaltura_get_enable_html5() {
    return get_config(KALTURA_PLUGIN_NAME, 'enable_html5');
}

/**
 * This function saves standard video metadata
 *
 * @param obj - Kaltura connection object
 * @param int - Kaltura video id
 * @param array - array of properties to update (accepted keys/value
 * pairs 'name', 'tags', 'desc', 'catids')
 *
 * @return bool - true of successful or false
 */
function local_kaltura_update_video_metadata($connection, $entry_id, $param) {

    $media_entry = new KalturaMediaEntry();

    if (array_key_exists('name', $param)) {
        $media_entry->name = $param['name'];
    }

    if (array_key_exists('tags', $param)) {
        $media_entry->tags = $param['tags'];
    }

    if (array_key_exists('desc', $param)) {
        $media_entry->description = $param['desc'];
    }

    if (array_key_exists('catids', $param)) {
        $media_entry->categoriesIds = $param['catids'];
    }

    $result = $connection->media->update($entry_id, $media_entry);

    if (!$result instanceof KalturaMediaEntry) {
        return false;
    }

    return true;

}

/**
 * This function validates video objects.  Checks to see if the video is of a
 * video type "mix".  If so, then an API call is made to get all media entries
 * that make up the mix. If the mix contains one entry then only the one entry
 * is returned.  If the mix contains more than one entry then boolean true is
 * returned.  This function will one day become deprecated; but it for now it is
 * needed because of KALDEV-28
 *
 * @param object - entry object
 *
 *  @return boolean - true of the entry type is valid, false if invliad AND the
 * id parameter of the entry_object is overwritten and must be retrieve from the
 * kaltura server again
 *
 */
function local_kaltura_video_type_valid($entry_obj) {
    try {
        $client_obj = local_kaltura_login(true, '');

        if (empty($client_obj)) {
            throw new Exception('Invalid client object');
        }

        // If we encounter a entry of type "mix", we must find the regular "video" type and display that for playback
        if (KalturaEntryType::MIX == $entry_obj->type and
            0 >= $entry_obj->duration) {

            // This call returns an array of "video" type entries that exist in the "mix" entry
            $media_entries = $client_obj->mixing->getReadyMediaEntries($entry_obj->id);

            if (!empty($media_entries)) {

                if (count($media_entries) == 1) {
                    $entry_obj->id = $media_entries[0]->id;
                    return false;
                } else {
                    return true;
                }

            }
        }
    } catch (Exception $ex) {
        // Connection failed for some reason.  Maybe proxy settings?
        add_to_log(SITEID, 'local_kaltura', ' | convert to valid entry type', '', $ex->getMessage());
        return false;
    }

}

/**
 * This function deletes a video from the Kaltura server
 *
 * @param obj - Kaltura connection object
 * @param string - Kaltura video entry id
 *
 * @param bool - true of success, false
 */
function local_kaltura_delete_video($connection, $entry_id) {

    return $connection->media->delete($entry_id);
}

/**
 * This function determins whether Moodle is at 2.2 or newer.
 *
 * @param - none
 * @return bool - true if this version of Moodle is newer than Moodel 2.3rc1
 * otherwise false
 */
function local_kaltura_is_moodle_pre_twothree() {
    // Retrieve the release number from the config table
    $release = get_config('', 'release');

    // If version is empty for some reason, return false and hope it works out okay
    if (empty($release)) {
        return false;
    }

    // Parse the release number
    $release_num = substr($release, 0, 3);

    // If this version of Moodle is lerger then Moodle 2.3rc1
    if (0 == strcmp($release_num, '2.2')) {
        return true;
    } else if (0 == strcmp($release_num, '2.1')) {
        return true;
    } else if (0 == strcmp($release_num, '2.0')) {
        return true;
    } else {
        return false;
    }
}

/**
 * This function creates a client tag used to identify API requests to the
 * Kaltura server
 *
 * @return string - client tag
 */
function local_kaltura_create_client_tag() {

    $client_tag      = 'moodle';
    $release         = get_config('', 'release');
    $release_array   = explode(' ', $release);
    $kaltura_version = get_config(KALTURA_PLUGIN_NAME, 'version');

    if (!empty($release_array) && is_array($release_array)) {
        $client_tag .= '_' . $release_array[0];
    }

    $client_tag .= "_k_{$kaltura_version}";

    return $client_tag;

}

/**
 * This function returns HTML markup and javascript required to use kwidget to
 * embed the HTML5 video markup. As of KALDEV-201, this function is used
 * exclusively by the filter plug-in because of the issues faced by Moodle's
 * caching filtered text and the XHR loading of course section by the MyMobile
 * theme.
 *
 * @param obj - Kaltura video object
 * @param int - player ui_conf_id (optional).  If no value is specified the
 * default player will be used.
 * @param int - Moodle course id (optional).  This parameter is required in
 * order to generate Kaltura analytics data.
 * @param string - A kaltura session string
 * @param int - a unique identifier, this value is appented to 'kaltura_player_'
 * and is used as the id of the object tag
 *
 */
function local_kaltura_get_kwidget_code($entry_obj, $uiconf_id = 0, $courseid = 0, $session = '', $uid = 0) {

    if (!local_kaltura_is_valid_entry_object($entry_obj)) {
        return 'Unable to play video ('. $entry_obj->id . ') please contact your site administrator.';
    }

    if (0 == $uid) {
        $uid  = floor(microtime(true));
        $uid .= '_' . mt_rand();
    }

    $host               = local_kaltura_get_host();
    $flash_vars         = local_kaltura_get_kdp_flashvars($courseid, $session);
    $flash_vars         = explode('&amp;', $flash_vars);
    $kwidget_flashvar   = '';

    // Re-format the flashvars into javascript object properties
    foreach ($flash_vars as $var) {
        $pro_val = explode('=', $var);
        $kwidget_flashvar .= ",'". $pro_val[0] ."' : '". $pro_val[1] . "'";
    }


    if (empty($uiconf_id)) {
        $uiconf = local_kaltura_get_player_uiconf('player');
    } else {
        $uiconf = $uiconf_id;
    }

    $markup = "<div id=\"kaltura_player_{$uid}\" style=\"width:{$entry_obj->width}px;height:{$entry_obj->height}px;\">

        <span property=\"dc:description\" content=\"{$entry_obj->description}\"></span>
        <span property=\"media:title\" content=\"{$entry_obj->name}\"></span>
        <span property=\"media:width\" content=\"{$entry_obj->width}\"></span>
        <span property=\"media:height\" content=\"{$entry_obj->height}\"></span>

        </div>
        <script language=javascript>

            if (document.readyState === \"complete\") {
                local_kaltura_kwidget_{$entry_obj->id}();
            } else {
                window.addEventListener(\"onload\", function() { local_kaltura_kwidget_{$entry_obj->id}(); }, false);
                document.addEventListener(\"DOMContentLoaded\", function () { local_kaltura_kwidget_{$entry_obj->id}(); }, false);
            }


            function local_kaltura_kwidget_{$entry_obj->id}() {
                console.log('calling kwidget.embed for - kaltura_player_{$uid}');

                kWidget.embed({
                    'targetId': 'kaltura_player_{$uid}',
                    'wid': '_{$entry_obj->partnerId}',
                    'uiconf_id' : '{$uiconf_id}',
                    'entry_id'  : '{$entry_obj->id}',
                    'width'     : '{$entry_obj->width}',
                    'height'    : '{$entry_obj->height}',
                    'flashvars' :{
                        'externalInterfaceDisabled' : false,
                        'autoPlay' : false{$kwidget_flashvar}
                    }
                  });
            }
         </script>";

         return $markup;
}

/**
 * Connection Class
 */
class kaltura_connection {

    private static $connection  = null;
    private static $timeout     = 0;
    private static $timestarted = 0;

    /**
     * Constructor for Kaltura connection class.
     *
     * @param int $timeout Length of timeout for Kaltura session in minutes
     */
    public function __construct($timeout = KALTURA_SESSION_LENGTH) {
        self::$connection = local_kaltura_login(true, '', $timeout);
        if (!empty(self::$connection)) {
            self::$timestarted = time();
            self::$timeout = $timeout;
        }
    }

    /**
     * Get the connection object.  Pass true to renew the connection
     *
     * @param bool $renew true to renew the session if it has expired.  Otherwise
     * false. (OBSOLETE the connection is always renewed.  TODO: remove this parameter
     * from the function and areas where this method is referenced in all the plug-ins)
     * @param int $timeout seconds to keep the session alive, if zero is passed the
     * last time out value will be used
     * @return object A Kaltura KalturaClient object
     */
    public function get_connection($renew = true, $timeout = 0) {
        self::$connection = local_kaltura_login(true, '', $timeout);
        return self::$connection;
    }

    /**
     * Return the number of seconds the session is alive for
     * @param - none
     * @return int - number of seconds the session is set to live
     */
    public function get_timeout() {

        return self::$timeout;
    }

    /**
     * Return the time the session started
     * @param - none
     * @return int - unix timestamp
     */
    public function get_timestarted() {
        return self::$timestarted;
    }

    public function __destruct() {
        global $SESSION;

        $SESSION->kaltura_con             = serialize(self::$connection);
        $SESSION->kaltura_con_timeout     = self::$timeout;
        $SESSION->kaltura_con_timestarted = self::$timestarted;

    }

}

/**
 * Search for Moodle courses with the given query
 *
 * @param string $query The course to search for
 * @return mixed An array of Moodle courses on success; false, otherwise
 */
function search_course($query) {
    global $CFG, $DB;

    $courses = array();
    $limit = get_config(KALTURA_PLUGIN_NAME, 'search_courses_display_limit');

    if (empty($query)) {
        return $courses;
    }

    $sql = "SELECT id, fullname, shortname, idnumber
             FROM {$CFG->prefix}course
            WHERE (".$DB->sql_like('fullname', '?', false)."
               OR ".$DB->sql_like('shortname', '?', false).")
              AND id != 1
         ORDER BY fullname ASC";

    if (($records = $DB->get_records_sql($sql, array('%'.$query.'%', '%'.$query.'%'), 0, $limit)) === false) {
        return false;
    }

    foreach ($records as $crs) {
        $context = context_course::instance($crs->id);
        if (has_capability('local/kaltura:view_report', $context, null, true)) {
            $course = new stdclass;
            $course->id = $crs->id;
            $course->fullname = $crs->fullname;
            $course->idnumber = $crs->idnumber;
            $course->shortname = $crs->shortname;
            $courses[] = $course;
        }
    }

    return $courses;
}

/**
 * Returns a list of recently accessed Moodle courses
 *
 * @return mixed An array of courses on success; false, otherwise
 */
function recent_course_history_listing() {
    global $USER, $DB;

    $limit = get_config(KALTURA_PLUGIN_NAME, 'recent_courses_display_limit');
    $courses = array();

    // Get the most recently accessed courses by this user
    // NOTE: JOIN on course table to only get courses which currently still exist
    $sql = "SELECT ul.courseid AS course, ul.timeaccess
              FROM {user_lastaccess} ul
        INNER JOIN {course} c ON c.id = ul.courseid
             WHERE ul.userid = :userid
          ORDER BY ul.timeaccess DESC";

    if (($records = $DB->get_records_sql($sql, array('userid' => $USER->id), 0, $limit)) === false) {
        return false;
    }

    foreach ($records as $crs) {
        if ($DB->record_exists('course', array('id' => $crs->course))) {
            $context = context_course::instance($crs->course);
            if (has_capability('local/kaltura:view_report', $context, null, true)) {
                if ($course = $DB->get_record('course', array('id' => $crs->course), 'id, idnumber, fullname, shortname', IGNORE_MISSING)) {
                    $courses[] = $course;
                }
            }
        }
    }

    return $courses;
}

/**
 * This function determines whether a user has any local/kaltura:view_report capabilities on a course context.
 * This is a similar and more efficient implementation of the get_user_capability_course function.
 * Currently this is used to determine if the "Kaltura Course Media Reports" link gets displayed to the user.
 *
 * @return boolean Returns true if user has permission; otherwise, false
 */
function kaltura_course_report_view_permission() {
    global $DB, $USER;

    $sql = "SELECT context.id
              FROM {context} context
              JOIN {role_assignments} role_assign ON context.id = role_assign.contextid
              JOIN {role} role ON role_assign.roleid = role.id
              JOIN {role_capabilities} role_cap ON role_cap.roleid = role.id
             WHERE context.contextlevel = :context
               AND role_cap.capability = :capability
               AND role_assign.userid = :userid
               AND role_cap.permission = :permission";

    $params = array(
        'context' => CONTEXT_COURSE,
        'capability' => 'local/kaltura:view_report',
        'userid' => $USER->id,
        'permission' => CAP_ALLOW
    );

    if ($DB->record_exists_sql($sql, $params)) {
        return true;
    }

    return false;
}
