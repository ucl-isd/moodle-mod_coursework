@mod @mod_coursework @mod_coursework_feedback_final_feedback_double_marking
Feature: Adding and editing final feedback

  In order to provide students with a fair final grade that combines the component grades
  As a course leader
  I want to be able to edit the final grade via a form

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
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |finalised |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here |1         |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here |1         |

  Scenario: Setting the final feedback grade
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I click on "Agree marking" "link" in the "student1" "table_row"
    And I set the field "Mark" to "57"
    And I press "Save and finalise"
    And I should see "Feedback saved" in the "student1" "table_row"
    Then I am on the "Coursework" "coursework activity" page
    And I should see "57" in the "[data-behat-markstage='final_agreed']" "css_element"

  @javascript
  Scenario: Setting and releasing the final feedback grade via a draft state
    Given the coursework "draftfeedbackenabled" setting is "1" in the database
    And I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I click on "Agree marking" "link" in the "student1" "table_row"
    And I set the field "Mark" to "57"
    And I press "Save as draft"
    And I should see "Feedback saved" in the "student1" "table_row"
    Then I am on the "Coursework" "coursework activity" page
    And I should see "57" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I should see "Draft" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I click on "57" "link" in the "student student1" "table_row"
    And I press "Save and finalise"
    And I should see "Ready for release" in the "student1" "table_row"
    And I follow "Release the marks"
    And I press "Confirm"
    And I should see "Released" in the "student1" "table_row"

  @javascript
  Scenario: Setting the final feedback comment
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I click on "Agree marking" "link" in the "student1" "table_row"
    And I should see "New comment here" in the "[data-behat-markstage='assessor_1']" "css_element"
    And I should see "67" in the "[data-behat-markstage='assessor_1']" "css_element"
    And I should see "New comment here" in the "[data-behat-markstage='assessor_2']" "css_element"
    And I should see "63" in the "[data-behat-markstage='assessor_2']" "css_element"
    And I set the field "Mark" to "57"
    And I set the field "Comment" to "New comment"
    And I press "Save and finalise"
    And I should see "Feedback saved" in the "student1" "table_row"
    Then I am on the "Coursework" "coursework activity" page
    And I click on "57" "link" in the "student student1" "table_row"
    And I wait until the page is ready
    And the field "Mark" matches value "57"
    And the following fields match these values:
      | Comment | New comment |

  @javascript
  Scenario: Setting a new final feedback but leaving require grade field blank
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I click on "Agree marking" "link" in the "student1" "table_row"
    And I set the field "Mark" to ""
    And I press "Save and finalise"
    And I should not see "Feedback saved"

  @javascript
  Scenario: Updating the final feedback but leaving require grade field blank
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I click on "Agree marking" "link" in the "student1" "table_row"
    And I set the field "Mark" to "22"
    And I press "Save and finalise"
    And I should see "Feedback saved" in the "student1" "table_row"
    And I am on the "Coursework" "coursework activity" page
    And I click on "22" "link" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I set the field "Mark" to ""
    And I press "Save and finalise"
    And I should not see "Feedback saved"
