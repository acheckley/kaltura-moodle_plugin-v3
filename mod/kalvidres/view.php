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
 * Kaltura video resource
 *
 * @package    mod
 * @subpackage kalvidres
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/locallib.php');

$id = optional_param('id', 0, PARAM_INT);           // Course Module ID

// Retrieve module instance
if (empty($id)) {
    print_error('invalidid', 'kalvidres');
}

if (!empty($id)) {

    if (! $cm = get_coursemodule_from_id('kalvidres', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('coursemisconf');
    }

    if (! $kalvidres = $DB->get_record('kalvidres', array("id"=>$cm->instance))) {
        print_error('invalidid', 'kalvidres');
    }
}

require_course_login($course->id, true, $cm);

global $SESSION, $CFG;

$PAGE->set_url('/mod/kalvidres/view.php', array('id'=>$id));
$PAGE->set_title(format_string($kalvidres->name));
$PAGE->set_heading($course->fullname);

// Try connection
$kaltura = new kaltura_connection();
$connection = $kaltura->get_connection(true, KALTURA_SESSION_LENGTH);

if ($connection) {
    if (local_kaltura_has_mobile_flavor_enabled() && local_kaltura_get_enable_html5()) {
        $uiconf_id = local_kaltura_get_player_uiconf('player_resource');
        $url = new moodle_url(local_kaltura_htm5_javascript_url($uiconf_id));
        $PAGE->requires->js($url, true);
        $url = new moodle_url('/local/kaltura/js/frameapi.js');
        $PAGE->requires->js($url, true);
    }
}

$context = $PAGE->context;

add_to_log($course->id, 'kalvidres', 'view video resource', 'view.php?id='.$cm->id, $kalvidres->id, $cm->id);

$completion = new completion_info($course);
$completion->set_module_viewed($cm);


echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('mod_kalvidres');

echo $OUTPUT->box_start('generalbox');

echo $renderer->display_mod_info($kalvidres->video_title);

echo format_module_intro('kalvidres', $kalvidres, $cm->id);

echo $OUTPUT->box_end();

if ($connection) {

    // Check if the repository plug-in exists.  Add Kaltura video to
    // the Kaltura category
    if (!empty($kalvidres->entry_id)) {

        $category = false;

        $enabled = local_kaltura_kaltura_repository_enabled();

        if ($enabled) {
            require_once($CFG->dirroot.'/repository/kaltura/locallib.php');

            // Create the course category
            $category = repository_kaltura_create_course_category($connection, $course->id);
        }

        if (!empty($category) && $enabled) {
            repository_kaltura_add_video_course_reference($connection, $course->id, array($kalvidres->entry_id));
        }
    }

    echo $renderer->embed_video($kalvidres);
} else {
    echo $renderer->connection_failure();
}

echo $OUTPUT->footer();


