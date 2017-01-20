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
 * Refactored code from the Kaltura local plug-in directory. This script
 * has less code and performs an additional check for the video's custom
 * metadata fields.
 *
 * @package    local
 * @subpackage mymedia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/locallib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/repository/kaltura/locallib.php');

$entry_id   = required_param('entry_id', PARAM_TEXT);
$height     = optional_param('height', 0, PARAM_INT);
$width      = optional_param('width', 0, PARAM_INT);
$uiconfid   = optional_param('uiconf_id', 0, PARAM_INT);
$title      = optional_param('video_title', '', PARAM_TEXT);
$widget     = optional_param('widget', 'kdp', PARAM_TEXT);
$courseid   = required_param('courseid', PARAM_INT);

require_login();

$thumbnail    = '';
$data         = '';
$entry_obj    = null;

// If request is for a kaltura dynamic player get the entry object disregarding
// the entry object status
if (0 == strcmp($widget, 'kdp')) {

    $entry_obj = local_kaltura_get_ready_entry_object($entry_id, false);

    if (empty($entry_obj)) { // Sometimes the connection to Kaltura times out
        $data->markup = get_string('video_retrival_error', 'local_mymedia');
        die;
    }

    // Determine the type of video (See KALDEV-28)
    if (!local_kaltura_video_type_valid($entry_obj)) {
        $entry_obj = local_kaltura_get_ready_entry_object($entry_obj->id, false);
    }

    $entry_obj->height = !empty($height) ? $height : $entry_obj->height;
    $entry_obj->width = !empty($width) ? $width : $entry_obj->width;

    $data = $entry_obj;
    $data->course_share = '';
    $data->site_share   = '';

    // Retrieve the video's custom metadata TODO: Eventually use the connection object everywhere
    $kaltura = new kaltura_connection();
    $connection = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);

    list($site_share, $course_share) = repository_kaltura_format_video_custom_metadata($connection, $entry_obj->id);

    $data->course_share = $course_share;
    $data->site_share   = $site_share;

    if (KalturaEntryStatus::READY == (string) $entry_obj->status) {

        // Create the user KS session
        $session  = local_kaltura_generate_kaltura_session(array($entry_obj->id));

        $data->markup = local_kaltura_get_kdp_code($entry_obj, $uiconfid, $courseid, $session);

        if (local_kaltura_has_mobile_flavor_enabled() && local_kaltura_get_enable_html5()) {
            $data->script = 'kAddedScript = false; kCheckAddScript();';
        }

    } else {

        // Clear the cache
        KalturaStaticEntries::removeEntry($data->id);

        switch ((string) $entry_obj->status) {
            case KalturaEntryStatus::ERROR_IMPORTING:
                $data->markup = get_string('video_error', 'local_mymedia');
                break;
            case KalturaEntryStatus::ERROR_CONVERTING:
                $data->markup = get_string('video_error', 'local_mymedia');
                break;
            case KalturaEntryStatus::INFECTED:
                $data->markup = get_string('video_bad', 'local_mymedia');
                break;
            case KalturaEntryStatus::PRECONVERT:
            case KalturaEntryStatus::IMPORT:
                $data->markup = get_string('converting', 'local_mymedia');
        }

    }

}

$data = json_encode($data);

echo $data;

die();
