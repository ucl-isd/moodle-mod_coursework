@mod @mod_coursework
Feature: visibility of agreed graders without blind marking

  As an agreed grader
  I want to be certain that teachers (and me) are unable to see the grades of other
  teachers before the agreement phase
  So that we are not influenced by one another or confused over what to do

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity        | coursework |
      | course          | C1         |
      | name            | Coursework |
      | numberofmarkers | 2          |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | teacher2 | teacher   | teacher2 | teacher2@example.com |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
      | teacher2 | C1     | teacher |
      | student1 | C1     | student |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And the following "permission overrides" exist:
      | capability                    | permission | role    | contextlevel | reference |
      | mod/coursework:addagreedgrade | Allow      | teacher | Course       | C1        |

  Scenario: agreed graders can view the feedback of the other assessors when all done
    Given the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  | finalised |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here | 1         |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here | 1         |
    And I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    Then I should see "63" in the "student student1" "table_row"
    And I should see "67" in the "student student1" "table_row"
    Then I should see "teacher teacher1" in the "student student1" "table_row"
    And I should see "teacher teacher2" in the "student student1" "table_row"
    Then I click on "67" "link" in the "student student1" "table_row"
    And I should see "New comment here"

  Scenario: agreed graders can not view the feedback of the other assessors when draft
    Given the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  | finalised |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here | 0         |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here | 0         |
    And I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    Then I should see "63" in the "student student1" "table_row"
    And I should not see "67" in the "student student1" "table_row"
    Then I should see "teacher teacher1" in the "student student1" "table_row"
    And I should see "teacher teacher2" in the "student student1" "table_row"
    Then I click on "63" "link" in the "student student1" "table_row"
    And I should see "New comment here"
