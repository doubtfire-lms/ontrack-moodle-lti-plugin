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
 * Language strings for the OnTrack LTI service.
 *
 * @package    ltiservice_ontrack
 * @copyright  2026 OnTrack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['allow'] = 'Allow OnTrack to read course integration data';
$string['ltiservice_ontrack'] = 'OnTrack course data';
$string['ltiservice_ontrack_help'] = 'Allows the registered OnTrack LTI tool to retrieve read-only course enrolments, user details, roles, groups, assignments, due dates and extensions in courses where the tool is used. Only site-level tools can use this service; it has no effect on tools added to a single course.';
$string['notallow'] = 'Do not expose course integration data to OnTrack';
$string['pluginname'] = 'OnTrack Course Data LTI Service';
$string['privacy:metadata:assignmentid'] = 'The identifier and due-date information of a Moodle assignment.';
$string['privacy:metadata:courseid'] = 'The identifier of the Moodle course linked to OnTrack.';
$string['privacy:metadata:email'] = 'The email address of a user enrolled in the linked course.';
$string['privacy:metadata:enrolment'] = 'The enrolment methods, states and dates for a user in the linked course.';
$string['privacy:metadata:extensionduedate'] = 'The assignment extension due date granted to a user.';
$string['privacy:metadata:externalpurpose'] = 'Course users, enrolments, roles, groups, assignments and extension dates are sent to the registered OnTrack LTI tool for enrolment and special-consideration synchronisation.';
$string['privacy:metadata:groupid'] = 'The identifier and display information of a Moodle group.';
$string['privacy:metadata:groupingid'] = 'The identifier of a Moodle grouping containing the group.';
$string['privacy:metadata:name'] = 'The name of a user enrolled in the linked course.';
$string['privacy:metadata:role'] = 'The roles assigned to a user in the linked course.';
$string['privacy:metadata:userid'] = 'The identifier and login details of a user enrolled in the linked course.';
