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
 * Kaltura video assignment library of hooks
 *
 * @package    mod
 * @subpackage kalvidassign
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

/** Include eventslib.php */
require_once($CFG->libdir.'/eventslib.php');
/** Include calendar/lib.php */
require_once($CFG->dirroot.'/calendar/lib.php');


/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $kalvidassign An object from the form in mod_form.php
 * @return int The id of the newly inserted kalvidassign record
 */
function kalvidassign_add_instance($kalvidassign) {
    global $DB;

    $kalvidassign->timecreated = time();

    $kalvidassign->id =  $DB->insert_record('kalvidassign', $kalvidassign);

    if ($kalvidassign->timedue) {
        $event = new stdClass();
        $event->name        = $kalvidassign->name;
        $event->description = format_module_intro('kalvidassign', $kalvidassign, $kalvidassign->coursemodule);
        $event->courseid    = $kalvidassign->course;
        $event->groupid     = 0;
        $event->userid      = 0;
        $event->modulename  = 'kalvidassign';
        $event->instance    = $kalvidassign->id;
        $event->eventtype   = 'due';
        $event->timestart   = $kalvidassign->timedue;
        $event->timeduration = 0;

        calendar_event::create($event);
    }

    kalvidassign_grade_item_update($kalvidassign);

    return $kalvidassign->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $kalvidassign An object from the form in mod_form.php
 * @return boolean Success/Fail
 */
function kalvidassign_update_instance($kalvidassign) {
    global $DB;

    $kalvidassign->timemodified = time();
    $kalvidassign->id = $kalvidassign->instance;

    $updated = $DB->update_record('kalvidassign', $kalvidassign);

    if ($kalvidassign->timedue) {
        $event = new stdClass();

        if ($event->id = $DB->get_field('event', 'id', array('modulename'=>'kalvidassign', 'instance'=>$kalvidassign->id))) {

            $event->name        = $kalvidassign->name;
            $event->description = format_module_intro('kalvidassign', $kalvidassign, $kalvidassign->coursemodule);
            $event->timestart   = $kalvidassign->timedue;

            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event);
        } else {
            $event = new stdClass();
            $event->name        = $kalvidassign->name;
            $event->description = format_module_intro('kalvidassign', $kalvidassign, $kalvidassign->coursemodule);
            $event->courseid    = $kalvidassign->course;
            $event->groupid     = 0;
            $event->userid      = 0;
            $event->modulename  = 'kalvidassign';
            $event->instance    = $kalvidassign->id;
            $event->eventtype   = 'due';
            $event->timestart   = $kalvidassign->timedue;
            $event->timeduration = 0;

            calendar_event::create($event);
        }
    } else {
        $DB->delete_records('event', array('modulename'=>'kalvidassign', 'instance'=>$kalvidassign->id));
    }

    if ($updated) {
        kalvidassign_grade_item_update($kalvidassign);
    }

    return $updated;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function kalvidassign_delete_instance($id) {
    global $DB;

    $result = true;

    if (! $kalvidassign = $DB->get_record('kalvidassign', array('id' => $id))) {
        return false;
    }

    if (! $DB->delete_records('kalvidassign_submission', array('vidassignid' => $kalvidassign->id))) {
        $result = false;
    }

    if (! $DB->delete_records('event', array('modulename'=>'kalvidassign', 'instance'=>$kalvidassign->id))) {
        $result = false;
    }

    if (! $DB->delete_records('kalvidassign', array('id' => $kalvidassign->id))) {
        $result = false;
    }

    kalvidassign_grade_item_delete($kalvidassign);

    return $result;
}


/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return null
 * @todo Finish documenting this function
 */
function kalvidassign_user_outline($course, $user, $mod, $kalvidassign) {
    $return = new stdClass;
    $return->time = 0;
    $return->info = ''; //TODO finish this function
    return $return;
}


/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function kalvidassign_user_complete($course, $user, $mod, $kalvidassign) {
    return true;  //TODO: finish this function
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in kalvidassign activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function kalvidassign_print_recent_activity($course, $viewfullnames, $timestart) {
    // TODO: finish this function
    return false;  //  True if anything was printed, otherwise false
}


/**
 * Must return an array of users who are participants for a given instance
 * of kalvidassign. Must include every user involved in the instance,
 * independient of his role (student, teacher, admin...). The returned objects
 * must contain at least id property. See other modules as example.
 *
 * @param int $kalvidassign ID of an instance of this module
 * @return boolean|array false if no participants, array of objects otherwise
 */
function kalvidassign_get_participants($kalvidassignid) {
    // TODO: finish this function
    return false;
}


/**
 * This function returns if a scale is being used by one kalvidassign
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $kalvidassign id ID of an instance of this module
 * @return mixed
 * @todo Finish documenting this function
 */
function kalvidassign_scale_used($kalvidassignid, $scaleid) {
    global $DB;

    $return = false;

    //$rec = $DB->get_record("newmodule", array("id" => "$newmoduleid", "scale" => "-$scaleid"));
    //
    //if (!empty($rec) && !empty($scaleid)) {
    //    $return = true;
    //}

    return $return;
}

/**
 * Checks if scale is being used by any instance of kalvidassign.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any kalvidassign
 */
function kalvidassign_scale_used_anywhere($scaleid) {
    global $DB;

    $param = array('grade' => -$scaleid);
    if ($scaleid and $DB->record_exists('kalvidassign', $param)) {
        return true;
    } else {
        return false;
    }
}

/**
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function kalvidassign_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return true;
        case FEATURE_BACKUP_MOODLE2:          return true;

        default: return null;
    }
}

/**
 * Create/update grade item for given kaltura video assignment
 *
 * @global object
 * @param object kalvidassign object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int, 0 if ok, error code otherwise
 */
function kalvidassign_grade_item_update($kalvidassign, $grades=NULL) {
    global $CFG;

    require_once(dirname(dirname(dirname(__FILE__))) . '/lib/gradelib.php');

    $params = array('itemname'=>$kalvidassign->name, 'idnumber'=>$kalvidassign->cmidnumber);

    if ($kalvidassign->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $kalvidassign->grade;
        $params['grademin']  = 0;

    } else if ($kalvidassign->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$kalvidassign->grade;

    } else {
        $params['gradetype'] = GRADE_TYPE_TEXT; // allow text comments only
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    return grade_update('mod/kalvidassign', $kalvidassign->course, 'mod', 'kalvidassign', $kalvidassign->id, 0, $grades, $params);

}

/**
 * Removes all grades from gradebook
 *
 * @global object
 * @param int $courseid
 * @param string optional type
 */
function kalvidassign_reset_gradebook($courseid, $type='') {
    global $DB;

    $sql = "SELECT l.*, cm.idnumber as cmidnumber, l.course as courseid
              FROM {kalvidassign} l, {course_modules} cm, {modules} m
             WHERE m.name='kalvidassign' AND m.id=cm.module AND cm.instance=l.id AND l.course=:course";

    $params = array ('course' => $courseid);

    if ($kalvisassigns = $DB->get_records_sql($sql,$params)) {

        foreach ($kalvisassigns as $kalvisassign) {
            kalvidassign_grade_item_update($kalvisassign, 'reset');
        }
    }

}


/**
 * Actual implementation of the reset course functionality, delete all the
 * kaltura video submissions attempts for course $data->courseid.
 *
 * @global stdClass
 * @global object
 * @param object $data the data submitted from the reset course.
 * @return array status array
 *
 * TODO: test user data reset feature
 */
function kalvidassign_reset_userdata($data) {
    global $CFG, $DB;

    $componentstr = get_string('modulenameplural', 'kalvidassign');
    $status = array();

    if (!empty($data->reset_kalvidassign)) {
        $kalvidassignsql = "SELECT l.id
                           FROM {kalvidassign} l
                           WHERE l.course=:course";

        $params = array ("course" => $data->courseid);
        $DB->delete_records_select('kalvidassign_submission', "vidassignid IN ($kalvidassignsql)", $params);

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            kalvidassign_reset_gradebook($data->courseid);
        }

        $status[] = array('component'=>$componentstr, 'item'=>get_string('deleteallsubmissions', 'kalvidassign'), 'error'=>false);
    }

    /// updating dates - shift may be negative too
    if ($data->timeshift) {
        shift_course_mod_dates('kalvidassign',array('timedue', 'timeavailable'), $data->timeshift, $data->courseid);
        $status[] = array('component'=>$componentstr,
                          'item'=>get_string('datechanged'),
                          'error'=>false);
    }

    return $status;
}

function kalvidassign_grade_item_delete($kalvidassign) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/kalvidassign', $kalvidassign->course, 'mod', 'kalvidassign', $kalvidassign->id, 0,
            null, array('deleted' => 1));
}


/**
 * Function to be run periodically according to the moodle cron
 *
 * Finds all assignment notifications that have yet to be mailed out, and mails them
 */
function kalvidassign_cron () {
    return false;
}

/**
 * Return list of marked submissions that have not been mailed out for currently enrolled students
 *
 * @return array
 */
function kalvidassign_get_unmailed_submissions($starttime, $endtime) {
/*    global $CFG, $DB;

    return $DB->get_records_sql("SELECT ks.*, k.course, k.name
                                   FROM {kalvidassign_submission} ks,
                                        {kalvidassign} k
                                  WHERE ks.mailed = 0
                                        AND ks.timemarked <= ?
                                        AND ks.timemarked >= ?
                                        AND ks.assignment = k.id", array($endtime, $starttime));
*/
}