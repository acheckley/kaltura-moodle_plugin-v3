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
 * Kaltura video resource settings page
 *
 * @package    mod
 * @subpackage kalvidres
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once(dirname(dirname(dirname(__FILE__))) . '/course/moodleform_mod.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/locallib.php');

class mod_kalvidres_mod_form extends moodleform_mod {

    var $_default_player = false;

    function definition() {
        global $CFG, $COURSE, $PAGE;

        $kaltura = new kaltura_connection();
        $connection = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);

        $login_session = '';

        if (!empty($connection)) {
            $login_session = $connection->getKs();
        }

        $PAGE->requires->css('/mod/kalvidres/styles.css');

        $partner_id    = local_kaltura_get_partner_id();
        $sr_unconf_id  = local_kaltura_get_player_uiconf('mymedia_screen_recorder');
        $host = local_kaltura_get_host();
        $url = new moodle_url("{$host}/p/{$partner_id}/sp/{$partner_id}/ksr/uiconfId/{$sr_unconf_id}");
        
        // This line is needed to avoid a PHP warning when the form is submitted
        // Because this value is set as the default for one of the formslib elements
        $uiconf_id = '';
        
        // Check if connection to Kaltura can be established
        if ($connection) {

            $PAGE->requires->js($url, true);
            $PAGE->requires->js('/local/kaltura/js/screenrecorder.js', true);
    
            $PAGE->requires->js('/local/kaltura/js/jquery.js', true);
            $PAGE->requires->js('/local/kaltura/js/swfobject.js', true);
            $PAGE->requires->js('/local/kaltura/js/kcwcallback.js', true);
    
            $jsmodule = array(
                'name'     => 'local_kaltura',
                'fullpath' => '/local/kaltura/js/kaltura.js',
                'requires' => array('yui2-yahoo-dom-event',
                                    'yui2-container',
                                    'yui2-dragdrop',
                                    'yui2-animation',
                                    'base',
                                    'dom',
                                    'node',
                                    ),
                'strings' => array(
                        array('upload_successful', 'kalvidres'),
                        array('video_converting', 'kalvidres'),
                        array('previewvideo', 'kalvidres'),
                        array('javanotenabled', 'kalvidres'),
                        array('checkingforjava', 'kalvidres')
                )
            );
    
            $courseid = get_courseid_from_context($PAGE->context);
    
            $conversion_script = "../local/kaltura/check_conversion.php?courseid={$courseid}&entry_id=";
    
            $panel_markup           = $this->get_popup_markup();
            $kcw                    = local_kaltura_get_kcw('res_uploader', true);
            $uiconf_id              = local_kaltura_get_player_uiconf('player_resource');
            $progress_bar_markup    = $this->draw_progress_bar();
    
            $PAGE->requires->js_init_call('M.local_kaltura.video_resource',
                                          array($conversion_script, $panel_markup, $kcw, $uiconf_id, $login_session, $partner_id, $progress_bar_markup),
                                          true, $jsmodule);
        }
    
        if (local_kaltura_has_mobile_flavor_enabled() && local_kaltura_get_enable_html5()) {

            $url = new moodle_url(local_kaltura_htm5_javascript_url($uiconf_id));
            $PAGE->requires->js($url, true);
        }

        $mform =& $this->_form;

        /* Hidden fields */
        $attr = array('id' => 'entry_id');
        $mform->addElement('hidden', 'entry_id', '', $attr);
        $mform->setType('entry_id', PARAM_NOTAGS);

        $attr = array('id' => 'video_title');
        $mform->addElement('hidden', 'video_title', '', $attr);
        $mform->setType('video_title', PARAM_TEXT);

        $attr = array('id' => 'uiconf_id');
        $mform->addElement('hidden', 'uiconf_id', '', $attr);
        $mform->setDefault('uiconf_id', $uiconf_id);
        $mform->setType('uiconf_id', PARAM_INT);

        $attr = array('id' => 'widescreen');
        $mform->addElement('hidden', 'widescreen', '', $attr);
        $mform->setDefault('widescreen', 0);
        $mform->setType('widescreen', PARAM_INT);

        $attr = array('id' => 'height');
        $mform->addElement('hidden', 'height', '', $attr);
        $mform->setDefault('height', '365');
        $mform->setType('height', PARAM_TEXT);

        $attr = array('id' => 'width');
        $mform->addElement('hidden', 'width', '', $attr);
        $mform->setDefault('width', '400');
        $mform->setType('width', PARAM_TEXT);

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name', 'kalvidres'), array('size'=>'64'));

        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }

        $mform->addRule('name', null, 'required', null, 'client');

        $this->add_intro_editor(false);

        if (local_kaltura_login(true, '')) {
            $mform->addElement('header', 'video', get_string('video_hdr', 'kalvidres'));

            $this->add_video_definition($mform);
        } else {
            $mform->addElement('static', 'connection_fail', get_string('conn_failed_alt', 'local_kaltura'));
        }

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    private function draw_progress_bar() {
        $attr         = array('id' => 'progress_bar');
        $progress_bar = html_writer::tag('span', '', $attr);

        $attr          = array('id' => 'slider_border');
        $slider_border = html_writer::tag('div', $progress_bar, $attr);

        $attr          = array('id' => 'loading_text');
        $loading_text  = html_writer::tag('div', get_string('checkingforjava', 'mod_kalvidres'), $attr);

        $attr   = array('id' => 'progress_bar_container',
                        'style' => 'width:100%; padding-left:10px; padding-right:10px; visibility: hidden');
        $output = html_writer::tag('span', $slider_border . $loading_text, $attr);

        return $output;

    }

    private function add_video_definition($mform) {
        global $COURSE;

        $thumbnail = $this->get_thumbnail_markup();
        $prop      = array();

        $mform->addElement('static', 'add_video_thumb', '&nbsp;', $thumbnail);

        if (empty($this->current->entry_id)) {
            $prop = array('style' => 'display:none;');
        }

        $radioarray = array();
        $attributes = array();
        $enable_ksr = get_config(KALTURA_PLUGIN_NAME, 'enable_screen_recorder');
        $context    = null;

        // Check of KSR is enabled via config or capability
        if (!empty($this->_cm)) {
            $context       = get_context_instance(CONTEXT_MODULE, $this->_cm->id);
        } else {

            $context       = get_context_instance(CONTEXT_COURSE, $COURSE->id);
        }

        if ($enable_ksr && has_capability('mod/kalvidres:screenrecorder', $context)) {
            $radioarray[] =& $mform->createElement('radio', 'media_method', '', get_string('use_screen_recorder', 'kalvidres'), 1, $attributes);
        }

        $radioarray[] =& $mform->createElement('radio', 'media_method', '', get_string('use_kcw', 'kalvidres'), 0, $attributes);
        $mform->addGroup($radioarray, 'radioar', get_string('media_method', 'kalvidres'), array('<br />'), false);
        $mform->addHelpButton('radioar', 'media_creation', 'kalvidres');

        $videogroup = array();
        $videogroup[] =& $mform->createElement('button', 'add_video', get_string('add_video', 'kalvidres'));
        $videogroup[] =& $mform->createElement('button', 'video_properties', get_string('video_properties', 'kalvidres'), $prop);
        $videogroup[] =& $mform->createElement('button', 'video_preview', get_string('vide_preview', 'kalvidres'), $prop);

        $mform->addGroup($videogroup, 'video_group', '&nbsp;', '&nbsp;', false);

    }

    private function get_popup_markup() {

        $output = '';

        // Panel markup to load the KCW
        $attr = array('id' => 'video_panel');
        $output .=  html_writer::start_tag('div', $attr);

        $attr = array('class' => 'hd');
        $output .= html_writer::tag('div', '', $attr);

        $attr = array('class' => 'bd');
        $output .= html_writer::tag('div', '', $attr);

        $output .= html_writer::end_tag('div');

        // Panel markup to set video properties
        $attr = array('id' => 'video_properties_panel');
        $output .=  html_writer::start_tag('div', $attr);

        $attr = array('class' => 'hd');
        $output .= html_writer::tag('div', get_string('vid_prop_header', 'kalvidres'), $attr);

        $attr = array('class' => 'bd');

        $properties_markup = $this->get_video_preferences_markup();

        $output .= html_writer::tag('div', $properties_markup, $attr);

        $output .= html_writer::end_tag('div');

        // Panel markup to preview video
        $attr = array('id' => 'video_preview_panel');
        $output .=  html_writer::start_tag('div', $attr);

        $attr = array('class' => 'hd');
        $output .= html_writer::tag('div', get_string('video_preview_header', 'kalvidres'), $attr);

        $attr = array('class' => 'bd',
                      'id' => 'video_preview_body');

        $output .= html_writer::tag('div', '', $attr);

        // Panel wait markup
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

    private function get_thumbnail_markup() {
        global $CFG;

        $source = '';

        // tabindex -1 is required in order for the focus event to be capture
        // amongst all browsers
        $attr = array('id' => 'notification',
                      'class' => 'notification',
                      'tabindex' => '-1');
        $output = html_writer::tag('div', '', $attr);

        $source = $CFG->wwwroot . '/local/kaltura/pix/vidThumb.png';;
        $alt    = get_string('add_video', 'kalvidres');
        $title  = get_string('add_video', 'kalvidres');

        if (!empty($this->current->entry_id)) {

            $entries = new KalturaStaticEntries();

            $entry_obj = KalturaStaticEntries::getEntry($this->current->entry_id, null, false);

            if (isset($entry_obj->thumbnailUrl)) {
                $source = $entry_obj->thumbnailUrl;
                $alt    = $entry_obj->name;
                $title  = $entry_obj->name;
            }

        }

        $attr = array('id' => 'video_thumbnail',
                      'src' => $source,
                      'alt' => $alt,
                      'title' => $title,
                      );

        $output .= html_writer::empty_tag('img', $attr);

        return $output;

    }

    /**
     * This function returns an array of video resource players.
     *
     * If the override configuration option is checked, then this function will
     * only return a single array entry with the overridden player
     *
     * @param none
     *
     * @return array - First element will be an array whose keys are player ids
     * and values are player name.  Second element will be the default selected
     * player.  The default player is determined by the Kaltura configuraiton
     * settings (local_kaltura).
     */
    private function get_video_resource_players() {

        // Get user's players
        $players = local_kaltura_get_custom_players();

        // Kaltura regular player selection
        $choices = array(KALTURA_PLAYER_PLAYERREGULARDARK  => get_string('player_regular_dark', 'local_kaltura'),
                         KALTURA_PLAYER_PLAYERREGULARLIGHT => get_string('player_regular_light', 'local_kaltura'),
                         );

        if (!empty($players)) {
            $choices = $choices + $players;
        }

        // Set default player only if the user is adding a new activity instance
        $default_player_id = local_kaltura_get_player_uiconf('player_resource');

        // If the default player id does not exist in the list of choice
        // then the user must be using a custom player id, add it to the list
        if (!array_key_exists($default_player_id, $choices)) {
            $choices = $choices + array($default_player_id => get_string('custom_player', 'kalvidres'));
        }

        // Check if player selection is globally overridden
        if (local_kaltura_get_player_override()) {
            return array(array( $default_player_id => $choices[$default_player_id]),
                         $default_player_id
                        );
        }

        return array($choices, $default_player_id);

    }

    /**
     * Create player properties panel markup.  Default values are loaded from
     * the javascript (see function "handle_cancel" in kaltura.js
     *
     * @param - none
     *
     * @return string - html markup
     */
    private function get_video_preferences_markup() {
        $output = '';

        // Display name input box
        $attr = array('for' => 'vid_prop_name');
        $output .= html_writer::tag('label', get_string('vid_prop_name', 'kalvidres'), $attr);
        $output .= '&nbsp;';

        $attr = array('type' => 'text',
                      'id' => 'vid_prop_name',
                      'name' => 'vid_prop_name',
                      'value' => '',
                      'maxlength' => '100');
        $output .= html_writer::empty_tag('input', $attr);
        $output .= html_writer::empty_tag('br');
        $output .= html_writer::empty_tag('br');

        // Display section element for player design
        $attr = array('for' => 'vid_prop_player');
        $output .= html_writer::tag('label', get_string('vid_prop_player', 'kalvidres'), $attr);
        $output .= '&nbsp;';

        list($options, $default_option) = $this->get_video_resource_players();

        $attr = array('id' => 'vid_prop_player');

        $output .= html_writer::select($options, 'vid_prop_player', $default_option, false, $attr);
        $output .= html_writer::empty_tag('br');
        $output .= html_writer::empty_tag('br');

        // Display player dimensions radio buttons
        $attr = array('for' => 'vid_prop_dimensions');
        $output .= html_writer::tag('label', get_string('vid_prop_dimensions', 'kalvidres'), $attr);
        $output .= '&nbsp;';

        $options = array(0 => get_string('normal', 'kalvidres'),
                         1 => get_string('widescreen', 'kalvidres')
                         );

        $attr = array('id' => 'vid_prop_dimensions');
        $selected = !empty($defaults) ? $defaults['vid_prop_dimensions'] : array();
        $output .= html_writer::select($options, 'vid_prop_dimensions', $selected, array(), $attr);

        $output .= html_writer::empty_tag('br');
        $output .= html_writer::empty_tag('br');

        // Display player size drop down button
        $attr = array('for' => 'vid_prop_size');
        $output .= html_writer::tag('label', get_string('vid_prop_size', 'kalvidres'), $attr);
        $output .= '&nbsp;';

        $options = array(0 => get_string('vid_prop_size_large', 'kalvidres'),
                         1 => get_string('vid_prop_size_small', 'kalvidres'),
                         2 => get_string('vid_prop_size_custom', 'kalvidres')
                         );

        $attr = array('id' => 'vid_prop_size');
        $selected = !empty($defaults) ? $defaults['vid_prop_size'] : array();

        $output .= html_writer::select($options, 'vid_prop_size', $selected, array(), $attr);

        // Display custom player size
        $output .= '&nbsp;&nbsp;';

        $attr = array('type' => 'text',
                      'id' => 'vid_prop_width',
                      'name' => 'vid_prop_width',
                      'value' => '',
                      'maxlength' => '3',
                      'size' => '3',
                      );
        $output .= html_writer::empty_tag('input', $attr);

        $output .= '&nbsp;x&nbsp;';

        $attr = array('type' => 'text',
                      'id' => 'vid_prop_height',
                      'name' => 'vid_prop_height',
                      'value' => '',
                      'maxlength' => '3',
                      'size' => '3',
                      );
        $output .= html_writer::empty_tag('input', $attr);

        return $output;
    }

    private function get_default_video_properties() {
        return $properties = array('vid_prop_player' => 4674741,
                                   'vid_prop_dimensions' => 0,
                                   'vid_prop_size' => 0,
                                  );
    }

    function definition_after_data() {
        $mform = $this->_form;

        if (!empty($mform->_defaultValues['entry_id'])) {
            foreach ($mform->_elements as $key => $data) {

                if ($data instanceof MoodleQuickForm_group) {

                    foreach ($data->_elements as $key2 => $data2) {
                        if (0 == strcmp('add_video', $data2->_attributes['name'])) {
                            $mform->_elements[$key]->_elements[$key2]->setValue(get_string('replace_video', 'kalvidres'));
                            break;
                        }

                        if (0 == strcmp('pres_info', $data2->_attributes['name'])) {
                            $mform->_elements[$key]->_elements[$key2]->setValue('');
                            break;
                        }
                    }
                }

            }

        }


    }
}
