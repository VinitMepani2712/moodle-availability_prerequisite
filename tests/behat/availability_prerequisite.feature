@availability @availability_prerequisite
Feature: Restrict activity access by course completion
  As a teacher
  I need to restrict an activity in Course B so it requires Course A to be complete
  So that students follow the intended learning path

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname        | shortname | enablecompletion |
      | Prerequisite Test 1 | Test 1        | 1                |
      | Main Course Test 2  | Test 2        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | Test 1     | editingteacher |
      | teacher1 | Test 2     | editingteacher |
      | student1 | Test 1     | student        |
      | student1 | Test 2     | student        |
    And the following "activities" exist:
      | activity | name            | course | section |
      | page     | Restricted Page | Test 2     | 1       |

  @javascript
  Scenario: Teacher can see the course completion condition in the restriction picker
    Given I am on the "Main Course Test 2" course page logged in as teacher1
    And I turn editing mode on
    And I open "Restricted Page" actions menu
    And I click on "Edit settings" "link" in the "Restricted Page" activity
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    Then I should see "Course completion" in the "Add restriction..." "dialogue"

  @javascript
  Scenario: Course completion condition is added to the activity form
    Given I am on the "Main Course Test 2" course page logged in as teacher1
    And I turn editing mode on
    And I open "Restricted Page" actions menu
    And I click on "Edit settings" "link" in the "Restricted Page" activity
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    When I click on "Course completion" "button" in the "Add restriction..." "dialogue"
    Then I should see "Course completion" in the ".availability-item" "css_element"

  @javascript
  Scenario: Restriction message is shown to students when prerequisite is not complete
    Given I am on the "Main Course Test 2" course page logged in as teacher1
    And I turn editing mode on
    And I open "Restricted Page" actions menu
    And I click on "Edit settings" "link" in the "Restricted Page" activity
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Course completion" "button" in the "Add restriction..." "dialogue"
    And I set the field "coursesearch" to "Prerequisite Test 1"
    And I click on "Prerequisite Test 1" "text" in the ".acc-results" "css_element"
    And I press "Save and return to course"
    When I am on the "Main Course Test 2" course page logged in as student1
    Then I should see "Not available unless"
