
# Changelog

## 1.2.0 - 2026-06-17

### Added

* LICENSE file with the full GNU GPL v3 text (reviewer feedback)
* Auto-apply prerequisite on new activity — when a new activity is created
  in a course that already has prerequisite restrictions on other activities,
  the same prerequisite is automatically applied to the new activity
* New capability `availability/prerequisite:bulkmanage` controlling access to
  the bulk setter page. Granted to editing teachers and managers by default;
  sites can grant it to any custom role via standard role configuration

### Changed

* Performance: the prerequisite course picker now loads at most 250 courses
  at a time using a SQL LIMIT, instead of querying and rendering the entire
  course table. On large sites this prevents memory spikes and slow page
  loads. When more courses exist than the cap, the widget and the bulk setter
  page prompt the user to refine their search (reviewer feedback)
* Performance: course-name lookups and completion-percentage calculations
  in the restriction message are now cached per request, avoiding repeated
  identical database queries when many activities on a page share the same
  prerequisite (reviewer feedback)

## 1.1.0 - 2026-06-02

### Added

* Bulk prerequisite setter (`bulkset.php`) — apply a prerequisite course
  restriction to all activities in a course in one action, instead of
  setting it activity by activity
* Completion progress in restriction message — students see their current
  percentage (e.g. "currently 45% complete") next to the prerequisite course
  link, so they know how close they are
* Clear-all function in the bulk setter to remove all prerequisite
  restrictions from a course at once

## 1.0.0 - 2026-06-01

### Added

* Initial release of availability_prerequisite
* Restrict activity and section access until a student completes a
  prerequisite course
* Live search widget to find courses quickly on large Moodle sites
* Direct clickable link to the prerequisite course in the restriction message
* Supports "must be complete" and "must not be complete" states
* Compatible with Moodle 4.1 through 5.0
* PHPUnit and Behat test coverage
* GDPR privacy provider — no personal data stored
* Backup and restore support
