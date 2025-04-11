@mod @mod_coursework @javascript
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
    And I expand the coursework grading row
    And I click on the only interactable link with title "New feedback"
    And I grade the submission as 59 using the ajax form
    And I log out
    And I log in as the other teacher
    And I visit the coursework page
    And I expand the coursework grading row
    And I click on the only interactable link with title "New feedback"
    And I grade the submission as 58 using the ajax form
    Then I should see the final grade as 58.5 on the multiple marker page

  Scenario: A manager can enter decimals for the final grade
    Given I am logged in as a teacher
    And I visit the coursework page
    And I expand the coursework grading row
    And I click on the only interactable link with title "New feedback"
    And I grade the submission as 59 using the ajax form
    And I log out
    And I log in as the other teacher
    And I visit the coursework page
    And I expand the coursework grading row
    And I click on the only interactable link with title "New feedback"
    And I grade the submission as 58 using the ajax form
    And I log out
    And I log in as a manager
    And I visit the coursework page
    And I click the new multiple final feedback button for the student
    And I grade the submission as 56.12 using the ajax form
    Then I should see the final grade as 56.12 on the multiple marker page
