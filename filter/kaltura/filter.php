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
 * Automatic media embedding filter class.
 *
 * @package    filter_kaltura
 * @copyright  2013 onwards Remote-Learner {@link http://www.remote-learner.ca/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class filter_kaltura extends moodle_text_filter {

    // Static class variables are used to generate the same
    // user session string for all videos displayed on the page
    /** @var array $videos - an array of videos that have been rendered on a single page request */
    public static $videos    = array();

    /** @var string $ksession - holds the kaltura session string */
    public static $ksession = '';

    /** @var string $player - the player id used to render embedded video in */
    public static $player = '';

    /** @var int $courseid - the course id */
    public static $courseid = 0;

    /** @var bool $kalturamobilejsinit - flag to denote whether the mobile javascript has been initialized */
    public static $kalturamobilejsinit = false;

    /** @var bool $mobilethemeused - flag to denote whether the mobile theme is used */
    public static $mobilethemeused = false;

    /** @var int $playernumber - keeps a count of the number of players rendered on the page in a single page request */
    public static $playernumber = 0;

    /* @var bool $kalturalocal - indicates if local/kaltura has been installed */
    public static $kalturalocal = false;

    /**
     * This function runs once during a single page request and initialzies
     * some data.  This function also resolves KALDEV-201
     * @param stdClass $page - Moodle page object
     * @param stdClass $context - page context object
     * @return void
     */
    public function setup($page, $context) {
        global $CFG, $THEME;

        // Check if the local Kaltura plug-in exists.
        if (self::$kalturalocal === false) {
            if (file_exists($CFG->dirroot.'/local/kaltura/locallib.php')) {
                require_once($CFG->dirroot.'/local/kaltura/locallib.php');
                self::$kalturalocal = true;
            } else {
                // Leave
                return;
            }
        }

        // Determine if the mobile theme is being used
        $theme = get_selected_theme_for_device_type();

        if (0 == strcmp($theme, 'mymobile')) {
            self::$mobilethemeused = true;
        }


        if (empty(self::$kalturamobilejsinit)) {

            if (local_kaltura_has_mobile_flavor_enabled() && local_kaltura_get_enable_html5()) {

                $uiconf_id = local_kaltura_get_player_uiconf('player_filter');
                $js_url = new moodle_url(local_kaltura_htm5_javascript_url($uiconf_id));
                $js_url_frame = new moodle_url('/local/kaltura/js/frameapi.js');

                $page->requires->js($js_url, false);
                $page->requires->js($js_url_frame, false);

            }
            self::$kalturamobilejsinit = true;
        }
    }

    /**
     * This function does the work of converting text that matches a regular expression into
     * Kaltura video markup, so that links to Kaltura videos are displayed in the Kaltura
     * video player.
     * @param string $text - Text that is to be displayed on the page
     * @param array $options - an array of additional options
     * @return string - The same text or modified text is returned
     */
    function filter($text, array $options = array()) {
        global $CFG, $PAGE, $DB;

        // Check if the local Kaltura plug-in exists.
        if (!self::$kalturalocal) {
            return $text;
        }

        // Clear video list
        self::$videos = array();

        if (!is_string($text) or empty($text)) {
            // non string data can not be filtered anyway
            return $text;
        }

        if (stripos($text, '</a>') === false) {
            // performance shortcut - all regexes bellow end with the </a> tag, if not present nothing can match
            return $text;
        }

        // we need to return the original value if regex fails!
        $newtext = $text;

        if (!empty($CFG->filter_kaltura_enable)) {
            $uri = local_kaltura_get_host();
            $uri = rtrim($uri, '/');
            $uri = str_replace(array('.', '/', 'https'), array('\.', '\/', 'https?'), $uri);

            $search = '/<a\s[^>]*href="('.$uri.')\/index\.php\/kwidget\/wid\/_([0-9]+)\/uiconf_id\/([0-9]+)\/entry_id\/([\d]+_([a-z0-9]+))\/v\/flash"[^>]*>([^>]*)<\/a>/is';

            // Update the static array of videos, so that later on in the code we can create generate a viewing session for each video
            preg_replace_callback($search, 'update_video_list', $newtext);

            // Exit the function if the video entries array is empty
            if (empty(self::$videos)) {
                return $text;
            }

            // Get the filter player ui conf id
            if (empty(self::$player)) {
                self::$player = local_kaltura_get_player_uiconf('player_filter');
            }

            // Get the course id of the current context
            if (empty(self::$courseid)) {
                self::$courseid = get_courseid_from_context($PAGE->context);
            }

            try {
                // Create the the session for viewing of each video detected
                self::$ksession = local_kaltura_generate_kaltura_session(self::$videos);

                $kaltura    = new kaltura_connection();
                $connection = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);

                if (!$connection) {
                    throw new Exception("Unable to connect");
                }

                // Check if the repository plug-in exists.  Add Kaltura video to the Kaltura category
                $enabled  = local_kaltura_kaltura_repository_enabled();
                $category = false;

                if ($enabled) {
                    // Because the filter() method is called multiple times during a page request (once for every course section or once for every forum post),
                    // the Kaltura repository library file is included only if the repository plug-in is enabled.
                    require_once($CFG->dirroot.'/repository/kaltura/locallib.php');

                   // Create the course category
                   repository_kaltura_add_video_course_reference($connection, self::$courseid, self::$videos);
                }

                $newtext = preg_replace_callback($search, 'filter_kaltura_callback', $newtext);

            } catch (Exception $exp) {
                add_to_log(self::$courseid, 'filter_kaltura', 'Error embedding video', '', $exp->getMessage());
            }
        }

        if (empty($newtext) || $newtext === $text) {
            // error or not filtered
            unset($newtext);
            return $text;
        }

        return $newtext;

    }
}

/**
 * This functions adds the video entry id to a static array
 */
function update_video_list($link) {

    filter_kaltura::$videos[] = $link[4];
}

/**
 * Change links to Kaltura into embedded Kaltura videos
 *
 * Note: resizing via url is not supported, user can click the fullscreen button instead
 *
 * @param  array $link: an array of elements matching the regular expression from class filter_kaltura - filter()
 * @return string - Kaltura embed video markup
 */
function filter_kaltura_callback($link) {
    global $CFG, $PAGE;

    $entry_obj = local_kaltura_get_ready_entry_object($link[4], false);

    if (empty($entry_obj)) {
        return get_string('unable', 'filter_kaltura');
    }

    $config = get_config(KALTURA_PLUGIN_NAME);

    $width  = isset($config->filter_player_width) ? $config->filter_player_width : 0;
    $height = isset($config->filter_player_height) ? $config->filter_player_height : 0;

    // Set the embedded player width and height
    $entry_obj->width  = empty($width) ? $entry_obj->width : $width;
    $entry_obj->height = empty($height) ? $entry_obj->height : $height;

    // Generate player markup
    $markup = '';

    filter_kaltura::$playernumber++;
    $uid = filter_kaltura::$playernumber . '_' . mt_rand();

    if (!filter_kaltura::$mobilethemeused) {
        $markup  = local_kaltura_get_kdp_code($entry_obj, filter_kaltura::$player, filter_kaltura::$courseid, filter_kaltura::$ksession/*, $uid*/);
    } else {
        $markup  = local_kaltura_get_kwidget_code($entry_obj, filter_kaltura::$player, filter_kaltura::$courseid, filter_kaltura::$ksession/*, $uid*/);
    }

return <<<OET
$markup
OET;
}
