@mod @mod_coursework @mod_coursework_submission_release_mark
Feature: Manager releasing the mark for a single student

  As a manager
  I want to be able to release the mark for a single student's submission
  so that the student does not have to wait for the marks of all students to be released

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activities" exist:
      | activity   | course | name        | numberofmarkers |
      | coursework | C1     | Coursework1 | 1               |
      | coursework | C1     | Coursework2 | 2               |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | teacher2 | teacher   | teacher2 | teacher2@example.com |
      | manager1 | manager   | manager1 | manager1@example.com |
      | student1 | student   | student1 | student1@example.com |
      | student2 | student   | student2 | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
      | teacher2 | C1     | teacher |
      | manager1 | C1     | manager |
      | student1 | C1     | student |
      | student2 | C1     | student |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework  | finalisedstatus |
      | student1    | Coursework1 | 1               |
      | student2    | Coursework1 | 1               |
      | student1    | Coursework2 | 1               |
      | student2    | Coursework2 | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework  | assessor | stageidentifier | grade | feedbackcomment | finalised |
      | student1    | Coursework1 | teacher1 | assessor_1      | 57    | Good work       | 1         |
      | student2    | Coursework1 | teacher1 | assessor_1      | 62    | Nice work       | 0         |
      | student1    | Coursework2 | teacher1 | assessor_1      | 60    | Good work       | 1         |
      | student1    | Coursework2 | teacher2 | assessor_2      | 64    | Good work       | 1         |
      | student1    | Coursework2 | manager1 | final_agreed_1  | 62    | Agreed          | 1         |
      | student2    | Coursework2 | teacher1 | assessor_1      | 70    | Nice work       | 1         |
      | student2    | Coursework2 | teacher2 | assessor_2      | 55    | Nice work       | 1         |

  Scenario: Teacher cannot release the mark for a single student
    When I am on the "Coursework1" "coursework activity" page logged in as "teacher1"
    Then I should see "Ready for release" in the "student student1" "table_row"
    And "Release mark" "link" should not exist

  @javascript
  Scenario: Manager releases the mark for a single student which is ready for release
    When I am on the "Coursework1" "coursework activity" page logged in as "manager1"
    Then I should see "Ready for release" in the "student student1" "table_row"
    And I should not see "Ready for release" in the "student student2" "table_row"
    And I click on "Actions" "button" in the "student student1" "table_row"
    And I click on "Release mark" "link" in the "student student1" "table_row"
    And I should see "Are you sure you want to release the mark for student student1?"
    And I click on "Yes" "button"
    And I wait until the page is ready
    And I should see "Marks released"
    And I should see "Released" in the "student student1" "table_row"
    And I should not see "Ready for release" in the "student student1" "table_row"
    # The mark for the other student has not been released.
    And I should not see "Released" in the "student student2" "table_row"

  Scenario: Manager cannot release the mark for a single student which is not ready for release
    When I am on the "Coursework1" "coursework activity" page logged in as "manager1"
    Then I should not see "Ready for release" in the "student student2" "table_row"
    And "Release mark" "link" should not exist in the "student student2" "table_row"

  @javascript
  Scenario: Manager releases the agreed mark for a single student when double marking
    When I am on the "Coursework2" "coursework activity" page logged in as "manager1"
    Then I should see "Ready for release" in the "student student1" "table_row"
    # Both markers have marked student2 but there is no agreed mark yet, so it cannot be released.
    And I should not see "Ready for release" in the "student student2" "table_row"
    And "Release mark" "link" should not exist in the "student student2" "table_row"
    And I click on "Actions" "button" in the "student student1" "table_row"
    And I click on "Release mark" "link" in the "student student1" "table_row"
    And I should see "Are you sure you want to release the mark for student student1?"
    And I click on "Yes" "button"
    And I wait until the page is ready
    And I should see "Marks released"
    And I should see "Released" in the "student student1" "table_row"
    And I should not see "Released" in the "student student2" "table_row"
