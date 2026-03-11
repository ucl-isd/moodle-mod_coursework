@mod @mod_coursework
Feature: When a coursework has multiple markers
  As a student when I check my submission's status this should reflect the marking state

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
      | student1 | student   | student1 | student1@example.com |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |

  Scenario: One (of two) markers has marked the submission
    Given I have an assessor feedback at grade 67
    And I log in as a student
    When I am on the "Coursework" "coursework activity" page
    Then I should see submission status "In marking"

  Scenario: Both markers have added draft feedback but not finalised their markers
    Given there is another teacher
    And there are draft feedbacks from both teachers
    And I log in as a student
    When I am on the "Coursework" "coursework activity" page
    Then I should see submission status "In marking"

  Scenario: Both markers have added final feedback but there is no agreed mark
    Given there is another teacher
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here |
    And I log in as a student
    When I am on the "Coursework" "coursework activity" page
    Then I should see submission status "In marking"

  Scenario: The submission is included in sample marking and there is a final mark
    Given the coursework has sampling enabled
    And sample marking includes student for stage 2
    And there is another teacher
    And there is a manager
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here |
    And there is final feedback
    And I log in as a student
    When I am on the "Coursework" "coursework activity" page
    Then I should see submission status "In marking"

  Scenario: There is a final mark
    Given there is another teacher
    And there is a manager
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here |
    And there is final feedback
    And I log in as a student
    When I am on the "Coursework" "coursework activity" page
    Then I should see submission status "In marking"

  Scenario: The submission is included in sample marking but the final mark is not finalised
    Given the coursework has sampling enabled
    And sample marking includes student for stage 2
    And there is another teacher
    And there is a manager
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here |
    And there is draft final feedback
    And I log in as a student
    When I am on the "Coursework" "coursework activity" page
    Then I should see submission status "In marking"

  Scenario: There is a final mark but this is not finalised
    Given there is another teacher
    And there is a manager
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here |
    And there is draft final feedback
    And I log in as a student
    When I am on the "Coursework" "coursework activity" page
    Then I should see submission status "In marking"

  Scenario: Mark is finalised and has been released
    Given there is another teacher
    And there is a manager
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here |
    And there is final feedback
    And grades have been released
    And I log in as a student
    When I am on the "Coursework" "coursework activity" page
    Then I should see submission status "Released"
    And I should see mark 45
