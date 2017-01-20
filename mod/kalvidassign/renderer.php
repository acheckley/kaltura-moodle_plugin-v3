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
 * Kaltura video assignment renderer class
 *
 * @package    mod_kalvidassign
 * @copyright  2013 onwards Remote-Learner {@link http://www.remote-learner.ca/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once(dirname(dirname(dirname(__FILE__))) . '/lib/tablelib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/lib/moodlelib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/locallib.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/kaltura_entries.class.php');

/**
 * Table class for displaying video submissions for grading
 */
class submissions_table extends table_sql {

    var $_quickgrade;
    var $_gradinginfo;
    var $_cminstance;
    var $_grademax;
    var $_cols = 20;
    var $_rows = 4;
    var $_tifirst;
    var $_tilast;
    var $_page;
    var $_entries;
    var $_access_all_groups = false;
    var $_connection = false;

    function __construct($uniqueid, $cm, $grading_info, $quickgrade = false,
                         $tifirst = '', $tilast = '', $page = 0, $entries = array(),
                         $connection) {

        global $DB;

        parent::__construct($uniqueid);

        $this->_quickgrade = $quickgrade;
        $this->_gradinginfo = $grading_info;

        $instance = $DB->get_record('kalvidassign', array('id' => $cm->instance),
                                    'id,grade');

        $instance->cmid = $cm->id;

        $this->_cminstance = $instance;

        $this->_grademax = $this->_gradinginfo->items[0]->grademax;

        $this->_tifirst      = $tifirst;
        $this->_tilast       = $tilast;
        $this->_page         = $page;
        $this->_entries      = $entries;
        $this->_connection   = $connection;

    }

    function col_picture($data) {
        global $OUTPUT;

        $user = new stdClass();
        $user->id           = $data->id;
        $user->picture      = $data->picture;
        $user->imagealt     = $data->imagealt;
        $user->firstname    = $data->firstname;
        $user->lastname     = $data->lastname;
        $user->email        = $data->email;

        $output = $OUTPUT->user_picture($user);

        $attr = array('type' => 'hidden',
                     'name' => 'users['.$data->id.']',
                     'value' => $data->id);
        $output .= html_writer::empty_tag('input', $attr);


        return $output;
    }

    function col_selectgrade($data) {
        global $CFG;

        $output      = '';
        $final_grade = false;

        if (array_key_exists($data->id, $this->_gradinginfo->items[0]->grades)) {

            $final_grade = $this->_gradinginfo->items[0]->grades[$data->id];

            if ($CFG->enableoutcomes) {

                $final_grade->formatted_grade = $this->_gradinginfo->items[0]->grades[$data->id]->str_grade;
            } else {

                // Equation taken from mod/assignment/lib.php display_submissions()
                $final_grade->formatted_grade = round($final_grade->grade,2) . ' / ' . round($this->_grademax,2);
            }
        }

        if (!is_bool($final_grade) && ($final_grade->locked || $final_grade->overridden) ) {

            $locked_overridden = 'locked';

            if ($final_grade->overridden) {
                $locked_overridden = 'overridden';
            }
            $attr = array('id' => 'g'.$data->id,
                          'class' => $locked_overridden);


            $output = html_writer::tag('div', $final_grade->formatted_grade, $attr);


        } else if (!empty($this->_quickgrade)) {

            $attributes = array();

            $grades_menu = make_grades_menu($this->_cminstance->grade);

            $default = array(-1 => get_string('nograde'));

            $grade = null;

            if (!empty($data->timemarked)) {
                $grade = $data->grade;
            }

            $output = html_writer::select($grades_menu, 'menu['.$data->id.']', $grade, $default, $attributes);

        } else {

            $output = get_string('nograde');

            if (!empty($data->timemarked)) {
                $output = $this->display_grade($data->grade);
            }
        }


        return $output;
    }



    function col_submissioncomment($data) {
        global $OUTPUT;

        $output      = '';
        $final_grade = false;

        if (array_key_exists($data->id, $this->_gradinginfo->items[0]->grades)) {
            $final_grade = $this->_gradinginfo->items[0]->grades[$data->id];
        }

        if ( (!is_bool($final_grade) && ($final_grade->locked || $final_grade->overridden)) ) {

            $output = shorten_text(strip_tags($data->submissioncomment),15);

        } else if (!empty($this->_quickgrade)) {

            $param = array('id' => 'comments_' . $data->submitid,
                           'rows' => $this->_rows,
                           'cols' => $this->_cols,
                           'name' => 'submissioncomment['.$data->id.']');

            $output .= html_writer::start_tag('textarea', $param);
            $output .= $data->submissioncomment;
            $output .= html_writer::end_tag('textarea');

        } else {
            $output = shorten_text(strip_tags($data->submissioncomment),15);
        }

        return $output;
    }

    function col_grademarked($data) {

        $output = '';

        if (!empty($data->timemarked)) {
            $output = userdate($data->timemarked);
        }

        return $output;
    }

    function col_timemodified($data) {

        $attr = array('id' => 'ts'.$data->id);

        $date_modified = $data->timemodified;
        $date_modified = is_null($date_modified) || empty($data->timemodified) ?
                            '' : userdate($date_modified);

        $output = html_writer::tag('div', $date_modified, $attr);

        $output .= html_writer::empty_tag('br');
        $output .= html_writer::start_tag('center');

        if (!empty($data->entry_id)) {

            $note = '';

            $attr = array('id' => 'video_' .$data->entry_id,
                          'class' => 'video_thumbnail_cl',
                          'style' => 'cursor:pointer;',
                          /*'style' => 'z-index: -2'*/);


            // Check if connection to Kaltura can be established
            if ($this->_connection) {

                if (!array_key_exists($data->entry_id, $this->_entries)) {
                    $note = get_string('grade_video_not_cache', 'kalvidassign');
    
                    // If the entry has not yet been cached, force a call to retrieve the entry object
                    // from the Kaltura server so that the thumbnail can be displayed
                    $entry_object = local_kaltura_get_ready_entry_object($data->entry_id, false);
                    $attr['src'] = $entry_object->thumbnailUrl;
                    $attr['alt'] = $entry_object->name;
                    $attr['title'] = $entry_object->name;
                } else {
                    // Retrieve object from cache
                    $attr['src'] = $this->_entries[$data->entry_id]->thumbnailUrl;
                    $attr['alt'] = $this->_entries[$data->entry_id]->name;
                    $attr['title'] = $this->_entries[$data->entry_id]->name;
                }

                $output .= html_writer::tag('p', $note);

                $output .= html_writer::empty_tag('img', $attr);
            } else {
                $output .= html_writer::tag('p', get_string('cannotdisplaythumbnail', 'kalvidassign'));
            }

            $attr = array('id' => 'hidden_video_' .$data->entry_id,
                          'type' => 'hidden',
                          'value' => $data->entry_id,);
            $output .= html_writer::empty_tag('input', $attr);
        }


        $output .= html_writer::end_tag('center');


        return $output;
    }

    function col_grade($data) {
        $final_grade = false;

        if (array_key_exists($data->id, $this->_gradinginfo->items[0]->grades)) {
            $final_grade = $this->_gradinginfo->items[0]->grades[$data->id];
        }

        $final_grade = (!is_bool($final_grade)) ? $final_grade->str_grade : '-';

        $attr = array('id' => 'finalgrade_'.$data->id);
        $output = html_writer::tag('span', $final_grade, $attr);

        return $output;
    }

    function col_timemarked($data) {

        $output = '-';

        if (0 < $data->timemarked) {

                $attr = array('id' => 'tt'.$data->id);
                $output = html_writer::tag('div', userdate($data->timemarked), $attr);

        } else {
            $otuput = '-';
        }

        return $output;
    }


    function col_status($data) {
        global $OUTPUT, $CFG;

        require_once(dirname(dirname(dirname(__FILE__))) . '/lib/weblib.php');

        $url = new moodle_url('/mod/kalvidassign/single_submission.php',
                                    array('cmid' => $this->_cminstance->cmid,
                                          'userid' => $data->id,
                                          'sesskey' => sesskey()));

        if (!empty($this->_tifirst)) {
            $url->param('tifirst', $this->_tifirst);
        }

        if (!empty($this->_tilast)) {
            $url->param('tilast', $this->_tilast);
        }

        if (!empty($this->_page)) {
            $url->param('page', $this->_page);
        }


        $buttontext = '';
        if ($data->timemarked > 0) {
            $class = 's1';
            $buttontext = get_string('update');
        } else {
            $class = 's0';
            $buttontext  = get_string('grade');
        }

        $attr = array('id' => 'up'.$data->id,
                      'class' => $class);

        $output = html_writer::link($url, $buttontext, $attr);

        return $output;

    }

    /**
     *  Return a grade in user-friendly form, whether it's a scale or not
     *
     * @global object
     * @param mixed $grade
     * @return string User-friendly representation of grade
     *
     * TODO: Move this to locallib.php
     */
    function display_grade($grade) {
        global $DB;

        static $kalscalegrades = array();   // Cache scales for each assignment - they might have different scales!!

        if ($this->_cminstance->grade >= 0) {    // Normal number
            if ($grade == -1) {
                return '-';
            } else {
                return $grade.' / '.$this->_cminstance->grade;
            }

        } else {                                // Scale

            if (empty($kalscalegrades[$this->_cminstance->id])) {

                if ($scale = $DB->get_record('scale', array('id'=>-($this->_cminstance->grade)))) {

                    $kalscalegrades[$this->_cminstance->id] = make_menu_from_list($scale->scale);
                } else {

                    return '-';
                }
            }

            if (isset($kalscalegrades[$this->_cminstance->id][$grade])) {
                return $kalscalegrades[$this->_cminstance->id][$grade];
            }
            return '-';
        }
    }

}

class mod_kalvidassign_renderer extends plugin_renderer_base {

    function display_submission($kalvideoobj, $userid, $entry_obj = null) {
        global $CFG;

        $img_source = '';
        $img_name   = '';

        $html = '';
        $html .= html_writer::start_tag('p');
        $html .= html_writer::start_tag('center');

        // tabindex -1 is required in order for the focus event to be capture
        // amongst all browsers
        $attr = array('id' => 'notification',
                      'class' => 'notification',
                      'tabindex' => '-1');
        $html .= html_writer::tag('div', '', $attr);

        if (!empty($entry_obj)) {

            $img_name   = $entry_obj->name;
            $img_source = $entry_obj->thumbnailUrl;

        } else {
            $img_name   = 'Video submission';
            $img_source = $CFG->wwwroot . '/local/kaltura/pix/vidThumb.png';
        }


        $attr = array('id' => 'video_thumbnail',
                      'src' => $img_source,
                      'alt' => $img_name,
                      'title' => $img_name,
                      'style' => 'z-index: -2');

        $html .= html_writer::empty_tag('img', $attr);


        $html .= html_writer::end_tag('center');
        $html .= html_writer::end_tag('p');

        return $html;

    }

    function display_mod_info($kalvideoobj, $context) {
        global $DB;
        $html = '';

        if (!empty($kalvideoobj->timeavailable)) {
            $html .= html_writer::start_tag('p');
            $html .= html_writer::tag('b', get_string('availabledate', 'kalvidassign') . ': ');
            $html .= userdate($kalvideoobj->timeavailable);
            $html .= html_writer::end_tag('p');
        }

        if (!empty($kalvideoobj->timedue)) {
            $html .= html_writer::start_tag('p');
            $html .= html_writer::tag('b', get_string('duedate', 'kalvidassign') . ': ');
            $html .= userdate($kalvideoobj->timedue);
            $html .= html_writer::end_tag('p');
        }

        // Display a count of the numuber of submissions
        if (has_capability('mod/kalvidassign:gradesubmission', $context)) {

            $param = array('vidassignid' => $kalvideoobj->id,
                           'timecreated' => 0,
                           'timemodified' => 0);

            $csql = "SELECT COUNT(*) ".
                    "FROM {kalvidassign_submission} ".
                    "WHERE vidassignid = :vidassignid ".
                    "  AND (timecreated > :timecreated ".
                    "  OR timemodified > :timemodified) ";

            $count = $DB->count_records_sql($csql, $param);

            if ($count) {
                $html .= html_writer::start_tag('p');
                $html .= get_string('numberofsubmissions', 'kalvidassign', $count);
                $html .= html_writer::end_tag('p');
            }

        }

        return $html;
    }

    function display_student_submit_buttons($cm, $userid, $disablesubmit = false) {

        $html = '';

        $target = new moodle_url('/mod/kalvidassign/submission.php');

        $attr = array('method'=>'POST', 'action'=>$target);

        $html .= html_writer::start_tag('form', $attr);

        $attr = array('type' => 'hidden',
                     'name' => 'entry_id',
                     'id' => 'entry_id',
                     'value' => '');
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden',
                     'name' => 'cmid',
                     'value' => $cm->id);
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden',
                     'name' => 'sesskey',
                     'value' => sesskey());
        $html .= html_writer::empty_tag('input', $attr);

        $html .= html_writer::start_tag('center');

        $html .= html_writer::start_tag('table');

        // Check of KSR is enabled via config or capability
        $enable_ksr = get_config(KALTURA_PLUGIN_NAME, 'enable_screen_recorder');
        $context    = get_context_instance(CONTEXT_MODULE, $cm->id);
        

        if ($enable_ksr && has_capability('mod/kalvidassign:screenrecorder', $context)) {

            $html .= html_writer::start_tag('tr');
            $html .= html_writer::start_tag('td');
            $attr = array('type' => 'radio',
                          'name' => 'media_method',
                          'id' => 'id_media_method_1',
                          'value' => '1');
            $html .= html_writer::empty_tag('input', $attr);
            $html .= html_writer::end_tag('td');
    
            $html .= html_writer::start_tag('td');
            $attr = array('for' => 'id_media_method_1');
            $html .= html_writer::tag('label', get_string('use_screen_recorder', 'kalvidassign'), $attr);
            $html .= html_writer::end_tag('td');
            $html .= html_writer::end_tag('tr');
        }

        $html .= html_writer::start_tag('tr');
        $html .= html_writer::start_tag('td');
        $attr = array('type' => 'radio',
                      'name' => 'media_method',
                      'id' => 'id_media_method_0',
                      'value' => '0',
                      'checked' => 'checked');
        $html .= html_writer::empty_tag('input', $attr);
        $html .= html_writer::end_tag('td');

        $html .= html_writer::start_tag('td');
        $attr = array('for' => 'id_media_method_0');
        $html .= html_writer::tag('label', get_string('use_kcw', 'kalvidassign'), $attr);
        $html .= html_writer::end_tag('td');
        $html .= html_writer::end_tag('tr');
        $html .= html_writer::end_tag('table');

        $attr = array('type' => 'button',
                     'id' => 'add_video',
                     'name' => 'add_video',
                     'value' => get_string('addvideo', 'kalvidassign'));

        if ($disablesubmit) {
            $attr['disabled'] = 'disabled';
        }

        $html .= html_writer::empty_tag('input', $attr);

        $html .= '&nbsp;&nbsp;';

        $attr = array('type' => 'button',
                      'id'   => 'preview_video',
                      'name' => 'preview_video',
                      'disabled' => 'disabled',
                      'value' => get_string('previewvideo', 'kalvidassign'));

        $html .= html_writer::empty_tag('input', $attr);

        $html .= '&nbsp;&nbsp;';

        $attr = array('type' => 'submit',
                     'name' => 'submit_video',
                     'id' => 'submit_video',
                     'disabled' => 'disabled',
                     'value' => get_string('submitvideo', 'kalvidassign'));

        $html .= html_writer::empty_tag('input', $attr);

        $html .= html_writer::end_tag('center');

        $html .= html_writer::end_tag('form');


        return $html;
    }

    function display_student_resubmit_buttons($cm, $userid, $disablesubmit = false) {
        global $DB;

        $param = array('vidassignid' => $cm->instance, 'userid' => $userid);
        $submissionrec = $DB->get_record('kalvidassign_submission', $param);

        $html = '';

        $target = new moodle_url('/mod/kalvidassign/submission.php');

        $attr = array('method'=>'POST', 'action'=>$target);

        $html .= html_writer::start_tag('form', $attr);

        $attr = array('type' => 'hidden',
                     'name'  => 'cmid',
                     'value' => $cm->id);
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden',
                     'name'  => 'entry_id',
                     'id'    => 'entry_id',
                     'value' => $submissionrec->entry_id);
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden',
                     'name'  => 'sesskey',
                     'value' => sesskey());
        $html .= html_writer::empty_tag('input', $attr);

        $html .= html_writer::start_tag('center');

        // Add media type radio buttons
        $html .= html_writer::start_tag('table');

        // Check of KSR is enabled via config or capability
        $enable_ksr = get_config(KALTURA_PLUGIN_NAME, 'enable_screen_recorder');
        $context    = get_context_instance(CONTEXT_MODULE, $cm->id);
        

        if ($enable_ksr && has_capability('mod/kalvidassign:screenrecorder', $context)) {

            $html .= html_writer::start_tag('tr');
            $html .= html_writer::start_tag('td');
            $attr = array('type'  => 'radio',
                          'name'  => 'media_method',
                          'id'    => 'id_media_method_1',
                          'value' => '1');
    
            if ($disablesubmit) {
                $attr['disabled'] = 'disabled';
            }
    
            $html .= html_writer::empty_tag('input', $attr);
            $html .= html_writer::end_tag('td');
    
            $html .= html_writer::start_tag('td');
            $attr = array('for' => 'id_media_method_1');
            $html .= html_writer::tag('label', get_string('use_screen_recorder', 'kalvidassign'), $attr);
            $html .= html_writer::end_tag('td');
            $html .= html_writer::end_tag('tr');
        }


        $html .= html_writer::start_tag('tr');
        $html .= html_writer::start_tag('td');
        $attr = array('type'    => 'radio',
                      'name'    => 'media_method',
                      'id'      => 'id_media_method_0',
                      'value'   => '0',
                      'checked' => 'checked');

        if ($disablesubmit) {
            $attr['disabled'] = 'disabled';
        }

        $html .= html_writer::empty_tag('input', $attr);
        $html .= html_writer::end_tag('td');

        $html .= html_writer::start_tag('td');
        $attr = array('for' => 'id_media_method_0');
        $html .= html_writer::tag('label', get_string('use_kcw', 'kalvidassign'), $attr);
        $html .= html_writer::end_tag('td');
        $html .= html_writer::end_tag('tr');
        $html .= html_writer::end_tag('table');

        // Add submit and review buttons
        $attr = array('type' => 'button',
                     'name' => 'replace_video',
                     'id' => 'replace_video',
                     'value' => get_string('replacevideo', 'kalvidassign'));

        if ($disablesubmit) {
            $attr['disabled'] = 'disabled';
        }

        $html .= html_writer::empty_tag('input', $attr);

        $html .= '&nbsp;&nbsp;';

        $attr = array('type' => 'button',
                      'id'   => 'preview_video',
                      'name' => 'preview_video',
                      'value' => get_string('reviewvideo', 'kalvidassign'));

        $html .= html_writer::empty_tag('input', $attr);

        $html .= '&nbsp;&nbsp;';

        $attr = array('type' => 'submit',
                     'id'   => 'submit_video',
                     'name' => 'submit_video',
                     'disabled' => 'disabled',
                     'value' => get_string('submitvideo', 'kalvidassign'));

        if ($disablesubmit) {
            $attr['disabled'] = 'disabled';
        }


        $html .= html_writer::empty_tag('input', $attr);

        $html .= html_writer::end_tag('center');

        $html .= html_writer::end_tag('form');

        return $html;

    }

    function display_instructor_buttons($cm,  $userid) {

        $html = '';

        $target = new moodle_url('/mod/kalvidassign/grade_submissions.php');

        $attr = array('method'=>'POST', 'action'=>$target);

        $html .= html_writer::start_tag('form', $attr);

        $html .= html_writer::start_tag('center');

        $attr = array('type' => 'hidden',
                     'name' => 'sesskey',
                     'value' => sesskey());
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'hidden',
                     'name' => 'cmid',
                     'value' => $cm->id);
        $html .= html_writer::empty_tag('input', $attr);

        $attr = array('type' => 'submit',
                     'name' => 'grade_submissions',
                     'value' => get_string('gradesubmission', 'kalvidassign'));

        $html .= html_writer::empty_tag('input', $attr);

        $html .= html_writer::end_tag('center');

        $html .= html_writer::end_tag('form');

        return $html;
    }

    function display_submissions_table($cm, $group_filter = 0, $filter = 'all', $perpage, $quickgrade = false,
                                       $tifirst = '', $tilast = '', $page = 0) {

        global $DB, $OUTPUT, $COURSE, $USER;

        // Get a list of users who have submissions and retrieve grade data for those users
        $users = kalvidassign_get_submissions($cm->instance, $filter);

        $define_columns = array('picture', 'fullname', 'selectgrade', 'submissioncomment', 'timemodified',
                                'timemarked', 'status', 'grade');

        if (empty($users)) {
            $users = array();
        }

        $entryids = array();
        $entries = array();
        foreach ($users as $usersubmission) {
            $entryids[$usersubmission->entry_id] = $usersubmission->entry_id;
        }

        if (!empty($entryids)) {
            $client_obj = local_kaltura_login(true);

            if ($client_obj) {
                $entries = new KalturaStaticEntries();
                $entries = KalturaStaticEntries::listEntries($entryids, $client_obj->baseEntry);
            } else {
                echo $OUTPUT->notification(get_string('conn_failed_alt', 'local_kaltura'));
            }
        }

        // Compare student who have submitted to the assignment with students who are
        // currently enrolled in the course
        $students = kalvidassign_get_assignment_students($cm);
        $users = array_intersect(array_keys($users), array_keys($students));


        if (empty($users)) {
            echo html_writer::tag('p', get_string('noenrolledstudents', 'kalvidassign'));
            return;
        }


        $grading_info = grade_get_grades($cm->course, 'mod', 'kalvidassign', $cm->instance, $users);

        $where = '';
        switch ($filter) {
            case KALASSIGN_SUBMITTED:
                $where = ' kvs.timemodified > 0 AND ';
                break;
            case KALASSIGN_REQ_GRADING:
                $where = ' kvs.timemarked < kvs.timemodified AND ';
                break;
        }

        // Determine logic needed for groups mode
        $param         = array();
        $groups_where  = '';
        $groups_column = '';
        $groups_join   = '';
        $groups        = array();
        $group_ids     = '';
        $context       = get_context_instance(CONTEXT_COURSE, $COURSE->id);

        // Get all groups that the user belongs to, check if the user has capability to access all groups
        if (!has_capability('moodle/site:accessallgroups', $context, $USER->id)) {
            $groups    = groups_get_all_groups($COURSE->id, $USER->id);

            if (empty($groups)) {
                $message = get_string('nosubmissions', 'kalvidassign');
                echo html_writer::tag('center', $message);
                return;
            }
        } else {
            $groups = groups_get_all_groups($COURSE->id);            
        }

        // Create a comma separated list of group ids
        foreach ($groups as $group) {
            $group_ids .= $group->id . ',';
        }

        $group_ids = rtrim($group_ids, ',');

        switch (groups_get_activity_groupmode($cm)) {
            case NOGROUPS:
                // No groups, do nothing
                break;
            case SEPARATEGROUPS:

                // If separate groups, but displaying all users then we must display only users
                // who are in the same group as the current user
                if (0 == $group_filter) {
                    $groups_column = ', gm.groupid ';
                    $groups_join   = ' RIGHT JOIN {groups_members} gm ON gm.userid = u.id RIGHT JOIN {groups} g ON g.id = gm.groupid ';

                    $param['courseid'] = $cm->course;
                    $groups_where  .= ' AND g.courseid = :courseid ';

                    $param['groupid'] = $group_filter;
                    $groups_where .= ' AND g.id IN ('.$group_ids.') ';

                }

            case VISIBLEGROUPS:

                // if visible groups but displaying a specific group then we must display users within
                // that group, if displaying all groups then display all users in the course
                if (0 != $group_filter) {

                    $groups_column = ', gm.groupid ';
                    $groups_join   = ' RIGHT JOIN {groups_members} gm ON gm.userid = u.id RIGHT JOIN {groups} g ON g.id = gm.groupid ';

                    $param['courseid'] = $cm->course;
                    $groups_where  .= ' AND g.courseid = :courseid ';

                    $param['groupid'] = $group_filter;
                    $groups_where .= ' AND gm.groupid = :groupid ';

                }
                break;
        }

        $kaltura        = new kaltura_connection();
        $connection     = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);

        $table          = new submissions_table('kal_vid_submit_table', $cm, $grading_info, $quickgrade,
                                                $tifirst, $tilast, $page, $entries, $connection);

        // In order for the sortable first and last names to work.  User ID has to be the first column returned and must be
        // returned as id.  Otherwise the table will display links to user profiles that are incorrect or do not exist
        $columns        = 'u.id, kvs.id AS submitid, u.firstname, u.lastname, u.picture, u.imagealt, u.email, '.
                          ' kvs.grade, kvs.submissioncomment, kvs.timemodified, kvs.entry_id, kvs.timemarked, '.
                          '1 AS status, 1 AS selectgrade' . $groups_column;
        $where          .= ' u.deleted = 0 AND u.id IN ('.implode(',', $users).') ' . $groups_where;


        $param['instanceid'] = $cm->instance;
        $from = "{user} u LEFT JOIN {kalvidassign_submission} kvs ON kvs.userid = u.id AND kvs.vidassignid = :instanceid ".
                $groups_join;

        $baseurl        = new moodle_url('/mod/kalvidassign/grade_submissions.php',
                                        array('cmid' => $cm->id));

        $col1 = get_string('fullname', 'kalvidassign');
        $col2 = get_string('grade', 'kalvidassign');
        $col3 = get_string('submissioncomment', 'kalvidassign');
        $col4 = get_string('timemodified', 'kalvidassign');
        $col5 = get_string('grademodified', 'kalvidassign');
        $col6 = get_string('status', 'kalvidassign');
        $col7 = get_string('finalgrade', 'kalvidassign');

        $table->set_sql($columns, $from, $where, $param);
        $table->define_baseurl($baseurl);
        $table->collapsible(true);

        $table->define_columns($define_columns);
        $table->define_headers(array('', $col1, $col2, $col3, $col4, $col5, $col6, $col7));

        echo html_writer::start_tag('center');

        $attributes = array('action' => new moodle_url('grade_submissions.php'),
                            'id'     => 'fastgrade',
                            'method' => 'post');
        echo html_writer::start_tag('form', $attributes);

        $attributes = array('type' => 'hidden',
                            'name' => 'cmid',
                            'value' => $cm->id);
        echo html_writer::empty_tag('input', $attributes);

        $attributes['name'] = 'mode';
        $attributes['value'] = 'fastgrade';

        echo html_writer::empty_tag('input', $attributes);

        $attributes['name'] = 'sesskey';
        $attributes['value'] = sesskey();

        echo html_writer::empty_tag('input', $attributes);

        $table->out($perpage, true);

        if ($quickgrade) {
            $attributes = array('type' => 'submit',
                                'name' => 'save_feedback',
                                'value' => get_string('savefeedback', 'kalvidassign'));

            echo html_writer::empty_tag('input', $attributes);
        }

        echo html_writer::end_tag('form');

        echo html_writer::end_tag('center');
    }

    /**
     * Displays the assignments listing table.
     *
     * @param object $course The course odject.
     */
    public function display_kalvidassignments_table($course) {
        global $CFG, $DB, $PAGE, $OUTPUT, $USER;

        echo html_writer::start_tag('center');

        $strplural = get_string('modulenameplural', 'kalvidassign');

        if (!$cms = get_coursemodules_in_course('kalvidassign', $course->id, 'm.timedue')) {
            echo get_string('noassignments', 'mod_kalvidassign');
            echo $OUTPUT->continue_button($CFG->wwwroot.'/course/view.php?id='.$course->id);
        }

        $strsectionname  = get_string('sectionname', 'format_'.$course->format);
        $usesections = course_format_uses_sections($course->format);
        $modinfo = get_fast_modinfo($course);

        if ($usesections) {
            $sections = $modinfo->get_section_info_all();
        }
        $courseindexsummary = new kalvidassign_course_index_summary($usesections, $strsectionname);

        $timenow = time();
        $currentsection = '';
        $assignmentcount = 0;

        foreach ($modinfo->instances['kalvidassign'] as $cm) {
            if (!$cm->uservisible) {
                continue;
            }

            $assignmentcount++;
            $timedue = $cms[$cm->id]->timedue;

            $sectionname = '';
            if ($usesections && $cm->sectionnum) {
                $sectionname = get_section_name($course, $sections[$cm->sectionnum]);
            }

            $submitted = '';
            $context = context_module::instance($cm->id);

            if (has_capability('mod/kalvidassign:gradesubmission', $context)) {
                $submitted = $DB->count_records('kalvidassign_submission', array('vidassignid' => $cm->instance));
            } else if (has_capability('mod/kalvidassign:submit', $context)) {
                if ($DB->count_records('kalvidassign_submission', array('vidassignid' => $cm->instance, 'userid' => $USER->id)) > 0) {
                    $submitted = get_string('submitted', 'mod_kalvidassign');
                } else {
                    $submitted = get_string('nosubmission', 'mod_kalvidassign');
                }
            }

            $gradinginfo = grade_get_grades($course->id, 'mod', 'kalvidassign', $cm->instance, $USER->id);
            if (isset($gradinginfo->items[0]->grades[$USER->id]) && !$gradinginfo->items[0]->grades[$USER->id]->hidden ) {
                $grade = $gradinginfo->items[0]->grades[$USER->id]->str_grade;
            } else {
                $grade = '-';
            }

            $courseindexsummary->add_assign_info($cm->id, $cm->name, $sectionname, $timedue, $submitted, $grade);
        }

        if ($assignmentcount > 0) {
            $pagerenderer = $PAGE->get_renderer('mod_kalvidassign');
            echo $pagerenderer->render($courseindexsummary);
        }

        echo html_writer::end_tag('center');
    }

    /**
     * Displays the YUI panel markup used to display the KCW
     *
     * @return string - HTML markup
     */
    function display_kcw_panel_markup() {

        $output = '';

        $attr = array('id' => 'video_panel');
        $output .= html_writer::start_tag('div', $attr);

        $attr = array('class' => 'hd');
        $output .= html_writer::tag('div', '', $attr);

        $attr = array('class' => 'bd');
        $output .= html_writer::tag('div', '', $attr);

        $output .= html_writer::end_tag('div');

        return $output;
    }

    /**
     * Displays the YUI panel markup used to display embedded video markup
     *
     * @return string - HTML markup
     */
    function display_video_preview_markup() {
        $output = '';

        $attr = array('id' => 'id_video_preview',
                      'class' => 'video_preview');
        $output .= html_writer::start_tag('div', $attr);

        $attr = array('class' => 'hd');
        $output .= html_writer::tag('div', get_string('video_preview_header', 'kalvidassign'), $attr);

        $attr = array('class' => 'bd');
        $output .= html_writer::tag('div', '', $attr);

        $output .= html_writer::end_tag('div');

        return $output;

    }

    /**
     * Displays the YUI panel markup used to display loading screen
     *
     * @return string - HTML markup
     */
    function display_loading_markup() {
        // Panel wait markup
        $output = '';

        $output .= html_writer::end_tag('div');

        $attr = array('id' => 'wait');
        $output .=  html_writer::start_tag('div', $attr);

        $attr = array('class' => 'hd');
        $output .= html_writer::tag('div', '', $attr);

        $attr = array('class' => 'bd');

        $output .= html_writer::tag('div', '', $attr);

        $output .= html_writer::end_tag('div');

        return $output;
    }

    function display_all_panel_markup() {
        $output = $this->display_kcw_panel_markup();
        $output .= $this->display_video_preview_markup();
        $output .= $this->display_loading_markup();

        return $output;
    }

    /**
     * Display the feedback to the student
     *
     * This default method prints the teacher picture and name, date when marked,
     * grade and teacher submissioncomment.
     *
     * @global object
     * @global object
     * @global object
     * @param object $submission The submission object or NULL in which case it will be loaded
     *
     * TODO: correct documentation for this function
     */
    function display_grade_feedback($kalvidassign, $context) {
        global $USER, $CFG, $DB, $OUTPUT;

        require_once($CFG->libdir.'/gradelib.php');

        // Check if the user is enrolled to the coruse and can submit to the assignment
        if (!is_enrolled($context, $USER, 'mod/kalvidassign:submit')) {
            // can not submit assignments -> no feedback
            return;
        }

        // Get the user's submission obj
        //$submission = kalvidassign_get_submission($kalvidassign->id, $USER->id);

        $grading_info = grade_get_grades($kalvidassign->course, 'mod', 'kalvidassign', $kalvidassign->id, $USER->id);

        $item = $grading_info->items[0];
        $grade = $item->grades[$USER->id];

        if ($grade->hidden or $grade->grade === false) { // hidden or error
            return;
        }

        if ($grade->grade === null and empty($grade->str_feedback)) {   /// Nothing to show yet
            return;
        }

        $graded_date = $grade->dategraded;
        $graded_by   = $grade->usermodified;

    /// We need the teacher info
        if (!$teacher = $DB->get_record('user', array('id'=>$graded_by))) {
            print_error('cannotfindteacher');
        }

    /// Print the feedback
        echo $OUTPUT->heading(get_string('feedbackfromteacher', 'assignment', fullname($teacher)));

        echo '<table cellspacing="0" class="feedback">';

        echo '<tr>';
        echo '<td class="left picture">';
        if ($teacher) {
            echo $OUTPUT->user_picture($teacher);
        }
        echo '</td>';
        echo '<td class="topic">';
        echo '<div class="from">';
        if ($teacher) {
            echo '<div class="fullname">'.fullname($teacher).'</div>';
        }
        echo '<div class="time">'.userdate($graded_date).'</div>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';

        echo '<tr>';
        echo '<td class="left side">&nbsp;</td>';
        echo '<td class="content">';
        echo '<div class="grade">';
        echo get_string("grade").': '.$grade->str_long_grade;
        echo '</div>';
        echo '<div class="clearer"></div>';

        echo '<div class="comment">';
        echo $grade->str_feedback;
        echo '</div>';
        echo '</tr>';

        echo '</table>';
    }

    function render_progress_bar() {

        // Add progress bar
        $output = '';

        $attr         = array('id' => 'progress_bar');
        $progress_bar = html_writer::tag('span', '', $attr);

        $attr          = array('id' => 'slider_border');
        $slider_border = html_writer::tag('div', $progress_bar, $attr);

        $attr          = array('id' => 'loading_text');
        $loading_text  = html_writer::tag('div', get_string('checkingforjava', 'mod_kalvidassign'), $attr);

        $attr   = array('id' => 'progress_bar_container',
                        'style' => 'width:100%; padding-left:10px; padding-right:10px; visibility: hidden');
        $output = '<br /><center>' .html_writer::tag('span', $slider_border . $loading_text, $attr) . '</center>';

        return $output;
    }

    /**
     * Render a course index summary.
     *
     * @param kalvidassign_course_index_summary $indexsummary Structure for index summary.
     * @return string HTML for assignments summary table
     */
    public function render_kalvidassign_course_index_summary(kalvidassign_course_index_summary $indexsummary) {
        $strplural = get_string('modulenameplural', 'kalvidassign');
        $strsectionname  = $indexsummary->courseformatname;
        $strduedate = get_string('duedate', 'kalvidassign');
        $strsubmission = get_string('submission', 'kalvidassign');
        $strgrade = get_string('grade');

        $table = new html_table();
        if ($indexsummary->usesections) {
            $table->head  = array ($strsectionname, $strplural, $strduedate, $strsubmission, $strgrade);
            $table->align = array ('left', 'left', 'center', 'right', 'right');
        } else {
            $table->head  = array ($strplural, $strduedate, $strsubmission, $strgrade);
            $table->align = array ('left', 'left', 'center', 'right');
        }
        $table->data = array();

        $currentsection = '';
        foreach ($indexsummary->assignments as $info) {
            $params = array('id' => $info['cmid']);
            $link = html_writer::link(new moodle_url('/mod/kalvidassign/view.php', $params), $info['cmname']);
            $due = $info['timedue'] ? userdate($info['timedue']) : '-';

            $printsection = '';
            if ($indexsummary->usesections) {
                if ($info['sectionname'] !== $currentsection) {
                    if ($info['sectionname']) {
                        $printsection = $info['sectionname'];
                    }
                    if ($currentsection !== '') {
                        $table->data[] = 'hr';
                    }
                    $currentsection = $info['sectionname'];
                }
            }

            if ($indexsummary->usesections) {
                $row = array($printsection, $link, $due, $info['submissioninfo'], $info['gradeinfo']);
            } else {
                $row = array($link, $due, $info['submissioninfo'], $info['gradeinfo']);
            }
            $table->data[] = $row;
        }

        return html_writer::table($table);
    }
}
