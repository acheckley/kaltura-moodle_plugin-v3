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
 * Saves information about the Kaltura video and returns a status
 *
 *
 * @package    local
 * @subpackage mymedia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/locallib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/repository/kaltura/locallib.php');

$entry_id   = required_param('entry_id', PARAM_TEXT);
$name       = required_param('name', PARAM_TEXT);
$tags       = required_param('tags', PARAM_TEXT);
$desc       = required_param('desc', PARAM_TEXT);
$gshare     = required_param('gshare', PARAM_INT);
$share      = required_param('share', PARAM_SEQUENCE);

require_login();

global $USER;

$context = get_context_instance(CONTEXT_USER, $USER->id);
require_capability('local/mymedia:view', $context, $USER);

// Explode the variable into an array
if (!empty($share)) {
    $share = explode(',', $share);
} else {
    $share = array();
}

if (empty($entry_id)) {
    add_to_log(SITEID, 'local_mymedia', 'update - video details', '', 'video entry id empty ' . $entry_id);
    echo 'n 1';
    die();
}

if (empty($name)) {
    echo 'n 2';
    die();
}

// Initialize video cache
$entries = new KalturaStaticEntries();

try {
    // Create a Kaltura connection object
    $client_obj = local_kaltura_login(true, '');

    if (!$client_obj) {
        add_to_log(SITEID, 'local_mymedia', 'view - connection failed', '', 'Connection failed when saving');
        echo 'n 3';
    }

    // Start a multi request
    $client_obj->startMultiRequest();

    // Get the entry object
    $client_obj->media->get($entry_id);

    // Create KalturaMediaEntry object with new properties
    $media_entry = new KalturaMediaEntry();

    if (has_capability('local/mymedia:editmetadata', $context, $USER)) {
        $media_entry->name        = $name;
        $media_entry->tags        = $tags;
        $media_entry->description = $desc;

    } else {
        $media_entry->name        = '{1:result:name}';
        $media_entry->tags        = '{1:result:tags}';
        $media_entry->description = '{1:result:description}';
    }

    $client_obj->media->update('{1:result:id}', $media_entry);

    // Get the custom metadata profile id from the repository configuration setting
    $metadata_profile_id = get_config(REPOSITORY_KALTURA_PLUGIN_NAME, 'metadata_profile_id');

    // Verify the repository plug-in is configured correctly
    if (!$metadata_profile_id) {
        add_to_log(SITEID, 'local_mymedia', 'view - metadata profile id', '', 'Kaltura repo not set up properly');
        echo 'n 4';
        die();
    }

    // Retrieve the video's custom metadata
    $meta_filter = new KalturaMetadataFilter();
    $meta_filter->metadataObjectTypeEqual = KalturaMetadataObjectType::ENTRY;
    $meta_filter->objectIdEqual = '{2:result:id}';
    $meta_filter->metadataProfileIdEqual = $metadata_profile_id;

    $client_obj->metadata->listaction($meta_filter);

    $result = $client_obj->doMultiRequest();

    // Clear the cache
    KalturaStaticEntries::removeEntry($entry_id);

    // Verify returned data
    if (!is_array($result)) {
        add_to_log(SITEID, 'local_mymedia', 'view - connection failed', '', 'Connection failed when saving');
        echo 'n 5';
        die();
    }

    // Verify the first API call
    if (!array_key_exists(0, $result) || !$result[0] instanceof KalturaMediaEntry) {
        add_to_log(SITEID, 'local_mymedia', 'view - media->get', '', $result[0]['message']);
        echo 'n 6';
        die();
    }

    if (0 != strcmp($result[0]->userId, $USER->username)) { // Verify that the user is the owner of the requested video
        add_to_log(SITEID, 'local_mymedia', 'update - video details', '', 'User is not the owner of video');
        echo 'n 7';
        die();
    }

    if (!array_key_exists(1, $result) || !$result[1] instanceof KalturaMediaEntry) {
        add_to_log(SITEID, 'local_mymedia', 'update - media->update', '', $result[1]['message']);
        echo 'n 8';
        die();
    }

    if (!array_key_exists(2, $result) || !$result[2] instanceof KalturaMetadataListResponse) {
        add_to_log(SITEID, 'local_mymedia', 'update - metadata->listaction', '', $result[2]['message']);
        echo 'n 9';
    }

    // Check if the user has the required capabilities
    $course_share = has_capability('local/mymedia:sharecourse', $context, $USER);
    $site_share   = has_capability('local/mymedia:sharesite', $context, $USER);

    if ($course_share || $site_share) {

        // Verify one last time that the user is enrolled in the courses they are sharing with
        $courses    = enrol_get_my_courses(array('id','fullname'), 'visible DESC,sortorder ASC');

        $course_ids = array_keys($courses);
        $share      = array_intersect($share, $course_ids);


        $metadata   = false;         // Metadata return variable
        $final_xml  = '<metadata>';  // Set default values for xml

        // Create site share XML schema
        if ($site_share) {
            $final_xml .= repository_kaltura_create_site_share_metadata_xml($gshare);
        }

        // Create course share XML schema
        if ($course_share) {
            $final_xml .= repository_kaltura_create_course_share_metadata_xml($share);
        }

        $final_xml .= '</metadata>';

        // If total count is zero then we must use an API call to create metadata for the first time,
        // otherwise we perform a metadata update call
        if (0  == $result[2]->totalCount) {

            $object_type = KalturaMetadataObjectType::ENTRY;
            $metadata = $client_obj->metadata->add($metadata_profile_id, $object_type, $result[1]->id, $final_xml);
        } else {

            $metadata = $client_obj->metadata->update($result[2]->objects[0]->id, $final_xml);
        }

        if (!$metadata instanceof KalturaMetadata) {
            add_to_log(SITEID, 'local_mymedia', 'update - metadata->update/add', '', 'Error adding/updating custom metadata');
            echo 'n 10';
            die();
        }

    }

    // Only cache the entry if the status is equal to ready
    if (KalturaEntryStatus::READY == $result[1]->status) {
        KalturaStaticEntries::addEntryObject($result[1]);
    }

    echo 'y';

} catch (Exception $exp) {

    add_to_log(SITEID, 'local_mymedia', 'Error - exception caught', '', $exp->getMessage());
    echo 'n';
}

die();