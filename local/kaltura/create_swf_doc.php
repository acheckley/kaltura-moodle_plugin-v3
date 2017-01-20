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
 * Kaltura create swf document
 *
 * @package    local
 * @subpackage kaltura
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(dirname(dirname(__FILE__))) . '/local/kaltura/locallib.php');

require_login(null, false, null, false, true);

if (isguestuser()) {
    throw new coding_exception('Guest users do not have access to this');
}

$video_entry_id = required_param('video_entry_id', PARAM_TEXT);
$doc_entry_id   = required_param('doc_entry_id', PARAM_TEXT);

$pres_entry_id = local_kaltura_create_swfdoc($doc_entry_id, $video_entry_id);

if (!empty($pres_entry_id)) {
    echo "y:{$pres_entry_id}";
} else {
    echo 'n: Something went wrong';
}

die();