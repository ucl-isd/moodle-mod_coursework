@mod @mod_coursework @mod_coursework_feedback_zero_grades
Feature: Zero grades should show up just like the others

    As a teacher
    I want to be abel to award a grade of zero
    So that in case there is no work submitted or the work is truly and irredeemably useless,
    the student will know

  Background:
    Given there is a course
    And there is a coursework
    And there is a student
    And the student has a submission
    And the submission is finalised

  @javascript
  Scenario: Single maker final feedback
    Given the coursework "grade" setting is "9" in the database
    Given I am logged in as a teacher
    And the coursework "numberofmarkers" setting is "1" in the database
    When I visit the coursework page
    And I click the new single final feedback button for the student
    And I set the field "Grade" to "0"
    And I press "Save and finalise"
    Then I visit the coursework page
    And I wait until the page is ready
    And I should see the final grade as 0 on the single marker page
