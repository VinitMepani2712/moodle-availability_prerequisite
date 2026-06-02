@availability @availability_prerequisite
Feature: Restrict activity access by prerequisite course completion
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
      | Prerequisite T1 | T1        | 1                |
      | Main Course T2  | T2        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | T1     | editingteacher |
      | teacher1 | T2     | editingteacher |
      | student1 | T1     | student        |
      | student1 | T2     | student        |
    And the following "activities" exist:
      | activity | name            | course | section |
      | page     | Restricted Page | T2     | 1       |

  @javascript
  Scenario: Teacher can see the prerequisite course condition in the restriction picker
    Given I am on the "Main Course T2" course page logged in as teacher1
    And I turn editing mode on
    And I open "Restricted Page" actions menu
    And I click on "Edit settings" "link" in the "Restricted Page" activity
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    Then I should see "Prerequisite course" in the "Add restriction..." "dialogue"

  @javascript
  Scenario: Prerequisite course condition is added to the activity form
    Given I am on the "Main Course T2" course page logged in as teacher1
    And I turn editing mode on
    And I open "Restricted Page" actions menu
    And I click on "Edit settings" "link" in the "Restricted Page" activity
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    When I click on "Prerequisite course" "button" in the "Add restriction..." "dialogue"
    Then I should see "Prerequisite course" in the ".availability-item" "css_element"

  @javascript
  Scenario: Restriction message is shown to students when prerequisite is not complete
    Given I am on the "Main Course T2" course page logged in as teacher1
    And I turn editing mode on
    And I open "Restricted Page" actions menu
    And I click on "Edit settings" "link" in the "Restricted Page" activity
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Prerequisite course" "button" in the "Add restriction..." "dialogue"
    And I set the field "coursesearch" to "Prerequisite T1"
    And I click on "Prerequisite T1" "text" in the ".acc-results" "css_element"
    And I press "Save and return to course"
    When I am on the "Main Course T2" course page logged in as student1
    Then I should see "Not available unless"
