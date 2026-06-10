@mod @mod_coursework @mod_coursework_feedback_final_feedback_single_marking
Feature: Adding and editing feedback

  In order to provide students with a single grade
  As a course leader
  I want to be able to edit the final grade via a form

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activities" exist:
    |activity|course|name|numberofmarkers|
    |coursework|C1  |Coursework1|1        |
    |coursework|C1  |Coursework2|1        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | student1 | student   | student1 | student1@example.com |
      | manager1 | manager   | manager1 | manager1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
      | manager1 | C1     | manager |
      | student1 | C1     | student |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework1 | 1               |
      | student1    | Coursework2 | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |finalised |
      | student1    | Coursework1 | teacher1 | assessor_1      | 57    | New comment here |0         |
      | student1    | Coursework2 | teacher1 | assessor_1      | 67    | New comment here |1         |

  @javascript
  Scenario: Editing the final feedback grade via a draft state
    Given I am on the "Coursework1" "coursework activity" page logged in as "teacher1"
    And I should see "57" in the "[data-behat-markstage='1']" "css_element"
    And I should see "Draft" in the "[data-behat-markstage='1']" "css_element"
    And I should not see "Ready for release" in the "student1" "table_row"
    And I should not see "Released" in the "student student1" "table_row"
    And I click on "57" "link" in the "student student1" "table_row"
    And I press "Save and finalise"
    And I should see "Ready for release" in the "student1" "table_row"
    And I should not see "Released" in the "student student1" "table_row"
    And I should not see "Release the marks" in the "student student1" "table_row"

  @javascript
  Scenario: Setting and releasing the final feedback grade via a draft state
    When I am on the "Coursework1" "coursework activity" page logged in as "manager1"
    Then I should see "57" in the "[data-behat-markstage='1']" "css_element"
    And I should see "Draft" in the "[data-behat-markstage='1']" "css_element"
    And I should not see "Ready for release" in the "student1" "table_row"
    And I should not see "Released" in the "student student1" "table_row"
    And I click on "57" "link" in the "student student1" "table_row"
    And I press "Save and finalise"
    And I should see "Ready for release" in the "student1" "table_row"
    And I should not see "Released" in the "student student1" "table_row"
    And I follow "Release the marks"
    And I press "Confirm"
    And I should see "Released" in the "student1" "table_row"
    And I should not see "Ready for release" in the "student1" "table_row"
    And I should see "Released" in the "student1" "table_row"
    And I should not see "Ready for release" in the "student1" "table_row"
