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
 * Kaltura my media library script
 *
 * @package    local
 * @subpackage mymedia
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('MYMEDIA_ITEMS_PER_PAGE', '9');

/**
 * This function adds my media links to the navigation block
 */
function local_mymedia_extends_navigation($navigation) {

    global $USER;

    $mymedia = get_string('nav_mymedia', 'local_mymedia');
    $upload = get_string('nav_upload', 'local_mymedia');

    $node_home = $navigation->get('home');

    $context = get_context_instance(CONTEXT_USER, $USER->id);

    if ($node_home && has_capability('local/mymedia:view', $context, $USER)) {
        $node_mymedia = $node_home->add($mymedia, new moodle_url('/local/mymedia/mymedia.php'),
                                        navigation_node::NODETYPE_LEAF, $mymedia, 'mymedia');
    }
}

/**
 * This function checks for capability across all context levels.
 *
 * @param string $capability The name of capability we are checking
 * @return boolean true if capability found, false otherwise
 */
function local_mymedia_check_capability($capability) {
    global $DB, $USER;
    $result = false;

    // Site admins can do anything
    if (is_siteadmin($USER->id)) {
        $result = true;
    }

    // Look for share permissions in the USER global
    if (!$result && isset($USER->access['rdef'])) {
        foreach ($USER->access['rdef'] as $contextelement) {
            if (isset($contextelement[$capability]) && $contextelement[$capability] == 1) {
                $result = true;
            }
        }
    }

    // Look for share permissions in the database for any context level in case it wasn't found in USER global
    if (!$result) {
        $sql = "SELECT ra.*
                  FROM {role_assignments} ra
            INNER JOIN {role_capabilities} rc ON rc.roleid = ra.roleid
                 WHERE ra.userid = :userid
                       AND rc.capability = :capability
                       AND rc.permission = :permission";

        $params = array(
            'userid' => $USER->id,
            'capability' => $capability,
            'permission' => CAP_ALLOW
        );

        if ($DB->record_exists_sql($sql, $params)) {
            $result = true;
        }
    }

    return $result;
}
