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
      | manager1 | manager   | manager1 | manager1@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | teacher2 | teacher   | teacher2 | teacher2@example.com |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | manager1 | C1     | manager |
      | teacher1 | C1     | teacher |
      | teacher2 | C1     | teacher |
      | student1 | C1     | student |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |

  Scenario: One (of two) markers has marked the submission
    Given the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  | finalised |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here | 1         |
    When I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "In marking" in the ".behat-submission-information" "css_element"

  Scenario: Both markers have added draft feedback but not finalised their markers
    Given the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  | finalised |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here | 0         |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here | 0         |
    And I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "In marking" in the ".behat-submission-information" "css_element"

  Scenario: Both markers have added final feedback but there is no agreed mark
    Given the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  | finalised |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here | 1         |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here | 1         |
    And I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "In marking" in the ".behat-submission-information" "css_element"

  Scenario: The submission is included in sample marking and there is a final mark
    Given I am on the "Coursework" "coursework activity" page logged in as "admin"
    And I navigate to "Settings" in current page administration
    And I set the field "samplingenabled" to "1"
    And I press "Save and display"
    And I navigate to "Allocate markers" in current page administration
    And I set the following fields to these values:
      | assessor_2_samplingstrategy     | Automatic |
      | assessor_2_sampletotal_checkbox | 1         |
      | assessor_2_sampletotal          | 100       |
    And I press "save_sampling"
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  | finalised |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here | 1         |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here | 1         |
      | student1    | Coursework | manager1 | final_agreed_1  | 45    | blah             | 1         |
    And I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "In marking" in the ".behat-submission-information" "css_element"

  Scenario: There is a final mark
    Given the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  | finalised |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here | 1         |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here | 1         |
      | student1    | Coursework | manager1 | final_agreed_1  | 45    | blah             | 1         |
    And I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "In marking" in the ".behat-submission-information" "css_element"

  Scenario: The submission is included in sample marking but the final mark is not finalised
    Given I am on the "Coursework" "coursework activity" page logged in as "admin"
    And I navigate to "Settings" in current page administration
    And I set the field "samplingenabled" to "1"
    And I press "Save and display"
    And I navigate to "Allocate markers" in current page administration
    And I set the following fields to these values:
      | assessor_2_samplingstrategy     | Automatic |
      | assessor_2_sampletotal_checkbox | 1         |
      | assessor_2_sampletotal          | 100       |
    And I press "save_sampling"
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  | finalised |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here | 1         |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here | 1         |
      | student1    | Coursework | manager1 | final_agreed_1  | 45    | blah             | 0         |
    And I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "In marking" in the ".behat-submission-information" "css_element"

  Scenario: There is a final mark but this is not finalised
    Given the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  | finalised |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here | 1         |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here | 1         |
      | student1    | Coursework | manager1 | final_agreed_1  | 45    | blah             | 0         |
    And I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "In marking" in the ".behat-submission-information" "css_element"

  Scenario: Mark is finalised and has been released
    Given the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  | finalised |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here | 1         |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here | 1         |
      | student1    | Coursework | manager1 | final_agreed_1  | 45    | blah             | 1         |
    And I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I follow "Release the marks"
    And I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "Released" in the ".behat-submission-information" "css_element"
    And I should see "45" in the "#behat-final-feedback-grade" "css_element"
