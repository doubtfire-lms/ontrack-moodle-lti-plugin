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

use advanced_testcase;
use context_course;
use ltiservice_ontrack\local\course_snapshot;

/**
 * Course snapshot tests.
 *
 * @covers \ltiservice_ontrack\local\course_snapshot
 */
final class course_snapshot_test extends advanced_testcase
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
        $this->assertSame('student', $snapshot['users'][0]['roles'][0]['short_name']);
        $this->assertSame(get_string('defaultcoursestudent'), $snapshot['users'][0]['roles'][0]['name']);
        $this->assertSame('manual', $snapshot['users'][0]['enrolments'][0]['method']);
        $this->assertTrue($snapshot['users'][0]['enrolments'][0]['active']);
        $this->assertSame([(string) $user->id], $snapshot['groups'][0]['member_user_ids']);
        $this->assertCount(1, $snapshot['assignments']);
        $this->assertSame((string) $assignment->id, $snapshot['assignments'][0]['id']);
        $this->assertSame((string) $user->id, $snapshot['assignments'][0]['extensions'][0]['user_id']);
        $this->assertSame(1_800_086_400, $snapshot['assignments'][0]['extensions'][0]['extension_due_date']);
    }

    /**
     * Assignments pending recycle-bin deletion are excluded.
     */
    public function test_snapshot_excludes_assignments_pending_deletion(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $assignment = $this->getDataGenerator()->get_plugin_generator('mod_assign')->create_instance([
            'course' => $course->id,
        ]);
        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $assignment->cmid]);
        rebuild_course_cache($course->id, true);

        $snapshot = course_snapshot::for_course($course, ['assignments']);

        $this->assertSame([], $snapshot['assignments']);
        $this->assertFalse(course_snapshot::assignment_exists((int) $course->id, (int) $assignment->id));
    }

    /**
     * Course-level role renames are used for role names.
     */
    public function test_snapshot_uses_course_role_alias(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $DB->insert_record('role_names', (object) [
            'roleid' => $studentrole->id,
            'contextid' => context_course::instance($course->id)->id,
            'name' => 'Learner',
        ]);

        $snapshot = course_snapshot::for_course($course, ['users']);

        $this->assertSame('Learner', $snapshot['users'][0]['roles'][0]['name']);
    }

    /**
     * Hidden group memberships are included because the service has no Moodle user.
     */
    public function test_snapshot_includes_hidden_group_members(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $group = $this->getDataGenerator()->create_group([
            'courseid' => $course->id,
            'visibility' => GROUPS_VISIBILITY_NONE,
        ]);
        groups_add_member($group, $user);
        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id]);
        groups_assign_grouping($grouping->id, $group->id);

        $snapshot = course_snapshot::for_course($course, ['groups']);

        $this->assertSame(GROUPS_VISIBILITY_NONE, $snapshot['groups'][0]['visibility']);
        $this->assertSame([(string) $user->id], $snapshot['groups'][0]['member_user_ids']);
        $this->assertSame([(string) $grouping->id], $snapshot['groups'][0]['grouping_ids']);
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
