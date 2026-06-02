# Changelog

## [1.0.0] - 2024-12-01

### Added
- Initial release of `availability_prerequisite`
- Availability condition: restrict activity/section access until another course is complete
- Supports "must be complete" and "must not be complete" states
- Compatible with Moodle 4.1, 4.2, 4.3, 4.4, 4.5
- PHPUnit test suite (`tests/condition_test.php`)
- Behat acceptance tests (`tests/behat/availability_prerequisite.feature`)
- GDPR privacy provider (no personal data stored)
- Backup/restore support via `update_after_restore()`
- Proper YUI module structure (`yui/build/`) for Moodle core_availability framework
