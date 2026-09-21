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
 * Tests for the OnTrack course snapshot builder.
 *
 * @package    ltiservice_ontrack
 * @category   test
 * @copyright  2026 OnTrack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace ltiservice_ontrack;

use ltiservice_ontrack\local\course_snapshot;

/**
 * Course snapshot tests.
 *
 * @covers \ltiservice_ontrack\local\course_snapshot
 */
final class course_snapshot_test extends \advanced_testcase
{
    /**
     * The full snapshot contains every section.
     */
    public function test_snapshot_contains_enrolments_groups_assignments_and_extensions(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user(['username' => 'student1']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($group, $user);
        $assignment = $this->getDataGenerator()->get_plugin_generator('mod_assign')->create_instance([
        'course' => $course->id,
        'name' => 'Special consideration source',
        'duedate' => 1_800_000_000,
        ]);
        $DB->insert_record('assign_user_flags', (object) [
        'assignment' => $assignment->id,
        'userid' => $user->id,
        'extensionduedate' => 1_800_086_400,
        ]);

        $snapshot = course_snapshot::for_course($course, course_snapshot::SECTIONS, (int) $assignment->id);

        $this->assertSame('2', $snapshot['version']);
        $this->assertCount(1, $snapshot['users']);
        $this->assertSame('student1', $snapshot['users'][0]['username']);
        $this->assertArrayNotHasKey('group_ids', $snapshot['users'][0]);
        $this->assertSame([(string) $user->id], $snapshot['groups'][0]['member_user_ids']);
        $this->assertCount(1, $snapshot['assignments']);
        $this->assertSame((string) $assignment->id, $snapshot['assignments'][0]['id']);
        $this->assertSame((string) $user->id, $snapshot['assignments'][0]['extensions'][0]['user_id']);
        $this->assertSame(1_800_086_400, $snapshot['assignments'][0]['extensions'][0]['extension_due_date']);
    }

    /**
     * Only requested sections are built.
     */
    public function test_snapshot_only_builds_requested_sections(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $snapshot = course_snapshot::for_course($course, ['assignments']);

        $this->assertArrayNotHasKey('users', $snapshot);
        $this->assertArrayNotHasKey('groups', $snapshot);
        $this->assertArrayHasKey('assignments', $snapshot);
    }
}
