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
 * Kaltura video assignment grade preferences form
 *
 * @package    Repository
 * @subpackage Kaltura
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/lib/outputrenderers.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/lib/weblib.php');

require_login(null, false, null, false, false);

global $CFG, $OUTPUT, $PAGE, $DB;

require_capability('moodle/site:config',get_context_instance(CONTEXT_SYSTEM), $USER);

$confirm = optional_param('confirm', 0, PARAM_INT);

$sesskey = sesskey();

$url          = new moodle_url($CFG->wwwroot . '/repository/kaltura/resetcategory.php');
$continue     = new moodle_url($CFG->wwwroot . '/repository/kaltura/resetcategory.php', array('confirm' => 1, 'sesskey' => $sesskey));
$repo_setting = new moodle_url($CFG->wwwroot . '/admin/repository.php', array('action' => 'edit', 'repos' => 'kaltura', 'sesskey' => $sesskey));

$PAGE->set_url($url);
$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));
$PAGE->set_title(get_string('resetroot', 'repository_kaltura'));
$PAGE->set_heading(get_string('resetroot', 'repository_kaltura'));

if ($confirm && confirm_sesskey()) {

    $param = array('plugin' => 'kaltura', 'name' => 'rootcategory');
    $DB->delete_records('config_plugins', $param);

    $param = array('plugin' => 'kaltura', 'name' => 'rootcategory_id');
    $DB->delete_records('config_plugins', $param);

    redirect($repo_setting, get_string('category_reset_complete', 'repository_kaltura'), 6);
    echo $OUTPUT->footer();
    die();
}

echo $OUTPUT->header();

echo $OUTPUT->confirm(get_string('confirm_category_reset', 'repository_kaltura'), $continue, $repo_setting);

echo $OUTPUT->footer();
