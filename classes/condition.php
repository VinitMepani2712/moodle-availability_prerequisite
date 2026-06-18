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
 * Availability condition: require completion of a prerequisite course.
 *
 * @package   availability_prerequisite
 * @copyright 2026 Vinit Mepani <vinitmepani07@email.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_prerequisite;

/**
 * Condition class for prerequisite course availability.
 *
 * Restricts access to activities/sections until a student has
 * completed (or not completed) a prerequisite course.
 *
 * @package   availability_prerequisite
 * @copyright 2026 Vinit Mepani <vinitmepani07@email.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {
    /** @var int Student must have completed the other course. */
    const COMPLETION_COMPLETE = 1;

    /** @var int Student must NOT have completed the other course. */
    const COMPLETION_INCOMPLETE = 0;

    /** @var int ID of the prerequisite course. */
    protected $courseid;

    /** @var int Expected completion state. */
    protected $expectedcompletion;

    /**
     * @var array Request-level cache of course full names, keyed by course id.
     *            Avoids repeated {course} lookups when many activities on the
     *            same page reference the same prerequisite course.
     */
    protected static $namecache = [];

    /**
     * @var array Request-level cache of completion percentages, keyed by
     *            "courseid:userid". Avoids re-running the completion count
     *            queries for every restricted item on a page.
     */
    protected static $pctcache = [];

    /**
     * Constructor.
     *
     * @param \stdClass $structure Data structure decoded from JSON.
     * @throws \coding_exception When the structure is invalid.
     */
    public function __construct($structure) {
        if (isset($structure->course) && is_number($structure->course)) {
            $this->courseid = (int)$structure->course;
        } else {
            throw new \coding_exception(
                'Missing or invalid ->course for availability_prerequisite condition'
            );
        }

        $validstates = [self::COMPLETION_COMPLETE, self::COMPLETION_INCOMPLETE];
        if (isset($structure->e) && in_array((int)$structure->e, $validstates, true)) {
            $this->expectedcompletion = (int)$structure->e;
        } else {
            throw new \coding_exception(
                'Missing or invalid ->e for availability_prerequisite condition'
            );
        }
    }

    /**
     * Saves the condition back to a JSON-serialisable object.
     *
     * @return \stdClass
     */
    public function save() {
        return (object)[
            'type'   => 'prerequisite',
            'course' => $this->courseid,
            'e'      => $this->expectedcompletion,
        ];
    }

    /**
     * Returns a JSON object for this condition (used in unit tests).
     *
     * @param int $courseid           The prerequisite course id.
     * @param int $expectedcompletion COMPLETION_COMPLETE or COMPLETION_INCOMPLETE.
     * @return \stdClass
     */
    public static function get_json(int $courseid, int $expectedcompletion): \stdClass {
        return (object)[
            'type'   => 'prerequisite',
            'course' => $courseid,
            'e'      => $expectedcompletion,
        ];
    }

    /**
     * Checks whether this condition is satisfied for a given user.
     *
     * @param bool                    $not        True if the condition is negated.
     * @param \core_availability\info $info       Availability info object.
     * @param bool                    $grabthelot True for bulk-preloading.
     * @param int                     $userid     User to check.
     * @return bool True if the condition is met.
     */
    public function is_available($not, \core_availability\info $info, $grabthelot, $userid): bool {
        global $DB;

        $completed = $DB->record_exists_select(
            'course_completions',
            'course = :course AND userid = :userid AND timecompleted > 0',
            ['course' => $this->courseid, 'userid' => $userid]
        );

        $allow = ($this->expectedcompletion === self::COMPLETION_COMPLETE)
            ? $completed
            : !$completed;

        if ($not) {
            $allow = !$allow;
        }

        return $allow;
    }

    /**
     * Calculates the student's current completion percentage in the prerequisite course.
     *
     * Returns a value 0–100 based on how many completable activities the
     * student has completed, giving them meaningful progress feedback even
     * before the course is formally marked complete.
     *
     * @param int $userid The user to calculate progress for.
     * @return int|null Percentage 0-100, or null if progress cannot be calculated.
     */
    protected function get_completion_percentage(int $userid): ?int {
        global $DB;

        // Return cached value if we already calculated it this request.
        $cachekey = $this->courseid . ':' . $userid;
        if (array_key_exists($cachekey, self::$pctcache)) {
            return self::$pctcache[$cachekey];
        }

        // Count total completable activities in the prerequisite course.
        $total = $DB->count_records_select(
            'course_modules',
            'course = :course AND completion > 0 AND deletioninprogress = 0',
            ['course' => $this->courseid]
        );

        if ($total === 0) {
            self::$pctcache[$cachekey] = null;
            return null;
        }

        // Count activities the user has completed.
        $done = $DB->count_records_sql(
            'SELECT COUNT(cmc.id)
               FROM {course_modules_completion} cmc
               JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              WHERE cm.course = :course
                AND cmc.userid = :userid
                AND cmc.completionstate > 0',
            ['course' => $this->courseid, 'userid' => $userid]
        );

        $pct = (int)round(($done / $total) * 100);
        self::$pctcache[$cachekey] = $pct;
        return $pct;
    }

    /**
     * Returns a human-readable description of this condition.
     *
     * When shown to a student, includes their current completion percentage
     * so they can see how far they are from meeting the prerequisite.
     *
     * @param bool                    $full True for a full description.
     * @param bool                    $not  True if the condition is negated.
     * @param \core_availability\info $info Availability info object.
     * @return string
     */
    public function get_description($full, $not, \core_availability\info $info): string {
        global $USER;

        // Look up the course name (request-cached to avoid repeat queries
        // when many activities on a page share the same prerequisite).
        if (array_key_exists($this->courseid, self::$namecache)) {
            $coursename = self::$namecache[$this->courseid];
        } else {
            global $DB;
            $coursename = $DB->get_field('course', 'fullname', ['id' => $this->courseid]);
            self::$namecache[$this->courseid] = $coursename;
        }

        if ($coursename === false) {
            $displayname = get_string('missing', 'availability_prerequisite');
        } else {
            $name = format_string($coursename);
            $url  = new \moodle_url('/course/view.php', ['id' => $this->courseid]);
            $link = \html_writer::link(
                $url,
                $name,
                ['target' => '_blank', 'rel' => 'noopener noreferrer']
            );

            // Show completion progress percentage to the student.
            $displayname = $link;
            if (!$not && $this->expectedcompletion === self::COMPLETION_COMPLETE) {
                $pct = $this->get_completion_percentage((int)$USER->id);
                if ($pct !== null && $pct < 100) {
                    $progressstr = get_string(
                        'progress_percent',
                        'availability_prerequisite',
                        $pct
                    );
                    $displayname .= ' ' . \html_writer::span(
                        $progressstr,
                        'availability-prerequisite-progress'
                    );
                }
            }
        }

        if ($not) {
            $key = ($this->expectedcompletion === self::COMPLETION_COMPLETE)
                ? 'requires_not_complete'
                : 'requires_not_incomplete';
        } else {
            $key = ($this->expectedcompletion === self::COMPLETION_COMPLETE)
                ? 'requires_complete'
                : 'requires_incomplete';
        }

        return get_string($key, 'availability_prerequisite', $displayname);
    }

    /**
     * Returns a short debug string for this condition.
     *
     * @return string
     */
    protected function get_debug_string(): string {
        $state = ($this->expectedcompletion === self::COMPLETION_COMPLETE)
            ? 'COMPLETE'
            : 'INCOMPLETE';
        return 'course' . $this->courseid . ' ' . $state;
    }

    /**
     * Updates the stored course id after a restore operation.
     *
     * @param string       $restoreid The restore operation ID.
     * @param int          $courseid  The new course id context.
     * @param \base_logger $logger    Logger for warnings.
     * @param string       $name      Name for log messages.
     * @return bool True if this object was modified.
     */
    public function update_after_restore($restoreid, $courseid, \base_logger $logger, $name): bool {
        global $DB;

        $rec = \restore_dbops::get_backup_ids_record($restoreid, 'course', $this->courseid);
        if (!$rec || !$rec->newitemid) {
            if ($DB->record_exists('course', ['id' => $this->courseid])) {
                return false;
            }
            $this->courseid = 0;
            $logger->process(
                'Restored item (' . $name . ') has availability_prerequisite condition ' .
                'pointing to a course that was not restored',
                \backup::LOG_WARNING
            );
        } else {
            $this->courseid = (int)$rec->newitemid;
        }

        return true;
    }

    /**
     * Updates a dependency id when a record is renumbered.
     *
     * @param string $table  DB table name.
     * @param int    $oldid  Old record id.
     * @param int    $newid  New record id.
     * @return bool True if this object was modified.
     */
    public function update_dependency_id($table, $oldid, $newid): bool {
        if ($table === 'course' && (int)$this->courseid === (int)$oldid) {
            $this->courseid = (int)$newid;
            return true;
        }
        return false;
    }
}