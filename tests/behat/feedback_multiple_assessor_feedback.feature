@mod @mod_coursework @mod_coursework_feedback_multiple_assessors
Feature: Multiple assessors simple grading form

  As a teacher
  I want there to be a simple grading form
  So that I can give students a grade and a feedback comment without any frustrating extra work

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | manager1 | manager   | manager1 | manager1@example.com |
      | teacher2 | teacher   | teacher2 | teacher2@example.com |
      | student1 | student   | student1 | student1@example.com |
      | student2 | student   | student2 | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | manager1 | C1     | manager |
      | teacher1 | C1     | teacher |
      | teacher2 | C1     | teacher |
      | student1 | C1     | student |
      | student2 | C1     | student |
    And the following "permission overrides" exist:
      | capability                      | permission | role    | contextlevel | reference |
      | mod/coursework:editinitialgrade | Allow      | teacher | Course       | C1        |
    And the following "activity" exists:
      | activity          | coursework |
      | course            | C1         |
      | name              | Coursework |
      | numberofmarkers   | 2          |
      | allocationenabled | 0          |
      | filetypes         | pdf        |
      | blindmarking      | 0          |
      | moderationenabled | 0          |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
      | student2    | Coursework | 0               |

  Scenario: I should not see the feedback icon when the submission has not been finalised
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I should see "Add mark" in the "student1" "table_row"
    But I should not see "Add mark" in the "student2" "table_row"

  Scenario: managers can grade the initial stages
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I click on "Add mark" "link" in the "student1" "table_row"
    When  I set the following fields to these values:
      | Mark    | 56               |
      | Comment | A test comment 9 |
    And I press "Save and finalise"
    And I am on the "Coursework" "coursework activity" page
    Then I should see "56" in the "student1" "table_row"

  Scenario: Teachers do not see the agree marking button unless they have the specific permission awarded
    Given the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here |
    And I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    And I follow "Add mark"
    And I set the field "Mark" to "59"
    And I press "Save and finalise"
    And I should see "Changes saved"
    And I am on the "Coursework" "coursework activity" page
    # Cannot see agree marking until specific capability awarded.
    Then I should not see "Agree marking"
    And the following "permission overrides" exist:
      | capability                    | permission | role    | contextlevel | reference |
      | mod/coursework:addagreedgrade | Allow      | teacher | Course       | C1        |
    And I am on the "Coursework" "coursework activity" page
    And I follow "Agree marking"
    And I wait until the page is ready
    And I set the field "Mark" to "71.1"
    And I press "Save and finalise"
    And I am on the "Coursework" "coursework activity" page
    And I should see "71.1"

  Scenario: Grades must be published for a student to see feedback
    Given the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here |
    And I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I click on "Agree marking" "link" in the "student1" "table_row"
    And I set the following fields to these values:
      | Mark    | 45   |
      | Comment | Final comment |
    And I press "Save and finalise"

    When I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should not see "45"
    And I should not see "Final comment"

    When I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I follow "Release the marks"
    And I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "45"
    And I should see "Final comment"
