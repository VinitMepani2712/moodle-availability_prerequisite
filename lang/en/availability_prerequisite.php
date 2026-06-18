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
 * Language strings.
 *
 * @package   availability_prerequisite
 * @copyright 2026 Vinit Mepani <vinitmepani07@email.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Bulk setter page strings.
$string['bulkset_applied']       = 'Prerequisite course "{$a->course}" applied to {$a->count} activities.';
$string['bulkset_apply']         = 'Apply to all activities';
$string['bulkset_capped']        = 'Showing {$a->shown} of {$a->total} courses. If you do not see the course you need, it is not listed here due to the large number of courses on this site.';
$string['bulkset_clearconfirm']  = 'Are you sure you want to remove all prerequisite restrictions from every activity in this course?';
$string['bulkset_cleardesc']     = 'Remove all prerequisite course restrictions from every activity in this course.';
$string['bulkset_cleared']       = 'Prerequisite restrictions removed from {$a} activities.';
$string['bulkset_clearheading']  = 'Clear all prerequisites';
$string['bulkset_desc']          = 'Select a prerequisite course below. The "must be complete" restriction will be applied to every activity in this course at once, saving you from setting it activity by activity.';
$string['bulkset_selectcourse']  = 'Prerequisite course';
$string['bulkset_title']         = 'Set prerequisite for all activities';

$string['choosecourse']            = 'Choose a course...';
$string['description']             = 'Prevent access until a student has completed a prerequisite course.';
$string['error_selectcourse']      = 'You must select a course for the completion condition.';
$string['label_completion']        = 'Required completion status';
$string['label_course']            = 'Required course';
$string['missing']                 = '(Missing course)';
$string['noresults']               = 'No courses match your search.';
$string['option_complete']         = 'must be marked complete';
$string['option_incomplete']       = 'must not be marked complete';
$string['pluginname']              = 'Restriction by prerequisite course';
$string['prerequisite:bulkmanage'] = 'Apply or clear prerequisite restrictions on all activities in a course';
$string['privacy:metadata']        = 'The Restriction by prerequisite course plugin does not store any personal data.';
$string['progress_percent']        = '(currently {$a}% complete)';
$string['requires_complete']       = 'You complete course <strong>{$a}</strong>';
$string['requires_incomplete']     = 'You have not yet completed course <strong>{$a}</strong>';
$string['requires_not_complete']   = 'You do not complete course <strong>{$a}</strong>';
$string['requires_not_incomplete'] = 'You have completed course <strong>{$a}</strong>';
$string['searchcourse']            = 'Search courses by name...';
$string['title']                   = 'Prerequisite course';
$string['toomany']                 = 'Showing the first matches only. Type to narrow your search.';
$string['visit_course']            = 'Open course';
