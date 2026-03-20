@mod @mod_coursework
Feature: visibility of agreed graders with blind marking

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
      | blindmarking    | 1          |
      | renamefiles     | 1          |
    And the following "permission overrides" exist:
      | capability                    | permission | role    | contextlevel | reference |
      | mod/coursework:addagreedgrade | Allow      | teacher | Course       | C1        |
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

  Scenario: agreed graders cannot see other feedbacks before they have done their own
    Given the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  | finalised |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here | 0         |
    When I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    Then I should see "Draft" in the "Hidden" "table_row"
    And I should see "Add mark" in the "Hidden" "table_row"
    And I should not see "67" in the "Hidden" "table_row"

  Scenario: agreed graders can view the feedback of the other assessors when all done
    Given the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  | finalised |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here | 1         |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here | 1         |

    When I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    Then I should see "63" in the "Hidden" "table_row"
    And I should see "67" in the "Hidden" "table_row"
    Then I should see "teacher teacher1" in the "Hidden" "table_row"
    And I should see "teacher teacher2" in the "Hidden" "table_row"
    Then I click on "67" "link" in the "Hidden" "table_row"
    And I should see "New comment here"

  Scenario: agreed graders can not view the feedback of the other assessors when not finalised
    Given the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  | finalised |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here | 0         |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here | 0         |

    When I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should not see "student1"
    And I should not see "63" in the "Hidden" "table_row"
    But I should see "Marked" in the "Hidden" "table_row"
    And I should see "67" in the "Hidden" "table_row"
    And I should see "teacher teacher1" in the "Hidden" "table_row"
    And I should see "teacher teacher2" in the "Hidden" "table_row"
    When I click on "67" "link" in the "Hidden" "table_row"
    And I should see "New comment here"

    When I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    Then I should not see "student1"
    And I should see "63" in the "Hidden" "table_row"
    But I should see "Marked" in the "Hidden" "table_row"
    And I should not see "67" in the "Hidden" "table_row"
    And I should see "teacher teacher1" in the "Hidden" "table_row"
    And I should see "teacher teacher2" in the "Hidden" "table_row"
    When I click on "63" "link" in the "Hidden" "table_row"
    And I should see "New comment here"
