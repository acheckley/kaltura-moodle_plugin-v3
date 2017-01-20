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

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

/**
 * Kaltura static entries class
 *
 * @package    local
 * @subpackage kaltura
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * Thanks to Gonen Radai
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/locallib.php');

class KalturaStaticEntries {

    private static $_entries = array();

    /**
     * Retrieve all entry objects that have been seralized and stored in the
     * session global
     */
    public function __construct() {

        global $SESSION;

        if (!isset($SESSION->kaltura_entries)) {

            $SESSION->kaltura_entries = array();
        } else {

            foreach ($SESSION->kaltura_entries as $entry_id => $data) {

                if (!array_key_exists($entry_id, self::$_entries)) {
                    self::$_entries[$entry_id] = unserialize($data);
                }
            }
        }
    }

    /**
     * Add an entry object directory to the array
     * 
     * @param object - an entry object
     * 
     * @return - nothing
     */
    public static function addEntryObject($entry_obj) {
        if (!array_key_exists($entry_obj->id, self::$_entries)) {
            $key = $entry_obj->id;
            self::$_entries[$key] = $entry_obj;
        }
    }
    
    /**
     * Retrieve an entry object.  First verify if the object has already been
     * cached.  If not, retreive the object via API calls.  Else just return the
     * object
     * 
     * @param string - entry id to retrieve
     * @param object - a KalturaBaseEntryService object
     * @param bool - true to make an API call if the entry object doesn't exist.
     * False do not make an API call
     * 
     * @return mixed - entry object or false if it was not found
     */
    public static function getEntry($entryId, $base_entry_service, $fetch = true) {

        if (!array_key_exists($entryId, self::$_entries)) {
            if ($fetch) {
                self::getEntryFromApi($entryId, $base_entry_service);
            } else {
                return false;
            }

        }

        return self::$_entries[$entryId];
    }

    /**
     * Makes an API call to retrieve an entry object and store the object in the
     * static entries list
     * 
     * @param string - entry id
     * @param object - a KalturaBaseEntryService object
     * 
     * @return - nothing
     */
    private static function getEntryFromApi($entryId, $base_entry_service) {

        $entryObj = $base_entry_service->get($entryId);

        // put entry object in array:
        self::$_entries[$entryId] = $entryObj;
        

    }

    /**
     * Return a list of entry objects
     * 
     * @param string - an entry id
     * @param object - a KalturaBaseEntryService object
     * 
     * @return array - array of entry objects with the entry id as the key
     */
    public static function listEntries($entryIds = array(), $base_entry_service) {

        $returnedEntries = array();
        $fetchEntriesFromApi = array();

        foreach($entryIds as $key => $entryid) {

            if (array_key_exists($entryid, self::$_entries)) {
                $returnedEntries[$key] = self::$_entries[$entryid];
            } else {
                $fetchEntriesFromApi[$key] = $entryid;
            }
        }

        self::listEntriesFromApi($fetchEntriesFromApi, $base_entry_service);

        // populate the “blanks” in $returnedEntries with the results from the API
        foreach($fetchEntriesFromApi as $key => $id) {

            if (array_key_exists($id, self::$_entries)) {
                $returnedEntries[$key] = self::$_entries[$id];
            }
        }

        return $returnedEntries;
    }


    /**
     * Retrieve a list of entry objects; and store the objects in the static
     * array.
     * 
     * @param array - array of entry ids to retreive
     * @param object - a KalturaBaseEntryService object
     * 
     * @return nothing
     */
    private static function listEntriesFromApi($entryIds = array(), $base_entry_service) {

        // perform baseEntry->listAction() call:
        $filter = new KalturaBaseEntryFilter();
        $filter->idIn = implode(',', $entryIds);
        $result = $base_entry_service->listAction($filter);

        // put entry objects in array:
        foreach($result->objects as $entry) {
            self::$_entries[$entry->id] = $entry;
        }
    }
    
    /**
     * Remove an entry from cache
     */
    public static function removeEntry($entry_id) {
        global $SESSION;
        
        if (array_key_exists($entry_id, self::$_entries)) {
            unset(self::$_entries[$entry_id]);
            
            if (array_key_exists($entry_id, $SESSION->kaltura_entries)) {
                unset($SESSION->kaltura_entries[$entry_id]);
            }
        }
    }


    /**
     * All stored entry objects will be serialized and stored in the PHP session
     * global.
     */
    public function __destruct() {
        global $SESSION;

        foreach (self::$_entries as $entry_id => $data) {

            if (!array_key_exists($entry_id, $SESSION->kaltura_entries)) {
                $SESSION->kaltura_entries[$entry_id] = serialize($data);
            }

        }
    }
}
