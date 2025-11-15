@mod @mod_coursework
Feature: Add assessors tab appears for users with moodle/role:assign

  Background:
    Given there is a course
    And there is a coursework
    And there is an editing teacher

  Scenario: Manager can see add assessors
    Given I am logged in as a manager
    When I visit the coursework page
    Then I should see "Add markers"

  Scenario: Editing teacher cannot see add assessors
    Given I am logged in as an editing teacher
    When I visit the coursework page
    Then I should not see "Add markers"
