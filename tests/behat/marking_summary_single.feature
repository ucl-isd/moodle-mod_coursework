@mod @mod_coursework

Feature: When a coursework uses single marking the marking summary table should display the expected values

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
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | student1 | student   | student1 | student1@example.com |
      | manager1 | manager   | manager1 | manager1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
      | student1 | C1     | student |
      | manager1 | C1     | manager |

  Scenario: Teacher's view when there are no submissions
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should see "0/1" in the "Submissions" "list_item"
    And I should see "0" in the "Ready for release" "list_item"
    And I should see "0" in the "Released" "list_item"

  Scenario: Teacher's view when student has uploaded submission
    Given the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should see "1/1" in the "Submissions" "list_item"
    And I should see "0" in the "Ready for release" "list_item"
    And I should see "0" in the "Released" "list_item"

  Scenario: Teacher's view when submission is marked
    Given the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here |
    And I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should see "1/1" in the "Submissions" "list_item"
    And I should see "1" in the "Ready for release" "list_item"
    And I should see "0" in the "Released" "list_item"

  Scenario: Manager's view when marks are released
    Given the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here |
    And I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I follow "Release the marks"
    Then I should see "1/1" in the "Submissions" "list_item"
    And I should see "0" in the "Ready for release" "list_item"
    And I should see "1" in the "Released" "list_item"
