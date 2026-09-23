<?php
// This file is part of Moodle - http://moodle.org/
//
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tests for which tools can read OnTrack course data.
 *
 * @package    ltiservice_ontrack
 * @category   test
 * @copyright  2026 OnTrack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace ltiservice_ontrack;

use advanced_testcase;
use ltiservice_ontrack\local\resources\coursedata;
use ltiservice_ontrack\local\service\ontrack;
use mod_lti\local\ltiservice\response;

/**
 * OnTrack service access tests.
 *
 * @covers \ltiservice_ontrack\local\service\ontrack
 * @covers \ltiservice_ontrack\local\resources\coursedata
 */
final class ontrack_test extends advanced_testcase
{
    /**
     * Load the LTI library.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/lti/locallib.php');
        parent::setUpBeforeClass();
    }

    /**
     * Clear the request globals set by course-data requests.
     */
    protected function tearDown(): void {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['PATH_INFO'], $_SERVER['HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    /**
     * The token endpoint only grants the scope to site tools with the service enabled.
     */
    public function test_only_enabled_site_tools_are_granted_the_scope(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $sitetool = $this->create_tool(SITEID, true);
        $coursetool = $this->create_tool($course->id, true);
        $disabledtool = $this->create_tool(SITEID, false);

        $this->assertContains(ontrack::SCOPE_COURSE_DATA_READ, $this->token_scopes($sitetool));
        $this->assertNotContains(ontrack::SCOPE_COURSE_DATA_READ, $this->token_scopes($coursetool));
        $this->assertNotContains(ontrack::SCOPE_COURSE_DATA_READ, $this->token_scopes($disabledtool));
    }

    /**
     * A site tool used in the course can read its course data.
     */
    public function test_site_tool_can_read_course_data(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $typeid = $this->create_tool(SITEID, true);
        $this->getDataGenerator()->create_module('lti', ['course' => $course->id, 'typeid' => $typeid]);

        $response = $this->request_course_data($course->id, $typeid);

        $this->assertSame(200, $response->get_code());
        $this->assertSame((string) $course->id, json_decode($response->get_body())->context->id);
    }

    /**
     * A course tool is refused, even with a token issued before course tools were excluded.
     */
    public function test_course_tool_cannot_read_course_data(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $typeid = $this->create_tool($course->id, true);

        $response = $this->request_course_data($course->id, $typeid);

        $this->assertSame(401, $response->get_code());
        $this->assertSame('', $response->get_body());
    }

    /**
     * Create an LTI tool type.
     *
     * @param int $courseid Course the tool belongs to, or SITEID for a site tool.
     * @param bool $enabled Whether the OnTrack service is enabled for the tool.
     * @return int Tool type id.
     */
    private function create_tool(int $courseid, bool $enabled): int {
        $type = (object) [
            'state' => LTI_TOOL_STATE_CONFIGURED,
            'name' => 'OnTrack',
            'baseurl' => 'https://ontrack.example.com/lti/api/',
            'course' => $courseid,
        ];
        $config = (object) ['ltiservice_ontrack' => $enabled ? ontrack::SERVICE_ENABLED : 0];

        return lti_add_type($type, $config);
    }

    /**
     * Scopes the token endpoint would grant a tool.
     *
     * @param int $typeid Tool type id.
     * @return string[]
     */
    private function token_scopes(int $typeid): array {
        return lti_get_permitted_service_scopes(lti_get_type($typeid), lti_get_type_config($typeid));
    }

    /**
     * Request a course's data with a token for the course-data scope.
     *
     * @param int $courseid Moodle course id.
     * @param int $typeid Tool type id.
     * @return response
     */
    private function request_course_data(int $courseid, int $typeid): response {
        $token = lti_new_access_token($typeid, [ontrack::SCOPE_COURSE_DATA_READ]);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['PATH_INFO'] = "/CourseSection/{$courseid}/bindings/{$typeid}/ontrack-course-data";
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token->token;

        $response = new response();
        (new coursedata(new ontrack()))->execute($response);
        return $response;
    }
}
