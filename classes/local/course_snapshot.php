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
 * Builds the read-only Moodle course snapshot returned to OnTrack.
 *
 * @package    ltiservice_ontrack
 * @copyright  2026 OnTrack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace ltiservice_ontrack\local;

/**
 * Snapshot builder kept separate from HTTP and authentication concerns.
 */
class course_snapshot
{
    /** Sections which callers may include in a snapshot. */
    public const SECTIONS = ['users', 'groups', 'assignments'];

    /**
     * Build a snapshot containing course enrolments, groups and assignments.
     *
     * @param \stdClass $course Moodle course record.
     * @param string[] $includes Sections to include.
     * @param int|null $assignmentid Return only this assignment when supplied.
     * @return array<string, mixed>
     */
    public static function for_course(
        \stdClass $course,
        array $includes = self::SECTIONS,
        ?int $assignmentid = null
    ): array {
        $context = \context_course::instance($course->id);
        $payload = [
        'version' => '2',
        'generated_at' => time(),
        'context' => [
        'id' => (string) $course->id,
        'label' => format_string($course->shortname, true, ['context' => $context]),
        'title' => format_string($course->fullname, true, ['context' => $context]),
        'start_date' => (int) ($course->startdate ?? 0),
        'end_date' => (int) ($course->enddate ?? 0),
        ],
        ];

        if (in_array('users', $includes, true)) {
            $payload['users'] = array_values(self::users_for_course($course, $context));
        }
        if (in_array('groups', $includes, true)) {
            $payload['groups'] = self::groups_for_course($course, $context);
        }
        if (in_array('assignments', $includes, true)) {
            $payload['assignments'] = self::assignments_for_course($course, $context, $assignmentid);
        }

        return $payload;
    }

    /**
     * Whether an assignment exists in the course and is not pending deletion.
     *
     * @param int $courseid Moodle course id.
     * @param int $assignmentid Assignment instance id.
     * @return bool
     */
    public static function assignment_exists(int $courseid, int $assignmentid): bool {
        $instances = get_fast_modinfo($courseid)->get_instances_of('assign');
        return isset($instances[$assignmentid]) && !$instances[$assignmentid]->deletioninprogress;
    }

    /**
     * Return enrolled users, their enrolment records, course roles and groups.
     *
     * @param \stdClass $course Moodle course record.
     * @param \context_course $context Course context.
     * @return array<int, array<string, mixed>>
     */
    private static function users_for_course(\stdClass $course, \context_course $context): array {
        $instances = enrol_get_instances($course->id, false);
        $enrolments = array_values(enrol_get_course_users($course->id));
        usort($enrolments, static function ($a, $b) {
            return [\core_text::strtolower($a->lastname), \core_text::strtolower($a->firstname), (int) $a->id, (int) $a->ueid]
                <=> [\core_text::strtolower($b->lastname), \core_text::strtolower($b->firstname), (int) $b->id, (int) $b->ueid];
        });
        $users = [];
        $now = time();

        foreach ($enrolments as $enrolment) {
            $userid = (int) $enrolment->id;
            $instance = $instances[$enrolment->ueenrolid] ?? null;
            if (!isset($users[$userid])) {
                $users[$userid] = [
                'id' => (string) $userid,
                'username' => (string) $enrolment->username,
                'idnumber' => (string) $enrolment->idnumber,
                'first_name' => (string) $enrolment->firstname,
                'last_name' => (string) $enrolment->lastname,
                'full_name' => fullname($enrolment),
                'email' => (string) $enrolment->email,
                'suspended' => (bool) $enrolment->suspended,
                'deleted' => (bool) $enrolment->deleted,
                'roles' => [],
                'enrolments' => [],
                ];
            }

            $start = (int) $enrolment->uetimestart;
            $end = (int) $enrolment->uetimeend;
            // Matches the onlyactive rules in enrol_get_course_users().
            $users[$userid]['enrolments'][] = [
            'id' => (string) $enrolment->ueid,
            'instance_id' => (string) $enrolment->ueenrolid,
            'method' => (string) ($instance->enrol ?? ''),
            'instance_name' => (string) ($instance->name ?? ''),
            'status' => (int) $enrolment->uestatus,
            'instance_status' => (int) $enrolment->estatus,
            'start_date' => $start,
            'end_date' => $end,
            'active' => !(bool) $enrolment->suspended
            && !(bool) $enrolment->deleted
            && (int) $enrolment->uestatus === ENROL_USER_ACTIVE
            && (int) $enrolment->estatus === ENROL_INSTANCE_ENABLED
            && $start < $now
            && ($end === 0 || $end > $now),
            ];
        }

        if (empty($users)) {
            return [];
        }

        self::add_roles($users, $context);
        return $users;
    }

    /**
     * Add roles assigned in the course context or any parent context.
     *
     * @param array $users Users indexed by Moodle user id, updated in place.
     * @param \context_course $context Course context.
     */
    private static function add_roles(array &$users, \context_course $context): void {
        $allroles = get_all_roles($context);
        $assignments = get_users_roles($context, array_keys($users), true, 'r.sortorder ASC, ra.id ASC');

        foreach ($assignments as $userid => $userassignments) {
            $seen = [];
            foreach ($userassignments as $assignment) {
                $roleid = (int) $assignment->roleid;
                if (!isset($users[$userid]) || isset($seen[$roleid]) || !isset($allroles[$roleid])) {
                    continue;
                }
                $role = $allroles[$roleid];
                $users[$userid]['roles'][] = [
                'id' => (string) $roleid,
                'short_name' => (string) $role->shortname,
                'name' => role_get_name($role, $context),
                'archetype' => (string) $role->archetype,
                ];
                $seen[$roleid] = true;
            }
        }
    }

    /**
     * Return all course groups with their groupings and members.
     *
     * @param \stdClass $course Moodle course record.
     * @param \context_course $context Course context.
     * @return array<int, array<string, mixed>>
     */
    private static function groups_for_course(\stdClass $course, \context_course $context): array {
        global $DB;

        $data = groups_get_course_data($course->id);
        $groups = [];

        foreach ($data->groups as $group) {
            $groups[(int) $group->id] = [
            'id' => (string) $group->id,
            'idnumber' => (string) ($group->idnumber ?? ''),
            'name' => format_string($group->name, true, ['context' => $context]),
            'visibility' => (int) $group->visibility,
            'participation' => (bool) $group->participation,
            'grouping_ids' => [],
            'member_user_ids' => [],
            ];
        }

        if (empty($groups)) {
            return [];
        }

        // Core member functions hide groups from the current $USER, and LTI service requests have none.
        $members = $DB->get_recordset_list(
            'groups_members',
            'groupid',
            array_keys($groups),
            'groupid, userid',
            'id, groupid, userid'
        );
        foreach ($members as $member) {
            $groups[(int) $member->groupid]['member_user_ids'][] = (string) $member->userid;
        }
        $members->close();

        foreach ($data->mappings as $mapping) {
            if (isset($groups[(int) $mapping->groupid])) {
                $groups[(int) $mapping->groupid]['grouping_ids'][] = (string) $mapping->groupingid;
            }
        }
        foreach ($groups as &$group) {
            sort($group['grouping_ids'], SORT_NUMERIC);
        }
        unset($group);

        return array_values($groups);
    }

    /**
     * Return assignments and their per-user extension due dates.
     *
     * @param \stdClass $course Moodle course record.
     * @param \context_course $context Course context.
     * @param int|null $assignmentid Optional assignment filter.
     * @return array<int, array<string, mixed>>
     */
    private static function assignments_for_course(
        \stdClass $course,
        \context_course $context,
        ?int $assignmentid
    ): array {
        global $DB;

        $cms = [];
        foreach (get_fast_modinfo($course)->get_instances_of('assign') as $instanceid => $cm) {
            if (!$cm->deletioninprogress && ($assignmentid === null || $instanceid === $assignmentid)) {
                $cms[(int) $instanceid] = $cm;
            }
        }

        if (empty($cms)) {
            return [];
        }

        // mod_assign has no bulk read API for dates, extensions or overrides.
        $records = $DB->get_records_list(
            'assign',
            'id',
            array_keys($cms),
            'name ASC, id ASC',
            'id, name, allowsubmissionsfromdate, duedate, cutoffdate, gradingduedate, nosubmissions'
        );
        $assignments = [];

        foreach ($records as $assignment) {
            $cm = $cms[(int) $assignment->id];
            $assignments[(int) $assignment->id] = [
            'id' => (string) $assignment->id,
            'course_module_id' => (string) $cm->id,
            'name' => format_string($assignment->name, true, ['context' => $context]),
            'allows_submissions_from_date' => (int) $assignment->allowsubmissionsfromdate,
            'due_date' => (int) $assignment->duedate,
            'cutoff_date' => (int) $assignment->cutoffdate,
            'grading_due_date' => (int) $assignment->gradingduedate,
            'accepts_submissions' => !(bool) $assignment->nosubmissions,
            'visible' => (bool) $cm->visible,
            'visible_on_course_page' => (bool) $cm->visibleoncoursepage,
            'extensions' => [],
            'user_overrides' => [],
            'group_overrides' => [],
            ];
        }

        if (empty($assignments)) {
            return [];
        }

        $assignmentids = array_keys($assignments);
        [$insql, $inparams] = $DB->get_in_or_equal($assignmentids, SQL_PARAMS_NAMED, 'assignment');
        $flags = $DB->get_recordset_select(
            'assign_user_flags',
            "assignment {$insql} AND extensionduedate > 0",
            $inparams,
            'assignment, userid',
            'id, assignment, userid, extensionduedate'
        );
        foreach ($flags as $flag) {
            $assignments[(int) $flag->assignment]['extensions'][] = [
            'user_id' => (string) $flag->userid,
            'extension_due_date' => (int) $flag->extensionduedate,
            ];
        }
        $flags->close();

        $overrides = $DB->get_recordset_list(
            'assign_overrides',
            'assignid',
            $assignmentids,
            'assignid, sortorder, id',
            'id, assignid, userid, groupid, allowsubmissionsfromdate, duedate, cutoffdate'
        );
        foreach ($overrides as $override) {
            $entry = [
            'allows_submissions_from_date' => self::nullable_int($override->allowsubmissionsfromdate),
            'due_date' => self::nullable_int($override->duedate),
            'cutoff_date' => self::nullable_int($override->cutoffdate),
            ];
            if ($override->userid !== null) {
                $entry['user_id'] = (string) $override->userid;
                $assignments[(int) $override->assignid]['user_overrides'][] = $entry;
            } else if ($override->groupid !== null) {
                $entry['group_id'] = (string) $override->groupid;
                $assignments[(int) $override->assignid]['group_overrides'][] = $entry;
            }
        }
        $overrides->close();

        return array_values($assignments);
    }

    /**
     * Preserve null override values while normalising timestamp values to integers.
     *
     * @param mixed $value Database value.
     * @return int|null
     */
    private static function nullable_int($value): ?int {
        return $value === null ? null : (int) $value;
    }
}
