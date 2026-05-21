@mod @mod_coursework
Feature: External examiner role can view student submissions and marker's feedback

  Background:
    Given the following "roles" exist:
      | name   | shortname  | archetype |
      | External examiner | externalexaminer     |            |
    And the following "users" exist:
      | username  | firstname | lastname | email              |
      | examiner1    | External    | Examiner     | external@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | student1 | student   | student1 | student1@example.com |
    Given the following "role capability" exists:
      | role                    | externalexaminer  |
      | mod/coursework:view                           | allow                 |
      | mod/coursework:viewallgradesatalltimes        | allow                 |
      | mod/coursework:viewextensions                 | allow                 |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "activity" exists:
      | activity             | coursework    |
      | course               | C1            |
      | name                 | Coursework    |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
      | teacher1 | C1 | editingteacher |
      | examiner1 | C1 | externalexaminer |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  | finalised |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | Some comment here | 1         |

  Scenario: External examinder visits the coursework page and sees the submissions table and Download menu
    Given I am on the "Coursework" "coursework activity" page logged in as "examiner1"
    Then the following should exist in the "Submissions table" table:
      | Student |
      | student student1 |
    And I should see "67" in the "student student1" "table_row"
    And "Download" "button" should exist
    But "Upload" "button" should not exist
