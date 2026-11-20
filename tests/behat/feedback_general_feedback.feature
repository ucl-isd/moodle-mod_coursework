@mod @mod_coursework @mod_coursework_feedback_general
Feature: general feedback

  As a manager
  I want to be able to provide some general feedback for all of the students before their individual feedback is released
  So that they can prepare effectively for upcoming exams or assignments

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity        | coursework                            |
      | course          | C1                                    |
      | name            | Coursework                            |
      | generalfeedback | ##-1 week##                           |
      | feedbackcomment | <p>Some general feedback comments</p> |
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

  Scenario: enabling general feedback shows the place for managers but not teachers to enter feedback
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    Then I should see "Feedback for all students" in the ".secondary-navigation" "css_element"

    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should not see "Feedback for all students" in the ".secondary-navigation" "css_element"

  Scenario: enabling general feedback shows students the feedback when the deadline has passed
    Given the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "Feedback for all students"
    And I should see "Some general feedback comments"

  Scenario: the release date is set on the feedback for all students page, not in the settings
    Given I am on the "Coursework" "coursework activity editing" page logged in as "manager1"
    Then I should not see "Release date of feedback for all students"
    And I am on the "Coursework" "coursework activity" page
    And I navigate to "Feedback for all students" in current page administration
    And I should see "Release date of feedback for all students"
    And the field "generalfeedback[enabled]" matches value "1"
    When I set the field "generalfeedback[year]" to "##+1 year##%Y##"
    And I press "Save changes"
    And I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should not see "Some general feedback comments"
