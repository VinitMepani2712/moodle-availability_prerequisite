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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Frontend class for the availability condition editor.
 *
 * @package   availability_prerequisite
 * @copyright 2026 Vinit Mepani <vinitmepani07@email.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_prerequisite;

/**
 * Frontend (PHP side of the availability condition editor widget).
 *
 * @package   availability_prerequisite
 * @copyright 2026 Vinit Mepani <vinitmepani07@email.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class frontend extends \core_availability\frontend {
    /** @var array Cached init params keyed by course/cm/section. */
    protected $cacheparams = [];

    /** @var string Cache key currently stored. */
    protected $cachekey = '';

    /**
     * Returns lang string identifiers to pass to the JavaScript module.
     *
     * @return string[]
     */
    protected function get_javascript_strings(): array {
        return [
            'label_course',
            'label_completion',
            'option_complete',
            'option_incomplete',
            'error_selectcourse',
            'choosecourse',
            'searchcourse',
            'visit_course',
            'noresults',
        ];
    }

    /**
     * Returns init parameters passed to the JavaScript module.
     *
     * @param \stdClass          $course  Current course.
     * @param \cm_info|null      $cm      Current module (null if editing a section).
     * @param \section_info|null $section Current section (null if editing a module).
     * @return array One-element array containing the course list.
     */
    protected function get_javascript_init_params(
        $course,
        ?\cm_info $cm = null,
        ?\section_info $section = null
    ): array {
        $cachekey = $course->id . ',' . ($cm ? $cm->id : '') . ($section ? $section->id : '');
        if ($cachekey === $this->cachekey) {
            return $this->cacheparams;
        }

        global $DB;

        $context = \context_course::instance($course->id);

        $courses = $DB->get_records_select(
            'course',
            'category > 0 AND id <> :currentcourse',
            ['currentcourse' => $course->id],
            'fullname ASC',
            'id, fullname'
        );

        $courselist = [];
        foreach ($courses as $c) {
            $courseurl = new \moodle_url('/course/view.php', ['id' => $c->id]);
            $courselist[] = (object)[
                'id'   => (int)$c->id,
                'name' => format_string($c->fullname, true, ['context' => $context]),
                'url'  => $courseurl->out(false),
            ];
        }

        $this->cachekey    = $cachekey;
        $this->cacheparams = [$courselist];

        return $this->cacheparams;
    }

    /**
     * Decides whether this condition type can be added in the current context.
     *
     * @param \stdClass          $course  Current course.
     * @param \cm_info|null      $cm      Current module.
     * @param \section_info|null $section Current section.
     * @return bool
     */
    protected function allow_add(
        $course,
        ?\cm_info $cm = null,
        ?\section_info $section = null
    ): bool {
        global $CFG;

        require_once($CFG->libdir . '/completionlib.php');
        $info = new \completion_info($course);
        if (!$info->is_enabled()) {
            return false;
        }

        $params = $this->get_javascript_init_params($course, $cm, $section);
        return !empty($params[0]);
    }
}
