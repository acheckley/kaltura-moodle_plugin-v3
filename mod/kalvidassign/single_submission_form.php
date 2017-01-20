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
 * Kaltura video assignment single submission form
 *
 * @package    mod_kalvidassign
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once(dirname(dirname(dirname(__FILE__))) . '/course/moodleform_mod.php');

class kalvidassign_singlesubmission_form extends moodleform {

    /**
     * This function defines the forums elments that are to be displayed
     */
    public function definition() {
        global $CFG, $PAGE;

        $mform =& $this->_form;

        $cm = $this->_customdata->cm;
        $userid = $this->_customdata->userid;

        $mform->addElement('hidden', 'cmid', $cm->id);
        $mform->setType('cmid', PARAM_INT);
        $mform->addelement('hidden', 'userid', $userid);
        $mform->setType('userid', PARAM_INT);
        $mform->addElement('hidden', 'tifirst', $this->_customdata->tifirst);
        $mform->setType('tifirst', PARAM_TEXT);
        $mform->addElement('hidden', 'tilast', $this->_customdata->tilast);
        $mform->setType('tilast', PARAM_TEXT);
        $mform->addElement('hidden', 'page', $this->_customdata->page);
        $mform->setType('page', PARAM_INT);

        /* Submission section */
        $mform->addElement('header', 'single_submission_1', get_string('submission', 'kalvidassign'));

        $mform->addelement('static', 'submittinguser',
                           $this->_customdata->submissionuserpic,
                           $this->_customdata->submissionuserinfo);

        /* Video preview */
        $mform->addElement('header', 'single_submission_2', get_string('previewvideo', 'kalvidassign'));

        $submission     = $this->_customdata->submission;
        $grading_info   = $this->_customdata->grading_info;
        $entryobject   = '';
        $timemodified   = '';

        if (!empty($submission->entry_id)) {

            $kaltura        = new kaltura_connection();
            $connection     = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);
            
            if ($connection) {
                $entryobject = local_kaltura_get_ready_entry_object($this->_customdata->submission->entry_id);
    
                // Determine the type of video (See KALDEV-28)
                if (!local_kaltura_video_type_valid($entryobject)) {
                    $entryobject = local_kaltura_get_ready_entry_object($entryobject->id, false);
                }
            }

        }

        if (!empty($entryobject)) {
            list($entryobject->width, $entryobject->height) = kalvidassign_get_player_dimensions();
            $courseid = get_courseid_from_context($this->_customdata->context);

            // Set the session
            $session = local_kaltura_generate_kaltura_session(array($entryobject->id));


            $mform->addElement('static', 'description', get_string('submission', 'kalvidassign'),
                    local_kaltura_get_kdp_code($entryobject, 0, $courseid));

        } else if (empty($entryobject) && isset($submission->timemodified) && !empty($submission->timemodified)) {

            if ($connection) {
                // an empty entry object and a time modified timestamp means the video is still converting
                $mform->addElement('static', 'description', get_string('submission', 'kalvidassign'),
                                   get_string('video_converting', 'local_kaltura'));
            } else {

                $mform->addElement('static', 'description', get_string('submission', 'kalvidassign'),
                                   get_string('conn_failed_alt', 'local_kaltura'));
            }
        } else {

            // an empty entry object and an empty time modified tamstamp mean the student hasn't submitted anything
            $mform->addElement('static', 'description', get_string('submission', 'kalvidassign'),
                               '');
        }

        /* Grades section */
        $mform->addElement('header', 'single_submission_3', get_string('grades', 'kalvidassign'));

        $attributes = array();

        if ($this->_customdata->gradingdisabled || $this->_customdata->gradingdisabled) {
            $attributes['disabled'] = 'disabled';
        }

        $grademenu = make_grades_menu($this->_customdata->cminstance->grade);
        $grademenu['-1'] = get_string('nograde');

        $mform->addElement('select', 'xgrade', get_string('grade').':', $grademenu, $attributes);

        if (isset($submission->grade)) {
            $mform->setDefault('xgrade', $this->_customdata->submission->grade ); //@fixme some bug when element called 'grade' makes it break
        } else {
            $mform->setDefault('xgrade', '-1' ); //@fixme some bug when element called 'grade' makes it break
        }

        $mform->setType('xgrade', PARAM_INT);

        if (!empty($this->_customdata->enableoutcomes) && !empty($grading_info)) {

            foreach($grading_info->outcomes as $n => $outcome) {

                $options = make_grades_menu(-$outcome->scaleid);

                if (array_key_exists($this->_customdata->userid, $outcome->grades) &&
                    $outcome->grades[$this->_customdata->userid]->locked) {

                    $options[0] = get_string('nooutcome', 'grades');
                    echo $options[$outcome->grades[$this->_customdata->userid]->grade];

                } else {

                    $options[''] = get_string('nooutcome', 'grades');
                    $attributes = array('id' => 'menuoutcome_'.$n );
                    $mform->addElement('select', 'outcome_'.$n.'['.$this->_customdata->userid.']', $outcome->name.':', $options, $attributes );
                    $mform->setType('outcome_'.$n.'['.$this->_customdata->userid.']', PARAM_INT);

                    if (array_key_exists($this->_customdata->userid, $outcome->grades)) {
                        $mform->setDefault('outcome_'.$n.'['.$this->_customdata->userid.']', $outcome->grades[$this->_customdata->userid]->grade );
                    }
                }
            }
        }

        if (has_capability('gradereport/grader:view', $this->_customdata->context) &&
            has_capability('moodle/grade:viewall', $this->_customdata->context)) {


            if (empty($grading_info) || !array_key_exists($this->_customdata->userid, $grading_info->items[0]->grades)) {

                $grade = ' - ';

            } elseif (0 != strcmp('-', $grading_info->items[0]->grades[$this->_customdata->userid]->str_grade)) {

                $grade = '<a href="'.$CFG->wwwroot.'/grade/report/grader/index.php?id='. $this->_customdata->cm->course .'" >'.
                            $this->_customdata->grading_info->items[0]->grades[$this->_customdata->userid]->str_grade . '</a>';
            } else {

                $grade = $this->_customdata->grading_info->items[0]->grades[$this->_customdata->userid]->str_grade;
            }

        } else {

            $grade = $this->_customdata->grading_info->items[0]->grades[$this->_customdata->userid]->str_grade;

        }

        $mform->addElement('static', 'finalgrade', get_string('currentgrade', 'assignment').':' ,$grade);
        $mform->setType('finalgrade', PARAM_INT);

        /* Feedback section */
        $mform->addElement('header', 'single_submission_4', get_string('feedback', 'kalvidassign'));

        if (!empty($this->_customdata->gradingdisabled)) {

            if (array_key_exists($this->_customdata->userid, $grading_info->items[0]->grades)) {
                $mform->addElement('static', 'disabledfeedback', '&nbsp;', $grading_info->items[0]->grades[$this->_customdata->userid]->str_feedback );
            } else {
                $mform->addElement('static', 'disabledfeedback', '&nbsp;', '' );
            }

        } else {

            $mform->addElement('editor', 'submissioncomment_editor', get_string('feedback', 'kalvidassign').':', null, $this->get_editor_options() );
            $mform->setType('submissioncomment_editor', PARAM_RAW); // to be cleaned before display

        }


        /* Marked section */
        $mform->addElement('header', 'single_submission_5', get_string('lastgrade', 'kalvidassign'));

        $mform->addElement('static', 'markingteacher',
                           $this->_customdata->markingteacherpic,
                           $this->_customdata->markingteacherinfo);


        $this->add_action_buttons();
    }

    public function set_data($data) {

        if (!isset($data->submission->format)) {
            $data->textformat = FORMAT_HTML;
        } else {
            $data->textformat = $data->submission->format;
        }

        $editoroptions = $this->get_editor_options();

        return parent::set_data($data);

    }

    protected function get_editor_options() {

        $editoroptions = array();
        $editoroptions['component'] = 'mod_kalvidassign';
        //$editoroptions['filearea'] = 'feedback';
        $editoroptions['noclean'] = false;
        $editoroptions['maxfiles'] = 0; //TODO: no files for now, we need to first implement assignment_feedback area, integration with gradebook, files support in quickgrading, etc. (skodak)
        //$editoroptions['maxbytes'] = $this->_customdata->maxbytes;
        $editoroptions['context'] = $this->_customdata->context;

        return $editoroptions;
    }

}