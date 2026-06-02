# Restriction by prerequisite course (`availability_prerequisite`)

[![Moodle Plugin CI](https://github.com/yourusername/moodle-availability_prerequisite/actions/workflows/moodle-ci.yml/badge.svg)](https://github.com/yourusername/moodle-availability_prerequisite/actions)

An availability condition plugin for Moodle that restricts access to activities, resources, or course sections until a student has **completed another course**.

---

## Features

- Restrict any activity or section in **Course B** until a student completes **Course A**
- Supports both "must be complete" and "must NOT be complete" conditions
- Works across any number of courses — chain T1 → T2 → T3 as needed
- Gracefully hides or grays out restricted content (respects Moodle's standard restriction display)
- No database tables added — reads Moodle core's `{course_completions}` table
- Fully GDPR compliant (no personal data stored)

## Use case example

You have three courses: **T1**, **T2**, **T3**. Students are enrolled in all three simultaneously. You want to enforce a learning path:

| Activity in... | Restriction set to...          |
|---------------|-------------------------------|
| T2            | Course completion → T1 complete |
| T3            | Course completion → T2 complete |

Students cannot access T2 activities until T1 is done, and T3 until T2 is done.

---

## Compatibility

| Moodle | PHP   | Status      |
|--------|-------|-------------|
| 4.1    | 7.4+  | ✅ Supported |
| 4.2    | 8.0+  | ✅ Supported |
| 4.3    | 8.0+  | ✅ Supported |
| 4.4    | 8.1+  | ✅ Supported |
| 4.5    | 8.1+  | ✅ Supported |

---

## Installation

### Via Moodle Plugin Directory (recommended)
1. Log in as admin → Site Administration → Plugins → Install plugins
2. Search for "availability_prerequisite" and click Install

### Manual installation
1. Download the zip from the Moodle Plugin Directory
2. Extract to `<moodleroot>/availability/condition/coursecompleted/`
3. Log in as admin → Site Administration → Notifications → click **Upgrade Moodle database**

### Prerequisites
- **Completion tracking** must be enabled:
  Site Administration → Advanced features → ✅ Enable completion tracking
- The **prerequisite course** must have completion criteria configured
- Students must be enrolled in both the prerequisite course and the target course

---

## Usage

1. Go to the target course (e.g. T2)
2. Turn editing on
3. Click the edit menu (⚙) on any activity → **Edit settings**
4. Expand the **Restrict access** section
5. Click **Add restriction** → **Course completion**
6. Select the prerequisite course from the dropdown
7. Choose "must be marked complete" or "must not be marked complete"
8. Save

---

## Running the tests

### PHPUnit
```bash
# From your Moodle root:
vendor/bin/phpunit availability/condition/coursecompleted/tests/condition_test.php
```

### Behat
```bash
# Initialise Behat if you haven't already:
php admin/tool/behat/cli/init.php

# Run only this plugin's tests:
vendor/bin/behat --config /path/to/behat.yml \
  --tags @availability_prerequisite
```

---

## Changelog

### 1.0.0 (2024-12-01)
- Initial release
- Supports Moodle 4.1 – 4.5
- Requires completion of any other course as a condition

---

## Contributing

Pull requests are welcome. Please ensure:
- Code follows [Moodle coding style](https://moodledev.io/general/development/policies/codingstyle)
- PHPUnit tests pass
- Behat scenarios pass

## License

GNU General Public License v3 or later.
See [LICENSE](https://www.gnu.org/licenses/gpl-3.0.html) for details.

## Credits

Inspired by the `availability_othercompleted` plugin by MU DOT MY PLT.
Rebuilt from scratch for Moodle 4.1+ compatibility with proper YUI module
structure and parameterised database queries.
