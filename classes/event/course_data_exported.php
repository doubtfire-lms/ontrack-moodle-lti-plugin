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
 * Event raised when an LTI tool retrieves OnTrack course data.
 *
 * @package    ltiservice_ontrack
 * @copyright  2026 OnTrack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace ltiservice_ontrack\event;

use core\event\base;

/**
 * Records a successful course-data export to an LTI tool.
 */
class course_data_exported extends base {
    /**
     * Initialise event metadata.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return the event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventcoursedataexported', 'ltiservice_ontrack');
    }

    /**
     * Describe the export.
     *
     * @return string
     */
    public function get_description() {
        $sections = isset($this->other['sections']) ? " ({$this->other['sections']})" : '';
        return "LTI tool {$this->other['toolid']} retrieved course data{$sections} for course {$this->courseid}.";
    }
}
