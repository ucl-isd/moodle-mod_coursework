@mod @mod_coursework @mod_coursework_submissions_deadlines
Feature: Deadlines for submissions

  As a teacher
  I want to set deadlines that are visible to the student
  So that they know when they are expected to submit, and can be sent automatic reminders

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activity" exists:
      | activity           | coursework |
      | course             | C1         |
      | name               | Coursework |
      | individualfeedback | 0          |

  # General feedback visibility was included here, but it is now no longer shown to markers.
  # Instead, it is now accessible to managers via the secondary navigation only.
  # See @mod_coursework_feedback_general

  Scenario: the individual feedback deadline should not be visible if not enabled
    When I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should not see "Auto-release feedback"

    Given I navigate to "Settings" in current page administration
    And I set the field "individualfeedback" to "##+1 week##"
    And I press "Save and display"
    When I am on the "Coursework" "coursework activity" page
    Then I should see "Auto-release feedback"
    And I should see "##+1 week##" in the ".behat-duedate" "css_element"
