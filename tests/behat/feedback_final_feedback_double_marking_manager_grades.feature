@mod @mod_coursework @mod_coursework_feedback_final_feedback_double_marking
Feature: A manager can both provide their own feedback and edit other graders feedback

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
      | manager1 | manager   | manager1 | manager1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
      | teacher2 | C1     | teacher |
      | manager1 | C1     | manager |
      | student1 | C1     | student |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  | finalised |
      | student1    | Coursework | manager1 | assessor_1      | 67    | New comment here | 1         |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here | 1         |

  Scenario: I can be both an initial assessor and the manager who agrees grades
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    When I click on "Agree marking" "link" in the "student1" "table_row"
    And I set the field "Mark" to "59"
    And I press "Save and finalise"
    And I should see "Feedback saved" in the "student1" "table_row"

  Scenario: Editing final feedback from others
    Given the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  | finalised | isfinalgrade |
      | student1    | Coursework | teacher2 | final_agreed_1  | 45    | New comment here | 1         | 1            |
    And I am on the "Coursework" "coursework activity" page logged in as "manager1"
    When I click on "45" "link" in the "student student1" "table_row"
    And the field "Mark" matches value "45"
    And I set the field "Mark" to "49"
    And I press "Save and finalise"
    Then I should see "49" in the "student student1" "table_row"
