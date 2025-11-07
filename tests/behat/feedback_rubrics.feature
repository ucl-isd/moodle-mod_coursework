@mod @mod_coursework @mod_coursework_feedback_rubrics
Feature: Adding feedback using the built in Moodle rubrics

    As a teacher
    I want to be able to give detailed feedback about specific parts of the students work
    in a standardised way
    So that I can grade the work faster, give more consistent responses and make the process more fair

  Background:
    Given there is a course
    And there is a coursework
    And the coursework "numberofmarkers" setting is "1" in the database
    And there is a student
    And the student has a submission
    And the submission is finalised
    And I am logged in as a teacher

  Scenario: I should be able to add feedback using a simple rubric
    Given there is a rubric defined for the coursework
    Given I visit the coursework page
    When I click on the add feedback button
    And I grade by filling the rubric with:
      | first criterion | 1 | New comment here |
    And I press "Save and finalise"
    And I log out

    And I log in as a manager
    And I visit the coursework page
    And I publish the grades
    And I log out
    And I log in as a student
    And I visit the coursework page
    Then I should see the rubric grade on the page
    And I should see the rubric comment "New comment here"

  @javascript
  Scenario: I should see the rubric grade show up in the gradebook
    Given there is a rubric defined for the coursework
    Given I visit the coursework page
    When I click on the add feedback button
     And I grade by filling the rubric with:
       | first criterion | 2 | Very good |
    And I press "Save and finalise"
    And I log out
    And I log in as a manager
    And I visit the coursework page
    And I publish the grades
    And I log out
    And I log in as a student
    When I visit the gradebook page
    And I wait until the page is ready
    And I wait "1" seconds
    Then I should see the rubric grade "100" in the gradebook
    When I visit the coursework page
    And I should see the rubric comment "Very good"
