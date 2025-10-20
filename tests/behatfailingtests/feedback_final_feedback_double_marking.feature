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

  @javascript
  Scenario: Setting the final feedback grade
    Given there are feedbacks from both teachers
    And I am logged in as a manager
    And I visit the coursework page
    And I click the new multiple final feedback button for the student
    And I set the field "Grade" to "57"
    And I press "Save and finalise"
    Then I visit the coursework page
    And I should see the final grade as 57 on the multiple marker page

  @javascript
  Scenario: Setting the final feedback comment
    Given there are feedbacks from both teachers
    And I am logged in as a manager
    And I visit the coursework page
    And I click the new multiple final feedback button for the student
    And I set the field "Grade" to "58"
    And I press "Save and finalise"
    Then I visit the coursework page
    When I click the edit final feedback button
    And I wait until the page is ready
    And I wait "1" seconds
    And the field "Grade" matches value "58"
    And the grade comment textarea field matches "New comment"

  @javascript
  Scenario: I can be both an initial assessor and the manager who agrees grades
    And managers do not have the manage capability
    Given I am logged in as a manager
    And there are feedbacks from both me and another teacher
    And I visit the coursework page
    When I click the new multiple final feedback button for the student
    And I set the field "Grade" to "59"
    And I press "Save and finalise"

  @javascript
  Scenario: Editing final feedback from others
    And managers do not have the manage capability
    Given I am logged in as a manager
    And there are feedbacks from both me and another teacher
    And there is final feedback from the other teacher with grade 45
    When I visit the coursework page
    When I click the edit final feedback button
    And I wait until the page is ready
    And I wait "2" seconds
    And I wait until the page is ready
    And the field "Grade" matches value "45"
