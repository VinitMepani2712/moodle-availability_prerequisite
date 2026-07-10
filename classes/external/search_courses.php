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
 * External function: search courses for the prerequisite picker.
 *
 * @package   availability_prerequisite
 * @copyright 2026 Vinit Mepani <vinitmepani07@email.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_prerequisite\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;

// Moodle 4.2 moved the external API classes into the core_external namespace,
// where they are autoloaded. On Moodle 4.1 they are global classes defined in
// externallib.php, so on that version load them and alias them onto the
// core_external names used below. This keeps 4.1 working while avoiding any
// include of externallib.php on 4.2+ (which is disallowed during unit tests).
if (!class_exists(\core_external\external_api::class)) {
    global $CFG;
    require_once($CFG->libdir . '/externallib.php');
    class_alias(\external_api::class, \core_external\external_api::class);
    class_alias(\external_function_parameters::class, \core_external\external_function_parameters::class);
    class_alias(\external_value::class, \core_external\external_value::class);
    class_alias(\external_single_structure::class, \core_external\external_single_structure::class);
    class_alias(\external_multiple_structure::class, \core_external\external_multiple_structure::class);
}

/**
 * Server-side course search backing the availability condition editor widget.
 *
 * The editor must work on sites with far more courses than can reasonably be
 * shipped to the browser, so the picker queries this function as the user
 * types instead of filtering a pre-loaded (and necessarily truncated) list.
 *
 * @package   availability_prerequisite
 * @copyright 2026 Vinit Mepani <vinitmepani07@email.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_courses extends external_api {
    /**
     * Describes the parameters accepted by {@see self::execute()}.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(
                PARAM_RAW,
                'Text to match against course full and short names',
                VALUE_DEFAULT,
                ''
            ),
            'currentcourseid' => new external_value(
                PARAM_INT,
                'ID of the course being edited; excluded from results and used for the permission check'
            ),
        ]);
    }

    /**
     * Searches courses that may be used as a prerequisite.
     *
     * @param string $query           Text to match against course names.
     * @param int    $currentcourseid Course being edited (excluded; provides the permission context).
     * @return array Array with 'courses' (list of id/name/url) and 'capped' (bool) keys.
     */
    public static function execute(string $query, int $currentcourseid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'query'           => $query,
            'currentcourseid' => $currentcourseid,
        ]);
        $query           = trim($params['query']);
        $currentcourseid = $params['currentcourseid'];

        // Only users who can edit activities in the course being edited (the
        // people who actually see this picker) may search the course list.
        $context = \context_course::instance($currentcourseid);
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        $limit = \availability_prerequisite\frontend::MAX_COURSES;

        // Candidate courses: real courses (category > 0), never the current one.
        $select    = 'category > 0 AND id <> :currentcourse';
        $sqlparams = ['currentcourse' => $currentcourseid];

        if ($query !== '') {
            // Case-insensitive partial match on the full or short name.
            $fullnamelike  = $DB->sql_like('fullname', ':qfull', false, false);
            $shortnamelike = $DB->sql_like('shortname', ':qshort', false, false);
            $select       .= " AND ($fullnamelike OR $shortnamelike)";
            $escaped             = '%' . $DB->sql_like_escape($query) . '%';
            $sqlparams['qfull']  = $escaped;
            $sqlparams['qshort'] = $escaped;
        }

        // Count first so we can tell the widget when the result set was capped.
        $total = $DB->count_records_select('course', $select, $sqlparams);

        $courses = $DB->get_records_select(
            'course',
            $select,
            $sqlparams,
            'fullname ASC',
            'id, fullname',
            0,
            $limit
        );

        $results = [];
        foreach ($courses as $c) {
            $url = new \moodle_url('/course/view.php', ['id' => $c->id]);
            // format_string() HTML-encodes for display, but we're returning JSON.
            // Decode back to literal characters so search input matches result names.
            $formatted = format_string($c->fullname, true, ['context' => $context]);
            $results[] = [
                'id'   => (int)$c->id,
                'name' => html_entity_decode($formatted, ENT_QUOTES | ENT_HTML5),
                'url'  => $url->out(false),
            ];
        }

        return [
            'courses' => $results,
            'capped'  => ($total > $limit),
        ];
    }

    /**
     * Describes the value returned by {@see self::execute()}.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'id'   => new external_value(PARAM_INT, 'Course id'),
                    'name' => new external_value(PARAM_RAW, 'Formatted course full name'),
                    'url'  => new external_value(PARAM_URL, 'URL to view the course'),
                ])
            ),
            'capped' => new external_value(
                PARAM_BOOL,
                'True when more matches exist than were returned'
            ),
        ]);
    }
}
