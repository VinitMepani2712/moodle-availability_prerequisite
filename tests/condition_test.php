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
 * PHPUnit tests for availability_prerequisite condition.
 *
 * Run with:
 *   vendor/bin/phpunit availability/condition/prerequisite/tests/condition_test.php
 *
 * @package   availability_prerequisite
 * @copyright 2026 Vinit Mepani <vinitmepani07@email.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \availability_prerequisite\condition
 */

namespace availability_prerequisite;

use advanced_testcase;

/**
 * Test class for the availability_prerequisite condition.
 *
 * @package   availability_prerequisite
 * @copyright 2026 Vinit Mepani <vinitmepani07@email.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \availability_prerequisite\condition
 */
final class condition_test extends advanced_testcase {
    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Helper: create a course with a page activity and return info_module for it.
     *
     * @param \stdClass $course The course to create the activity in.
     * @return \core_availability\info_module
     */
    private function get_info(\stdClass $course): \core_availability\info_module {
        $page   = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $modinfo = get_fast_modinfo($course);
        $cm     = $modinfo->get_cm($page->cmid);
        return new \core_availability\info_module($cm);
    }

    /**
     * Test that valid JSON structures are accepted by the constructor.
     */
    public function test_constructor_valid(): void {
        $cond = new condition((object)['course' => 5, 'e' => 1]);
        $this->assertNotNull($cond);

        $cond = new condition((object)['course' => 5, 'e' => 0]);
        $this->assertNotNull($cond);
    }

    /**
     * Test that a missing course id throws a coding_exception.
     */
    public function test_constructor_missing_course(): void {
        $this->expectException(\coding_exception::class);
        new condition((object)['e' => 1]);
    }

    /**
     * Test that an invalid expected completion value throws a coding_exception.
     */
    public function test_constructor_invalid_e(): void {
        $this->expectException(\coding_exception::class);
        new condition((object)['course' => 5, 'e' => 99]);
    }

    /**
     * Test that save() returns the correct JSON structure.
     */
    public function test_save(): void {
        $cond  = new condition((object)['course' => 7, 'e' => 1]);
        $saved = $cond->save();

        $this->assertEquals('prerequisite', $saved->type);
        $this->assertEquals(7, $saved->course);
        $this->assertEquals(1, $saved->e);
    }

    /**
     * Test is_available() returns false when the prerequisite course is not complete.
     */
    public function test_is_available_not_completed(): void {
        $prereq = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $prereq->id);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $cond = new condition((object)['course' => (int)$prereq->id, 'e' => 1]);
        $info = $this->get_info($course);

        $this->assertFalse($cond->is_available(false, $info, false, $user->id));
    }

    /**
     * Test is_available() returns true after the prerequisite course is marked complete.
     */
    public function test_is_available_completed(): void {
        global $DB;

        $prereq = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $prereq->id);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $DB->insert_record('course_completions', (object)[
            'userid'        => $user->id,
            'course'        => $prereq->id,
            'timeenrolled'  => time() - 200,
            'timestarted'   => time() - 100,
            'timecompleted' => time(),
            'reaggregate'   => 0,
        ]);

        $cond = new condition((object)['course' => (int)$prereq->id, 'e' => 1]);
        $info = $this->get_info($course);

        $this->assertTrue($cond->is_available(false, $info, false, $user->id));
    }

    /**
     * Test that the NOT flag correctly inverts the result.
     */
    public function test_is_available_not_flag(): void {
        global $DB;

        $prereq = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $prereq->id);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $DB->insert_record('course_completions', (object)[
            'userid'        => $user->id,
            'course'        => $prereq->id,
            'timeenrolled'  => time() - 200,
            'timestarted'   => time() - 100,
            'timecompleted' => time(),
            'reaggregate'   => 0,
        ]);

        $cond = new condition((object)['course' => (int)$prereq->id, 'e' => 1]);
        $info = $this->get_info($course);

        $this->assertTrue($cond->is_available(false, $info, false, $user->id));
        $this->assertFalse($cond->is_available(true, $info, false, $user->id));
    }

    /**
     * Test the INCOMPLETE condition: available when course has NOT been completed.
     */
    public function test_is_available_incomplete_condition(): void {
        $prereq = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $prereq->id);
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $cond = new condition((object)['course' => (int)$prereq->id, 'e' => 0]);
        $info = $this->get_info($course);

        $this->assertTrue($cond->is_available(false, $info, false, $user->id));
    }

    /**
     * Test get_description() contains the course name.
     */
    public function test_get_description_complete(): void {
        $prereq = $this->getDataGenerator()->create_course(
            ['fullname' => 'Prerequisite Course', 'enablecompletion' => 1]
        );
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $cond = new condition((object)['course' => (int)$prereq->id, 'e' => 1]);
        $info = $this->get_info($course);

        $description = $cond->get_description(true, false, $info);
        $this->assertStringContainsString('Prerequisite Course', $description);
    }

    /**
     * Test get_description() handles a deleted course gracefully.
     */
    public function test_get_description_missing_course(): void {
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $cond = new condition((object)['course' => 99999, 'e' => 1]);
        $info = $this->get_info($course);

        $description = $cond->get_description(true, false, $info);
        $this->assertStringContainsString(
            get_string('missing', 'availability_prerequisite'),
            $description
        );
    }

    /**
     * Test the static get_json() helper.
     */
    public function test_get_json(): void {
        $json = condition::get_json(42, condition::COMPLETION_COMPLETE);

        $this->assertEquals('prerequisite', $json->type);
        $this->assertEquals(42, $json->course);
        $this->assertEquals(1, $json->e);
    }
}