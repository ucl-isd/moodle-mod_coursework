@mod @mod_coursework @mod_coursework_feedback_final_feedback_double_marking
Feature: Adding and editing final feedback

    In order to provide students with a fair final grade that combines the component grades
    As a course leader
    I want to be able to edit the final grade via a form

  Background:
    Given there is a course
    And there is a coursework
    And there is a teacher
    And there is another teacher
    And the coursework "numberofmarkers" setting is "2" in the database
    And there is a student
    And the student has a submission
    And the submission is finalised

  Scenario: Setting the final feedback grade
    Given there are feedbacks from both teachers
    And I am logged in as a manager
    And I visit the coursework page
    And I click the new multiple final feedback button for the student
    And I set the field "Mark" to "57"
    And I press "Save and finalise"
    And I should see "Changes saved"
    Then I visit the coursework page
    And I should see the final agreed grade as 57

  @javascript
  Scenario: Setting and releasing the final feedback grade via a draft state
    Given there are feedbacks from both teachers
    And the coursework "draftfeedbackenabled" setting is "1" in the database
    And I am logged in as a manager
    And I visit the coursework page
    And I click the new multiple final feedback button for the student
    And I set the field "Mark" to "57"
    And I press "Save as draft"
    And I should see "Changes saved"
    Then I visit the coursework page
    And I should see the final agreed grade as 57
    And I should see the final agreed grade status "Draft"
    And I click the edit final feedback button
    And I press "Save and finalise"
    And I should see the final agreed grade status "Ready for release"
    And I follow "Release the marks"
    And I press "Confirm"
    And I should see the final agreed grade status "Released"

  @javascript
  Scenario: Setting the final feedback comment
    Given there are feedbacks from both teachers
    And I am logged in as a manager
    And I visit the coursework page
    And I click the new multiple final feedback button for the student
    And I should see "New comment here" in the "[data-behat-markstage='assessor_1']" "css_element"
    And I should see "67" in the "[data-behat-markstage='assessor_1']" "css_element"
    And I should see "New comment here" in the "[data-behat-markstage='assessor_2']" "css_element"
    And I should see "63" in the "[data-behat-markstage='assessor_2']" "css_element"
    And I set the field "Mark" to "58"
    And I set the field "Comment" to "New comment"
    And I press "Save and finalise"
    And I should see "Changes saved"
    Then I visit the coursework page
    When I click the edit final feedback button
    And I wait until the page is ready
    And the field "Mark" matches value "58"
    And the grade comment textarea field matches "New comment"

  Scenario: I can be both an initial assessor and the manager who agrees grades
    And managers do not have the manage capability
    Given I am logged in as a manager
    And there are feedbacks from both me and another teacher
    And I visit the coursework page
    When I click the new multiple final feedback button for the student
    And I set the field "Mark" to "59"
    And I press "Save and finalise"
    And I should see "Changes saved"

  Scenario: Editing final feedback from others
    And managers do not have the manage capability
    Given I am logged in as a manager
    And there are feedbacks from both me and another teacher
    And there is final feedback from the other teacher with grade 45
    When I visit the coursework page
    When I click the edit final feedback button
    And I wait until the page is ready
    And the field "Mark" matches value "45"

  @javascript
  Scenario: Setting a new final feedback but leaving require grade field blank
    Given there are feedbacks from both teachers
    And I am logged in as a manager
    And I visit the coursework page
    And I click the new multiple final feedback button for the student
    And I set the field "Mark" to ""
    And I press "Save and finalise"
    And I should not see "Changes saved"

  @javascript
  Scenario: Updating the final feedback but leaving require grade field blank
    Given there are feedbacks from both teachers
    And I am logged in as a manager
    And I visit the coursework page
    And I click the new multiple final feedback button for the student
    And I set the field "Mark" to "22"
    And I press "Save and finalise"
    And I should see "Changes saved"
    And I visit the coursework page
    And I click on "22" "link" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I set the field "Mark" to ""
    And I press "Save and finalise"
    And I should not see "Changes saved"
