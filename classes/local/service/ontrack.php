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
 * OnTrack LTI service definition.
 *
 * @package    ltiservice_ontrack
 * @copyright  2026 OnTrack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace ltiservice_ontrack\local\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Advertises and authorises the OnTrack course-data resource.
 */
class ontrack extends \mod_lti\local\ltiservice\service_base
{
    /** OAuth scope required to read course enrolments, groups and assignments. */
    public const SCOPE_COURSE_DATA_READ = 'https://ontrack.edu.au/lti/scope/course-data.readonly';

    /**
     * Construct the service.
     */
    public function __construct() {
        parent::__construct();
        $this->id = 'ontrack';
        $this->name = get_string('pluginname', $this->get_component_id());
    }

    /**
     * Return the resources provided by this service.
     *
     * @return \mod_lti\local\ltiservice\resource_base[]
     */
    public function get_resources() {
        if (empty($this->resources)) {
            $this->resources = [
            new \ltiservice_ontrack\local\resources\coursedata($this),
            ];
        }

        return $this->resources;
    }

    /**
     * Return the scopes defined by the plugin.
     *
     * @return string[]
     */
    public function get_scopes() {
        return [self::SCOPE_COURSE_DATA_READ];
    }

    /**
     * Return scopes enabled for the current external tool.
     *
     * @return string[]
     */
    public function get_permitted_scopes() {
        $enabled = !empty($this->get_type())
        && isset($this->get_typeconfig()[$this->get_component_id()])
        && (int) $this->get_typeconfig()[$this->get_component_id()] === self::SERVICE_ENABLED;

        return $enabled ? [self::SCOPE_COURSE_DATA_READ] : [];
    }

    /**
     * Add the enable/disable option to the external tool configuration.
     *
     * @param \MoodleQuickForm $mform Moodle form.
     */
    public function get_configuration_options(&$mform) {
        $elementname = $this->get_component_id();
        $options = [
        0 => get_string('notallow', $this->get_component_id()),
        self::SERVICE_ENABLED => get_string('allow', $this->get_component_id()),
        ];

        $mform->addElement('select', $elementname, get_string($elementname, $this->get_component_id()), $options);
        $mform->setType($elementname, PARAM_INT);
        $mform->setDefault($elementname, 0);
        $mform->addHelpButton($elementname, $elementname, $this->get_component_id());
    }

    /**
     * Advertise the service URL and scope in the signed LTI custom claim.
     *
     * @param string $messagetype LTI message type.
     * @param int $courseid Moodle course id.
     * @param int $userid Moodle user id.
     * @param int $typeid LTI tool type id.
     * @param int|null $modlti LTI activity id.
     * @return array<string, string>
     */
    public function get_launch_parameters($messagetype, $courseid, $userid, $typeid, $modlti = null) {
        $this->set_type(lti_get_type($typeid));
        $this->set_typeconfig(lti_get_type_config($typeid));

        if (empty($this->get_permitted_scopes()) || !$this->is_used_in_context($typeid, $courseid)) {
            return [];
        }

        return [
        'ontrack_course_data_url' => '$OnTrack.courseData.url',
        'ontrack_course_data_scope' => self::SCOPE_COURSE_DATA_READ,
        ];
    }
}
