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
 * Bulk prerequisite setter page.
 *
 * Allows a teacher to apply a single prerequisite course restriction
 * to ALL activities in the current course in one action, instead of
 * setting the restriction activity by activity.
 *
 * NOTE: this file lives in availability_prerequisite/bulkset/bulkset.php
 * (one level deeper than the plugin root), so config.php is FIVE
 * directories up, not four.
 *
 * Access: requires availability/prerequisite:bulkmanage in the course context.
 *
 * @package   availability_prerequisite
 * @copyright 2026 Vinit Mepani <vinitmepani07@email.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/completionlib.php');

$courseid = required_param('courseid', PARAM_INT);
$prereqid = optional_param('prereqid', 0, PARAM_INT);
$confirm  = optional_param('confirm', 0, PARAM_BOOL);
$clear    = optional_param('clear', 0, PARAM_BOOL);

$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
require_capability('availability/prerequisite:bulkmanage', $context);

$PAGE->set_url(new moodle_url('/availability/condition/prerequisite/bulkset/bulkset.php',
    ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('bulkset_title', 'availability_prerequisite'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// ── Handle form submission ────────────────────────────────────────────────────

if ($confirm && ($prereqid > 0 || $clear)) {

    require_sesskey();

    // Build the new availability JSON for this condition (or null to clear).
    if ($clear) {
        $newavailability = null;
    } else {
        $prereqcourse = $DB->get_record('course', ['id' => $prereqid], '*', MUST_EXIST);
        $newavailability = json_encode([
            'op'    => '&',
            'c'     => [
                [
                    'type'   => 'prerequisite',
                    'course' => (int)$prereqid,
                    'e'      => 1,
                ],
            ],
            'showc' => [true],
        ]);
    }

    // Get all course modules for this course.
    $cms = $DB->get_records('course_modules', ['course' => $courseid], '', 'id');
    $count = 0;
    foreach ($cms as $cm) {
        $DB->set_field('course_modules', 'availability', $newavailability, ['id' => $cm->id]);
        $count++;
    }

    // Rebuild course cache so changes take effect immediately.
    rebuild_course_cache($courseid, true);

    $successmsg = $clear
        ? get_string('bulkset_cleared', 'availability_prerequisite', $count)
        : get_string('bulkset_applied', 'availability_prerequisite',
            (object)['count' => $count, 'course' => format_string($prereqcourse->fullname)]);

    redirect(
        new moodle_url('/availability/condition/prerequisite/bulkset/bulkset.php',
            ['courseid' => $courseid]),
        $successmsg,
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// ── Build course list for select ─────────────────────────────────────────────

// Cap the number of courses loaded to avoid memory issues on large sites.
$maxcourses   = \availability_prerequisite\frontend::MAX_COURSES;
$totalcourses = $DB->count_records_select(
    'course',
    'category > 0 AND id <> :cid',
    ['cid' => $courseid]
);
$othercourses = $DB->get_records_select(
    'course',
    'category > 0 AND id <> :cid',
    ['cid' => $courseid],
    'fullname ASC',
    'id, fullname',
    0,
    $maxcourses
);

$courseoptions = [0 => get_string('choosecourse', 'availability_prerequisite')];
foreach ($othercourses as $c) {
    $courseoptions[$c->id] = format_string($c->fullname);
}

// ── Render page ───────────────────────────────────────────────────────────────

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('bulkset_title', 'availability_prerequisite'));

echo html_writer::tag('p', get_string('bulkset_desc', 'availability_prerequisite'));

// Warn if not all courses are shown due to the safety cap.
if ($totalcourses > $maxcourses) {
    echo $OUTPUT->notification(
        get_string('bulkset_capped', 'availability_prerequisite',
            (object)['shown' => $maxcourses, 'total' => $totalcourses]),
        \core\output\notification::NOTIFY_INFO
    );
}

// Form.
$formurl = new moodle_url('/availability/condition/prerequisite/bulkset/bulkset.php');
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $formurl->out(false),
    'class'  => 'mform',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid',  'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm',   'value' => 1]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey',   'value' => sesskey()]);

// Course select.
echo html_writer::start_div('form-group row');
echo html_writer::tag('label',
    get_string('bulkset_selectcourse', 'availability_prerequisite'),
    ['class' => 'col-md-4 col-form-label', 'for' => 'prereqid']
);
echo html_writer::start_div('col-md-6');
echo html_writer::select($courseoptions, 'prereqid', $prereqid, false,
    ['id' => 'prereqid', 'class' => 'custom-select form-control']);
echo html_writer::end_div();
echo html_writer::end_div();

// Submit button.
echo html_writer::start_div('form-group row');
echo html_writer::start_div('col-md-6 offset-md-4');
echo html_writer::empty_tag('input', [
    'type'  => 'submit',
    'value' => get_string('bulkset_apply', 'availability_prerequisite'),
    'class' => 'btn btn-primary mr-2',
]);
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_tag('form');

// Clear all section.
echo html_writer::tag('hr', '');
echo html_writer::tag('h4', get_string('bulkset_clearheading', 'availability_prerequisite'));
echo html_writer::tag('p',  get_string('bulkset_cleardesc',    'availability_prerequisite'));

$clearurl = new moodle_url('/availability/condition/prerequisite/bulkset/bulkset.php', [
    'courseid' => $courseid,
    'confirm'  => 1,
    'clear'    => 1,
    'sesskey'  => sesskey(),
]);
echo $OUTPUT->confirm(
    get_string('bulkset_clearconfirm', 'availability_prerequisite'),
    $clearurl,
    new moodle_url('/availability/condition/prerequisite/bulkset/bulkset.php',
        ['courseid' => $courseid])
);

echo $OUTPUT->footer();