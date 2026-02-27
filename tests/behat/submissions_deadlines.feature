@mod @mod_coursework @mod_coursework_submissions_deadlines
Feature: Deadlines for submissions

    As a teacher
    I want to set deadlines that are visible to the student
    So that they know when they are expected to submit, and can be sent automatic reminders

  Background:
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
    And I am logged in as a teacher
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |

  # General feedback visibility was included here, but it is now no longer shown to markers.
  # Instead, it is now accessible to managers via the secondary navigation only.
  # See @mod_coursework_feedback_general

  Scenario: the individual feedback deadline should not be visible if not enabled
    Given the coursework "individualfeedback" setting is "0" in the database
    When I am on the "Coursework" "coursework activity" page
    Then I should not see the date when the individual feedback will be released

  Scenario: the individual feedback deadline should be visible if enabled
    Given the coursework "individualfeedback" setting is "777777" in the database
    When I am on the "Coursework" "coursework activity" page
    Then I should see the date when the individual feedback will be released
