@mod @mod_coursework @mod_coursework_feedback_collisions
Feature: Collisions: two people try to create feedback at the same time

  As a teacher
  I want to see a warning message if I try to save my feedback when another
  teacher has already done so
  So that I do not get a surprise when the grades I have awarded disappear

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity        | coursework |
      | course          | C1         |
      | name            | Coursework |
      | numberofmarkers | 1          |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | student   | student1 | student1@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | teacher2 | teacher   | teacher2 | teacher2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
      | teacher2 | C1     | teacher |
      | student1 | C1     | student |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |

  Scenario: Single marker: If I submit feedback and it's already been given then the form should show a warning
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment | finalised |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | Blah            | 0         |
    And I click on "Add mark" "link" in the "student1" "table_row"
    Then I should see "Another user has already submitted feedback for this student. Your changes will not be saved."
