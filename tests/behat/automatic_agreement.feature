@mod @mod_coursework @mod_coursework_automatic_agreement
Feature: Automatic agreement for simple grades

    As an user with add/edit coursework capability
    I can add an automatic agreement for double marking when both simple grades are adjacent within a specified range,
    so that the highest grade is chosen for all cases apart from the fail grades.

  Background:
    Given there is a course
    And there is a coursework
    And the coursework "numberofmarkers" setting is "2" in the database
    And there is a teacher
    And there is another teacher
    And there is a student
    And the student has a submission
    And the submission is finalised
    And the coursework deadline has passed

  @javascript
  Scenario: Only one grade in the submissions
    And the coursework "automaticagreementstrategy" setting is "none" in the database
    Given I am logged in as a teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 1
    When I set the field "Grade" to "56"
    And I press "Save and finalise"
    Then I should not see the final grade on the multiple marker page

  @javascript
  Scenario: Simple grades within 10% boundaries takes higher mark as a final grade
    Given the coursework "automaticagreementstrategy" setting is "percentage_distance" in the database
    Given the coursework "automaticagreementrange" setting is "10" in the database
    And I am logged in as a teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 1
    When I set the field "Grade" to "67"
    And I press "Save and finalise"
    And I log out

    And I log in as the other teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 2
    When I set the field "Grade" to "63"
    And I press "Save and finalise"
    And I visit the coursework page
    Then I should see the final grade as 67 on the multiple marker page

  @javascript
  Scenario: If "Auto-populate agreed feedback comment" is enabled then the final grade should contain the combined feedback of markers
    Given the coursework "automaticagreementstrategy" setting is "percentage_distance" in the database
    Given the coursework "automaticagreementrange" setting is "10" in the database
    Given the coursework "autopopulatefeedbackcomment" setting is "1" in the database
    Given there are feedbacks from both teachers
    And I am logged in as a manager
    And I visit the coursework page
    When I click the edit final feedback button
    And I wait until the page is ready
    Then the grade comment textarea field matches "Assessor 1 comment:New comment hereAssessor 2 comment:New comment here"
