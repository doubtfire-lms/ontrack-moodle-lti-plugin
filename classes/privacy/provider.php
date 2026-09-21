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
 * Privacy provider for the OnTrack LTI service.
 *
 * @package    ltiservice_ontrack
 * @copyright  2026 OnTrack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace ltiservice_ontrack\privacy;

use core_privacy\local\metadata\collection;

/**
 * Declares data sent to OnTrack. The plugin stores no personal data itself.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider
{
    /**
     * Describe the external data transfer.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->link_external_location('OnTrack LTI tool', [
        'assignmentid' => 'privacy:metadata:assignmentid',
        'courseid' => 'privacy:metadata:courseid',
        'email' => 'privacy:metadata:email',
        'enrolment' => 'privacy:metadata:enrolment',
        'extensionduedate' => 'privacy:metadata:extensionduedate',
        'groupid' => 'privacy:metadata:groupid',
        'groupingid' => 'privacy:metadata:groupingid',
        'name' => 'privacy:metadata:name',
        'role' => 'privacy:metadata:role',
        'userid' => 'privacy:metadata:userid',
        ], 'privacy:metadata:externalpurpose');

        return $collection;
    }

    /**
     * No data is stored, so no contexts hold user data.
     *
     * @param int $userid User id.
     * @return \core_privacy\local\request\contextlist
     */
    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        return new \core_privacy\local\request\contextlist();
    }

    /**
     * No data is stored, so no users are added.
     *
     * @param \core_privacy\local\request\userlist $userlist User list.
     */
    public static function get_users_in_context(\core_privacy\local\request\userlist $userlist) {
    }

    /**
     * No data is stored, so there is nothing to export.
     *
     * @param \core_privacy\local\request\approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(\core_privacy\local\request\approved_contextlist $contextlist) {
    }

    /**
     * No data is stored, so there is nothing to delete.
     *
     * @param \context $context Context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
    }

    /**
     * No data is stored, so there is nothing to delete.
     *
     * @param \core_privacy\local\request\approved_userlist $userlist Approved users.
     */
    public static function delete_data_for_users(\core_privacy\local\request\approved_userlist $userlist) {
    }

    /**
     * No data is stored, so there is nothing to delete.
     *
     * @param \core_privacy\local\request\approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(\core_privacy\local\request\approved_contextlist $contextlist) {
    }
}
