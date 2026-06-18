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
 * PHPUnit tests for the availability_prerequisite course search web service.
 *
 * @package   availability_prerequisite
 * @copyright 2026 Vinit Mepani <vinitmepani07@email.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \availability_prerequisite\external\search_courses
 */

namespace availability_prerequisite\external;

use advanced_testcase;
use core_external\external_api;

/**
 * Test class for the search_courses external function.
 *
 * @package   availability_prerequisite
 * @copyright 2026 Vinit Mepani <vinitmepani07@email.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \availability_prerequisite\external\search_courses
 */
final class search_courses_test extends advanced_testcase {
    /**
     * Set up test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Calls the external function and cleans the return value against its schema.
     *
     * @param string $query           Search text.
     * @param int    $currentcourseid Course being edited.
     * @return array Cleaned response.
     */
    private function call(string $query, int $currentcourseid): array {
        $result = search_courses::execute($query, $currentcourseid);
        return external_api::clean_returnvalue(search_courses::execute_returns(), $result);
    }

    /**
     * A course is found by name even though it sorts late in the alphabet,
     * which is exactly the case the old client-side, capped search missed.
     */
    public function test_search_finds_course_by_name(): void {
        $gen     = $this->getDataGenerator();
        $current = $gen->create_course(['enablecompletion' => 1]);
        $gen->create_course(['fullname' => 'Alpha Course']);
        $target  = $gen->create_course(['fullname' => 'T1 Testing Course']);

        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $current->id, 'editingteacher');
        $this->setUser($teacher);

        $response = $this->call('T1', (int)$current->id);

        $ids = array_column($response['courses'], 'id');
        $this->assertContains((int)$target->id, $ids);
        $this->assertNotContains((int)$current->id, $ids, 'The current course must be excluded.');
    }

    /**
     * An empty query returns the candidate courses (initial display), and never
     * includes the course being edited.
     */
    public function test_empty_query_returns_candidates(): void {
        $gen     = $this->getDataGenerator();
        $current = $gen->create_course(['enablecompletion' => 1]);
        $other   = $gen->create_course(['fullname' => 'Some Other Course']);

        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $current->id, 'editingteacher');
        $this->setUser($teacher);

        $response = $this->call('', (int)$current->id);

        $ids = array_column($response['courses'], 'id');
        $this->assertContains((int)$other->id, $ids);
        $this->assertNotContains((int)$current->id, $ids);
    }

    /**
     * A non-matching query returns no courses.
     */
    public function test_search_no_match(): void {
        $gen     = $this->getDataGenerator();
        $current = $gen->create_course(['enablecompletion' => 1]);
        $gen->create_course(['fullname' => 'Biology 101']);

        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $current->id, 'editingteacher');
        $this->setUser($teacher);

        $response = $this->call('zzz-no-such-course', (int)$current->id);

        $this->assertSame([], $response['courses']);
        $this->assertFalse($response['capped']);
    }

    /**
     * Users without the manage-activities capability cannot search.
     */
    public function test_requires_capability(): void {
        $gen     = $this->getDataGenerator();
        $current = $gen->create_course();
        $student = $gen->create_user();
        $gen->enrol_user($student->id, $current->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        search_courses::execute('anything', (int)$current->id);
    }
}
