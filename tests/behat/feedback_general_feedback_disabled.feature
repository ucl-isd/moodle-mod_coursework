@mod @mod_coursework @mod_coursework_feedback_general
Feature: general feedback can be disabled

  As a manager
  I do not want to be able to provide some general feedback for all of the students

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity        | coursework                            |
      | course          | C1                                    |
      | name            | Coursework                            |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | manager   | manager1 | manager1@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | manager1 | C1     | manager        |
      | student1 | C1     | student        |

  Scenario: disabling general feedback hides the feedback from students
    Given I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should not see "Feedback for all students"

  Scenario: disabling general feedback does not hide the place for managers to enter feedback
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    Then I should see "Feedback for all students" in the ".secondary-navigation" "css_element"
