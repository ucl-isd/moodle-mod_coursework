@mod @mod_coursework @mod_coursework_feedback_collisions
Feature: Collisions: two people try to create feedback at the same time

    As a teacher
    I want to see a warning message if I try to save my feedback when another
    teacher has already done so
    So that I do not get a surprise when the grades I have awarded disappear

  Background:
    Given there is a course
    And there is a coursework
    And there is a student
    And the student has a submission
    And the submission is finalised

  Scenario: Multiple marker: If I submit feedback and it's already been given then it should be given a new stageidentifier
    Given there is a teacher
    And there is another teacher
    And I am logged in as the other teacher
    And the coursework is set to double marker
    And I have an assessor feedback at grade 67
    And I visit the coursework page
    And I click on the add feedback button for assessor 2
    And I set the field "Mark" to "56"
    And I press "Save and finalise"

  @javascript
  Scenario: Single marker: If I submit feedback and it's already been given then the form should show a warning
    Given there is a teacher
    And there is another teacher
    And I am logged in as the other teacher
    And the coursework is set to single marker
    When I visit the coursework page
    And I have an assessor feedback at grade 67
    And I click on the add feedback button
    And I should see "Another user has already submitted feedback for this student. Your changes will not be saved."
