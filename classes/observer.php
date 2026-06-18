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
 * Event observer for availability_prerequisite.
 *
 * @package   availability_prerequisite
 * @copyright 2026 Vinit Mepani <vinitmepani07@email.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_prerequisite;

/**
 * Observes Moodle events and auto-applies prerequisite restrictions.
 *
 * When a new activity is created in a course that already has at least one
 * activity with an availability_prerequisite condition, the same prerequisite
 * is automatically applied to the new activity.
 *
 * How it works:
 *  1. A teacher creates a new activity in Course T2.
 *  2. Moodle fires the course_module_created event.
 *  3. This observer scans other activities in T2 for an existing
 *     availability_prerequisite condition.
 *  4. If one is found, the same condition JSON is copied to the new activity.
 *  5. The course cache is rebuilt so the restriction takes effect immediately.
 *
 * If existing activities have different prerequisites set (mixed conditions),
 * the observer takes the most common one. If no prerequisite exists yet,
 * nothing happens — the teacher sets the first one manually (or uses bulkset.php).
 *
 * @package   availability_prerequisite
 * @copyright 2026 Vinit Mepani <vinitmepani07@email.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Fired when a new course module is created.
     *
     * Checks whether other activities in the same course already have a
     * prerequisite restriction and if so, applies the same one to the
     * new activity automatically.
     *
     * @param \core\event\course_module_created $event The event.
     * @return void
     */
    public static function course_module_created(
        \core\event\course_module_created $event
    ): void {
        global $DB;

        $courseid = (int)$event->courseid;
        $newcmid  = (int)$event->objectid;

        // Find the most commonly used availability_prerequisite condition
        // among existing activities in this course (excluding the new one).
        $cms = $DB->get_records_select(
            'course_modules',
            'course = :course AND id <> :newcm AND availability IS NOT NULL',
            ['course' => $courseid, 'newcm' => $newcmid],
            '',
            'id, availability'
        );

        if (empty($cms)) {
            return; // No other activities have restrictions — nothing to copy.
        }

        // Extract all prerequisite conditions found across activities.
        // Key = JSON-encoded condition, Value = count of activities using it.
        $found = [];
        foreach ($cms as $cm) {
            $tree = json_decode($cm->availability, true);
            if (!$tree || empty($tree['c'])) {
                continue;
            }
            foreach ($tree['c'] as $cond) {
                if (isset($cond['type']) && $cond['type'] === 'prerequisite') {
                    $key = json_encode($cond);
                    $found[$key] = ($found[$key] ?? 0) + 1;
                }
            }
        }

        if (empty($found)) {
            return; // No prerequisite conditions found on any existing activity.
        }

        // Use the most commonly occurring prerequisite condition.
        arsort($found);
        $mostcommon = json_decode(array_key_first($found), true);

        // Build the availability JSON for the new activity.
        $newavailability = json_encode([
            'op'    => '&',
            'c'     => [$mostcommon],
            'showc' => [true],
        ]);

        // Apply it to the new activity.
        $DB->set_field(
            'course_modules',
            'availability',
            $newavailability,
            ['id' => $newcmid]
        );

        // Rebuild the course cache so it takes effect immediately.
        rebuild_course_cache($courseid, true);
    }
}
