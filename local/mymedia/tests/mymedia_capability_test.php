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
 * Unit tests for Kaltura mymedia local plugin capabilities
 *
 * @package    local
 * @subpackage tests
 * @author     Remote-Learner Inc
 * @copyright  (C) 2008-2013 http://www.remote-learner.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/../lib.php');

class mymedia_capability_testcase extends advanced_testcase {

    protected function setUp() {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test function that check capability reports found
     */
    public function test_local_mymedia_check_capability_found() {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $course = $this->getDataGenerator()->create_course(array('idnumber' => 'crs1', 'fullname' => 'course1', 'shortname' => 'crs1'));
        $role = $DB->get_record('role', array('shortname' => 'student'));
        $coursecontext = context_course::instance($course->id);
        role_assign($role->id, $user->id, $coursecontext->id);
        assign_capability('local/mymedia:sharecourse', CAP_ALLOW, $role->id, $coursecontext);
        assign_capability('local/mymedia:sharesite', CAP_ALLOW, $role->id, $coursecontext);

        $result = local_mymedia_check_capability('local/mymedia:sharecourse');
        $this->assertTrue($result);

        $result = local_mymedia_check_capability('local/mymedia:sharesite');
        $this->assertTrue($result);
    }

    /**
     * Test function that checks capability reports missing
     */
    public function test_local_mymedia_check_capability_missing() {
        $result = local_mymedia_check_capability('local/mymedia:sharecourse');
        $this->assertFalse($result);

        $result = local_mymedia_check_capability('local/mymedia:sharesite');
        $this->assertFalse($result);
    }
}
