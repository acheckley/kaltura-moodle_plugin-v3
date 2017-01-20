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
 * Kaltura reports page
 *
 * @package    local_kaltura
 * @subpackage kaltura
 * @copyright  2013 Remote-Learner http://www.remote-learner.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(dirname(dirname(__FILE__))).'/config.php');
require($CFG->dirroot.'/local/kaltura/locallib.php');
if (!file_exists($CFG->dirroot.'/repository/kaltura/locallib.php')) {
    print_error(get_string('repo_not_installed', 'local_kaltura'));
}
require($CFG->dirroot.'/repository/kaltura/locallib.php');

require_login();

$isadmin = is_siteadmin();

if (!$isadmin) {
    if (kaltura_course_report_view_permission() === false) {
        print_error('nopermissions', '', '', '');
    }
}

$PAGE->set_context(context_system::instance());

$reports = get_string('header_kaltura_reports', 'local_kaltura');
$header  = format_string($SITE->shortname).": $reports";

$PAGE->set_url('/local/kaltura/reports.php');
$PAGE->set_pagetype('kaltura-reports-index');
$PAGE->set_pagelayout('standard');
$PAGE->set_title($header);
$PAGE->set_heading($header);
$PAGE->add_body_class('kaltura-reports-index');

echo $OUTPUT->header();

$enabled = get_config(KALTURA_PLUGIN_NAME, 'enable_reports');

if (empty($enabled)) {
    echo get_string('report_disabled', 'local_kaltura');
} else  {
    $renderer = $PAGE->get_renderer('local_kaltura');

    echo $renderer->render_course_search();

    if (($courses = $renderer->render_recent_courses()) === false) {
        echo get_string('conn_failed', 'local_kaltura');
    } else {
        echo $courses;
    }
}

echo $OUTPUT->footer();
