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
        global $DB;

        return $DB->record_exists_sql(
            "SELECT 1
               FROM {assign} a
               JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = a.course AND cm.deletioninprogress = 0
               JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
              WHERE a.id = :assignmentid AND a.course = :courseid",
            ['assignmentid' => $assignmentid, 'courseid' => $courseid, 'modulename' => 'assign']
        );
    }

    /**
     * Return enrolled users, their enrolment records, course roles and groups.
     *
     * @param \stdClass $course Moodle course record.
     * @param \context_course $context Course context.
     * @return array<int, array<string, mixed>>
     */
    private static function users_for_course(\stdClass $course, \context_course $context): array {
        global $DB;

        $sql = "SELECT ue.id AS userenrolmentid, ue.userid, ue.status AS userenrolmentstatus,
                       ue.timestart, ue.timeend, e.id AS enrolmentinstanceid, e.enrol,
                       e.name AS enrolmentname, e.status AS enrolmentinstancestatus,
                       u.username, u.idnumber, u.firstname, u.lastname, u.email,
                       u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
                       u.suspended, u.deleted
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {user} u ON u.id = ue.userid
                 WHERE e.courseid = :courseid
              ORDER BY u.lastname, u.firstname, u.id, ue.id";
        $enrolments = $DB->get_recordset_sql($sql, ['courseid' => $course->id]);
        $users = [];
        $now = time();

        foreach ($enrolments as $enrolment) {
            $userid = (int) $enrolment->userid;
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

            $start = (int) $enrolment->timestart;
            $end = (int) $enrolment->timeend;
            $users[$userid]['enrolments'][] = [
            'id' => (string) $enrolment->userenrolmentid,
            'instance_id' => (string) $enrolment->enrolmentinstanceid,
            'method' => (string) $enrolment->enrol,
            'instance_name' => (string) ($enrolment->enrolmentname ?? ''),
            'status' => (int) $enrolment->userenrolmentstatus,
            'instance_status' => (int) $enrolment->enrolmentinstancestatus,
            'start_date' => $start,
            'end_date' => $end,
            'active' => !(bool) $enrolment->suspended
            && !(bool) $enrolment->deleted
            && (int) $enrolment->userenrolmentstatus === ENROL_USER_ACTIVE
            && (int) $enrolment->enrolmentinstancestatus === ENROL_INSTANCE_ENABLED
            && ($start === 0 || $start <= $now)
            && ($end === 0 || $end > $now),
            ];
        }
        $enrolments->close();

        if (empty($users)) {
            return [];
        }

        self::add_roles($users, $context);
        return $users;
    }

    /**
     * Add roles assigned in the course context or any parent context.
     *
     * @param array<int, array<string, mixed>> $users Users indexed by Moodle user id.
     * @param \context_course $context Course context.
     */
    private static function add_roles(array &$users, \context_course $context): void {
        global $DB;

        $contextids = array_values(array_filter(explode('/', trim($context->path, '/'))));
        [$contextsql, $contextparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'context');
        [$usersql, $userparams] = $DB->get_in_or_equal(array_keys($users), SQL_PARAMS_NAMED, 'user');
        $sql = "SELECT ra.id, ra.userid, r.id AS roleid, r.shortname, r.name, r.archetype
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.contextid {$contextsql}
                   AND ra.userid {$usersql}
              ORDER BY r.sortorder, r.id";
        $roles = $DB->get_recordset_sql($sql, $contextparams + $userparams);
        $seen = [];

        foreach ($roles as $role) {
            $userid = (int) $role->userid;
            $roleid = (int) $role->roleid;
            if (isset($users[$userid]) && empty($seen[$userid][$roleid])) {
                $users[$userid]['roles'][] = [
                'id' => (string) $roleid,
                'short_name' => (string) $role->shortname,
                'name' => (string) ($role->name ?: $role->shortname),
                'archetype' => (string) $role->archetype,
                ];
                $seen[$userid][$roleid] = true;
            }
        }
        $roles->close();
    }

    /**
     * Return all course groups with their groupings and members.
     *
     * The service is authorised as the registered LTI tool rather than a Moodle
     * user, so this deliberately includes hidden group memberships.
     *
     * @param \stdClass $course Moodle course record.
     * @param \context_course $context Course context.
     * @return array<int, array<string, mixed>>
     */
    private static function groups_for_course(\stdClass $course, \context_course $context): array {
        global $DB;

        $groupstable = new \xmldb_table('groups');
        $hasvisibility = $DB->get_manager()->field_exists($groupstable, new \xmldb_field('visibility'));
        $hasparticipation = $DB->get_manager()->field_exists($groupstable, new \xmldb_field('participation'));
        $fields = 'id,idnumber,name';
        if ($hasvisibility) {
            $fields .= ',visibility';
        }
        if ($hasparticipation) {
            $fields .= ',participation';
        }
        $records = $DB->get_records('groups', ['courseid' => $course->id], 'name ASC, id ASC', $fields);
        $groups = [];

        foreach ($records as $group) {
            $groups[(int) $group->id] = [
            'id' => (string) $group->id,
            'idnumber' => (string) ($group->idnumber ?? ''),
            'name' => format_string($group->name, true, ['context' => $context]),
            'visibility' => $hasvisibility ? (int) $group->visibility : 0,
            'participation' => $hasparticipation ? (bool) $group->participation : true,
            'grouping_ids' => [],
            'member_user_ids' => [],
            ];
        }

        if (empty($groups)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($groups), SQL_PARAMS_NAMED, 'groupid');
        $members = $DB->get_recordset_sql(
            "SELECT gm.id, gm.groupid, gm.userid
               FROM {groups_members} gm
              WHERE gm.groupid {$insql}
           ORDER BY gm.groupid, gm.userid",
            $inparams
        );
        foreach ($members as $member) {
            $groups[(int) $member->groupid]['member_user_ids'][] = (string) $member->userid;
        }
        $members->close();

        $mappings = $DB->get_recordset_sql(
            "SELECT gg.id, gg.groupid, gg.groupingid
               FROM {groupings_groups} gg
              WHERE gg.groupid {$insql}
           ORDER BY gg.groupid, gg.groupingid",
            $inparams
        );
        foreach ($mappings as $mapping) {
            $groups[(int) $mapping->groupid]['grouping_ids'][] = (string) $mapping->groupingid;
        }
        $mappings->close();

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

        $params = ['courseid' => $course->id, 'modulename' => 'assign'];
        $assignmentfilter = '';
        if ($assignmentid !== null) {
            $assignmentfilter = ' AND a.id = :assignmentid';
            $params['assignmentid'] = $assignmentid;
        }

        $sql = "SELECT a.id, a.name, a.allowsubmissionsfromdate, a.duedate, a.cutoffdate,
                       a.gradingduedate, a.nosubmissions, cm.id AS coursemoduleid,
                       cm.visible, cm.visibleoncoursepage
                  FROM {assign} a
                  JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = a.course AND cm.deletioninprogress = 0
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modulename
                 WHERE a.course = :courseid{$assignmentfilter}
              ORDER BY a.name, a.id";
        $records = $DB->get_records_sql($sql, $params);
        $assignments = [];

        foreach ($records as $assignment) {
            $assignments[(int) $assignment->id] = [
            'id' => (string) $assignment->id,
            'course_module_id' => (string) $assignment->coursemoduleid,
            'name' => format_string($assignment->name, true, ['context' => $context]),
            'allows_submissions_from_date' => (int) $assignment->allowsubmissionsfromdate,
            'due_date' => (int) $assignment->duedate,
            'cutoff_date' => (int) $assignment->cutoffdate,
            'grading_due_date' => (int) $assignment->gradingduedate,
            'accepts_submissions' => !(bool) $assignment->nosubmissions,
            'visible' => (bool) $assignment->visible,
            'visible_on_course_page' => (bool) $assignment->visibleoncoursepage,
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
        $flags = $DB->get_recordset_sql(
            "SELECT auf.id, auf.assignment, auf.userid, auf.extensionduedate
               FROM {assign_user_flags} auf
              WHERE auf.assignment {$insql}
                AND auf.extensionduedate > 0
           ORDER BY auf.assignment, auf.userid",
            $inparams
        );
        foreach ($flags as $flag) {
            $assignments[(int) $flag->assignment]['extensions'][] = [
            'user_id' => (string) $flag->userid,
            'extension_due_date' => (int) $flag->extensionduedate,
            ];
        }
        $flags->close();

        $overrides = $DB->get_recordset_sql(
            "SELECT ao.id, ao.assignid, ao.userid, ao.groupid,
                    ao.allowsubmissionsfromdate, ao.duedate, ao.cutoffdate
               FROM {assign_overrides} ao
              WHERE ao.assignid {$insql}
           ORDER BY ao.assignid, ao.sortorder, ao.id",
            $inparams
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
