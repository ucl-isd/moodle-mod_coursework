@mod @mod_coursework @mod_coursework_feedback_final_feedback_double_marking
Feature: Adding and editing final feedback

    In order to provide students with a fair final grade that combines the component grades
    As a course leader
    I want to be able to edit the final grade via a form

  Background:
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
      | numberofmarkers            | 2          |
    And there is a teacher
    And there is another teacher
    And there is a student
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |

  Scenario: Setting the final feedback grade
    the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here |
    And I am logged in as a manager
    And I am on the "Coursework" "coursework activity" page
    And I click the new multiple final feedback button for the student
    And I set the field "Mark" to "57"
    And I press "Save and finalise"
    And I should see "Changes saved"
    Then I am on the "Coursework" "coursework activity" page
    And I should see "57" in the "[data-behat-markstage='final_agreed']" "css_element"

  @javascript
  Scenario: Setting and releasing the final feedback grade via a draft state
    the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here |
    And the coursework "draftfeedbackenabled" setting is "1" in the database
    And I am logged in as a manager
    And I am on the "Coursework" "coursework activity" page
    And I click the new multiple final feedback button for the student
    And I set the field "Mark" to "57"
    And I press "Save as draft"
    And I should see "Changes saved"
    Then I am on the "Coursework" "coursework activity" page
    And I should see "57" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I should see "Draft" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I click on "Agree marking" "link" in the "student student1" "table_row"
    And I press "Save and finalise"
    And I should see the final agreed grade status "Ready for release"
    And I follow "Release the marks"
    And I press "Confirm"
    And I should see the final agreed grade status "Released"

  @javascript
  Scenario: Setting the final feedback comment
    the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here |
    And I am logged in as a manager
    And I am on the "Coursework" "coursework activity" page
    And I click the new multiple final feedback button for the student
    And I should see "New comment here" in the "[data-behat-markstage='assessor_1']" "css_element"
    And I should see "67" in the "[data-behat-markstage='assessor_1']" "css_element"
    And I should see "New comment here" in the "[data-behat-markstage='assessor_2']" "css_element"
    And I should see "63" in the "[data-behat-markstage='assessor_2']" "css_element"
    And I set the field "Mark" to "58"
    And I set the field "Comment" to "New comment"
    And I press "Save and finalise"
    And I should see "Changes saved"
    Then I am on the "Coursework" "coursework activity" page
    When I click on "Agree marking" "link" in the "student student1" "table_row"
    And I wait until the page is ready
    And the field "Mark" matches value "58"
    And the following fields match these values:
      | Comment | New comment |

  Scenario: I can be both an initial assessor and the manager who agrees grades
    And managers do not have the manage capability
    Given I am logged in as a manager
    And there are feedbacks from both me and another teacher
    And I am on the "Coursework" "coursework activity" page
    When I click the new multiple final feedback button for the student
    And I set the field "Mark" to "59"
    And I press "Save and finalise"
    And I should see "Changes saved"

  Scenario: Editing final feedback from others
    And managers do not have the manage capability
    Given I am logged in as a manager
    And there are feedbacks from both me and another teacher
    And there is final feedback from the other teacher with grade 45
    When I am on the "Coursework" "coursework activity" page
    When I click on "Agree marking" "link" in the "student student1" "table_row"
    And I wait until the page is ready
    And the field "Mark" matches value "45"

  @javascript
  Scenario: Setting a new final feedback but leaving require grade field blank
    the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here |
    And I am logged in as a manager
    And I am on the "Coursework" "coursework activity" page
    And I click the new multiple final feedback button for the student
    And I set the field "Mark" to ""
    And I press "Save and finalise"
    And I should not see "Changes saved"

  @javascript
  Scenario: Updating the final feedback but leaving require grade field blank
    the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here |
    And I am logged in as a manager
    And I am on the "Coursework" "coursework activity" page
    And I click the new multiple final feedback button for the student
    And I set the field "Mark" to "22"
    And I press "Save and finalise"
    And I should see "Changes saved"
    And I am on the "Coursework" "coursework activity" page
    And I click on "22" "link" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I set the field "Mark" to ""
    And I press "Save and finalise"
    And I should not see "Changes saved"
