@mod @mod_coursework @mod_coursework_feedback_general
Feature: general feedback can be immediate

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
      | generalfeedback | 0                           |
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

  Scenario: no general feedback release date shows students the feedback immediately
    Given the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "Feedback for all students"
    And I should see "Some general feedback comments"
