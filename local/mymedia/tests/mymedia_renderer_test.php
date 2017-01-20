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
 * Unit tests for Kaltura mymedia local plugin renderer functionality
 *
 * @package    local
 * @subpackage tests
 * @author     Remote-Learner Inc
 * @copyright  (C) 2008-2013 http://www.remote-learner.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once(dirname(__FILE__).'/../lib.php');
require_once(dirname(__FILE__).'/../renderer.php');
require_once(dirname(__FILE__).'/../../kaltura/kaltura_entries.class.php');

class mymedia_renderer_testcase extends advanced_testcase {

    protected function setUp() {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test that create_video_entry_markup creates a share link
     */
    public function test_create_video_entry_markup_share_link() {
        global $DB, $USER, $PAGE;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $course = $this->getDataGenerator()->create_course(array('idnumber' => 'crs1', 'fullname' => 'course1', 'shortname' => 'crs1'));
        $role = $DB->get_record('role', array('shortname' => 'student'));
        $coursecontext = context_course::instance($course->id);
        assign_capability('local/mymedia:sharecourse', CAP_ALLOW, $role->id, $coursecontext);
        assign_capability('local/mymedia:sharesite', CAP_ALLOW, $role->id, $coursecontext);

        $renderer = new local_mymedia_renderer($PAGE, 'target');

        $video = new stdClass();
        $video->id = '1_test1234';
        $video->name = 'Test';
        $video->createdAt = 1340138912;
        $video->thumbnailUrl = 'http://test.com/test.jpg';
        $result = $renderer->create_video_entry_markup($video);

        $expectedshare = get_string('share_link', 'local_mymedia');
        $this->assertRegExp('/'.$expectedshare.'/', $result);
    }
}
