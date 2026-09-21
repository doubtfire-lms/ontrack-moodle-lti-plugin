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
 * Course data resource for the OnTrack LTI service.
 *
 * @package    ltiservice_ontrack
 * @copyright  2026 OnTrack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace ltiservice_ontrack\local\resources;

use ltiservice_ontrack\local\course_snapshot;
use ltiservice_ontrack\local\service\ontrack;
use mod_lti\local\ltiservice\resource_base;

/**
 * Provides one read-only course integration snapshot.
 */
class coursedata extends resource_base
{
    /** Vendor media type returned by the endpoint. */
    public const MEDIA_TYPE = 'application/vnd.ontrack.course-data.v2+json';

    /**
     * Construct the resource.
     *
     * @param ontrack $service Parent service.
     */
    public function __construct($service) {
        parent::__construct($service);
        $this->id = 'OnTrackCourseData';
        $this->template = '/CourseSection/{context_id}/bindings/{tool_code}/ontrack-course-data';
        $this->variables[] = 'OnTrack.courseData.url';
        $this->formats[] = self::MEDIA_TYPE;
        $this->methods[] = self::HTTP_GET;
    }

    /**
     * Execute a course snapshot request.
     *
     * Add ?include=users,groups or ?assignment_id=123 to filter the response.
     *
     * @param \mod_lti\local\ltiservice\response $response LTI service response.
     */
    public function execute($response) {
        global $DB;

        try {
            $params = $this->parse_template();
            $contextid = clean_param($params['context_id'] ?? '', PARAM_INT);
            $toolcode = clean_param($params['tool_code'] ?? '', PARAM_INT);
            $assignmentid = optional_param('assignment_id', 0, PARAM_INT);
            $includeparam = optional_param('include', '', PARAM_RAW_TRIMMED);

            $includes = course_snapshot::SECTIONS;
            if ($includeparam !== '') {
                $requested = explode(',', $includeparam);
                if (in_array('', $requested, true) || count($requested) !== count(array_unique($requested))) {
                    throw new \Exception('Invalid include parameter', 400);
                }
                foreach ($requested as $section) {
                    if (!in_array($section, course_snapshot::SECTIONS, true)) {
                        throw new \Exception('Invalid include parameter', 400);
                    }
                }
                $includes = array_values(array_intersect(course_snapshot::SECTIONS, $requested));
            }

            if (empty($contextid) || empty($toolcode)) {
                throw new \Exception('Invalid context or tool id', 400);
            }

            if (!$this->check_tool($toolcode, $response->get_request_data(), [ontrack::SCOPE_COURSE_DATA_READ])) {
                throw new \Exception('The LTI tool is not authorised for the OnTrack course-data scope', 401);
            }

            $course = $DB->get_record(
                'course',
                ['id' => $contextid],
                'id,shortname,fullname,startdate,enddate',
                MUST_EXIST
            );
            if (!$this->get_service()->is_used_in_context($toolcode, $course->id)) {
                  throw new \Exception('The OnTrack tool is not used in this course', 404);
            }
            if ($assignmentid > 0 && !$DB->record_exists('assign', ['id' => $assignmentid, 'course' => $course->id])) {
                throw new \Exception('Assignment not found in this course', 404);
            }
            if ($assignmentid > 0 && !in_array('assignments', $includes, true)) {
                throw new \Exception('assignment_id requires assignments to be included', 400);
            }

            $payload = course_snapshot::for_course(
                $course,
                $includes,
                $assignmentid > 0 ? $assignmentid : null
            );
            $response->set_content_type(self::MEDIA_TYPE);
            $response->set_body(json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (\dml_missing_record_exception $error) {
            $response->set_code(404);
            $response->set_reason('Course not found');
        } catch (\Throwable $error) {
            $code = (int) $error->getCode();
            $response->set_code($code >= 400 && $code <= 599 ? $code : 500);
            $response->set_reason($error->getMessage());
        }
    }

    /**
     * Resolve the launch-time endpoint substitution variable.
     *
     * @param string $value Custom parameter value.
     * @return string
     */
    public function parse_value($value) {
        global $COURSE;

        if (strpos($value, '$OnTrack.courseData.url') === false) {
            return $value;
        }

        $this->params['context_id'] = $COURSE->id;
        if ($tool = $this->get_service()->get_type()) {
            $this->params['tool_code'] = $tool->id;
        }

        return str_replace('$OnTrack.courseData.url', parent::get_endpoint(), $value);
    }
}
