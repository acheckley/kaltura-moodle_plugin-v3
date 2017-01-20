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
 * Kaltura Repository local library
 *
 * @package    Repository
 * @subpackage Kaltura
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/locallib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/API/KalturaPlugins/KalturaMetadataClientPlugin.php');

// Testing....
//require_once($CFG->dirroot . '/blocks/firephp/FirePHPCore/FirePHP.class.php');
//$firephp = FirePHP::getInstance(TRUE);
//$firephp->warn($path, 'PATH');


//define('REPOSITORY_KALTURA_PLUGIN_NAME', 'repository_kaltura');
define('REPOSITORY_KALTURA_PLUGIN_NAME', 'kaltura'); // Moodle saves repository instance configuration variables without the repository_ prefix
define('REPOSITORY_KALTURA_PROFILE_NAME', 'Moodle Repository Profile');
define('REPOSITORY_KALTURA_PROFILE_SYSTEM_NAME', 'moodleprofile');

define('REPOSITORY_KALTURA_SYSTEM_SHARE', 'SystemShare');
define('REPOSITORY_KALTURA_COURSE_SHARE', 'CourseShare');

define('REPOSITORY_KALTURA_SHARED_PATH', '/shared');
define('REPOSITORY_KALTURA_USED_PATH', '/used');
define('REPOSITORY_KALTURA_SITE_SHARED_PATH', '/siteshare');


/**
 * Get metadata profile information
 *
 * @param - none
 * @param mixed - string with profile information or false
 */
function repository_kaltura_get_metadata_profile_info($connection) {

    // Get the profile object
    $profile = repository_kaltura_get_metadata_profile($connection);

    if (!$profile) {
        return false;
    }

    $profile = repository_kaltura_format_metadata_profile($profile);

    return $profile;

}

/**
 * Get retrieve metadata profile
 *
 * @param - Kaltura connection object
 * @return mixed - KalturaMetadataProfile if profile was found, false if one
 * wasn't found
 */
function repository_kaltura_get_metadata_profile($connection) {
    global $DB;

    $profileid = get_config(REPOSITORY_KALTURA_PLUGIN_NAME, 'metadata_profile_id');
    $profile = false;

    // Get the saved profile id
    if ($profileid) {

        // Search for a profile by Id
        $filter = new KalturaMetadataProfileFilter();
        $filter->idEqual = $profileid;
        $profile = $connection->metadataProfile->listAction($filter);

        if (!$profile instanceof KalturaMetadataProfileListResponse || 0 == $profile->totalCount) {
            // Something happened with the metadata profile id.  Remove it.
            $param = array('plugin' => 'kaltura', 'name' => 'metadata_profile_id');
            $DB->delete_records('config_plugins', $param);
            return false;
        }

        $profile = $connection->metadataProfile->get($profileid);
    } else {

        // Search for a profile by Name
        $filter = new KalturaMetadataProfileFilter();
        $filter->orderBy = 'CREATED_AT_ASC';
        $filter->nameEqual = REPOSITORY_KALTURA_PROFILE_NAME;

        $profile_list = $connection->metadataProfile->listAction($filter);

        if (!$profile_list instanceof KalturaMetadataProfileListResponse) {
            return false;
        }

        // Validate the profile
        foreach ($profile_list->objects as $key => $data) {

            $profile = repository_kaltura_validate_metadata_profile($connection, $data);

            // If validation passed use the profile
            if (false !== $profile) {
                break;
            }
        }


    }

    if (!$profile instanceof KalturaMetadataProfile) {
        return false;
    }

    return $profile;
}

/**
 * This function returns the fields definition of the metadata profile
 *
 * @param obj - Kaltura connection object
 * @param int - metadata profile id
 * @return mixed - a KalturaMetadataProfileFieldListResponse object or false
 *
 */
function repository_kaltura_get_metadata_profile_fields($connection, $profileid) {
    $profile_fields = $connection->metadataProfile->listFields($profileid);

    if (!$profile_fields instanceof KalturaMetadataProfileFieldListResponse &&
        0 < $profile_fields->totalCount) {
        return false;
    }

    return $profile_fields;
}


/**
 * This function returns the xPath string of the the share field in the metadata
 * profile.  The 3rd parameter must be either 'CourseShare' or 'SystemShare'.
 *
 * @param obj - Kaltura connection object
 * @param int - KalturaMetadataProfile id, or false if something went wrong
 * @param string - pass either 'CourseShare' or 'SystemShare'
 *
 * @return mixed - xPath of field or false if it wasn't found
 */
function repository_kaltura_get_metadata_share_field_path($connection, $profile_id, $field_key) {


    if (0 != strcmp('SystemShare', $field_key) &&
        0 != strcmp('CourseShare', $field_key)) {
            return false;
    }

    $fields = repository_kaltura_get_metadata_profile_fields($connection, $profile_id);
    $xpath = false;


    foreach ($fields->objects as $field) {

        if (0 == strcmp($field_key, $field->key)) {
            $xpath = $field->xPath;
            break;
        }
    }

    return $xpath;
}

/**
 * Creates a new metadata profile in the Kaltura account
 * @param - Kaltura connection object
 * @return mixed - KalturaMetadataProfileListResponse
 */
function repository_kaltura_create_metadata_profile($connection) {

    require_once(dirname(__FILE__) . '/xsd_schema.php');

    $profile = new KalturaMetadataProfile();
    $profile->name = REPOSITORY_KALTURA_PROFILE_NAME;
    $profile->description = REPOSITORY_KALTURA_PROFILE_NAME . ' Do not remove.';
    $profile->systemName = REPOSITORY_KALTURA_PROFILE_SYSTEM_NAME;

    $profile->createMode = KalturaMetadataProfileCreateMode::API;
    $profile->metadataObjectType = KalturaMetadataObjectType::ENTRY;

    $result = $connection->metadataProfile->add($profile, $schema);

    if ($result instanceof KalturaMetadataProfile) {
        return $result->id;
    } else {
        return false;
    }

}

/**
 * Validate the profile fields names
 * @param obj - Katura connection object
 * @param obj - KalturaMetadataProfile object
 * @return mixed - KalturaMetadataProfile if validation passed, false if
 * validation failed
 */
function repository_kaltura_validate_metadata_profile($connection, $profile) {

    if (!$profile instanceof KalturaMetadataProfile) {
        return false;
    }

    $profile_detail = $connection->metadataProfile->listFields($profile->id);

    if ($profile_detail instanceof KalturaMetadataProfileFieldListResponse) {
        $condition = strcmp($profile_detail->objects[0]->key, 'SystemShare');
        $condition2 = strcmp($profile_detail->objects[1]->key, 'CourseShare');

        if (0 == $condition &&
            0 == $condition2) {

            return $profile;
        }
    } else {
        return false;
    }
}

/** Format the metadata profile object into a nice string of information
 *
 * @param obj - KalturaMetadataProfile
 * @return string - information about the profile being used
 */
function repository_kaltura_format_metadata_profile($profile) {
    if (!$profile instanceof KalturaMetadataProfile) {
        return 'Error formatting metadata profile information';
    }

    $info = new stdClass();
    $info->profileid = $profile->id;
    $info->profilename = $profile->name;
    $info->created = userdate($profile->createdAt);
    $output = get_string('metadata_profile_info', 'repository_kaltura', $info);

    return $output;
}


/**
 * Create the root category structure in the KMC
 *
 * @param - Kaltura connection object
 * @return mixed - an array with the root category path and the root category id
 *  or false if something wrong happened
 */
function repository_kaltura_create_root_category($connection) {

    $first            = true;
    $parent_id        = '';
    $categories       = null;
    $root_category    = get_config(REPOSITORY_KALTURA_PLUGIN_NAME, 'rootcategory');
    $root_category_id = get_config(REPOSITORY_KALTURA_PLUGIN_NAME, 'rootcategory_id');
    $duplicate        = false;

    // Split categories into an array
    if (!empty($root_category)) {
        $categories = explode('>', $root_category);
    }

    $category_to_created = '';

    // Check if categories already exist in the KMC
    foreach ($categories as $category_name) {

        if (empty($category_to_created)) {
            $category_to_created = $category_name;
        } else {
            $category_to_created = $category_to_created . '>' . $category_name;
        }

        // Check if the category already exists.  If any exists then we cannot create the category
        if (repository_kaltura_category_path_exists($connection, $category_to_created)) {
            $duplicate = true;
            break;
        }
    }

    // If thre is a duplicate, the user must specify a different root category format
    if ($duplicate) {
        return false;
    }

    // Create categories
    foreach ($categories as $category_name) {

        if ($first) {
            $result = repository_kaltura_create_category($connection, $category_name);
        } else {
            $result = repository_kaltura_create_category($connection, $category_name, $parent_id);
        }

        if (!empty($result)) {

            if ($first) {
                $root_category = $result->name;
            } else {
                $root_category .= '>' . $result->name;
            }

            $first         = false;
            $parent_id     = $result->id;
        }
    }

    // Save configuration
    set_config('rootcategory', $root_category, REPOSITORY_KALTURA_PLUGIN_NAME);
    set_config('rootcategory_id', $result->id, REPOSITORY_KALTURA_PLUGIN_NAME);

    return array($root_category, $result->id);
}

/**
 * Create a category in the KMC.  If a perent id is passed then the category
 * will be created as a sub category of the parent id
 *
 * @param obj - Kaltura connection object
 * @param string - category name
 * @param int - (optional) parent id
 * @return mixed - KalturaCategory if category was created, otherwise false
 */
 function repository_kaltura_create_category($connection, $name, $parent_id = 0) {

    if (empty($name)) {
        return false;
    }

    $category = new KalturaCategory();
    $category->name = $name;

    if (!empty($parent_id)) {
        $category->parentId = $parent_id;
    }

    $result = $connection->category->add($category);

    if ($result instanceof KalturaCategory) {
        return $result;
    }

    return false;
}

/**
 * Checks to see if a category with the same name exists.  If parent_id is
 * passed it checks to see a category with the same name and parent id exists
 * @param obj - Kaltura connection object
 * @param string - category name
 * @param int - (optional) parent id
 * @return bool - true if category exists, otherwise false
 */
function repository_kaltura_category_exists($connection, $name, $parent_id = 0) {
    // TODO: is this function still needed?
}

/**
 * Checks if a specific category has a matching fullName value
 * @param obj - Kaltura connection object
 * @param int - category id
 * @param string - category fullName path
 * @return bool - true if category with fullName path exists. Else false
 */
function repository_kaltura_category_id_path_exists($connection, $category_id, $path) {
    if (empty($path) || empty($category_id)) {
        return false;
    }

    $filter = new KalturaCategoryFilter();
    $filter->fullNameEqual = $path;
    $filter->idEqual = $category_id;
    $result = $connection->category->listAction($filter);

    if ($result instanceof KalturaCategoryListResponse &&
        1 == $result->totalCount) {

        if ($result->objects[0] instanceof KalturaCategory) {
            return $result->objects[0];
        }
    }

    return false;

}

/**
 * Checks if a category path exists, if path exists then it returns the
 * a KalturaCategory object.  Otherwise false.  The API does not allow searching
 * for categories (using the 'category' service) by name
 *
 * @param kaltura_connection $connection An instance of the kaltura_connection class
 * @param string $path The Kaltura repository root path
 * @return bool|KalturaCategory - A KalturaCategory if path exists, otherwise false
 */
function repository_kaltura_category_path_exists($connection, $path) {
    global $SESSION;

    if (empty($path)) {
        return false;
    }

    // Retrieve access data from the session global
    if (isset($SESSION->kalrepo)) {

        if (isset($SESSION->kalrepo["$path"]) && !empty($SESSION->kalrepo["$path"])) {
            // Gonen - 2nd change - unserialize the found object before returning.
            $obj = unserialize($SESSION->kalrepo["$path"]);
            if($obj !== false) {
                return $obj;
            }
        }
    } else {
        $SESSION->kalrepo = array(array());
    }

    $filter = new KalturaCategoryFilter();
    $filter->fullNameEqual = $path;
    $result = $connection->category->listAction($filter);

    // "<=" temp solution to KALDEV-401
    if ($result instanceof KalturaCategoryListResponse &&
        1 <= $result->totalCount) {

        if ($result->objects[0] instanceof KalturaCategory) {
            // Gonen - 1st change - save serialized object to session.
            $SESSION->kalrepo["$path"] = serialize($result->objects[0]);
            return $result->objects[0];
        }
    }

    return false;
}


/**
 * This function creates a Kaltura category, if one doesn't exist, whose name is
 * the Moodle course id; and returns the category
 *
 * @param obj - Kaltura connection object
 * @param int - Moodle course id
 *
 * @return KalturaCategory object, or false if it failed to create one
 */
function repository_kaltura_create_course_category($connection, $courseid) {

    // Get the root category path
    $root_path = get_config(REPOSITORY_KALTURA_PLUGIN_NAME, 'rootcategory');

    // Check if the root category path is an empty string
    if (empty($root_path)) {
        return false;
    }
    $path = $root_path . '>' . $courseid;


    // Check if the category exists
    $course_category = repository_kaltura_category_path_exists($connection, $path);

    if (!$course_category) {

        $root_id = get_config(REPOSITORY_KALTURA_PLUGIN_NAME, 'rootcategory_id');

        // Create category
        $course_category = repository_kaltura_create_category($connection, $courseid, $root_id);

        if (!$course_category) {
            return false;
        }
    }

    return $course_category;
}

/**
 * TODO: NEED TO REFACTOR THIS IN A BAD WAY
 *
 * Build a list of courses that the user can view based on their capability
 *
 * @param string - capability to build access list for
 *
 * @return array - array of courses that the user has access to for the given
 * capability.  Or an empty array if they have no access
 */
function repository_kaltura_get_course_access_list($capability = '') {
    global $DB, $SESSION, $USER;

    // Retrieve access data from the session global
    if (isset($SESSION->kalrepo)) {

        if (array_key_exists($capability, $SESSION->kalrepo) &&
            array_key_exists($USER->id, $SESSION->kalrepo[$capability])) {

            return $SESSION->kalrepo[$capability][$USER->id];
        }

    } else {
        $SESSION->kalrepo = array(array());
    }

    $user_role_access = repository_kaltura_get_user_kaltura_repo_access($USER->id, $capability);

    // Find roles that have this capability in the system context
    $roles_with_cap = get_roles_with_caps_in_context(get_context_instance(CONTEXT_SYSTEM), array($capability));

    $courses       = array();
    $final_courses = array();

    foreach ($user_role_access['ra'] as $role_assign_context_path => $roles_array) {

        $context_path           = $role_assign_context_path;
        $context_path           = explode('/', $context_path);
        $role_assign_context_id = end($context_path);
        $role_assign_context    = get_context_instance_by_id($role_assign_context_id);

        // Check if the user has a role assignment with the capability set to allowed
        $user_role_with_cap = array_intersect($roles_array, $roles_with_cap);

        if (!empty($user_role_with_cap)) {

            foreach ($user_role_with_cap as $roleid) {

                // Get all courses under the context and create a list of courses the user can search from
                $courses = repository_kaltura_get_all_courses_in_context($role_assign_context_id);

                // add arrays to avoid re-indexing array
                $final_courses = $final_courses + $courses;
            }

            // Traverse through the capability definitions (allow/prevent) across all roles and contexts on the site
            foreach ($user_role_access['rdef'] as $rdef_context_role_path => $permission) {

                $role_delimiter_pos = strpos($rdef_context_role_path, ':');
                $context_path = substr($rdef_context_role_path, 0, $role_delimiter_pos);

                $rdef_path_roleid = substr($rdef_context_role_path, $role_delimiter_pos + 1);

                // Make sure we only look at overrides that apply to the user's roles
                if (!array_key_exists($rdef_path_roleid, $user_role_with_cap)) {
                    continue;
                }

                // If an 'prevent' override occurs within a similar context path as the role assignment context path
                // Retrieve all the courses under that context and mark them as requiring a has_capability() check
                if (-1 == current($permission)) {

                    // Find all courses within this context
                    $sql_like = $DB->sql_like('ctx.path', ':path');
                    $params = array('path' => $context_path. '%');

                    $sql = "SELECT ctx.instanceid ".
                           "  FROM {context} ctx ".
                           "  JOIN {course} c ON c.id = ctx.instanceid ".
                           "  WHERE ctx.contextlevel = ".CONTEXT_COURSE.
                           "   AND {$sql_like}";

                    $check_courses = $DB->get_records_sql($sql, $params);

                    if (empty($check_courses)) {
                        continue;
                    }

                    // Verify that the returned course exists within the list of final courses
                    foreach ($check_courses as $courseid => $course) {

                        if (array_key_exists($courseid, $final_courses)) {

                            // Flag the course to have it's capability checked at the course level
                            $course_context = get_context_instance(CONTEXT_COURSE, $courseid);

                            if (!has_capability($capability, $course_context)) {
                                unset($final_courses[$courseid]);
                            }
                        }
                    }
                }
            }

        } else {
            // This means that the role at the system level was set to prevent.  However, we know
            // the user has this capability otherwise this function would not have been called.  So we enter the
            // worst case scenario. Get the course(s) in the context of this role assignment.  Mark each course for a has_capability check.

            foreach ($roles_array as $roleid) {

                $courses = repository_kaltura_get_all_courses_in_context($role_assign_context_id);

                // add arrays to avoid re-indexing array
                $final_courses = $final_courses + $courses;

            }

            // Flag all course(s) to have it's capability checked at the course level
            foreach ($final_courses as $id => $data) {

                // Flag the course to have it's capability checked at the course level
                $course_context = get_context_instance(CONTEXT_COURSE, $id);

                if (!has_capability($capability, $course_context)) {
                    unset($final_courses[$id]);
                }
            }

        }
    }

    // Remove the site course id
    if (array_key_exists(1, $final_courses)) {
        unset($final_courses[1]);
    }

    // Save the user access structure for the session
    return $SESSION->kalrepo[$capability][$USER->id] = $final_courses;

}

function repository_kaltura_get_all_courses_in_context($context_id) {
    global $DB;

    $context = get_context_instance_by_id($context_id);
    $result  = array();
    $records = array();

    switch($context->contextlevel) {
        case CONTEXT_SYSTEM:
        case CONTEXT_COURSECAT:
            // Retrieve all courses under the current category
            $sql_like = $DB->sql_like('ctx.path', ':path');
            $params = array('path' => $context->path. '%');

            $sql = "SELECT ctx.*, c.visible ".
                   "   FROM {context} ctx ".
                   "  JOIN {course} c ON c.id = ctx.instanceid ".
                   "  WHERE ctx.contextlevel = ".CONTEXT_COURSE.
                   "   AND {$sql_like}";

            $records = $DB->get_records_sql($sql, $params);

            if (empty($records)) {
                $records = array();
            }
            break;
        case CONTEXT_COURSE:
            $param = array('id' => $context->instanceid);
            $record = $DB->get_record('course', $param, 'id,visible');

            if (!empty($record)) {

                $rec = new stdClass();
                $rec->id = $record->id;
                $rec->visible = $record->visible;

                $result[$rec->id] = $rec;
            }
            break;
    }


    foreach ($records as $context_id => $data) {
        $rec = new stdClass();
        $rec->id = $data->instanceid;
        $rec->visible = $data->visible;

        $result[$data->instanceid] = $rec;
    }

    return $result;
}

/**
 * Return a nested array showing role assignments
 * all relevant role capabilities for the user at
 * site/course_category/course levels
 *
 * We do _not_ delve deeper than courses because the number of
 * overrides at the module/block levels is HUGE.
 *
 * [ra]   => [/path/][]=roleid
 * [rdef] => [/path/:roleid][capability]=permission
 * [loaded] => array('/path', '/path')
 *
 * @param int $userid - the id of the user
 * @return array
 */
function repository_kaltura_get_user_kaltura_repo_access($userid, $capability) {
    global $CFG, $DB;

    /* Get in 3 cheap DB queries...
     * - role assignments
     * - relevant role caps
     *   - above and within this user's RAs
     *   - below this user's RAs - limited to course level
     */

    $accessdata = array(); // named list
    $accessdata['ra']     = array();
    $accessdata['rdef']   = array();

    //
    // Role assignments
    //
    $sql = "SELECT ctx.path, ra.roleid
              FROM {role_assignments} ra
              JOIN {context} ctx ON ctx.id=ra.contextid
              JOIN {role_capabilities} rc ON rc.roleid = ra.roleid
             WHERE ra.userid = ?
               AND ctx.contextlevel <= ".CONTEXT_COURSE;
    $params = array($userid);
    $rs = $DB->get_recordset_sql($sql, $params);

    //
    // raparents collects paths & roles we need to walk up
    // the parenthood to build the rdef
    //
    $raparents = array();
    if ($rs) {
        foreach ($rs as $ra) {
            // RAs leafs are arrays to support multi
            // role assignments...
            if (!isset($accessdata['ra'][$ra->path])) {
                $accessdata['ra'][$ra->path] = array();
            }
            $accessdata['ra'][$ra->path][$ra->roleid] = $ra->roleid;

            // Concatenate as string the whole path (all related context)
            // for this role. This is damn faster than using array_merge()
            // Will unique them later
            if (isset($raparents[$ra->roleid])) {
                $raparents[$ra->roleid] .= $ra->path;
            } else {
                $raparents[$ra->roleid] = $ra->path;
            }
        }
        unset($ra);
        $rs->close();
    }

    // Walk up the tree to grab all the roledefs
    // of interest to our user...
    //
    // NOTE: we use a series of IN clauses here - which
    // might explode on huge sites with very convoluted nesting of
    // categories... - extremely unlikely that the number of categories
    // and roletypes is so large that we hit the limits of IN()
    $clauses = '';
    $cparams = array();
    foreach ($raparents as $roleid=>$strcontexts) {
        $contexts = implode(',', array_unique(explode('/', trim($strcontexts, '/'))));
        if ($contexts ==! '') {
            if ($clauses) {
                $clauses .= ' OR ';
            }
            $clauses .= "(roleid=? AND contextid IN ($contexts))";
            $cparams[] = $roleid;
        }
    }

    if ($clauses !== '') {
        $sql = "SELECT ctx.path, rc.roleid, rc.capability, rc.permission
                  FROM {role_capabilities} rc
                  JOIN {context} ctx ON rc.contextid=ctx.id
                 WHERE ($clauses) AND rc.capability = ?";

        $cparams[] = $capability;

        unset($clauses);
        $rs = $DB->get_recordset_sql($sql, $cparams);


        if ($rs) {
            foreach ($rs as $rd) {
                $k = "{$rd->path}:{$rd->roleid}";
                $accessdata['rdef'][$k][$rd->capability] = $rd->permission;
            }
            unset($rd);
            $rs->close();
        }
    }

    //
    // Overrides for the role assignments IN SUBCONTEXTS
    // (though we still do _not_ go below the course level.
    //
    // NOTE that the JOIN w sctx is with 3-way triangulation to
    // catch overrides to the applicable role in any subcontext, based
    // on the path field of the parent.
    //
    $sql = "SELECT sctx.path, ra.roleid,
                   ctx.path AS parentpath,
                   rco.capability, rco.permission
              FROM {role_assignments} ra
              JOIN {context} ctx
                   ON ra.contextid=ctx.id
              JOIN {context} sctx
                   ON (sctx.path LIKE " . $DB->sql_concat('ctx.path',"'/%'"). " )
              JOIN {role_capabilities} rco
                   ON (rco.roleid=ra.roleid AND rco.contextid=sctx.id)
             WHERE ra.userid = ?
                   AND ctx.contextlevel <= ".CONTEXT_COURSECAT."
                   AND sctx.contextlevel <= ".CONTEXT_COURSE."
                   AND rco.capability = ?
          ORDER BY sctx.depth, sctx.path, ra.roleid";

    $params = array($userid, $capability);

    $rs = $DB->get_recordset_sql($sql, $params);
    if ($rs) {
        foreach ($rs as $rd) {
            $k = "{$rd->path}:{$rd->roleid}";
            $accessdata['rdef'][$k][$rd->capability] = $rd->permission;
        }
        unset($rd);
        $rs->close();
    }
    return $accessdata;
}


/**
 * This function formats the returned data from a search to a format the Moodle
 * repo plug-in accepts
 *
 * @param KalturaMediaListResponse - array of video objects
 * @param string - Kaltura connection URI
 * @param int - Kaltura partner id
 * @param string - player UI Conf Id
 *
 * @return array - structured repository video entity
 */
function repository_kaltura_format_data($video_data, $uri, $partner_id, $uiconf_id) {

    $results     = array();
    $name        = '';

    if (empty($video_data)) {
        return $results;
    }

    foreach ($video_data->objects as $video) {

        $source = '';

        // the /v/flash is required in order to trick the TinyMCE popup and force it to display flash
        switch ($video->mediaType) {
            case KalturaMediaType::AUDIO:  // May need a special case to handle audio files
            case KalturaMediaType::VIDEO:
                $name = $video->name . '.avi'; // Manually adding an image extension.  This is only to force moodle to display the correct icons
                $source = $uri .'/index.php/kwidget/wid/_'.$partner_id.
                          '/uiconf_id/'.$uiconf_id.'/entry_id/' . $video->id . '/v/flash#'.
                          $video->name;
                break;

            case KalturaMediaType::IMAGE:
                $name = $video->name . '.png'; // Manually adding an image extension.  This is only to force moodle to display the correct icons
                $source = $video->thumbnailUrl . '/height/200/width/300/type/1/v/flash#'. $video->name;
                break;
            default:
                $name   = 'Unknown Media Type';
                $source = 'Unknown Media Type';

        }

        $results[] = array('title' => $name,
                           'shorttitle' => $video->name,
                           'date' => userdate($video->updatedAt),
                           'thumbnail' => $video->thumbnailUrl,
                           'thumbnail_width' => 150,
                           'thumbnail_height' => 70,
                           'source' =>  $source,
                           'hasauthor' => true,
                           //'url' => '',
                           'haslicense' => true
                            );

    }

    return $results;
}

/**
 * This function creates and returns a KalturaFilterPager object.  If a page
 * index greater than 0 is passed, the pager object will be created with a
 * pageIndex equal to the parameter.  The size of the page is determined by the
 * itemsperpage plug-in configuration setting.
 *
 * @param int - pager index value
 * @param int - number of items to display on a page (optinal override)
 *
 * @return KalturaFilterPager obj
 */
function repository_kaltura_create_pager($page_index = 0, $items_per_page = 0) {

    $page = new KalturaFilterPager();

    if (empty($items_per_page)) {
        $page->pageSize = get_config(REPOSITORY_KALTURA_PLUGIN_NAME, 'itemsperpage');
    } else {
        $page->pageSize = $items_per_page;
    }

    if (0 <= (int) $page_index) {
        $page->pageIndex = '+' . (int) $page_index;
    } else {
        $page->pageIndex = 0;
    }

    return $page;

}

/**
 * This function creates an array of course folders to display when browsing the
 * repository plug-in
 *
 * @param array - an array of Moodle course ids
 * @param string - prefix for the course path.
 */
function repository_kaltura_create_courses_folders($courses, $path_prefix) {
    global $OUTPUT, $DB;

    $results       = array();
    $course_access = implode(',', array_keys($courses));

    $sql = "SELECT id, fullname, shortname".
           "  FROM {course} ".
           "  WHERE id IN ($course_access) ".
           "  ORDER BY fullname ASC ";

    $params = array();
    $records = $DB->get_records_sql($sql, null);

    if (empty($records)) {
        return array();
    }

    foreach ($records as $courseid => $data) {
        $results[] = repository_kaltura_create_folder($data->fullname, $data->shortname, "{$path_prefix}/{$data->shortname}");
    }

    return $results;
}

/**
 * This function builds the listing for users who have system and shared access.
 * If the user has both capabilities the default view will consist of two
 * folders (system and shared).  Within each folder will be a folder of courses
 * that the user has access to for the given context (system or shared).
 *
 * The path is also build for the navigation crumb
 *
 * @param array - repository file picker structure
 * @param string - navigation path crumb trail
 * @param array - array of Moodle course ids the user has system access to (key
 * is the course id)
 * @param array - array of Moodle course ids the user has shared access to (key
 * is the course id)
 * @param int - page to display
 *
 * @return array - repository file picker structure
 */
function repository_kaltura_get_system_shared_listing($ret, $path, $system_access, $shared_access, $page = 1) {

    $newpath = array();
    $listing = array();

    // If the user is in the root folder
    if (empty($path)) {

        $newpath[] = array('name' => get_string('crumb_home', 'repository_kaltura'), 'path' => '');

        $name       = get_string('folder_site_shared_videos', 'repository_kaltura');
        $short_name = get_string('folder_site_shared_videos_shortname', 'repository_kaltura');
        $listing[]  = repository_kaltura_create_folder($name, $short_name, REPOSITORY_KALTURA_SITE_SHARED_PATH);

        $name       = get_string('folder_shared_videos', 'repository_kaltura');
        $short_name = get_string('folder_shared_videos_shortname', 'repository_kaltura');
        $listing[]  = repository_kaltura_create_folder($name, $short_name, REPOSITORY_KALTURA_SHARED_PATH);

        $name       = get_string('folder_used_videos', 'repository_kaltura');
        $short_name = get_string('folder_used_videos_shortname', 'repository_kaltura');
        $listing[]  = repository_kaltura_create_folder($name, $short_name, REPOSITORY_KALTURA_USED_PATH);

        $ret['path'] = $newpath;
        $ret['list'] = $listing;

    } else if (false !== strpos($path, REPOSITORY_KALTURA_SHARED_PATH)) { // If the user is in the shared folder

        $ret_temp = repository_kaltura_get_course_video_listing($shared_access, $path, REPOSITORY_KALTURA_SHARED_PATH, $page);
        $ret = array_merge($ret, $ret_temp);

    } else if (false !== strpos($path, REPOSITORY_KALTURA_SITE_SHARED_PATH)) { // If the user is in the site shared folder

        $ret_temp = repository_kaltura_get_site_video_listing($path, REPOSITORY_KALTURA_SITE_SHARED_PATH, $page);
        $ret = array_merge($ret, $ret_temp);

    } else {

        $ret_temp = repository_kaltura_get_course_video_listing($system_access, $path, REPOSITORY_KALTURA_USED_PATH, $page);
        $ret = array_merge($ret, $ret_temp);
    }

    return $ret;
}

function repository_kaltura_get_shared_listing($ret, $path, $shared_access, $page = 1) {

    $newpath = array();
    $listing = array();

    // If the user is in the root folder
    if (empty($path)) {

        $newpath[] = array('name' => get_string('crumb_home', 'repository_kaltura'), 'path' => '');

        $name       = get_string('folder_site_shared_videos', 'repository_kaltura');
        $short_name = get_string('folder_site_shared_videos_shortname', 'repository_kaltura');
        $listing[]  = repository_kaltura_create_folder($name, $short_name, REPOSITORY_KALTURA_SITE_SHARED_PATH);

        $name       = get_string('folder_shared_videos', 'repository_kaltura');
        $short_name = get_string('folder_shared_videos_shortname', 'repository_kaltura');
        $listing[]  = repository_kaltura_create_folder($name, $short_name, REPOSITORY_KALTURA_SHARED_PATH);

        $ret['path'] = $newpath;
        $ret['list'] = $listing;

    } else if (false !== strpos($path, REPOSITORY_KALTURA_SHARED_PATH)) { // If the user is in the shared folder

        $ret_temp = repository_kaltura_get_course_video_listing($shared_access, $path, REPOSITORY_KALTURA_SHARED_PATH, $page);
        $ret = array_merge($ret, $ret_temp);

    } else if (false !== strpos($path, REPOSITORY_KALTURA_SITE_SHARED_PATH)) { // If the user is in the site shared folder

        $ret_temp = repository_kaltura_get_site_video_listing($path, REPOSITORY_KALTURA_SITE_SHARED_PATH, $page);
        $ret = array_merge($ret, $ret_temp);

    }

    return $ret;

}


/**
 * This function constructs displays all videos that are marked as shared with
 * site.
 *
 * @param string - navigation crumb trail if either /shared or /used is passed
 * @param string - either constant REPOSITORY_KALTURA_SHARED_PATH or REPOSITORY_KALTURA_USED_PATH
 * @param int - current page
 */
function repository_kaltura_get_site_video_listing($path, $type_path, $page) {

    $newpath     = array();
    $listing     = array();
    $ret['list'] = array();
    $sub_crumb   = '';
    $type        = '';

    // Check if at least one path delimter exists
    if (0 >= substr_count($path, '/')) {
        return $ret;
    }

    $sub_crumb = get_string('crumb_site_shared', 'repository_kaltura');
    $type = 'site_shared';

    $page_size = get_config(REPOSITORY_KALTURA_PLUGIN_NAME, 'itemsperpage');

    // If they are deeper than the root of the course folder then determine the course
    // and display the videos for the course
    $kaltura    = new kaltura_connection();
    $connection = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);

    // Build navigation path
    $newpath[] = array('name' => get_string('crumb_home', 'repository_kaltura'), 'path' => '');
    $newpath[] = array('name' => $sub_crumb, 'path' => $type_path);

    $empty_list = array();

    $search_results = repository_kaltura_search_videos($connection, '', '',
                                    $empty_list, $page,
                                    $type);

    $uri         = local_kaltura_get_host();
    $uri         = rtrim($uri, '/');
    $partner_id  = local_kaltura_get_partner_id();
    $ui_conf_id  = local_kaltura_get_player_uiconf();
    $listing     = repository_kaltura_format_data($search_results, $uri, $partner_id, $ui_conf_id);

    $ret['path'] = $newpath;
    $ret['list'] = $listing;


    if (!empty($search_results) && $search_results->totalCount > $page_size) {

        $ret['page'] = $page;
        $ret['pages'] = ceil($search_results->totalCount / $page_size);
        $ret['total'] = $search_results->totalCount;
        $ret['perpage'] = (int) $page_size;

    }

    return $ret;
}


/**
 * This function constructs any folders or videos for either shared or system
 * access, for display in the file picker.  If either /shared or /used is passed
 * as the navigation path, then course folders will be constructed and returned.
 * If the path contains additional information, such as /shared/<course short
 * name> then all the videos, whose categories match the Moodle course id, on
 * the Kaltura server will be returned in a paged format.
 *
 * @param array - an array of Moodle course ids (keys are moodle course ids)
 * @param string - navigation crumb trail if either /shared or /used is passed
 * @param string - either constant REPOSITORY_KALTURA_SHARED_PATH or REPOSITORY_KALTURA_USED_PATH
 * @param int - current page
 */
function repository_kaltura_get_course_video_listing($courses, $path, $type_path = REPOSITORY_KALTURA_SHARED_PATH, $page = 1) {

    global $DB;

    $newpath     = array();
    $listing     = array();
    $ret['list'] = array();
    $sub_crumb   = '';
    $type        = '';

    if (empty($courses)) {
        return $ret;
    }

    // Check if at least one path delimter exists
    if (0 >= substr_count($path, '/')) {
        return $ret;
    }

    // Check if the type page has valid data
    if (0 == strcmp(REPOSITORY_KALTURA_SHARED_PATH, $type_path)) {

        $sub_crumb = get_string('crumb_shared', 'repository_kaltura');
        $type = 'shared';

    } else if (0 == strcmp(REPOSITORY_KALTURA_USED_PATH, $type_path)) {

        $sub_crumb = get_string('crumb_used', 'repository_kaltura');
        $type = 'used';

    } else {

        return $ret;
    }

    $page_size = get_config(REPOSITORY_KALTURA_PLUGIN_NAME, 'itemsperpage');

    // If there is only one '/' in the path then we are looking at course folders
    if (1 == substr_count($path, '/')) {

        $newpath[] = array('name' => get_string('crumb_home', 'repository_kaltura'), 'path' => '');
        $newpath[] = array('name' => $sub_crumb, 'path' => $type_path);

        $listing = repository_kaltura_create_courses_folders($courses, $path);

        $ret['path'] = $newpath;
        $ret['list'] = $listing;

    } else {

        // If they are deeper than the root of the course folder then determine the course
        // and display the videos for the course
        $kaltura    = new kaltura_connection();
        $connection = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);

        // Build navigation path
        $newpath[] = array('name' => get_string('crumb_home', 'repository_kaltura'), 'path' => '');
        $newpath[] = array('name' => $sub_crumb, 'path' => $type_path);

        $full_path = explode('/', $path);
        $course_shortname = end($full_path);

        $newpath[] = array('name' => $course_shortname, 'path' => $type_path. '/' . $course_shortname);

        // Get videos shared in course
        $param = array('shortname' => $course_shortname);
        $course = $DB->get_record('course', $param);

        $course = array($course->id => $course);

        $search_results = repository_kaltura_search_videos($connection, '', '',
                                        $course, $page,
                                        $type);

        $uri        = local_kaltura_get_host();
        $partner_id = local_kaltura_get_partner_id();
        $ui_conf_id = local_kaltura_get_player_uiconf();
        $listing    = repository_kaltura_format_data($search_results, $uri, $partner_id, $ui_conf_id);

        $ret['path'] = $newpath;
        $ret['list'] = $listing;

        if (!empty($search_resultst) && $search_results->totalCount > $page_size) {

            $ret['page'] = $page;
            $ret['pages'] = ceil($search_results->totalCount / $page_size);
            $ret['total'] = $search_results->totalCount;
            $ret['perpage'] = (int) $page_size;

        }

    }

    return $ret;
}


/**
 * This function creats a folder structure for displaying in the repository file
 * picker.
 *
 * @param string - full name of the folder
 * @param string - short name (optiona) if short name is included then the short
 * name will be displayed and the full name will appear as a tooltip
 * @param string - path of the folder
 *
 * @return array - array structure for displaying a single file picker folder
 */
function repository_kaltura_create_folder($fullname, $shortname, $path) {
    global $OUTPUT;

    return array('title'      => $fullname,
                 'shorttitle' => $shortname,
                 'thumbnail'  => $OUTPUT->pix_url('f/folder-32') . '',
                 'path'       => $path,
                 'children'   => array()
                );
}


/*###################################*/
/**
 * Searching functions
 */


/**
 * This function retrieves all of the videos uploaded by the current user.
 *
 * @param obj - Kaltura connection object
 * @param string - video name
 * @param string - video tags
 * @param int - pager index
 * @param int - number of videos to display on a single page (optional override)
 * @param string (optional) - generic search criteria override.  Forces the
 * function to use tagsNameMultiLikeOr search filter
 *
 * @return array
 */
function repository_kaltura_search_own_videos($connection, $name, $tags, $page_index = 0, $videos_per_page = 0, $override_filter_search = '') {

    global $USER;

    $results = array();

    // Create filter
    $filter = repository_kaltura_create_media_filter($name, $tags, $override_filter_search);

    // Filter vidoes with the user's username as the user id
    $filter->userIdEqual = $USER->username;

    // Create pager object
    $pager = repository_kaltura_create_pager($page_index, $videos_per_page);

    // Get results
    $results = $connection->media->listAction($filter, $pager);

    return $results;

}


/**
 * Refactored code from @see search_own_videos(), except it also returns videos
 * that are still being converted.
 *
 * @param obj $connection Kaltura connection object
 * @param string $search Search string (optional)
 * @param int $page_index Pager index
 * @param int $search Number of videos to display on a single page (optional override)
 * @param string $sort Optional sort (most recent or alphabetical)
 *
 * @return array List of videos
 */
function repository_kaltura_search_mymedia_videos($connection, $search = '', $page_index = 0, $videos_per_page = 0, $sort = null) {

    global $USER;

    $results = array();

    // Create filter
    $filter = repository_kaltura_create_mymedia_filter($search, $sort);

    // Filter vidoes with the user's username as the user id
    $filter->userIdEqual = $USER->username;

    // Create pager object
    $pager = repository_kaltura_create_pager($page_index, $videos_per_page);

    // Get results
    $results = $connection->media->listAction($filter, $pager);

    return $results;

}
/**
 * This functions retrieves videos that match the search criteria and searches
 * through the custom metadata profile for course ids that match
 *
 * @param obj - Kaltura connection object
 * @param string - video name
 * @param string - video tags
 * @param array - array of Moodle course ids whose keys are the course ids
 * @param int - pager index
 * @param string - 'used' to search for video used in courses or 'shared' to
 * search for videos shared with courses
 *
 * @return array
 */
function repository_kaltura_search_videos($connection, $name, $tags, $courses = array(), $page_index = 0, $search_for = 'shared') {

    $results = array();

    // Create filter
    $filter = repository_kaltura_create_media_filter($name, $tags);

    if (!empty($courses)) {

        switch ($search_for) {
            case 'shared':
                $results = repository_kaltura_retrieve_shared_videos($connection, $filter, $courses, $page_index);
                break;
            case 'used':
                $results = repository_kaltura_retrieve_used_videos($connection, $filter, $courses, $page_index);
                break;
        }
    } elseif (0 == strcmp('site_shared', $search_for)) {

        $results = repository_kaltura_retrieve_site_shared_videos($connection, $filter, $page_index);
    }

    return $results;

}

/**
 * This function retrieves videos that have been shared with the site
 *
 * @param obj - Kaltura connection object
 * @param obj - KalturaMediaEntryFilter @see repository_kaltura_create_media_filter()
 * @param int - current page index
 */
function repository_kaltura_retrieve_site_shared_videos($connection, $filter, $page_index) {
    $results = array();

    // Get metadata profile id
    // Retrieve the custom metadata profile id from the repository configuration option
    // This is a big performance gain as opposed to using @see repository_kaltura_get_metadata_profile()
    $metadata_profile_id = get_config(REPOSITORY_KALTURA_PLUGIN_NAME, 'metadata_profile_id');

    if (empty($metadata_profile_id)) {
        return array();
    }

    // Get the xPath for the field we are searching against
    $xpath = repository_kaltura_get_metadata_share_field_path($connection, $metadata_profile_id, REPOSITORY_KALTURA_SYSTEM_SHARE);

    // Create the advanced search filter
    if (false !== $xpath) {

        $adv_filter = repository_kaltura_create_site_shared_adv_search_filter($xpath, $metadata_profile_id);

        if (false === $adv_filter) {
            return array();
        }

        // Set the advanced search filter
        $filter->advancedSearch = $adv_filter;
    }

    // Create pager object
    $pager = repository_kaltura_create_pager($page_index);

    // Get results
    $results = $connection->media->listAction($filter, $pager);

    return $results;
}

/**
 * This function retrieves videos whose categories match the Moodle course ids.
 *
 * @param obj - Kaltura connection object
 * @param obj - KalturaMediaEntryFilter @see repository_kaltura_create_media_filter()
 * @param array - an array of Moodle courses to filter videos results by
 * @param int - current page index
 */
function repository_kaltura_retrieve_used_videos($connection, $filter, $courses, $page_index) {

    $results = array();
    $categories   = '';
    $rootcategory = get_config(REPOSITORY_KALTURA_PLUGIN_NAME, 'rootcategory');

    foreach ($courses as $courseid => $data) {
        $categories .= $rootcategory . '>' . $courseid . ',';
    }

    $categories = rtrim($categories, ',');

    $filter->categoriesMatchOr = $categories;

    // Create pager object
    $pager = repository_kaltura_create_pager($page_index);

    // Get results
    $results = $connection->media->listAction($filter, $pager);

    return $results;
}

/**
 * This function retrieves videos whose metadata contains Moodle course ids.
 *
 * @param obj - Kaltura connection object
 * @param obj - KalturaMediaEntryFilter @see repository_kaltura_create_media_filter()
 * @param array - an array of Moodle courses (keys are course ids) to filter
 * videos results by
 * @param int - current page index
 */
function repository_kaltura_retrieve_shared_videos($connection, $filter, $courses, $page_index) {

    $results = array();

    // Get metadata profile id
    // Retrieve the custom metadata profile id from the repository configuration option
    // This is a big performance gain as opposed to using @see repository_kaltura_get_metadata_profile()
    $metadata_profile_id = get_config(REPOSITORY_KALTURA_PLUGIN_NAME, 'metadata_profile_id');

    if (empty($metadata_profile_id)) {
        return array();
    }

    // Get the xPath for the field we are searching against
    $xpath = repository_kaltura_get_metadata_share_field_path($connection, $metadata_profile_id, REPOSITORY_KALTURA_COURSE_SHARE);

    // Create the advanced search filter
    if (false !== $xpath) {

        $adv_filter = repository_kaltura_create_course_shared_adv_search_filter($courses, $xpath, $metadata_profile_id);

        if (false === $adv_filter) {
            return array();
        }

        // Set the advanced search filter
        $filter->advancedSearch = $adv_filter;
    }

    // Create pager object
    $pager = repository_kaltura_create_pager($page_index);

    // Get results
    $results = $connection->media->listAction($filter, $pager);

    return $results;
}

/**
 * This function returns a KalturaMetadataSearchItem to be used as an advance
 * search filter
 *
 * @param array - an array of Moodle course ids (keys are course ids)
 *
 * @param string - xpath of the metadata field @see
 * repository_kaltura_get_metadata_share_field_path()
 *
 * @param mixed - KalturaMetadataProfile id or false if something went horribly
 * wrong
 */
function repository_kaltura_create_course_shared_adv_search_filter($courses, $xpath, $profile_id) {

    $adv_filter       = false;
    $search_condition = array();

    foreach ($courses as $courseid => $data) {

        // Create Metadata filter
        $search_condition[] = repository_kaltura_create_metadata_filter($courseid, $xpath);
    }

    if (!empty($search_condition)) {

        // Create the the metadata search item object
        $adv_filter = repository_kaltura_create_metadata_search_items($search_condition, $profile_id);
    }

    return $adv_filter;
}

/**
 * This function returns a KalturaMetadataSearchItem to be used as an advance
 * search filter
 *
 * @param string - xpath of the metadata field @see
 * repository_kaltura_get_metadata_share_field_path()
 *
 * @param mixed - KalturaMetadataProfile id or false if something went horribly
 * wrong
 */
function repository_kaltura_create_site_shared_adv_search_filter($xpath, $profile_id) {
    $adv_filter       = false;
    $search_condition = array();

    // Create Metadata filter
    $search_condition[] = repository_kaltura_create_metadata_filter(1, $xpath);

    if (!empty($search_condition)) {

        // Create the the metadata search item object
        $adv_filter = repository_kaltura_create_metadata_search_items($search_condition, $profile_id);
    }

    return $adv_filter;
}

/**
 * This functions creates a metadata search item, to be used as an advance
 * filter
 *
 * @param array - an array of KalturaSearchCondition objects @see
 * repository_kaltura_create_metadata_filter()
 *
 * @param mixed - a KalturaMetadataProfile id, or false if something went wrong
 *
 * @return obj - KalturaMetadataSearchItem
 */
function repository_kaltura_create_metadata_search_items($search_conditions, $profile_id) {

    $adv_search = new KalturaMetadataSearchItem();
    $adv_search->type = KalturaSearchOperatorType::SEARCH_OR;
    $adv_search->metadataProfileId = $profile_id;
    $adv_search->items = $search_conditions;

    return $adv_search;
}

/**
 * This function creates a KalturaSearchCondition object using the profile field
 * xPath and Moodle course id as the field and value respectively.
 *
 * @param int - Moodle course id
 * @param string - Metadata profile field xPath
 *
 * @return KalturaSearchCondition - search condition object
 */
function repository_kaltura_create_metadata_filter($course_id, $field_xpath) {

    $filter_item = new KalturaSearchCondition();
    $filter_item->field = $field_xpath;
    $filter_item->value = $course_id;

    return $filter_item;

}

/**
 * This function returns a KalturaMediaEntryFilter object with specific
 * properties based on the arguments passed.  A freeText search is used when
 * name and tags are not empty.  A tagsMultiLikeOr is used when tags is not
 * empty.  A nameMultiLikeOr is used when name is not empty
 *
 * @param string - video name search criteria
 * @param string - video tags serach criteria
 * @param string (optional) - generic search criteria override.  Forces the
 * function to use tagsNameMultiLikeOr search filter
 *
 * @return KalturaMediaEntryFilter - filter object
 */
function repository_kaltura_create_media_filter($name, $tags, $multi_override = '') {

    $filter = new KalturaMediaEntryFilter();

    if (!empty($multi_override)) {
        $search_name = explode(' ', $multi_override);

        // Removing duplicate search terms
        $search = array_flip(array_flip($search_name));

        $filter->tagsNameMultiLikeOr = implode(',', $search);

    } else if (!empty($name) && !empty($tags)) {
        // If name and tag is not empty use tagsNameMultiLikeOr search
        $search_tags = explode(' ', $tags);
        $search_name = explode(' ', $name);

        // Removing duplicate search terms
        $search = array_flip(array_flip(array_merge($search_tags, $search_name)));

        $filter->tagsNameMultiLikeOr = implode(',', $search);

    } else if (!empty($tags)) {

        $search_tags = explode(' ', $tags);
        $search_tags = implode(',', $search_tags);
        $filter->tagsMultiLikeOr = $search_tags;
    } else if (!empty($name)) {

        $search_name = explode(' ', $name);
        $search_name = implode(',', $search_name);
        $filter->nameMultiLikeOr = $search_name;
    }

    $filter->orderBy = 'name';

    return $filter;
}

/**
 * Refactored code from @see create_media_filter(), except it only uses
 * tagsNameMultiLikeOr and uses KalturaEntryStatus::READY, KalturaEntryStatus::
 * PRECONVERT, KalturaEntryStatus::IMPORT to retrieve videos that are still
 * being converted.
 *
 * @param string $search Video search string (separated by spaces)
 * @param string $sort Optional sort (most recent or alphabetical)
 *
 * @return KalturaMediaEntryFilter - filter object
 */
function repository_kaltura_create_mymedia_filter($search, $sort = null) {

    $filter = new KalturaMediaEntryFilter();

    $search_name = explode(' ', $search);

    // Removing duplicate search terms
    $search_name = array_flip(array_flip($search_name));

    $filter->tagsNameMultiLikeOr = implode(',', $search_name);
    $filter->statusIn = KalturaEntryStatus::READY .','.
                        KalturaEntryStatus::PRECONVERT .','.
                        KalturaEntryStatus::IMPORT;

    if ($sort == 'recent') {
        $filter->orderBy = KalturaBaseEntryOrderBy::CREATED_AT_DESC;
    } else if ($sort == 'old') {
        $filter->orderBy = KalturaBaseEntryOrderBy::CREATED_AT_ASC;
    } else if ($sort == 'name_asc') {
         $filter->orderBy = KalturaBaseEntryOrderBy::NAME_ASC;
    } else if ($sort == 'name_desc') {
         $filter->orderBy = KalturaBaseEntryOrderBy::NAME_DESC;
    } else {
        $filter->orderBy = KalturaBaseEntryOrderBy::CREATED_AT_DESC;
    }

    return $filter;
}

/**
 * This function creates site share metadata XML
 *
 * @param int - 1 to create site share metadata or 0 to not create it
 * @return string - XML
 */
function repository_kaltura_create_site_share_metadata_xml($global_share = 0) {
    $xml = '';

    if (!empty($global_share)) {
        $xml .= '<SystemShare>1</SystemShare>';
    } else {
        $xml .= '<SystemShare>0</SystemShare>';
    }

    return $xml;
}

/**
 * This function creates course share metadata XML
 *
 * @param array - array of course ids
 * @return string - XML
 */
function repository_kaltura_create_course_share_metadata_xml($courses = array()) {
    $xml = '';

    foreach ($courses as $course) {
        $xml .= '<CourseShare>'.$course.'</CourseShare>';
    }

    return $xml;
}

//
///**
// * This function creates the video's custom metadata xml from the passed
// * arguments
// *
// * @param int - 1 to create the site share node
// * @param array - an array of course ids
// * @return string - xml schema
// */
//function create_video_custom_metadata_xml($global_share = 0, $courses = array()) {
//
//    $xml = '<metadata>';
//
//    if (!empty($global_share)) {
//        $xml .= '<SystemShare>1</SystemShare>';
//    }
//
//    foreach ($courses as $course) {
//        $xml .= '<CourseShare>'.$course.'</CourseShare>';
//    }
//
//
//    return $xml . '</metadata>';
//}

/**
 * This function updates a video's custom metadata schema
 *
 * @param object - Kaltura connection object
 * @param string - Kaltura entry id
 * @param string - 'x' to retain the old Site share value, or a new site share
 * xml schema (ex. <SystemShare>1</SystemShare>)
 * @param string - 'x' to retain the old Course share value, or a new course
 * share xml schema (ex.
 * <CourseShare>2</CourseShare><CourseShare>5</CourseShare>)
 *
 * @return mixed - true if successful, otherwise false, or a string if an
 * exception was thrown.
 */
function repository_kaltura_update_video_custom_metadata($connection, $entry_id, $gshare = 'x', $cshare = 'x') {

    try {

        // Retrieve the custom metadata profile id from the repository configuration option
        // This is a big performance gain as opposed to using @see repository_kaltura_get_metadata_profile()
        $metadata_profile_id = get_config(REPOSITORY_KALTURA_PLUGIN_NAME, 'metadata_profile_id');

        if (!$metadata_profile_id) {
            return false;
        }

        if ('x' === $gshare && 'x' === $cshare) {
            return false;
        }

        $metadata_list_data = repository_kaltura_get_video_custom_metadata($connection, $metadata_profile_id, $entry_id);

        // If the video is not an instance of KalturaMetadataListResponse, then we must add metadata schema
        // To the video
        if (!$metadata_list_data instanceof KalturaMetadataListResponse) {

            // If error code of -1 was returned then return false
            if (-1 == $metadata_list_data) {
                return false;
            }

            // If value is 0 then we need to add metadata to this video for the first time
            if (0 == $metadata_list_data) {

                $xml         = '<metadata>';
                $xml         .= ('x' !== $gshare) ? $gshare : '';
                $xml         .= ('x' !== $cshare) ? $cshare : '';
                $xml         .= '</metadata>';

                $object_type = KalturaMetadataObjectType::ENTRY;
                $object_id   = $entry_id;

                $data = $connection->metadata->add($metadata_profile_id, $object_type, $object_id, $xml);

                if (!$data instanceof KalturaMetadata) {
                    return false;
                }

            }
        } else {

            /** Check if the object has at least one element or whether the
             * object's xml is empty.  This solves issues where a video is
             * uploaded from another system and for some reason the metadata
             * object isn't completely created/initialized - KALDEV-391
             */
            if (count($metadata_list_data) < 1 ||
                empty($metadata_list_data->objects[0]->xml)) {

                return false;
            }

            // Parse the XML into a useable format
            $xml         = '<metadata>';
            $data_schema = new SimpleXMLElement($metadata_list_data->objects[0]->xml);

            // If global share is 'x' then re-use the video's original SystemShare schema
            if ('x' === $gshare) {
                $gshare = '';

                if (isset($data_schema->SystemShare)) {
                    $gshare .= '<SystemShare>'.$data_schema->SystemShare.'</SystemShare>';
                }

                $xml .= $gshare;

            } else {

                // Set a new SystemShare schema
                $xml .= $gshare;
            }

            // If course share is 'x' then re-use the video's original CourseShare schema
            if ('x' === $cshare) {
                $cshare = '';

                if (isset($data_schema->CourseShare)) {

                    foreach ($data_schema->CourseShare as $course_id) {
                        $cshare .= '<CourseShare>'.$course_id.'</CourseShare>';
                    }
                }

                $xml .= $cshare;

            } else {

                // Set a new CourseShare schema
                $xml .= $cshare;
            }

            $xml .= '</metadata>';


            $data = $connection->metadata->update($metadata_list_data->objects[0]->id, $xml);

            if (!$data instanceof KalturaMetadata) {
                return false;
            }
        }

        return true;

    } catch (Exception $exp) {
        return $exp->getMessage();
    }
}

/**
 * This function retrieves a video's custom metadata schema
 *
 * @param obj - Kaltura connection object
 * @param int - Metadata profile id
 * @param  string - video entry id
 *
 *
 * @return mixed - returns a number code or an object.   -1 is returned if there
 * was an error. If a video never had any metadata then a 0 is returned and a
 * metadata->add action is required if adding metadata to the video.  A
 * KalturaMetadataListResponse is returned if the video has custom metadata.
 */
function repository_kaltura_get_video_custom_metadata($connection, $metadata_profile_id, $entry_id) {

    $meta_filter = new KalturaMetadataFilter();
    $meta_filter->metadataObjectTypeEqual = KalturaMetadataObjectType::ENTRY;
    $meta_filter->objectIdEqual = $entry_id;
    $meta_filter->metadataProfileIdEqual = $metadata_profile_id;

    $data = $connection->metadata->listaction($meta_filter);

    if (!$data instanceof KalturaMetadataListResponse) {
        return -1;
    }

    if (0 == $data->totalCount) {
        return 0;
    }

    return $data;
}

/**
 * This function formats a video's custom metadata into an array with two
 * values.  First value is the site share (1 or 0), the second value is a comma
 * separated string of course ids (1, 2, 44, 22)
 *
 * @param obj - Kaltura connection object
 * @param string - video entry id
 *
 * @return array - first value represents the site share settings (1 or 0).  The
 * second value is a comma separated list of course ids to share the video with
 * (2, 55, 44, 77, 8)
 */
function repository_kaltura_format_video_custom_metadata($connection, $entry_id) {

    $site_share  = 0;
    $course_list = '';

    // Retrieve the custom metadata profile id from the repository configuration option
    // This is a big performance gain as opposed to using @see repository_kaltura_get_metadata_profile()
    $metadata_profile_id = get_config(REPOSITORY_KALTURA_PLUGIN_NAME, 'metadata_profile_id');

    if (!$metadata_profile_id) {
        return array(0, '');
    }

    $metadata_list_data = repository_kaltura_get_video_custom_metadata($connection, $metadata_profile_id, $entry_id);

    /** Check if the object has at least one element or whether the
     * object's xml is empty.  This solves issues where a video is
     * uploaded from another system and for some reason the metadata
     * object isn't completely created/initialized - KALDEV-391
     */
    if (!$metadata_list_data instanceof KalturaMetadataListResponse ||
        count($metadata_list_data) < 1 ||
        empty($metadata_list_data->objects[0]->xml)) {

        return array(0, '');
    }

    // Parse the XML into a useable format
    $data_schema = new SimpleXMLElement($metadata_list_data->objects[0]->xml);

    if (isset($data_schema->CourseShare)) {

        foreach ($data_schema->CourseShare as $course_id) {
            $course_list .= $course_id . ',';
        }

        $course_list = rtrim($course_list, ',');
    }

    if (isset($data_schema->SystemShare) && 1 == $data_schema->SystemShare) {
        $site_share = 1;
    }

    return array($site_share, $course_list);

}

/**
 * This function adds a course/video reference to the repository table and adds
 * a video to the course.
 *
 * The first part of this function checks to see if a course/video reference
 * already exists.  If a reference exits then nothing else is performed.
 *
 * If the reference does not exist, the video object is retrieved from the
 * Kaltura server and the video's categoryids property is processed.
 *
 * If the video catagoryids value contains the categoryid argument, then only a
 * course/video reference is added to the table.
 *
 * If the video categoryids value does not contain the categoryid argument, the
 * video categoryids property is updated to include the categoryid arguement;
 * and the video is udpated on the Kaltura server. The video is also removed
 * from the cache.  Lastly the course/video reference is added to the table.
 *
 * @param obj - a Kaltura connection object
 * @param int - course id
 * @param array - array of Kaltura video ids
 *
 * @return - nothing useful
 */
function repository_kaltura_add_video_course_reference($connection, $courseid, $video_ids = array()) {
    global $DB;

    try {

        $root_path = get_config(REPOSITORY_KALTURA_PLUGIN_NAME, 'rootcategory');

        if (!$root_path) {
            add_to_log($courseid, 'repository_kaltura', 'view - root category', '', 'Error retrieving root category');
            return '';
        }

        $path   = $root_path . '>' . $courseid;
        $param  = array('courseid' => $courseid);

        foreach ($video_ids as $video_id) {

            // Check the repository table for a courseid and video_id combo
            $param['entryid'] = $video_id;

            if (!$DB->record_exists('repo_kaltura_videos', $param)) {

                // Get the video object
                $result = $connection->media->get($video_id);

                if (!$result instanceof KalturaMediaEntry) {
                    add_to_log($courseid, 'repository_kaltura', 'view - retrieving video', '', 'Error retrieving - ' . $video_id);
                    continue;
                }

                // Check if the video belongs to the category
                if (false === strpos($result->categories, $path)) {

                    // The Kaltura server will automatically create the category and removes any duplicates
                    $media_entry = new KalturaMediaEntry();
                    $media_entry->categories = $path . ',' . $result->categories;

                    // Update video properties
                    $update_result = $connection->media->update($result->id, $media_entry);

                    if (!$update_result instanceof KalturaMediaEntry) {
                        add_to_log($courseid, 'repository_kaltura', 'update - categories', '', 'Error updating categories for entry - ' . $video_id);
                        return '';
                    }
                }

                $record = new stdClass();
                $record->courseid    = $courseid;
                $record->entryid     = $video_id;
                $record->timecreated = time();

                // Add entry in repository reference table
                $rec_id = $DB->insert_record('repo_kaltura_videos', $record);

                if (empty($rec_id)) {
                    add_to_log($courseid, 'repository_kaltura', 'insert - repo reference table', '', 'Error inserting reference - ' . $video_id);
                }
            }
        }
    } catch (Exception $exp) {
        add_to_log($courseid, 'repository_kaltura', 'Error in add_video_course_reference', '', $exp->getMessage());
    }
}

/**
 * This function is an event handler that deletes a Kaltura category when the
 * 'course_deleted' event is triggered.  In addition to removing the Kaltura
 * category the course/video reference table is cleaned up, by removing all
 * records with a matching courseid
 *
 * @param obj - an object containing a Moodle course id
 * @return - nothing
 *
 */
function repository_kaltura_delete_category($course) {

    global $DB;

    $kaltura = new kaltura_connection();
    $connection = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);

    if (!empty($connection)) {
        $category = repository_kaltura_create_course_category($connection, $course->id);

        if ($category) {
            $param = array('courseid' => $course->id);

            if ($DB->delete_records('repo_kaltura_videos', $param)) {

                $connection->category->delete($category->id);
                add_to_log($course->id, 'repository_kaltura', 'Course category deleted', '', 'course id - ' . $course->id);
            }
        }
    } else {
        add_to_log($course->id, 'repository_kaltura', 'Course category not deleted', '', 'course id - ' . $course->id);
    }
}

/**
 * Check if the user's account has permissions to use custom
 * metadatata
 *
 * @return bool - true if enabled, otherwise false
 */
function repository_kaltura_account_enabled_metadata($connection) {

    $filter = new KalturaPermissionFilter();
    $filter->nameEqual = 'METADATA_PLUGIN_PERMISSION';

    $pager = new KalturaFilterPager();
    $pager->pageSize = 30;
    $pager->pageIndex = 1;

    try {

        if (empty($connection)) {
            throw new Exception("Unable to connect");
        }

        $results = $connection->permission->listAction($filter, $pager);

        if ( 0 == count($results->objects) ||
            $results->objects[0]->status != KalturaPermissionStatus::ACTIVE) {

            throw new Exception("partner doesn't have permission");

        }

        return true;

    } catch (Exception $ex) {
        add_to_log(SITEID, 'local_kaltura', ' | metadata no permissions ', '', $ex->getMessage());
        return false;
    }
}
