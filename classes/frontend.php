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
     * @var int Maximum number of courses to load into the picker at once.
     *          Prevents loading the entire course table on large sites.
     *          When the cap is hit, the JS widget tells the user to refine
     *          their search.
     */
    const MAX_COURSES = 250;

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
            'toomany',
        ];
    }

    /**
     * Returns init parameters passed to the JavaScript module.
     *
     * The course list is capped at self::MAX_COURSES to avoid loading and
     * rendering the entire course table on large sites. When more courses
     * exist than the cap, a "capped" flag is passed so the widget can prompt
     * the user to refine their search.
     *
     * @param \stdClass          $course  Current course.
     * @param \cm_info|null      $cm      Current module (null if editing a section).
     * @param \section_info|null $section Current section (null if editing a module).
     * @return array Two-element array: [course list, capped flag].
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

        // Count how many candidate courses exist (cheap COUNT query).
        $total = $DB->count_records_select(
            'course',
            'category > 0 AND id <> :currentcourse',
            ['currentcourse' => $course->id]
        );

        // Load at most MAX_COURSES rows, ordered by name. The final argument
        // to get_records_select is limitnum, which applies a SQL LIMIT so we
        // never pull the whole table into memory.
        $courses = $DB->get_records_select(
            'course',
            'category > 0 AND id <> :currentcourse',
            ['currentcourse' => $course->id],
            'fullname ASC',
            'id, fullname',
            0,
            self::MAX_COURSES
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

        // True when more courses exist than we loaded — the widget shows a hint.
        $capped = ($total > self::MAX_COURSES);

        $this->cachekey    = $cachekey;
        $this->cacheparams = [$courselist, $capped];

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
