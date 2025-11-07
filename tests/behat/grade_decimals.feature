@mod @mod_coursework
Feature: For the final grade the mark should be to the decimal point

  Background:
    Given there is a course
    And there is a coursework
    And the coursework "numberofmarkers" setting is "2" in the database
    And there is a teacher
    And there is another teacher
    And there is a student
    And the student has a submission
    And the submission is finalised

  Scenario: Automatic agreement of grades = "average grade" should use decimal places
    Given the coursework "automaticagreementstrategy" setting is "average_grade" in the database
    And I am logged in as a teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 1
    And I set the field "Grade" to "59"
    And I press "Save and finalise"
    And I log out
    And I log in as the other teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 2
    And I set the field "Grade" to "58"
    And I press "Save and finalise"
    Then I should see the final agreed grade as 58.5

  Scenario: A manager can enter decimals for the final grade
    Given I am logged in as a teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 1
    And I set the field "Grade" to "59"
    And I press "Save and finalise"
    And I log out
    And I log in as the other teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 2
    And I set the field "Grade" to "58"
    And I press "Save and finalise"
    And I log out
    And I log in as a manager
    And I visit the coursework page
    And I click the new multiple final feedback button for the student
    And I grade the submission as 56.12 using the grading form
    Then I should see the final agreed grade as 56.12
