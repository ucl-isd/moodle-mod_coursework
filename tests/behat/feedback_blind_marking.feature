@mod @mod_coursework @mod_coursework_feedback_blind_marking
Feature: Feedback with blind marking

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity        | coursework |
      | course          | C1         |
      | name            | Coursework |
      | numberofmarkers | 1          |
      | blindmarking    | 1          |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | manager   | manager1 | manager1@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | manager1 | C1     | manager |
      | teacher1 | C1     | teacher |
      | student1 | C1     | student |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment |
      | student1    | Coursework | teacher1 | assessor_1      | 58    | Quite good      |
    And I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I follow "Release the marks"

  Scenario: As a student I should see my own name on the feedback page
    Given I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "Agreed feedback for student student1"
