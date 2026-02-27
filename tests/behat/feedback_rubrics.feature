@mod @mod_coursework @mod_coursework_feedback_rubrics
Feature: Adding feedback using the built in Moodle rubrics

  As a teacher
  I want to be able to give detailed feedback about specific parts of the students work
  in a standardised way
  So that I can grade the work faster, give more consistent responses and make the process more fair

  Background:
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
      | numberofmarkers   | 1          |
    And there is a student
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And I am logged in as a teacher

  Scenario: I should be able to add feedback using a simple rubric
    Given there is a rubric defined for the coursework
    Given I am on the "Coursework" "coursework activity" page
    When I click on the add feedback button
    And I grade by filling the rubric with:
      | first criterion | 1 | New comment here |
    And I press "Save and finalise"
    And I log out

    And I log in as a manager
    And I am on the "Coursework" "coursework activity" page
    And I publish the grades
    And I log out
    And I log in as a student
    And I am on the "Coursework" "coursework activity" page
    Then I should see the rubric grade on the page
    And I should see the rubric comment "New comment here"

  @javascript
  Scenario: I should see the rubric grade show up in the gradebook
    Given there is a rubric defined for the coursework
    Given I am on the "Coursework" "coursework activity" page
    When I click on the add feedback button
    And I grade by filling the rubric with:
      | first criterion | 2 | Very good |
    And I press "Save and finalise"
    And I log out
    And I log in as a manager
    And I am on the "Coursework" "coursework activity" page
    And I publish the grades
    And I log out
    And I log in as a student
    When I visit the gradebook page
    And I wait until the page is ready
    And I wait "1" seconds
    Then I should see the rubric grade "100" in the gradebook
    When I am on the "Coursework" "coursework activity" page
    And I should see the rubric comment "Very good"
